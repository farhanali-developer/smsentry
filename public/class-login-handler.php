<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Login_Handler {

	private SMSentry_Authenticator $authenticator;
	private SMSentry_Session $session;
	private SMSentry_Rate_Limiter $rate_limiter;
	private SMSentry_Device_Trust $device_trust;

	public function __construct(
		SMSentry_Authenticator $authenticator,
		SMSentry_Session $session,
		SMSentry_Rate_Limiter $rate_limiter,
		SMSentry_Device_Trust $device_trust
	) {
		$this->authenticator = $authenticator;
		$this->session       = $session;
		$this->rate_limiter  = $rate_limiter;
		$this->device_trust  = $device_trust;
	}

	public function register(): void {
		add_filter( 'authenticate', array( $this, 'intercept_login' ), 100, 3 );
		add_action( 'wp_login_failed', array( $this, 'redirect_to_verify_page' ), 10, 2 );
		add_action( 'login_init', array( $this, 'maybe_handle_verify_page' ) );
		add_action( 'wp_ajax_nopriv_smsentry_resend_otp', array( $this, 'ajax_resend_otp' ) );
	}

	/**
	 * Hook: authenticate (priority 100 — after WP's own checks at 20 and 30).
	 *
	 * If credentials are valid and 2FA is needed:
	 *   - Create interim session
	 *   - Send OTP via SMS
	 *   - Return a special WP_Error to cancel the normal login flow
	 *   - wp_login_failed will redirect to the verify page
	 */
	public function intercept_login( mixed $user, string $username, string $password ): mixed {
		// Only act when WP has already authenticated successfully.
		if ( ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		$method = SMSentry_Plugin::get_2fa_method( $user->ID );

		if ( null === $method ) {
			return $user;
		}

		if ( (bool) get_option( 'smsentry_remember_device_enabled', true ) && $this->device_trust->is_trusted( $user->ID ) ) {
			return $user; // Trusted device — skip 2FA entirely.
		}

		$this->session->create( $user->ID, $method );

		$result = $this->authenticator->send_otp( $user->ID, $method );

		if ( is_wp_error( $result ) ) {
			$this->session->destroy();
			SMSentry_Audit_Log::log( $user->ID, 'otp_send_failed', $method . ': ' . $result->get_error_message() );
			// Allow site owners to hook in and handle the failure (e.g. their own logging/alerting).
			do_action( 'smsentry_otp_send_failed', $user->ID, $result );

			return new WP_Error(
				'smsentry_sms_failed',
				sprintf(
					/* translators: admin email address */
					__( '<strong>Error:</strong> Could not send your verification code. Please contact the site administrator at %s.', 'smsentry' ),
					esc_html( get_option( 'admin_email' ) )
				)
			);
		}

		return new WP_Error( 'smsentry_2fa_required', '' );
	}

	/**
	 * Hook: wp_login_failed
	 * Fires BEFORE login_header() outputs any HTML — safe to redirect here.
	 */
	public function redirect_to_verify_page( mixed $username_data, WP_Error $error ): void {
		if ( 'smsentry_2fa_required' !== $error->get_error_code() ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'action', 'smsentry_verify', wp_login_url() ) );
		exit;
	}

	/**
	 * Hook: login_init
	 * Handles our custom action on wp-login.php.
	 */
	public function maybe_handle_verify_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- action is a routing parameter on wp-login.php, not user-submitted form data.
		$action = sanitize_key( isset( $_REQUEST['action'] ) ? wp_unslash( $_REQUEST['action'] ) : 'login' );

		if ( 'smsentry_verify' !== $action ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method ) {
			$this->process_otp_submission();
		} else {
			$this->render_verify_page();
		}
	}

	private function process_otp_submission(): void {
		if ( ! check_admin_referer( 'smsentry_verify_otp', 'smsentry_nonce' ) ) {
			$this->render_verify_page( __( 'Security check failed. Please try again.', 'smsentry' ) );
			return;
		}

		$user_id = $this->session->get_user_id();

		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'smsentry_error', 'session_expired', wp_login_url() ) );
			exit;
		}

		$ip = SMSentry_Audit_Log::get_client_ip();

		if ( $this->rate_limiter->is_locked_out( $user_id ) ) {
			$this->session->destroy();
			SMSentry_Audit_Log::log( $user_id, 'lockout' );
			wp_safe_redirect( add_query_arg( 'smsentry_error', 'locked', wp_login_url() ) );
			exit;
		}

		if ( $this->rate_limiter->is_ip_locked_out( $ip ) ) {
			$this->session->destroy();
			SMSentry_Audit_Log::log( $user_id, 'ip_lockout', $ip );
			wp_safe_redirect( add_query_arg( 'smsentry_error', 'locked', wp_login_url() ) );
			exit;
		}

		$mode   = sanitize_text_field( wp_unslash( $_POST['smsentry_mode'] ?? 'otp' ) );
		$code   = sanitize_text_field( wp_unslash( $_POST['smsentry_otp'] ?? '' ) );
		$result = ( 'backup' === $mode )
			? $this->authenticator->verify_backup_code( $user_id, $code )
			: $this->authenticator->verify_otp( $user_id, $code );

		if ( is_wp_error( $result ) ) {
			$this->rate_limiter->record_attempt( $user_id );
			$this->rate_limiter->record_ip_attempt( $ip );
			SMSentry_Audit_Log::log( $user_id, 'login_failed', $result->get_error_message() );

			if ( $this->rate_limiter->is_locked_out( $user_id ) ) {
				$this->session->destroy();
				SMSentry_Audit_Log::log( $user_id, 'lockout' );
				wp_safe_redirect( add_query_arg( 'smsentry_error', 'locked', wp_login_url() ) );
				exit;
			}

			if ( $this->rate_limiter->is_ip_locked_out( $ip ) ) {
				$this->session->destroy();
				SMSentry_Audit_Log::log( $user_id, 'ip_lockout', $ip );
				wp_safe_redirect( add_query_arg( 'smsentry_error', 'locked', wp_login_url() ) );
				exit;
			}

			$this->render_verify_page( $result->get_error_message() );
			return;
		}

		// Verification passed.
		$channel = ( 'backup' === $mode ) ? 'backup code' : $this->session->get_method();
		SMSentry_Audit_Log::log( $user_id, 'login_success', 'via ' . $channel );
		if ( 'backup' === $mode ) {
			SMSentry_Audit_Log::log( $user_id, 'backup_code_used' );
		}

		if ( (bool) get_option( 'smsentry_remember_device_enabled', true ) && ! empty( $_POST['smsentry_remember_device'] ) ) {
			$this->device_trust->trust_device( $user_id );
			SMSentry_Audit_Log::log( $user_id, 'device_trusted' );
		}

		$this->rate_limiter->reset( $user_id );
		$redirect_to = $this->session->get_redirect_to();
		$this->session->destroy();

		$remember = ! empty( $_POST['rememberme'] );
		wp_set_auth_cookie( $user_id, $remember );

		$user = get_userdata( $user_id );
		if ( $user ) {
			do_action( 'wp_login', $user->user_login, $user );
		}

		wp_safe_redirect( $redirect_to ?: admin_url() );
		exit;
	}

	private function render_verify_page( ?string $error = null ): void {
		if ( ! $this->session->is_valid() ) {
			wp_safe_redirect( add_query_arg( 'smsentry_error', 'session_expired', wp_login_url() ) );
			exit;
		}

		$user_id      = $this->session->get_user_id();
		$method       = $this->session->get_method();
		$masked_phone = '';
		$masked_email = '';

		if ( $user_id && 'email' === $method ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$masked_email = $this->mask_email( $user->user_email );
			}
		} elseif ( $user_id ) {
			$raw = (string) get_user_meta( $user_id, 'smsentry_phone', true );
			if ( strlen( $raw ) >= 4 ) {
				$masked_phone = str_repeat( '*', max( 0, strlen( $raw ) - 4 ) ) . substr( $raw, -4 );
			}
		}

		$can_resend          = $user_id ? $this->rate_limiter->can_resend( $user_id ) : true;
		$resend_remaining    = $user_id ? $this->rate_limiter->get_resend_remaining( $user_id ) : 0;
		$has_backup_codes    = $user_id ? $this->authenticator->get_backup_codes_remaining( $user_id ) > 0 : false;
		$can_remember_device = (bool) get_option( 'smsentry_remember_device_enabled', true );
		$redirect_to         = $this->session->get_redirect_to();
		$resend_nonce        = wp_create_nonce( 'smsentry_resend' );
		$ajax_url            = admin_url( 'admin-ajax.php' );

		// Enqueue our CSS/JS inside the WP login page (jQuery isn't loaded here by default).
		add_action( 'login_enqueue_scripts', function () {
			wp_enqueue_style( 'smsentry-login', SMSENTRY_URL . 'assets/css/smsentry.css', array(), SMSENTRY_VERSION );
			wp_enqueue_script( 'smsentry-login', SMSENTRY_URL . 'assets/js/smsentry.js', array( 'jquery' ), SMSENTRY_VERSION, true );
		} );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- login_header is a WP core function
		login_header( __( 'Verify Your Identity', 'smsentry' ) );

		require SMSENTRY_DIR . 'public/views/verify-otp.php';

		login_footer();
		exit;
	}

	public function ajax_resend_otp(): void {
		check_ajax_referer( 'smsentry_resend', 'nonce' );

		$user_id = $this->session->get_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please log in again.', 'smsentry' ) ) );
		}

		if ( ! $this->rate_limiter->can_resend( $user_id ) ) {
			wp_send_json_error( array(
				'message'   => sprintf(
					/* translators: seconds remaining */
					__( 'Please wait %d seconds before requesting a new code.', 'smsentry' ),
					$this->rate_limiter->get_resend_remaining( $user_id )
				),
				'remaining' => $this->rate_limiter->get_resend_remaining( $user_id ),
			) );
		}

		$result = $this->authenticator->send_otp( $user_id, $this->session->get_method() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->rate_limiter->set_resend_cooldown( $user_id );

		$message = ( 'email' === $this->session->get_method() )
			? __( 'A new code has been sent to your email.', 'smsentry' )
			: __( 'A new code has been sent to your phone.', 'smsentry' );

		wp_send_json_success( array(
			'message'   => $message,
			'remaining' => 60,
		) );
	}

	private function mask_email( string $email ): string {
		$at = strpos( $email, '@' );

		if ( false === $at || $at < 2 ) {
			return $email;
		}

		return substr( $email, 0, 2 ) . str_repeat( '*', $at - 2 ) . substr( $email, $at );
	}
}
