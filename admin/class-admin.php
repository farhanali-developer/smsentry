<?php
defined( 'ABSPATH' ) || exit;

class SMSentry_Admin {

	private SMSentry_Plugin $plugin;

	public function __construct( SMSentry_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_smsentry_test_sms', array( $this, 'ajax_test_sms' ) );
		add_action( 'wp_ajax_smsentry_validate_credentials', array( $this, 'ajax_validate_credentials' ) );
		add_action( 'wp_ajax_smsentry_dismiss_setup_notice', array( $this, 'ajax_dismiss_setup_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SMSENTRY_FILE ), array( $this, 'add_action_links' ) );
	}

	public function add_settings_page(): void {
		add_menu_page(
			__( 'SMSentry — Two-Factor Authentication', 'smsentry' ),
			__( 'SMSentry', 'smsentry' ),
			'manage_options',
			'smsentry',
			array( $this, 'render_settings_page' ),
			'dashicons-lock',
			81
		);

		// Replace the auto-generated duplicate submenu entry with a labelled one.
		add_submenu_page(
			'smsentry',
			__( 'SMSentry Settings', 'smsentry' ),
			__( 'Settings', 'smsentry' ),
			'manage_options',
			'smsentry',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Two separate option groups — one per tab. Each tab's <form> only
	 * renders its own fields, and WordPress's options.php nulls out any
	 * registered option that isn't present in the submitted POST data. With
	 * a single shared group, saving the Security tab would wipe every
	 * Provider tab option (and vice versa). Splitting by tab means saving
	 * one tab never touches the other's settings at all.
	 */
	public function register_settings(): void {
		$provider_fields = array(
			'smsentry_provider'      => 'sanitize_text_field',
			'smsentry_twilio_sid'    => 'sanitize_text_field',
			'smsentry_twilio_from'   => 'sanitize_text_field',
			'smsentry_vonage_key'    => 'sanitize_text_field',
			'smsentry_vonage_from'   => 'sanitize_text_field',
			'smsentry_aws_access_key' => 'sanitize_text_field',
			'smsentry_aws_region'    => 'sanitize_text_field',
			'smsentry_aws_sender_id' => 'sanitize_text_field',
		);

		foreach ( $provider_fields as $option => $callback ) {
			register_setting( 'smsentry_provider_settings', $option, array( 'sanitize_callback' => $callback ) );
		}

		// Secrets are encrypted before storage. Separate methods per field — no fragile
		// introspection of which filter fired, unlike the single shared callback this replaces.
		register_setting( 'smsentry_provider_settings', 'smsentry_twilio_token', array(
			'sanitize_callback' => array( $this, 'sanitize_twilio_token' ),
		) );
		register_setting( 'smsentry_provider_settings', 'smsentry_vonage_secret', array(
			'sanitize_callback' => array( $this, 'sanitize_vonage_secret' ),
		) );
		register_setting( 'smsentry_provider_settings', 'smsentry_aws_secret_key', array(
			'sanitize_callback' => array( $this, 'sanitize_aws_secret_key' ),
		) );

		$security_fields = array(
			'smsentry_otp_ttl'                 => 'absint',
			'smsentry_max_attempts'             => 'absint',
			'smsentry_lockout_duration'         => 'absint',
			'smsentry_user_can_disable'         => 'rest_sanitize_boolean',
			'smsentry_email_fallback_enabled'   => 'rest_sanitize_boolean',
			'smsentry_security_emails_enabled'  => 'rest_sanitize_boolean',
			'smsentry_remember_device_enabled'  => 'rest_sanitize_boolean',
		);

		foreach ( $security_fields as $option => $callback ) {
			register_setting( 'smsentry_security_settings', $option, array( 'sanitize_callback' => $callback ) );
		}

		register_setting( 'smsentry_security_settings', 'smsentry_required_roles', array(
			'sanitize_callback' => array( $this, 'sanitize_roles' ),
		) );
	}

	public function sanitize_twilio_token( string $value ): string {
		// If the field was left blank (or is the placeholder), keep the existing value.
		if ( empty( $value ) || '••••••••' === $value ) {
			return (string) get_option( 'smsentry_twilio_token', '' );
		}
		return SMSentry_Crypto::encrypt( sanitize_text_field( $value ) );
	}

	public function sanitize_vonage_secret( string $value ): string {
		if ( empty( $value ) || '••••••••' === $value ) {
			return (string) get_option( 'smsentry_vonage_secret', '' );
		}
		return SMSentry_Crypto::encrypt( sanitize_text_field( $value ) );
	}

	public function sanitize_aws_secret_key( string $value ): string {
		if ( empty( $value ) || '••••••••' === $value ) {
			return (string) get_option( 'smsentry_aws_secret_key', '' );
		}
		return SMSentry_Crypto::encrypt( sanitize_text_field( $value ) );
	}

	public function sanitize_roles( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$valid_roles = array_keys( wp_roles()->roles );
		return array_values( array_intersect( array_map( 'sanitize_text_field', $value ), $valid_roles ) );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab     = sanitize_key( isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : 'provider' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab is a display parameter, not processed form data.
		$provider       = get_option( 'smsentry_provider', 'twilio' );
		$required_roles = (array) get_option( 'smsentry_required_roles', array() );
		$all_roles      = wp_roles()->get_names();
		$setup_checklist = $this->prepare_setup_checklist();

		if ( 'audit_log' === $active_tab ) {
			$audit_log = $this->prepare_audit_log_data();
		}

		if ( 'security' === $active_tab ) {
			$adoption = SMSentry_Stats::get_adoption_summary();
		}

		require SMSENTRY_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Build the first-run setup checklist shown above the tabs. Returns null
	 * once dismissed by the current admin, or once the core steps (provider
	 * credentials + a successful test send) are both complete.
	 */
	private function prepare_setup_checklist(): ?array {
		$current_user_id = get_current_user_id();

		if ( (bool) get_user_meta( $current_user_id, 'smsentry_setup_notice_dismissed', true ) ) {
			return null;
		}

		$has_credentials = $this->has_provider_credentials();
		$test_sent        = (bool) get_option( 'smsentry_test_sms_sent', false );

		if ( $has_credentials && $test_sent ) {
			return null;
		}

		return array(
			'credentials' => $has_credentials,
			'test_sent'   => $test_sent,
			'own_2fa'     => null !== SMSentry_Plugin::get_2fa_method( $current_user_id ),
		);
	}

	private function has_provider_credentials(): bool {
		if ( 'vonage' === get_option( 'smsentry_provider', 'twilio' ) ) {
			return ! empty( get_option( 'smsentry_vonage_key' ) ) && ! empty( get_option( 'smsentry_vonage_secret' ) );
		}
		return ! empty( get_option( 'smsentry_twilio_sid' ) ) && ! empty( get_option( 'smsentry_twilio_token' ) );
	}

	public function ajax_dismiss_setup_notice(): void {
		check_ajax_referer( 'smsentry_admin', 'nonce' );
		update_user_meta( get_current_user_id(), 'smsentry_setup_notice_dismissed', true );
		wp_send_json_success();
	}

	/**
	 * Build the filtered, paginated audit log dataset consumed by the "Audit Log" tab view.
	 */
	private function prepare_audit_log_data(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters on an admin listing page, not state-changing form data.
		$log_user_input = sanitize_text_field( wp_unslash( $_GET['log_user'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log_event  = sanitize_key( wp_unslash( $_GET['log_event'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged      = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
		$per_page   = 20;

		$log_user_id = 0;
		if ( '' !== $log_user_input ) {
			$found = is_numeric( $log_user_input )
				? get_user_by( 'id', (int) $log_user_input )
				: ( get_user_by( 'login', $log_user_input ) ?: get_user_by( 'email', $log_user_input ) );
			$log_user_id = $found ? $found->ID : -1; // -1 guarantees zero results for an unmatched filter instead of silently showing everyone.
		}

		return array(
			'entries'      => SMSentry_Audit_Log::get_entries( $per_page, $paged, $log_user_id, $log_event ),
			'total'        => SMSentry_Audit_Log::count_entries( $log_user_id, $log_event ),
			'per_page'     => $per_page,
			'paged'        => $paged,
			'log_user'     => $log_user_input,
			'log_event'    => $log_event,
			'event_labels' => SMSentry_Audit_Log::get_event_labels(),
		);
	}

	public function enqueue_scripts( string $hook ): void {
		if ( 'toplevel_page_smsentry' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'smsentry-admin', SMSENTRY_URL . 'assets/css/smsentry.css', array(), SMSENTRY_VERSION );
		wp_enqueue_script( 'smsentry-admin', SMSENTRY_URL . 'assets/js/smsentry.js', array( 'jquery' ), SMSENTRY_VERSION, true );

		wp_localize_script( 'smsentry-admin', 'smsentryAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'smsentry_admin' ),
			'i18n'    => array(
				'testing'   => __( 'Sending test SMS...', 'smsentry' ),
				'validating'=> __( 'Validating...', 'smsentry' ),
				'send'      => __( 'Send Test SMS', 'smsentry' ),
				'validate'  => __( 'Validate Credentials', 'smsentry' ),
			),
		) );
	}

	public function ajax_test_sms(): void {
		check_ajax_referer( 'smsentry_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smsentry' ) ) );
		}

		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a phone number to test with.', 'smsentry' ) ) );
		}

		$result = $this->plugin->provider->send(
			$phone,
			sprintf(
				/* translators: site name */
				__( 'SMSentry test from %s — your configuration is working!', 'smsentry' ),
				get_bloginfo( 'name' )
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_option( 'smsentry_test_sms_sent', true );

		wp_send_json_success( array( 'message' => __( 'Test SMS sent successfully!', 'smsentry' ) ) );
	}

	public function ajax_validate_credentials(): void {
		check_ajax_referer( 'smsentry_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smsentry' ) ) );
		}

		$result = $this->plugin->provider->validate_credentials();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Credentials are valid!', 'smsentry' ) ) );
	}

	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=smsentry' ) ),
			__( 'Settings', 'smsentry' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
