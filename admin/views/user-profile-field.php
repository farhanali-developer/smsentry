<?php
/**
 * Variables available from SMSentry_User_Profile::render_2fa_section():
 *
 * @var WP_User $user
 * @var string  $phone
 * @var bool    $phone_verified
 * @var bool    $enabled
 * @var bool    $email_2fa_enabled
 * @var bool    $user_can_disable
 * @var bool    $email_fallback_allowed
 * @var bool    $is_required
 * @var bool    $can_edit
 * @var string  $active_method          'sms' | 'email' | 'none'
 * @var int     $backup_codes_remaining
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="smsentry-profile-section" id="smsentry-profile-2fa">
	<h2><?php esc_html_e( 'Two-Factor Authentication', 'smsentry' ); ?></h2>

	<?php if ( $is_required && 'none' === $active_method ) : ?>
		<div class="smsentry-notice smsentry-notice-warning">
			<p>
				<strong><?php esc_html_e( 'Required by your role:', 'smsentry' ); ?></strong>
				<?php esc_html_e( 'Two-factor authentication is mandatory for your account. Verify a phone number, or use email instead, below.', 'smsentry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( 'sms' === $active_method ) : ?>

		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Status', 'smsentry' ); ?></th>
				<td>
					<span class="smsentry-badge <?php echo $enabled ? 'smsentry-badge-active' : 'smsentry-badge-inactive'; ?>">
						<?php echo $enabled ? esc_html__( 'Active', 'smsentry' ) : esc_html__( 'Paused', 'smsentry' ); ?>
					</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Verified Number', 'smsentry' ); ?></th>
				<td>
					<code><?php
					// Show first 3 chars (country code), mask middle, show last 4.
					echo esc_html(
						substr( $phone, 0, 3 )
						. str_repeat( '*', max( 0, strlen( $phone ) - 7 ) )
						. substr( $phone, -4 )
					);
					?></code>
				</td>
			</tr>
		</table>

		<?php if ( $can_edit && ( $user_can_disable || current_user_can( 'manage_options' ) ) ) : ?>
			<p class="smsentry-profile-actions">
				<?php if ( ! $is_required ) : ?>
					<button type="button" id="smsentry-toggle-2fa"
					        class="button button-secondary"
					        data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
						<?php echo $enabled ? esc_html__( 'Pause 2FA', 'smsentry' ) : esc_html__( 'Resume 2FA', 'smsentry' ); ?>
					</button>
				<?php endif; ?>
				<button type="button" id="smsentry-remove-2fa" class="button button-link-delete">
					<?php esc_html_e( 'Remove phone &amp; disable 2FA', 'smsentry' ); ?>
				</button>
			</p>
			<p id="smsentry-toggle-result" class="smsentry-result"></p>
		<?php endif; ?>

		<?php require __DIR__ . '/backup-codes-widget.php'; ?>

		<?php if ( $can_edit ) : ?>
			<hr style="margin: 20px 0;">
			<p><strong><?php esc_html_e( 'Change Phone Number', 'smsentry' ); ?></strong></p>
			<?php require __DIR__ . '/phone-setup-widget.php'; ?>
		<?php endif; ?>

	<?php elseif ( 'email' === $active_method ) : ?>

		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Status', 'smsentry' ); ?></th>
				<td>
					<span class="smsentry-badge smsentry-badge-active"><?php esc_html_e( 'Active', 'smsentry' ); ?></span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Method', 'smsentry' ); ?></th>
				<td>
					<?php esc_html_e( 'Email', 'smsentry' ); ?>
					&mdash;
					<code><?php echo esc_html( $user->user_email ); ?></code>
				</td>
			</tr>
		</table>

		<?php if ( $can_edit && ( $user_can_disable || current_user_can( 'manage_options' ) ) ) : ?>
			<p class="smsentry-profile-actions">
				<?php if ( ! $is_required ) : ?>
					<button type="button" id="smsentry-remove-2fa" class="button button-link-delete">
						<?php esc_html_e( 'Disable email 2FA', 'smsentry' ); ?>
					</button>
				<?php endif; ?>
			</p>
			<p id="smsentry-toggle-result" class="smsentry-result"></p>
		<?php endif; ?>

		<?php require __DIR__ . '/backup-codes-widget.php'; ?>

		<?php if ( $can_edit ) : ?>
			<hr style="margin: 20px 0;">
			<p><strong><?php esc_html_e( 'Use a Phone Number Instead', 'smsentry' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Verifying a phone number will switch you to SMS-based codes.', 'smsentry' ); ?></p>
			<?php require __DIR__ . '/phone-setup-widget.php'; ?>
		<?php endif; ?>

	<?php else : ?>

		<p><?php esc_html_e( 'Add two-factor authentication to protect your account. Enter your phone number below to get started.', 'smsentry' ); ?></p>
		<?php if ( $can_edit ) : ?>
			<?php require __DIR__ . '/phone-setup-widget.php'; ?>

			<?php if ( $email_fallback_allowed ) : ?>
				<p class="smsentry-or-divider"><?php esc_html_e( 'or', 'smsentry' ); ?></p>
				<button type="button" id="smsentry-enable-email-2fa" class="button button-secondary">
					<?php esc_html_e( 'Use Email Instead', 'smsentry' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'Receive login codes at your account email instead of by SMS.', 'smsentry' ); ?></p>
				<p id="smsentry-email-2fa-result" class="smsentry-result" style="display:none"></p>
			<?php endif; ?>
		<?php endif; ?>

	<?php endif; ?>
</div>
