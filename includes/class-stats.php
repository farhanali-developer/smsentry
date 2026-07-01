<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adoption statistics for the Security tab — how many users have 2FA
 * active, broken down by method, plus how many users under a required
 * role still haven't completed setup.
 */
class SMSentry_Stats {

	private const CACHE_KEY = 'smsentry_adoption_summary';
	private const CACHE_TTL = 300; // 5 minutes — avoids recomputing on every settings page load.

	public static function get_adoption_summary(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$user_counts = count_users();
		$total       = (int) $user_counts['total_users'];

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin-only adoption summary, transient-cached for 5 minutes. meta_key is the only practical way to query 2FA enrollment.
		$sms_ids   = get_users( array(
			'meta_key'     => 'smsentry_phone_verified',
			'meta_value'   => '1',
			'meta_compare' => '=',
			'fields'       => 'ID',
		) );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$email_ids = get_users( array(
			'meta_key'     => 'smsentry_email_2fa_enabled',
			'meta_value'   => '1',
			'meta_compare' => '=',
			'fields'       => 'ID',
		) );

		$enabled_ids = array_unique( array_merge( $sms_ids, $email_ids ) );

		$required_roles = (array) get_option( 'smsentry_required_roles', array() );
		$required_ids   = array();
		foreach ( $required_roles as $role ) {
			$required_ids = array_merge( $required_ids, get_users( array( 'role' => $role, 'fields' => 'ID' ) ) );
		}

		// Individually-enforced users (Users list bulk action) count as required too, even outside a required role.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$forced_ids = get_users( array(
			'meta_key'     => 'smsentry_force_required',
			'meta_value'   => '1',
			'meta_compare' => '=',
			'fields'       => 'ID',
		) );

		$required_ids = array_unique( array_merge( $required_ids, $forced_ids ) );

		$summary = array(
			'total'             => $total,
			'enabled'           => count( $enabled_ids ),
			'sms_count'         => count( $sms_ids ),
			'email_count'       => count( $email_ids ),
			'required_total'    => count( $required_ids ),
			'required_missing'  => count( array_diff( $required_ids, $enabled_ids ) ),
		);

		set_transient( self::CACHE_KEY, $summary, self::CACHE_TTL );

		return $summary;
	}

	/**
	 * Invalidate the cached summary — call after any action that changes
	 * 2FA enrollment so the widget doesn't show stale numbers for up to 5 minutes.
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
