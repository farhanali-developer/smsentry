<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manage SMSentry two-factor authentication from WP-CLI.
 * Primarily for emergency recovery when a user (or every user) is locked out.
 *
 * ## EXAMPLES
 *
 *     # List every user with 2FA configured
 *     $ wp smsentry list
 *
 *     # Reset 2FA for one locked-out user (lost phone, etc.)
 *     $ wp smsentry reset 42
 *     $ wp smsentry reset admin@example.com
 *
 *     # Reset 2FA for every user on the site (full lockout recovery)
 *     $ wp smsentry reset --all
 */
class SMSentry_CLI_Command {

	/**
	 * List users who have 2FA configured.
	 */
	public function list( array $args, array $assoc_args ): void {
		$meta_keys = array( 'smsentry_phone_verified', 'smsentry_email_2fa_enabled', 'smsentry_force_required' );
		$user_ids  = array();

		foreach ( $meta_keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WP-CLI admin command; querying meta is acceptable here as it runs outside the normal request cycle.
			$user_ids = array_merge( $user_ids, get_users( array(
				'meta_key'     => $meta_key,
				'meta_value'   => '1',
				'meta_compare' => '=',
				'fields'       => 'ID',
			) ) );
		}

		$user_ids = array_unique( $user_ids );

		if ( empty( $user_ids ) ) {
			WP_CLI::log( 'No users have 2FA configured.' );
			return;
		}

		$rows = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$rows[] = array(
				'ID'       => $user_id,
				'login'    => $user->user_login,
				'method'   => SMSentry_Plugin::get_2fa_method( $user_id ) ?? 'none (paused)',
				'enforced' => get_user_meta( $user_id, 'smsentry_force_required', true ) ? 'yes' : 'no',
				'phone'    => $this->mask_phone( (string) get_user_meta( $user_id, 'smsentry_phone', true ) ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'login', 'method', 'enforced', 'phone' ) );
	}

	/**
	 * Reset (disable) 2FA for one user, or every user with --all.
	 * Clears the verified phone, enabled flag, and any backup codes.
	 *
	 * ## OPTIONS
	 *
	 * [<user>]
	 * : User ID, login, or email address.
	 *
	 * [--all]
	 * : Reset 2FA for every user on the site. Use when SMS delivery is broken
	 * and the whole site is locked out.
	 *
	 * ## EXAMPLES
	 *
	 *     wp smsentry reset 42
	 *     wp smsentry reset --all
	 */
	public function reset( array $args, array $assoc_args ): void {
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
			$user_ids = get_users( array( 'fields' => 'ID' ) );

			foreach ( $user_ids as $user_id ) {
				$this->clear_user( (int) $user_id );
			}

			WP_CLI::success( sprintf( '2FA reset for %d user(s).', count( $user_ids ) ) );
			return;
		}

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Provide a user ID, login, or email — or pass --all.' );
		}

		$user = $this->find_user( $args[0] );

		if ( ! $user ) {
			WP_CLI::error( "User '{$args[0]}' not found." );
		}

		$this->clear_user( $user->ID );
		WP_CLI::success( "2FA reset for user #{$user->ID} ({$user->user_login})." );
	}

	private function clear_user( int $user_id ): void {
		delete_user_meta( $user_id, 'smsentry_phone' );
		delete_user_meta( $user_id, 'smsentry_phone_verified' );
		delete_user_meta( $user_id, 'smsentry_2fa_enabled' );
		delete_user_meta( $user_id, 'smsentry_backup_codes' );
		delete_user_meta( $user_id, 'smsentry_email_2fa_enabled' );
		delete_user_meta( $user_id, 'smsentry_force_required' );
		( new SMSentry_Device_Trust() )->forget_all( $user_id );
	}

	private function find_user( string $identifier ): WP_User|false {
		if ( is_numeric( $identifier ) ) {
			return get_user_by( 'id', (int) $identifier );
		}

		if ( is_email( $identifier ) ) {
			return get_user_by( 'email', $identifier );
		}

		return get_user_by( 'login', $identifier );
	}

	private function mask_phone( string $phone ): string {
		if ( strlen( $phone ) < 4 ) {
			return $phone;
		}
		return str_repeat( '*', strlen( $phone ) - 4 ) . substr( $phone, -4 );
	}
}
