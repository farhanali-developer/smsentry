<?php
/**
 * Plugin Name:       SMSentry
 * Plugin URI:        https://wordpress.org/plugins/smsentry/
 * Description:       Two-factor authentication for WordPress via SMS. Supports Twilio and Vonage with a swappable provider interface.
 * Version:           1.3.1
 * Author:            SMSentry
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smsentry
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SMSENTRY_VERSION', '1.3.1' );
define( 'SMSENTRY_DB_VERSION', '1.1' );
define( 'SMSENTRY_FILE', __FILE__ );
define( 'SMSENTRY_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMSENTRY_URL', plugin_dir_url( __FILE__ ) );

require_once SMSENTRY_DIR . 'includes/class-crypto.php';
require_once SMSENTRY_DIR . 'includes/class-countries.php';
require_once SMSENTRY_DIR . 'includes/class-audit-log.php';
require_once SMSENTRY_DIR . 'includes/class-stats.php';
require_once SMSENTRY_DIR . 'includes/class-notifier.php';
require_once SMSENTRY_DIR . 'includes/class-device-trust.php';
require_once SMSENTRY_DIR . 'includes/providers/interface-sms-provider.php';
require_once SMSENTRY_DIR . 'includes/providers/class-twilio-provider.php';
require_once SMSENTRY_DIR . 'includes/providers/class-vonage-provider.php';
require_once SMSENTRY_DIR . 'includes/class-rate-limiter.php';
require_once SMSENTRY_DIR . 'includes/class-session.php';
require_once SMSENTRY_DIR . 'includes/class-authenticator.php';
require_once SMSENTRY_DIR . 'includes/class-plugin.php';
require_once SMSENTRY_DIR . 'public/class-login-handler.php';

add_action( 'smsentry_prune_audit_log', array( 'SMSentry_Audit_Log', 'prune_old_entries' ) );

if ( is_admin() ) {
	require_once SMSENTRY_DIR . 'admin/class-admin.php';
	require_once SMSENTRY_DIR . 'admin/class-user-profile.php';
	require_once SMSENTRY_DIR . 'admin/class-users-list.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SMSENTRY_DIR . 'includes/class-cli-command.php';
	WP_CLI::add_command( 'smsentry', 'SMSentry_CLI_Command' );
}

function smsentry(): SMSentry_Plugin {
	return SMSentry_Plugin::instance();
}

add_action( 'plugins_loaded', 'smsentry' );

register_activation_hook( __FILE__, array( 'SMSentry_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SMSentry_Plugin', 'deactivate' ) );
