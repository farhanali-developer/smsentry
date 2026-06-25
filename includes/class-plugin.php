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

		$login_handler = new SMSentry_Login_Handler( $this->authenticator, $this->session, $this->rate_limiter );
		$login_handler->register();

		if ( is_admin() ) {
			( new SMSentry_Admin( $this ) )->register();
			( new SMSentry_User_Profile( $this->authenticator ) )->register();
		}
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
	 * Determine whether a user must complete 2FA on login.
	 * Returns true if:
	 *   - the user has a verified phone AND has enabled 2FA themselves, OR
	 *   - the user belongs to a role that the admin has marked as required.
	 */
	public static function user_needs_2fa( int $user_id ): bool {
		$phone_verified = (bool) get_user_meta( $user_id, 'smsentry_phone_verified', true );

		if ( ! $phone_verified ) {
			return false;
		}

		$required_roles = (array) get_option( 'smsentry_required_roles', array() );

		if ( ! empty( $required_roles ) ) {
			$user = get_userdata( $user_id );
			if ( $user && array_intersect( $user->roles, $required_roles ) ) {
				return true;
			}
		}

		return (bool) get_user_meta( $user_id, 'smsentry_2fa_enabled', true );
	}

	public static function activate(): void {
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
