<?php
/**
 * Variables available from SMSentry_Admin::render_settings_page():
 *
 * @var string     $active_tab
 * @var string     $provider
 * @var array      $required_roles
 * @var array      $all_roles
 * @var array|null $audit_log   Only set when $active_tab === 'audit_log'.
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap smsentry-settings">
	<h1>
		<span class="smsentry-logo">&#128274;</span>
		<?php esc_html_e( 'SMSentry — Two-Factor Authentication', 'smsentry' ); ?>
	</h1>

	<?php settings_errors( 'smsentry_settings' ); ?>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'provider', admin_url( 'admin.php?page=smsentry' ) ) ); ?>"
		   class="nav-tab <?php echo 'provider' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'SMS Provider', 'smsentry' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'security', admin_url( 'admin.php?page=smsentry' ) ) ); ?>"
		   class="nav-tab <?php echo 'security' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Security', 'smsentry' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit_log', admin_url( 'admin.php?page=smsentry' ) ) ); ?>"
		   class="nav-tab <?php echo 'audit_log' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Audit Log', 'smsentry' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'test', admin_url( 'admin.php?page=smsentry' ) ) ); ?>"
		   class="nav-tab <?php echo 'test' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Test & Validate', 'smsentry' ); ?>
		</a>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'smsentry_settings' ); ?>

		<?php if ( 'provider' === $active_tab ) : ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'SMS Provider', 'smsentry' ); ?></th>
				<td>
					<select name="smsentry_provider" id="smsentry_provider" class="smsentry-provider-select">
						<option value="twilio" <?php selected( $provider, 'twilio' ); ?>>Twilio</option>
						<option value="vonage" <?php selected( $provider, 'vonage' ); ?>>Vonage</option>
					</select>
				</td>
			</tr>
		</table>

		<!-- Twilio credentials -->
		<div class="smsentry-provider-section" data-provider="twilio" <?php echo 'twilio' !== $provider ? 'style="display:none"' : ''; ?>>
			<h2><?php esc_html_e( 'Twilio Credentials', 'smsentry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Find these in your Twilio Console dashboard.', 'smsentry' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="smsentry_twilio_sid"><?php esc_html_e( 'Account SID', 'smsentry' ); ?></label></th>
					<td>
						<input type="text" id="smsentry_twilio_sid" name="smsentry_twilio_sid"
						       value="<?php echo esc_attr( get_option( 'smsentry_twilio_sid', '' ) ); ?>"
						       class="regular-text" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smsentry_twilio_token"><?php esc_html_e( 'Auth Token', 'smsentry' ); ?></label></th>
					<td>
						<input type="password" id="smsentry_twilio_token" name="smsentry_twilio_token"
						       value="" placeholder="<?php echo get_option( 'smsentry_twilio_token' ) ? '••••••••' : ''; ?>"
						       class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Leave blank to keep the existing token.', 'smsentry' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smsentry_twilio_from"><?php esc_html_e( 'From Number', 'smsentry' ); ?></label></th>
					<td>
						<input type="text" id="smsentry_twilio_from" name="smsentry_twilio_from"
						       value="<?php echo esc_attr( get_option( 'smsentry_twilio_from', '' ) ); ?>"
						       class="regular-text" placeholder="+14155551234" />
						<p class="description"><?php esc_html_e( 'Your Twilio phone number in E.164 format.', 'smsentry' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Vonage credentials -->
		<div class="smsentry-provider-section" data-provider="vonage" <?php echo 'vonage' !== $provider ? 'style="display:none"' : ''; ?>>
			<h2><?php esc_html_e( 'Vonage Credentials', 'smsentry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Find these in your Vonage API Dashboard.', 'smsentry' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="smsentry_vonage_key"><?php esc_html_e( 'API Key', 'smsentry' ); ?></label></th>
					<td>
						<input type="text" id="smsentry_vonage_key" name="smsentry_vonage_key"
						       value="<?php echo esc_attr( get_option( 'smsentry_vonage_key', '' ) ); ?>"
						       class="regular-text" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smsentry_vonage_secret"><?php esc_html_e( 'API Secret', 'smsentry' ); ?></label></th>
					<td>
						<input type="password" id="smsentry_vonage_secret" name="smsentry_vonage_secret"
						       value="" placeholder="<?php echo get_option( 'smsentry_vonage_secret' ) ? '••••••••' : ''; ?>"
						       class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Leave blank to keep the existing secret.', 'smsentry' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smsentry_vonage_from"><?php esc_html_e( 'From Name / Number', 'smsentry' ); ?></label></th>
					<td>
						<input type="text" id="smsentry_vonage_from" name="smsentry_vonage_from"
						       value="<?php echo esc_attr( get_option( 'smsentry_vonage_from', '' ) ); ?>"
						       class="regular-text" placeholder="MySite" />
						<p class="description"><?php esc_html_e( 'Alphanumeric sender ID or a phone number.', 'smsentry' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php elseif ( 'security' === $active_tab ) : ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="smsentry_otp_ttl"><?php esc_html_e( 'Code Expiry', 'smsentry' ); ?></label></th>
				<td>
					<input type="number" id="smsentry_otp_ttl" name="smsentry_otp_ttl" min="60" max="900" step="30"
					       value="<?php echo esc_attr( get_option( 'smsentry_otp_ttl', 300 ) ); ?>" class="small-text" />
					<?php esc_html_e( 'seconds', 'smsentry' ); ?>
					<p class="description"><?php esc_html_e( 'How long a login code remains valid. Default: 300 (5 minutes).', 'smsentry' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="smsentry_max_attempts"><?php esc_html_e( 'Max Failed Attempts', 'smsentry' ); ?></label></th>
				<td>
					<input type="number" id="smsentry_max_attempts" name="smsentry_max_attempts" min="1" max="10"
					       value="<?php echo esc_attr( get_option( 'smsentry_max_attempts', 5 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Failed code entries before the session is locked out. Default: 5.', 'smsentry' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="smsentry_lockout_duration"><?php esc_html_e( 'Lockout Duration', 'smsentry' ); ?></label></th>
				<td>
					<input type="number" id="smsentry_lockout_duration" name="smsentry_lockout_duration" min="60" max="86400" step="60"
					       value="<?php echo esc_attr( get_option( 'smsentry_lockout_duration', 900 ) ); ?>" class="small-text" />
					<?php esc_html_e( 'seconds', 'smsentry' ); ?>
					<p class="description"><?php esc_html_e( 'How long a user is locked out after too many failed attempts. Default: 900 (15 minutes).', 'smsentry' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require 2FA for Roles', 'smsentry' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Require 2FA for Roles', 'smsentry' ); ?></legend>
						<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
							<label>
								<input type="checkbox" name="smsentry_required_roles[]"
								       value="<?php echo esc_attr( $role_slug ); ?>"
								       <?php checked( in_array( $role_slug, $required_roles, true ) ); ?> />
								<?php echo esc_html( translate_user_role( $role_name ) ); ?>
							</label><br>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Users with these roles must complete 2FA even if they have not opted in personally.', 'smsentry' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'User Self-Management', 'smsentry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smsentry_user_can_disable" value="1"
						       <?php checked( get_option( 'smsentry_user_can_disable', true ) ); ?> />
						<?php esc_html_e( 'Allow users to enable or disable 2FA from their own profile', 'smsentry' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When unchecked, only admins can control 2FA settings.', 'smsentry' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Fallback', 'smsentry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smsentry_email_fallback_enabled" value="1"
						       <?php checked( get_option( 'smsentry_email_fallback_enabled', true ) ); ?> />
						<?php esc_html_e( 'Allow email-based 2FA for users without a verified phone number', 'smsentry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Covers two cases: users who opt into email codes from their profile, and users under a required role who have not yet verified a phone. Uncheck to require SMS only.', 'smsentry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="smsentry-notice smsentry-notice-info">
			<p><strong><?php esc_html_e( 'Emergency Access', 'smsentry' ); ?></strong></p>
			<p>
				<?php esc_html_e( 'If SMS delivery breaks and logins are blocked site-wide, add this to wp-config.php to disable 2FA enforcement immediately:', 'smsentry' ); ?>
			</p>
			<p><code>define( 'SMSENTRY_DISABLE_2FA', true );</code></p>
			<p>
				<?php esc_html_e( 'To reset 2FA for a single locked-out user via WP-CLI instead:', 'smsentry' ); ?>
			</p>
			<p><code>wp smsentry reset &lt;user&gt;</code></p>
		</div>

		<?php elseif ( 'audit_log' === $active_tab ) : ?>

		<?php require __DIR__ . '/audit-log-tab.php'; ?>

		<?php elseif ( 'test' === $active_tab ) : ?>

		<h2><?php esc_html_e( 'Validate Credentials', 'smsentry' ); ?></h2>
		<p><?php esc_html_e( 'Checks that your API credentials are accepted by the provider.', 'smsentry' ); ?></p>
		<p>
			<button type="button" id="smsentry-validate" class="button button-secondary">
				<?php esc_html_e( 'Validate Credentials', 'smsentry' ); ?>
			</button>
			<span id="smsentry-validate-result" class="smsentry-result"></span>
		</p>

		<hr>

		<h2><?php esc_html_e( 'Send a Test SMS', 'smsentry' ); ?></h2>
		<p><?php esc_html_e( 'Send a real SMS to confirm delivery is working end-to-end.', 'smsentry' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="smsentry-test-phone"><?php esc_html_e( 'Phone Number', 'smsentry' ); ?></label></th>
				<td>
					<div class="smsentry-phone-row">
						<div class="smsentry-phone-group">
							<?php SMSentry_Countries::render_picker( 'smsentry-test-country', 'smsentry_test_country' ); ?>
							<input type="tel" id="smsentry-test-phone" class="smsentry-phone-number" placeholder="4155551234" />
						</div>
						<button type="button" id="smsentry-test-send" class="button button-secondary smsentry-phone-action">
							<?php esc_html_e( 'Send Test SMS', 'smsentry' ); ?>
						</button>
					</div>
					<p id="smsentry-test-result" class="smsentry-result" style="margin-top:8px"></p>
				</td>
			</tr>
		</table>

		<?php endif; ?>

		<?php if ( ! in_array( $active_tab, array( 'test', 'audit_log' ), true ) ) : ?>
			<?php submit_button(); ?>
		<?php endif; ?>

	</form>
</div>
