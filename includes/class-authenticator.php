<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Authenticator {

	private const OTP_PREFIX          = 'smsentry_otp_';
	private const PHONE_VERIFY_PREFIX = 'smsentry_phone_verify_';

	private SMSentry_SMS_Provider $provider;
	private int $otp_ttl;

	public function __construct( SMSentry_SMS_Provider $provider, int $otp_ttl = 300 ) {
		$this->provider = $provider;
		$this->otp_ttl  = $otp_ttl;
	}

	/**
	 * Generate a 6-digit OTP, store a bcrypt hash of it, and send it via SMS.
	 */
	public function send_otp( int $user_id ): true|WP_Error {
		$phone = get_user_meta( $user_id, 'smsentry_phone', true );

		if ( empty( $phone ) ) {
			return new WP_Error( 'smsentry_no_phone', __( 'No verified phone number on this account.', 'smsentry' ) );
		}

		$otp = $this->generate_and_store( $user_id );

		$minutes = (int) ceil( $this->otp_ttl / 60 );
		$message = sprintf(
			/* translators: 1: OTP code, 2: site name, 3: expiry in minutes */
			__( 'Your %2$s login code is %1$s. It expires in %3$d minute(s). Do not share this code.', 'smsentry' ),
			$otp,
			get_bloginfo( 'name' ),
			$minutes
		);

		$result = $this->provider->send( $phone, $message );

		if ( is_wp_error( $result ) ) {
			// Don't leave a dangling transient if the send fails.
			delete_transient( self::OTP_PREFIX . $user_id );
			return $result;
		}

		return true;
	}

	/**
	 * Verify an OTP submitted during login.
	 * Deletes the transient immediately on success to prevent replay.
	 */
	public function verify_otp( int $user_id, string $submitted ): true|WP_Error {
		$submitted = preg_replace( '/\D/', '', $submitted );

		if ( strlen( $submitted ) !== 6 ) {
			return new WP_Error( 'smsentry_invalid_format', __( 'Please enter the 6-digit code.', 'smsentry' ) );
		}

		$stored = get_transient( self::OTP_PREFIX . $user_id );

		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'smsentry_otp_expired', __( 'Your code has expired. Please log in again to receive a new one.', 'smsentry' ) );
		}

		if ( ! password_verify( $submitted, $stored['hash'] ) ) {
			return new WP_Error( 'smsentry_otp_mismatch', __( 'Incorrect code. Please try again.', 'smsentry' ) );
		}

		delete_transient( self::OTP_PREFIX . $user_id );

		return true;
	}

	/**
	 * Send an OTP for phone number verification during profile setup.
	 */
	public function send_phone_verification_otp( string $phone ): true|WP_Error {
		$otp = $this->generate_phone_verify_token( $phone );
		$message = sprintf(
			/* translators: 1: OTP code, 2: site name */
			__( 'Your %2$s phone verification code is %1$s. Valid for 5 minutes.', 'smsentry' ),
			$otp,
			get_bloginfo( 'name' )
		);

		return $this->provider->send( $phone, $message );
	}

	/**
	 * Verify the phone OTP entered on the profile setup form.
	 */
	public function verify_phone_otp( string $phone, string $submitted ): true|WP_Error {
		$submitted = preg_replace( '/\D/', '', $submitted );
		$key       = self::PHONE_VERIFY_PREFIX . md5( $phone );
		$stored    = get_transient( $key );

		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'smsentry_otp_expired', __( 'Code expired. Please request a new one.', 'smsentry' ) );
		}

		if ( ! hash_equals( $stored['phone'], $phone ) ) {
			return new WP_Error( 'smsentry_phone_mismatch', __( 'Phone number mismatch.', 'smsentry' ) );
		}

		if ( ! password_verify( $submitted, $stored['hash'] ) ) {
			return new WP_Error( 'smsentry_otp_mismatch', __( 'Incorrect code.', 'smsentry' ) );
		}

		delete_transient( $key );

		return true;
	}

	private function generate_and_store( int $user_id ): string {
		$otp = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		set_transient(
			self::OTP_PREFIX . $user_id,
			array(
				'hash'       => password_hash( $otp, PASSWORD_DEFAULT ),
				'created_at' => time(),
			),
			$this->otp_ttl
		);

		return $otp;
	}

	private function generate_phone_verify_token( string $phone ): string {
		$otp = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$key = self::PHONE_VERIFY_PREFIX . md5( $phone );

		set_transient(
			$key,
			array(
				'hash'  => password_hash( $otp, PASSWORD_DEFAULT ),
				'phone' => $phone,
			),
			300
		);

		return $otp;
	}
}
