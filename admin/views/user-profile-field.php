<?php
/**
 * Variables available from SMSentry_User_Profile::render_2fa_section():
 *
 * @var WP_User $user
 * @var string  $phone
 * @var bool    $phone_verified
 * @var bool    $enabled
 * @var bool    $user_can_disable
 * @var bool    $is_required
 * @var bool    $can_edit
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="smsentry-profile-section" id="smsentry-profile-2fa">
	<h2><?php esc_html_e( 'Two-Factor Authentication (SMS)', 'smsentry' ); ?></h2>

	<?php if ( $is_required ) : ?>
		<div class="smsentry-notice smsentry-notice-warning">
			<p>
				<strong><?php esc_html_e( 'Required by your role:', 'smsentry' ); ?></strong>
				<?php esc_html_e( 'Two-factor authentication is mandatory for your account. Verify a phone number below to enable it.', 'smsentry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $phone_verified ) : ?>

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

		<?php if ( $can_edit ) : ?>
			<hr style="margin: 20px 0;">
			<p><strong><?php esc_html_e( 'Change Phone Number', 'smsentry' ); ?></strong></p>
			<?php require __DIR__ . '/phone-setup-widget.php'; ?>
		<?php endif; ?>

	<?php else : ?>

		<p><?php esc_html_e( 'Add SMS two-factor authentication to protect your account. Enter your phone number below to get started.', 'smsentry' ); ?></p>
		<?php if ( $can_edit ) : ?>
			<?php require __DIR__ . '/phone-setup-widget.php'; ?>
		<?php endif; ?>

	<?php endif; ?>
</div>
