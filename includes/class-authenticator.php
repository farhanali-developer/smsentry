<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Authenticator {

	private const OTP_PREFIX          = 'smsentry_otp_';
	private const PHONE_VERIFY_PREFIX = 'smsentry_phone_verify_';
	private const BACKUP_CODES_META   = 'smsentry_backup_codes';
	private const BACKUP_CODE_COUNT   = 10;
	private const BACKUP_CODE_CHARS   = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // No 0/1/O/I/L — avoids transcription mistakes.

	private SMSentry_SMS_Provider $provider;
	private int $otp_ttl;

	public function __construct( SMSentry_SMS_Provider $provider, int $otp_ttl = 300 ) {
		$this->provider = $provider;
		$this->otp_ttl  = $otp_ttl;
	}

	/**
	 * Generate a 6-digit OTP, store a bcrypt hash of it, and deliver it via
	 * the requested method ('sms' or 'email').
	 */
	public function send_otp( int $user_id, string $method = 'sms' ): true|WP_Error {
		if ( 'email' === $method ) {
			return $this->send_email_otp( $user_id );
		}

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
	 * Generate a 6-digit OTP and email it to the user's account address.
	 * Used when a user has no verified phone but still needs (or has opted
	 * into) 2FA. Reuses the same OTP storage/verification as SMS — only the
	 * delivery channel differs.
	 */
	private function send_email_otp( int $user_id ): true|WP_Error {
		$user = get_userdata( $user_id );

		if ( ! $user || empty( $user->user_email ) ) {
			return new WP_Error( 'smsentry_no_email', __( 'No email address on this account.', 'smsentry' ) );
		}

		$otp     = $this->generate_and_store( $user_id );
		$minutes = (int) ceil( $this->otp_ttl / 60 );
		$site    = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: site name */
			__( '[%s] Your login verification code', 'smsentry' ),
			$site
		);

		$message = sprintf(
			/* translators: 1: OTP code, 2: site name, 3: expiry in minutes */
			__( "Your %2\$s login code is: %1\$s\n\nIt expires in %3\$d minute(s). Do not share this code with anyone.", 'smsentry' ),
			$otp,
			$site,
			$minutes
		);

		if ( ! wp_mail( $user->user_email, $subject, $message ) ) {
			delete_transient( self::OTP_PREFIX . $user_id );
			return new WP_Error( 'smsentry_email_failed', __( 'Could not send the verification email.', 'smsentry' ) );
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

	/**
	 * Generate a fresh set of backup codes, replacing any existing set.
	 * Returns the plain-text codes — this is the only time they are ever
	 * available outside of a bcrypt hash, so the caller must display them once.
	 *
	 * @return string[] Plain-text codes formatted as "XXXXX-XXXXX".
	 */
	public function generate_backup_codes( int $user_id ): array {
		$plain  = array();
		$stored = array();

		for ( $i = 0; $i < self::BACKUP_CODE_COUNT; $i++ ) {
			$code     = $this->generate_single_backup_code();
			$plain[]  = substr( $code, 0, 5 ) . '-' . substr( $code, 5, 5 );
			$stored[] = array(
				'hash' => password_hash( $code, PASSWORD_DEFAULT ),
				'used' => false,
			);
		}

		update_user_meta( $user_id, self::BACKUP_CODES_META, $stored );

		return $plain;
	}

	/**
	 * Verify a backup code submitted during login. Single-use — marks the
	 * matching code as spent on success so it cannot be replayed.
	 */
	public function verify_backup_code( int $user_id, string $submitted ): true|WP_Error {
		$submitted = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $submitted ) );

		if ( empty( $submitted ) ) {
			return new WP_Error( 'smsentry_invalid_format', __( 'Please enter a backup code.', 'smsentry' ) );
		}

		$codes = get_user_meta( $user_id, self::BACKUP_CODES_META, true );

		if ( ! is_array( $codes ) || empty( $codes ) ) {
			return new WP_Error( 'smsentry_no_backup_codes', __( 'No backup codes are available for this account.', 'smsentry' ) );
		}

		foreach ( $codes as $index => $code ) {
			if ( ! empty( $code['used'] ) ) {
				continue;
			}

			if ( password_verify( $submitted, $code['hash'] ) ) {
				$codes[ $index ]['used'] = true;
				update_user_meta( $user_id, self::BACKUP_CODES_META, $codes );
				return true;
			}
		}

		return new WP_Error( 'smsentry_otp_mismatch', __( 'Incorrect or already-used backup code.', 'smsentry' ) );
	}

	/**
	 * Count of unused backup codes remaining for a user.
	 */
	public function get_backup_codes_remaining( int $user_id ): int {
		$codes = get_user_meta( $user_id, self::BACKUP_CODES_META, true );

		if ( ! is_array( $codes ) ) {
			return 0;
		}

		return count( array_filter( $codes, static fn( $code ) => empty( $code['used'] ) ) );
	}

	private function generate_single_backup_code(): string {
		$chars = self::BACKUP_CODE_CHARS;
		$max   = strlen( $chars ) - 1;
		$code  = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$code .= $chars[ random_int( 0, $max ) ];
		}

		return $code;
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
