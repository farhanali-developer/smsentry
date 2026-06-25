<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Plugin {

	private static ?self $instance = null;

	public SMSentry_SMS_Provider $provider;
	public SMSentry_Authenticator $authenticator;
	public SMSentry_Session $session;
	public SMSentry_Rate_Limiter $rate_limiter;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->provider      = $this->resolve_provider();
		$this->rate_limiter  = new SMSentry_Rate_Limiter(
			(int) get_option( 'smsentry_max_attempts', 5 ),
			(int) get_option( 'smsentry_lockout_duration', 900 ),
			60
		);
		$this->session       = new SMSentry_Session();
		$this->authenticator = new SMSentry_Authenticator(
			$this->provider,
			(int) get_option( 'smsentry_otp_ttl', 300 )
		);

		$this->boot();
	}

	private function boot(): void {
		load_plugin_textdomain( 'smsentry', false, dirname( plugin_basename( SMSENTRY_FILE ) ) . '/languages' );

		$this->maybe_upgrade_db();

		$login_handler = new SMSentry_Login_Handler( $this->authenticator, $this->session, $this->rate_limiter );
		$login_handler->register();

		if ( is_admin() ) {
			( new SMSentry_Admin( $this ) )->register();
			( new SMSentry_User_Profile( $this->authenticator ) )->register();
		}
	}

	/**
	 * Create/upgrade the audit log table and schedule pruning. Runs on every
	 * request until the stored db version matches — covers both fresh
	 * activations and sites where the plugin was already active before this
	 * table was introduced (register_activation_hook won't fire again for them).
	 */
	private function maybe_upgrade_db(): void {
		if ( get_option( 'smsentry_db_version' ) === SMSENTRY_DB_VERSION ) {
			return;
		}

		SMSentry_Audit_Log::create_table();

		if ( ! wp_next_scheduled( 'smsentry_prune_audit_log' ) ) {
			wp_schedule_event( time(), 'daily', 'smsentry_prune_audit_log' );
		}

		update_option( 'smsentry_db_version', SMSENTRY_DB_VERSION );
	}

	private function resolve_provider(): SMSentry_SMS_Provider {
		$name = get_option( 'smsentry_provider', 'twilio' );

		return match ( $name ) {
			'vonage' => new SMSentry_Vonage_Provider(
				(string) get_option( 'smsentry_vonage_key', '' ),
				SMSentry_Crypto::decrypt( (string) get_option( 'smsentry_vonage_secret', '' ) ),
				(string) get_option( 'smsentry_vonage_from', '' )
			),
			default => new SMSentry_Twilio_Provider(
				(string) get_option( 'smsentry_twilio_sid', '' ),
				SMSentry_Crypto::decrypt( (string) get_option( 'smsentry_twilio_token', '' ) ),
				(string) get_option( 'smsentry_twilio_from', '' )
			),
		};
	}

	/**
	 * Resolve which 2FA delivery method (if any) applies to a user on login.
	 *
	 * Priority:
	 *   1. SMENTRY_DISABLE_2FA constant — emergency bypass, always wins.
	 *   2. Verified phone + (opted in OR role-required) — 'sms'.
	 *   3. No verified phone, but email 2FA opted in, OR role requires 2FA
	 *      and email fallback is allowed — 'email'.
	 *   4. Otherwise — null (no 2FA for this login).
	 */
	public static function get_2fa_method( int $user_id ): ?string {
		if ( defined( 'SMSENTRY_DISABLE_2FA' ) && SMSENTRY_DISABLE_2FA ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$required_roles = (array) get_option( 'smsentry_required_roles', array() );
		$role_required   = ! empty( $required_roles ) && array_intersect( $user->roles, $required_roles );

		$phone_verified = (bool) get_user_meta( $user_id, 'smsentry_phone_verified', true );

		if ( $phone_verified ) {
			if ( $role_required || (bool) get_user_meta( $user_id, 'smsentry_2fa_enabled', true ) ) {
				return 'sms';
			}
			return null;
		}

		$email_fallback_allowed = (bool) get_option( 'smsentry_email_fallback_enabled', true );
		$email_enabled          = (bool) get_user_meta( $user_id, 'smsentry_email_2fa_enabled', true );

		if ( $email_fallback_allowed && ( $email_enabled || $role_required ) ) {
			return 'email';
		}

		return null;
	}

	/**
	 * Back-compat boolean wrapper around get_2fa_method().
	 */
	public static function user_needs_2fa( int $user_id ): bool {
		return null !== self::get_2fa_method( $user_id );
	}

	public static function activate(): void {
		SMSentry_Audit_Log::create_table();
		update_option( 'smsentry_db_version', SMSENTRY_DB_VERSION );

		if ( ! wp_next_scheduled( 'smsentry_prune_audit_log' ) ) {
			wp_schedule_event( time(), 'daily', 'smsentry_prune_audit_log' );
		}

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'smsentry_prune_audit_log' );
		flush_rewrite_rules();
	}
}
