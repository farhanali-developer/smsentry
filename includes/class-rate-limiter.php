<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Rate_Limiter {

	private const ATTEMPTS_PREFIX = 'smsentry_attempts_';
	private const RESEND_PREFIX   = 'smsentry_resend_';

	private int $max_attempts;
	private int $lockout_duration;
	private int $resend_cooldown;

	public function __construct(
		int $max_attempts = 5,
		int $lockout_duration = 900,
		int $resend_cooldown = 60
	) {
		$this->max_attempts     = $max_attempts;
		$this->lockout_duration = $lockout_duration;
		$this->resend_cooldown  = $resend_cooldown;
	}

	public function record_attempt( int $user_id ): void {
		$key      = self::ATTEMPTS_PREFIX . $user_id;
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, $this->lockout_duration );
	}

	public function get_attempts( int $user_id ): int {
		return (int) get_transient( self::ATTEMPTS_PREFIX . $user_id );
	}

	public function is_locked_out( int $user_id ): bool {
		return $this->get_attempts( $user_id ) >= $this->max_attempts;
	}

	public function reset( int $user_id ): void {
		delete_transient( self::ATTEMPTS_PREFIX . $user_id );
	}

	/**
	 * Store expiry timestamp so JS can show an accurate countdown without an extra round-trip.
	 */
	public function set_resend_cooldown( int $user_id ): void {
		$expiry = time() + $this->resend_cooldown;
		// Value is the absolute expiry timestamp; TTL adds a small buffer.
		set_transient( self::RESEND_PREFIX . $user_id, $expiry, $this->resend_cooldown + 5 );
	}

	public function can_resend( int $user_id ): bool {
		return 0 === $this->get_resend_remaining( $user_id );
	}

	public function get_resend_remaining( int $user_id ): int {
		$expiry = (int) get_transient( self::RESEND_PREFIX . $user_id );
		if ( ! $expiry ) {
			return 0;
		}
		return max( 0, $expiry - time() );
	}
}
