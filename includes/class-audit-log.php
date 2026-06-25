<?php
defined( 'ABSPATH' ) || exit;

/**
 * Security event log: logins, failed attempts, lockouts, phone/email 2FA
 * changes, and backup code usage. Uses a dedicated table since this data
 * needs to be queried, filtered, and paginated as it grows.
 */
class SMSentry_Audit_Log {

	private const TABLE_SUFFIX = 'smsentry_audit_log';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(32) NOT NULL,
			details VARCHAR(255) NOT NULL DEFAULT '',
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function log( int $user_id, string $event_type, string $details = '' ): void {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'user_id'    => $user_id,
				'event_type' => $event_type,
				'details'    => $details,
				'ip_address' => self::get_client_ip(),
				'created_at' => current_time( 'mysql', true ), // Stored in GMT; views convert to site time for display.
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		/**
		 * Fires after a security event is written to the audit log.
		 * Used by SMSentry_Notifier to send security alert emails.
		 */
		do_action( 'smsentry_audit_logged', $user_id, $event_type, $details );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_entries( int $per_page = 20, int $page = 1, int $user_filter = 0, string $event_filter = '' ): array {
		global $wpdb;

		$table  = self::table_name();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		[ $where_sql, $params ] = self::build_where( $user_filter, $event_filter );
		$params[]               = $per_page;
		$params[]               = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is the internal prefixed table name; $where_sql is built only from fixed string fragments above, all values are bound via $params.
		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	public static function count_entries( int $user_filter = 0, string $event_filter = '' ): int {
		global $wpdb;

		$table = self::table_name();
		[ $where_sql, $params ] = self::build_where( $user_filter, $event_filter );

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no dynamic values; $where_sql is the static '1=1'.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- see get_entries() above.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) );
	}

	public static function prune_old_entries(): void {
		global $wpdb;

		$days  = (int) apply_filters( 'smsentry_audit_log_retention_days', 90 );
		$table = self::table_name();

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is the internal prefixed table name.
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}

	public static function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	public static function get_event_labels(): array {
		return array(
			'login_success'          => __( 'Login succeeded', 'smsentry' ),
			'login_failed'           => __( 'Code incorrect', 'smsentry' ),
			'lockout'                => __( 'Locked out (too many attempts)', 'smsentry' ),
			'ip_lockout'             => __( 'IP address locked out', 'smsentry' ),
			'otp_send_failed'        => __( 'Code delivery failed', 'smsentry' ),
			'device_trusted'         => __( 'Device remembered (30 days)', 'smsentry' ),
			'devices_forgotten'      => __( 'All trusted devices forgotten', 'smsentry' ),
			'phone_verified'         => __( 'Phone number verified', 'smsentry' ),
			'2fa_enabled'            => __( '2FA enabled', 'smsentry' ),
			'2fa_disabled'           => __( '2FA disabled / removed', 'smsentry' ),
			'email_2fa_enabled'      => __( 'Email 2FA enabled', 'smsentry' ),
			'email_2fa_disabled'     => __( 'Email 2FA disabled', 'smsentry' ),
			'backup_codes_generated' => __( 'Backup codes (re)generated', 'smsentry' ),
			'backup_code_used'       => __( 'Backup code used', 'smsentry' ),
		);
	}

	/**
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private static function build_where( int $user_filter, string $event_filter ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( $user_filter > 0 ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_filter;
		}

		if ( '' !== $event_filter ) {
			$where[]  = 'event_type = %s';
			$params[] = $event_filter;
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
