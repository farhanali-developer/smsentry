<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages the interim session between "password accepted" and "OTP verified".
 * A random 64-char hex token is stored in a cookie and mapped to the user ID
 * via a short-lived transient. No session data touches the URL.
 */
class SMSentry_Session {

	private const COOKIE_NAME = 'smsentry_interim';
	private const PREFIX      = 'smsentry_interim_';
	private const TTL         = 600; // 10 minutes

	public function create( int $user_id ): string {
		$token = bin2hex( random_bytes( 32 ) );

		set_transient(
			self::PREFIX . $token,
			array(
				'user_id'     => $user_id,
				'created_at'  => time(),
				'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? sanitize_url( wp_unslash( $_REQUEST['redirect_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect_to is a standard WP login parameter, not user-submitted form data.
			),
			self::TTL
		);

		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + self::TTL,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);

		return $token;
	}

	public function get_user_id(): int|false {
		$data = $this->get_data();
		return $data ? (int) $data['user_id'] : false;
	}

	public function get_redirect_to(): string {
		$data = $this->get_data();
		return $data ? (string) $data['redirect_to'] : '';
	}

	public function is_valid(): bool {
		return null !== $this->get_data();
	}

	public function destroy(): void {
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

		if ( $token ) {
			delete_transient( self::PREFIX . $token );
		}

		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - 3600,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	private function get_data(): ?array {
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

		// Validate token shape before touching the database.
		if ( empty( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		$data = get_transient( self::PREFIX . $token );

		return is_array( $data ) ? $data : null;
	}
}
