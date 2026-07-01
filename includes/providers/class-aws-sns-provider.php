<?php
defined( 'ABSPATH' ) || exit;

/**
 * AWS SNS provider — sends OTP codes via Amazon Simple Notification Service.
 * Uses the SNS Publish API with AWS Signature Version 4 (SigV4) signing,
 * built on top of wp_remote_post() only; no AWS PHP SDK required.
 *
 * Message type is always "Transactional" so OTP codes get delivery priority
 * over promotional traffic on shared phone pools.
 */
class SMSentry_AWS_SNS_Provider implements SMSentry_SMS_Provider {

	private string $access_key;
	private string $secret_key;
	private string $region;
	private string $sender_id;

	public function __construct(
		string $access_key,
		string $secret_key,
		string $region    = 'us-east-1',
		string $sender_id = ''
	) {
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region     = $region ?: 'us-east-1';
		$this->sender_id  = $sender_id;
	}

	public function get_name(): string {
		return 'aws_sns';
	}

	public function get_label(): string {
		return 'AWS SNS';
	}

	public function send( string $to, string $message ): true|WP_Error {
		if ( empty( $this->access_key ) || empty( $this->secret_key ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'AWS SNS credentials are not configured.', 'smsentry' ) );
		}

		$params = array(
			'PhoneNumber' => $to,
			'Message'     => $message,
			// Transactional = higher priority, better for time-sensitive OTP codes.
			'MessageAttributes.entry.1.Name'                   => 'AWS.SNS.SMS.SMSType',
			'MessageAttributes.entry.1.Value.DataType'         => 'String',
			'MessageAttributes.entry.1.Value.StringValue'      => 'Transactional',
		);

		if ( ! empty( $this->sender_id ) ) {
			$params['MessageAttributes.entry.2.Name']               = 'AWS.SNS.SMS.SenderID';
			$params['MessageAttributes.entry.2.Value.DataType']     = 'String';
			$params['MessageAttributes.entry.2.Value.StringValue']  = $this->sender_id;
		}

		return $this->api_request( 'Publish', $params );
	}

	public function validate_credentials(): true|WP_Error {
		if ( empty( $this->access_key ) || empty( $this->secret_key ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'AWS Access Key ID and Secret Access Key are required.', 'smsentry' ) );
		}

		// GetSMSAttributes is a lightweight read-only call that confirms the
		// credentials have SNS access without sending any messages.
		return $this->api_request( 'GetSMSAttributes', array() );
	}

	/**
	 * Sign and dispatch a POST request to the SNS API using AWS SigV4.
	 *
	 * @param string               $action SNS Action name (e.g. 'Publish').
	 * @param array<string,string> $params Additional POST body parameters.
	 * @return true|WP_Error
	 */
	private function api_request( string $action, array $params ): true|WP_Error {
		$params['Action']  = $action;
		$params['Version'] = '2010-03-31';
		ksort( $params );

		$endpoint   = 'https://sns.' . $this->region . '.amazonaws.com/';
		$service    = 'sns';
		$host       = 'sns.' . $this->region . '.amazonaws.com';
		$amz_date   = gmdate( 'Ymd\THis\Z' );
		$date_stamp = gmdate( 'Ymd' );

		$body = http_build_query( $params );

		// ── Step 1: Canonical request ─────────────────────────────────────
		// Sign only host and x-amz-date — the minimum AWS requires.
		// Not signing Content-Type avoids signature mismatches caused by
		// WordPress's HTTP layer normalising the header value in transit.
		$canonical_headers = "host:{$host}\nx-amz-date:{$amz_date}\n";
		$signed_headers    = 'host;x-amz-date';
		$payload_hash      = hash( 'sha256', $body );

		$canonical_request = implode( "\n", array(
			'POST',
			'/',
			'', // empty canonical query string (parameters are in body)
			$canonical_headers,
			$signed_headers,
			$payload_hash,
		) );

		// ── Step 2: String to sign ────────────────────────────────────────
		$credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";
		$string_to_sign   = implode( "\n", array(
			'AWS4-HMAC-SHA256',
			$amz_date,
			$credential_scope,
			hash( 'sha256', $canonical_request ),
		) );

		// ── Step 3: Signing key (HMAC-SHA256 chain) ───────────────────────
		$k_date    = hash_hmac( 'sha256', $date_stamp,      'AWS4' . $this->secret_key, true );
		$k_region  = hash_hmac( 'sha256', $this->region,    $k_date,    true );
		$k_service = hash_hmac( 'sha256', $service,         $k_region,  true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request',   $k_service, true );

		// ── Step 4: Signature ─────────────────────────────────────────────
		$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

		// ── Step 5: Authorization header ──────────────────────────────────
		$authorization = sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$this->access_key,
			$credential_scope,
			$signed_headers,
			$signature
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'X-Amz-Date'    => $amz_date,
				'Authorization' => $authorization,
			),
			'body'    => $body,
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsentry_request_failed', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$xml    = wp_remote_retrieve_body( $response );

		if ( $status >= 200 && $status < 300 ) {
			return true;
		}

		// Parse AWS XML error response for a useful message.
		$error_message = $this->parse_aws_error( $xml );
		return new WP_Error( 'smsentry_aws_sns_error', $error_message );
	}

	/**
	 * Extract the human-readable message from an AWS XML error response.
	 * Falls back to the raw response body if parsing fails.
	 */
	private function parse_aws_error( string $xml ): string {
		libxml_use_internal_errors( true );
		$dom = simplexml_load_string( $xml );

		if ( $dom && isset( $dom->Error->Message ) ) {
			return (string) $dom->Error->Message;
		}

		if ( $dom && isset( $dom->Errors->Error->Message ) ) {
			return (string) $dom->Errors->Error->Message;
		}

		return $xml ?: __( 'Unknown AWS SNS error.', 'smsentry' );
	}
}
