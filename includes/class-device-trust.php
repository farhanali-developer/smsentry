<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Remember this device" — lets a user skip the OTP step for 30 days on a
 * browser they've already verified once. A random token is stored in a
 * long-lived cookie; only its hash is kept in user meta, so the cookie
 * itself is the only thing that can prove trust (nothing to steal server-side).
 */
class SMSentry_Device_Trust {

	private const COOKIE_NAME = 'smsentry_trusted_device';
	private const META_KEY    = 'smsentry_trusted_devices';
	private const TTL         = 30 * DAY_IN_SECONDS;
	private const MAX_DEVICES = 5;

	/**
	 * Generate a new trust token for this user, store its hash, and set the cookie.
	 */
	public function trust_device( int $user_id ): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );

		$devices   = $this->get_devices( $user_id );
		$devices[] = array(
			'hash'    => $hash,
			'expires' => time() + self::TTL,
			'added'   => time(),
			'label'   => $this->describe_user_agent(),
		);

		// Cap the list — drop the oldest entries first if over the limit.
		if ( count( $devices ) > self::MAX_DEVICES ) {
			usort( $devices, static fn( $a, $b ) => $a['added'] <=> $b['added'] );
			$devices = array_slice( $devices, -self::MAX_DEVICES );
		}

		update_user_meta( $user_id, self::META_KEY, $devices );

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
	}

	/**
	 * Check whether the current request's cookie matches a non-expired
	 * trusted device for this user.
	 */
	public function is_trusted( int $user_id ): bool {
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

		if ( empty( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return false;
		}

		$hash    = hash( 'sha256', $token );
		$devices = $this->get_devices( $user_id );

		foreach ( $devices as $device ) {
			if ( hash_equals( $device['hash'], $hash ) && $device['expires'] > time() ) {
				return true;
			}
		}

		return false;
	}

	public function get_device_count( int $user_id ): int {
		return count( $this->get_devices( $user_id ) );
	}

	public function forget_all( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * @return array<int, array{hash: string, expires: int, added: int, label: string}>
	 */
	private function get_devices( int $user_id ): array {
		$devices = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $devices ) ) {
			return array();
		}

		// Drop expired entries opportunistically so the list doesn't grow stale.
		return array_values( array_filter( $devices, static fn( $d ) => ( $d['expires'] ?? 0 ) > time() ) );
	}

	private function describe_user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return substr( $ua, 0, 80 );
	}
}
