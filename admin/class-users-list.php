<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds a 2FA status column to the Users list table, plus bulk actions to
 * force/unforce 2FA for specific users regardless of their role.
 */
class SMSentry_Users_List {

	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'render_bulk_action_notice' ) );
	}

	public function add_column( array $columns ): array {
		$columns['smsentry_2fa'] = __( '2FA', 'smsentry' );
		return $columns;
	}

	public function render_column( string $output, string $column_name, int $user_id ): string {
		if ( 'smsentry_2fa' !== $column_name ) {
			return $output;
		}

		$method  = SMSentry_Plugin::get_2fa_method( $user_id );
		$enforced = (bool) get_user_meta( $user_id, 'smsentry_force_required', true );

		if ( null === $method ) {
			$badge = '<span class="smsentry-badge smsentry-badge-inactive">' . esc_html__( 'Not set up', 'smsentry' ) . '</span>';
		} elseif ( 'sms' === $method ) {
			$badge = '<span class="smsentry-badge smsentry-badge-active">' . esc_html__( 'SMS', 'smsentry' ) . '</span>';
		} else {
			$badge = '<span class="smsentry-badge smsentry-badge-active">' . esc_html__( 'Email', 'smsentry' ) . '</span>';
		}

		if ( $enforced ) {
			$badge .= ' <span class="smsentry-badge smsentry-badge-enforced">' . esc_html__( 'Enforced', 'smsentry' ) . '</span>';
		}

		return $badge;
	}

	public function add_bulk_actions( array $actions ): array {
		$actions['smsentry_enforce']   = __( 'Enforce 2FA', 'smsentry' );
		$actions['smsentry_unenforce'] = __( 'Remove 2FA enforcement', 'smsentry' );
		return $actions;
	}

	public function handle_bulk_actions( string $redirect_to, string $action, array $user_ids ): string {
		if ( ! in_array( $action, array( 'smsentry_enforce', 'smsentry_unenforce' ), true ) ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$count = 0;
		foreach ( $user_ids as $user_id ) {
			if ( 'smsentry_enforce' === $action ) {
				update_user_meta( $user_id, 'smsentry_force_required', true );
			} else {
				delete_user_meta( $user_id, 'smsentry_force_required' );
			}
			SMSentry_Audit_Log::log(
				(int) $user_id,
				'smsentry_enforce' === $action ? '2fa_enabled' : '2fa_disabled',
				'smsentry_enforce' === $action ? 'enforced by admin' : 'enforcement removed by admin'
			);
			$count++;
		}

		SMSentry_Stats::flush_cache();

		return add_query_arg(
			array(
				'smsentry_bulk'  => $action,
				'smsentry_count' => $count,
			),
			$redirect_to
		);
	}

	public function render_bulk_action_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice triggered by WP's own bulk-action redirect, not a state-changing request.
		$action = sanitize_key( wp_unslash( $_GET['smsentry_bulk'] ?? '' ) );

		if ( ! in_array( $action, array( 'smsentry_enforce', 'smsentry_unenforce' ), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = absint( wp_unslash( $_GET['smsentry_count'] ?? 0 ) );

		$message = 'smsentry_enforce' === $action
			? sprintf(
				/* translators: number of users */
				_n( '2FA enforced for %d user.', '2FA enforced for %d users.', $count, 'smsentry' ),
				$count
			)
			: sprintf(
				/* translators: number of users */
				_n( '2FA enforcement removed for %d user.', '2FA enforcement removed for %d users.', $count, 'smsentry' ),
				$count
			);

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
}
