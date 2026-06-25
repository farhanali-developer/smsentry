<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_User_Profile {

	private SMSentry_Authenticator $authenticator;

	public function __construct( SMSentry_Authenticator $authenticator ) {
		$this->authenticator = $authenticator;
	}

	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render_2fa_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_2fa_section' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_smsentry_send_phone_otp', array( $this, 'ajax_send_phone_otp' ) );
		add_action( 'wp_ajax_smsentry_verify_phone_otp', array( $this, 'ajax_verify_phone_otp' ) );
		add_action( 'wp_ajax_smsentry_toggle_2fa', array( $this, 'ajax_toggle_2fa' ) );
		add_action( 'wp_ajax_smsentry_remove_2fa', array( $this, 'ajax_remove_2fa' ) );
		add_action( 'wp_ajax_smsentry_generate_backup_codes', array( $this, 'ajax_generate_backup_codes' ) );
		add_action( 'wp_ajax_smsentry_enable_email_2fa', array( $this, 'ajax_enable_email_2fa' ) );
	}

	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'smsentry-profile', SMSENTRY_URL . 'assets/css/smsentry.css', array(), SMSENTRY_VERSION );
		wp_enqueue_script( 'smsentry-profile', SMSENTRY_URL . 'assets/js/smsentry.js', array( 'jquery' ), SMSENTRY_VERSION, true );

		wp_localize_script( 'smsentry-profile', 'smsentryProfile', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'smsentry_profile' ),
			'i18n'    => array(
				'sending'         => __( 'Sending code...', 'smsentry' ),
				'verifying'       => __( 'Verifying...', 'smsentry' ),
				'sendCode'        => __( 'Send Verification Code', 'smsentry' ),
				'resendCode'      => __( 'Resend Code', 'smsentry' ),
				'verifyCode'      => __( 'Verify Code', 'smsentry' ),
				'phoneVerified'   => __( '2FA is now active on your account.', 'smsentry' ),
				'phoneRequired'   => __( 'Please enter your phone number first.', 'smsentry' ),
				'invalidPhone'    => __( 'Enter a valid number in international format: +14155551234', 'smsentry' ),
				'confirmRemove'   => __( 'Remove 2FA from your account? You will no longer be required to enter an SMS code on login.', 'smsentry' ),
				'generating'      => __( 'Generating...', 'smsentry' ),
				'generateCodes'   => __( 'Generate Backup Codes', 'smsentry' ),
				'regenerateCodes' => __( 'Regenerate Backup Codes', 'smsentry' ),
				'confirmRegen'    => __( 'This will invalidate your existing backup codes. Continue?', 'smsentry' ),
				'codesCopied'     => __( 'Codes copied to clipboard.', 'smsentry' ),
				'saveCodesNotice' => __( 'Save these codes somewhere safe — they will not be shown again. Each code can be used once.', 'smsentry' ),
				'copyCodes'       => __( 'Copy Codes', 'smsentry' ),
				'downloadCodes'   => __( 'Download', 'smsentry' ),
				'enablingEmail'   => __( 'Enabling...', 'smsentry' ),
				'useEmailInstead' => __( 'Use Email Instead', 'smsentry' ),
				'emailEnabled'    => __( 'Email-based 2FA is now active on your account.', 'smsentry' ),
			),
		) );
	}

	public function render_2fa_section( WP_User $user ): void {
		$phone             = (string) get_user_meta( $user->ID, 'smsentry_phone', true );
		$phone_verified    = (bool) get_user_meta( $user->ID, 'smsentry_phone_verified', true );
		$enabled           = (bool) get_user_meta( $user->ID, 'smsentry_2fa_enabled', true );
		$email_2fa_enabled = (bool) get_user_meta( $user->ID, 'smsentry_email_2fa_enabled', true );
		$user_can_disable  = (bool) get_option( 'smsentry_user_can_disable', true );
		$email_fallback_allowed = (bool) get_option( 'smsentry_email_fallback_enabled', true );

		$required_roles = (array) get_option( 'smsentry_required_roles', array() );
		$is_required    = (bool) array_intersect( $user->roles, $required_roles );

		// Admins can edit any profile; users can only edit their own.
		$can_edit = current_user_can( 'manage_options' ) || get_current_user_id() === $user->ID;

		// Phone takes priority over email when both happen to be set — matches SMSentry_Plugin::get_2fa_method().
		$active_method = $phone_verified ? 'sms' : ( $email_2fa_enabled ? 'email' : 'none' );

		$backup_codes_remaining = ( 'none' !== $active_method ) ? $this->authenticator->get_backup_codes_remaining( $user->ID ) : 0;

		require SMSENTRY_DIR . 'admin/views/user-profile-field.php';
	}

	public function ajax_send_phone_otp(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

		if ( ! $this->is_valid_e164( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid number in E.164 format, e.g. +14155551234', 'smsentry' ) ) );
		}

		$result = $this->authenticator->send_phone_verification_otp( $phone );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Verification code sent! Check your phone.', 'smsentry' ) ) );
	}

	public function ajax_verify_phone_otp(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$otp   = sanitize_text_field( wp_unslash( $_POST['otp'] ?? '' ) );

		if ( ! $this->is_valid_e164( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid phone number.', 'smsentry' ) ) );
		}

		$result = $this->authenticator->verify_phone_otp( $phone, $otp );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_user_meta( $user_id, 'smsentry_phone', $phone );
		update_user_meta( $user_id, 'smsentry_phone_verified', true );
		update_user_meta( $user_id, 'smsentry_2fa_enabled', true );
		// Phone takes priority over email when verified — drop the email fallback flag to avoid confusion.
		delete_user_meta( $user_id, 'smsentry_email_2fa_enabled' );

		$masked = strlen( $phone ) >= 4
			? str_repeat( '*', max( 0, strlen( $phone ) - 4 ) ) . substr( $phone, -4 )
			: $phone;
		SMSentry_Audit_Log::log( $user_id, 'phone_verified', $masked );

		wp_send_json_success( array( 'message' => __( 'Phone verified. Two-factor authentication is now active.', 'smsentry' ) ) );
	}

	public function ajax_toggle_2fa(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		if ( ! get_option( 'smsentry_user_can_disable', true ) ) {
			wp_send_json_error( array( 'message' => __( '2FA is managed by the site administrator.', 'smsentry' ) ) );
		}

		$enabled = rest_sanitize_boolean( sanitize_text_field( wp_unslash( $_POST['enabled'] ?? '' ) ) );
		update_user_meta( $user_id, 'smsentry_2fa_enabled', $enabled );
		SMSentry_Audit_Log::log( $user_id, $enabled ? '2fa_enabled' : '2fa_disabled' );

		wp_send_json_success( array(
			'message' => $enabled
				? __( 'Two-factor authentication enabled.', 'smsentry' )
				: __( 'Two-factor authentication disabled.', 'smsentry' ),
		) );
	}

	public function ajax_remove_2fa(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		if ( ! get_option( 'smsentry_user_can_disable', true ) ) {
			wp_send_json_error( array( 'message' => __( '2FA is managed by the site administrator.', 'smsentry' ) ) );
		}

		delete_user_meta( $user_id, 'smsentry_phone' );
		delete_user_meta( $user_id, 'smsentry_phone_verified' );
		delete_user_meta( $user_id, 'smsentry_2fa_enabled' );
		delete_user_meta( $user_id, 'smsentry_backup_codes' );
		delete_user_meta( $user_id, 'smsentry_email_2fa_enabled' );

		SMSentry_Audit_Log::log( $user_id, '2fa_disabled', 'removed by user/admin' );

		wp_send_json_success( array( 'message' => __( 'Two-factor authentication has been removed from your account.', 'smsentry' ) ) );
	}

	public function ajax_generate_backup_codes(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		$has_method = (bool) get_user_meta( $user_id, 'smsentry_phone_verified', true )
			|| (bool) get_user_meta( $user_id, 'smsentry_email_2fa_enabled', true );

		if ( ! $has_method ) {
			wp_send_json_error( array( 'message' => __( 'Set up phone or email 2FA before generating backup codes.', 'smsentry' ) ) );
		}

		$codes = $this->authenticator->generate_backup_codes( $user_id );
		SMSentry_Audit_Log::log( $user_id, 'backup_codes_generated' );

		wp_send_json_success( array( 'codes' => $codes ) );
	}

	public function ajax_enable_email_2fa(): void {
		check_ajax_referer( 'smsentry_profile', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smsentry' ) ) );
		}

		if ( ! (bool) get_option( 'smsentry_email_fallback_enabled', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Email-based 2FA is disabled by the site administrator.', 'smsentry' ) ) );
		}

		if ( (bool) get_user_meta( $user_id, 'smsentry_phone_verified', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Remove your phone number first to switch to email-based 2FA.', 'smsentry' ) ) );
		}

		update_user_meta( $user_id, 'smsentry_email_2fa_enabled', true );
		SMSentry_Audit_Log::log( $user_id, 'email_2fa_enabled' );

		wp_send_json_success( array( 'message' => __( 'Email-based 2FA is now active on your account.', 'smsentry' ) ) );
	}

	private function is_valid_e164( string $phone ): bool {
		return (bool) preg_match( '/^\+[1-9]\d{6,14}$/', $phone );
	}
}
