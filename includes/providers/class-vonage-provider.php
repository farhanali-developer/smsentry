<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Vonage_Provider implements SMSentry_SMS_Provider {

	private string $api_key;
	private string $api_secret;
	private string $from_name;

	public function __construct( string $api_key, string $api_secret, string $from_name ) {
		$this->api_key    = $api_key;
		$this->api_secret = $api_secret;
		$this->from_name  = $from_name;
	}

	public function get_name(): string {
		return 'vonage';
	}

	public function get_label(): string {
		return 'Vonage';
	}

	public function send( string $to, string $message ): true|WP_Error {
		if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'Vonage credentials are not configured.', 'smsentry' ) );
		}

		$response = wp_remote_post(
			'https://rest.nexmo.com/sms/json',
			array(
				'body'    => array(
					'api_key'    => $this->api_key,
					'api_secret' => $this->api_secret,
					'to'         => $to,
					'from'       => $this->from_name ?: get_bloginfo( 'name' ),
					'text'       => $message,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsentry_request_failed', $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['messages'] ) ) {
			return new WP_Error( 'smsentry_vonage_api_error', __( 'Invalid response from Vonage.', 'smsentry' ) );
		}

		$status = (string) ( $body['messages'][0]['status'] ?? '1' );

		if ( '0' !== $status ) {
			$error = $body['messages'][0]['error-text'] ?? __( 'Unknown Vonage error.', 'smsentry' );
			return new WP_Error( 'smsentry_vonage_api_error', $error );
		}

		return true;
	}

	public function validate_credentials(): true|WP_Error {
		if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
			return new WP_Error( 'smsentry_missing_credentials', __( 'API Key and API Secret are required.', 'smsentry' ) );
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'api_key'    => $this->api_key,
					'api_secret' => $this->api_secret,
				),
				'https://rest.nexmo.com/account/get-balance'
			),
			array( 'timeout' => 10 )
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'smsentry_connection_failed', $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error-code'] ) && '200' !== (string) $body['error-code'] ) {
			return new WP_Error( 'smsentry_invalid_credentials', __( 'Invalid Vonage API Key or Secret.', 'smsentry' ) );
		}

		return true;
	}
}
