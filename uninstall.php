<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options
$options = array(
	'smsentry_provider',
	'smsentry_twilio_sid',
	'smsentry_twilio_token',
	'smsentry_twilio_from',
	'smsentry_vonage_key',
	'smsentry_vonage_secret',
	'smsentry_vonage_from',
	'smsentry_otp_ttl',
	'smsentry_max_attempts',
	'smsentry_lockout_duration',
	'smsentry_required_roles',
	'smsentry_user_can_disable',
	'smsentry_email_fallback_enabled',
	'smsentry_security_emails_enabled',
	'smsentry_remember_device_enabled',
	'smsentry_test_sms_sent',
	'smsentry_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove all user meta
$meta_keys = array(
	'smsentry_phone',
	'smsentry_phone_verified',
	'smsentry_2fa_enabled',
	'smsentry_backup_codes',
	'smsentry_email_2fa_enabled',
	'smsentry_force_required',
	'smsentry_trusted_devices',
	'smsentry_setup_notice_dismissed',
);

foreach ( $meta_keys as $key ) {
	delete_metadata( 'user', 0, $key, '', true );
}

// Bulk-delete SMSentry transients. No WordPress function exists for pattern-based
// transient deletion, so a direct query is the only practical option here.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_smsentry_%' OR option_name LIKE '_transient_timeout_smsentry_%'"
);

// Drop the audit log table.
$audit_table = $wpdb->prefix . 'smsentry_audit_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" );

wp_clear_scheduled_hook( 'smsentry_prune_audit_log' );
wp_cache_flush();
