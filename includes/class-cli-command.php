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
		$users = get_users( array(
			'meta_key'     => 'smsentry_phone_verified',
			'meta_value'   => '1',
			'meta_compare' => '=',
		) );

		if ( empty( $users ) ) {
			WP_CLI::log( 'No users have 2FA configured.' );
			return;
		}

		$rows = array();
		foreach ( $users as $user ) {
			$rows[] = array(
				'ID'      => $user->ID,
				'login'   => $user->user_login,
				'status'  => get_user_meta( $user->ID, 'smsentry_2fa_enabled', true ) ? 'enabled' : 'paused',
				'phone'   => $this->mask_phone( (string) get_user_meta( $user->ID, 'smsentry_phone', true ) ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'login', 'status', 'phone' ) );
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
