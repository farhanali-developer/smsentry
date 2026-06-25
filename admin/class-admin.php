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

	public function register_settings(): void {
		$simple_fields = array(
			'smsentry_provider'         => 'sanitize_text_field',
			'smsentry_twilio_sid'       => 'sanitize_text_field',
			'smsentry_twilio_from'      => 'sanitize_text_field',
			'smsentry_vonage_key'       => 'sanitize_text_field',
			'smsentry_vonage_from'      => 'sanitize_text_field',
			'smsentry_otp_ttl'          => 'absint',
			'smsentry_max_attempts'     => 'absint',
			'smsentry_lockout_duration' => 'absint',
		);

		foreach ( $simple_fields as $option => $callback ) {
			register_setting( 'smsentry_settings', $option, array( 'sanitize_callback' => $callback ) );
		}

		// Secrets are encrypted before storage.
		register_setting( 'smsentry_settings', 'smsentry_twilio_token', array(
			'sanitize_callback' => array( $this, 'sanitize_secret' ),
		) );
		register_setting( 'smsentry_settings', 'smsentry_vonage_secret', array(
			'sanitize_callback' => array( $this, 'sanitize_secret' ),
		) );

		register_setting( 'smsentry_settings', 'smsentry_required_roles', array(
			'sanitize_callback' => array( $this, 'sanitize_roles' ),
		) );
		register_setting( 'smsentry_settings', 'smsentry_user_can_disable', array(
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );
	}

	public function sanitize_secret( string $value ): string {
		// If the field was left blank (or is the placeholder), keep the existing value.
		if ( empty( $value ) || '••••••••' === $value ) {
			return get_option( 'smsentry_' . current_filter() === 'sanitize_option_smsentry_twilio_token' ? 'smsentry_twilio_token' : 'smsentry_vonage_secret', '' );
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

		require SMSENTRY_DIR . 'admin/views/settings-page.php';
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
