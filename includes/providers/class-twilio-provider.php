<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Twilio_Provider implements SMSentry_SMS_Provider {

	private string $account_sid;
	private string $auth_token;
	private string $from_number;

	public function __construct( string $account_sid, string $auth_token, string $from_number ) {
		$this->account_sid = $account_sid;
		$this->auth_token  = $auth_token;
		$this->from_number = $from_number;
	}

	public function get_name(): string {
		return 'twilio';
	}

	public function get_label(): string {
		return 'Twilio';
	}

	public function send( string $to, string $message ): true|WP_Error {
		if ( empty( $this->account_sid ) || empty( $this->auth_token ) || empty( $this->from_number ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'Twilio credentials are not configured.', 'smsentry' ) );
		}

		$url = sprintf(
			'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
			rawurlencode( $this->account_sid )
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'To'   => $to,
					'From' => $this->from_number,
					'Body' => $message,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsentry_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$error = $body['message'] ?? __( 'Unknown Twilio error.', 'smsentry' );
			return new WP_Error( 'smsentry_twilio_api_error', $error );
		}

		return true;
	}

	public function validate_credentials(): true|WP_Error {
		if ( empty( $this->account_sid ) || empty( $this->auth_token ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'Account SID and Auth Token are required.', 'smsentry' ) );
		}

		$url = sprintf(
			'https://api.twilio.com/2010-04-01/Accounts/%s.json',
			rawurlencode( $this->account_sid )
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->account_sid . ':' . $this->auth_token ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsentry_connection_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error( 'smsentry_invalid_credentials', __( 'Invalid Account SID or Auth Token.', 'smsentry' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'smsentry_twilio_api_error', __( 'Could not verify Twilio credentials.', 'smsentry' ) );
		}

		return true;
	}
}
