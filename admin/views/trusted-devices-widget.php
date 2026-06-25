<?php
/**
 * Trusted devices section — shared by the SMS and email 2FA states.
 * Included from user-profile-field.php.
 *
 * @var bool $can_edit
 * @var int  $trusted_device_count
 */
defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'smsentry_remember_device_enabled', true ) ) {
	return;
}
?>

<?php if ( $can_edit ) : ?>
	<hr style="margin: 20px 0;">
	<p><strong><?php esc_html_e( 'Trusted Devices', 'smsentry' ); ?></strong></p>
	<p class="description">
		<?php esc_html_e( 'Devices where you checked "Trust this device" skip the code for 30 days.', 'smsentry' ); ?>
	</p>
	<p>
		<?php
		printf(
			/* translators: number of trusted devices */
			esc_html__( '%d device(s) currently trusted.', 'smsentry' ),
			(int) $trusted_device_count
		);
		?>
	</p>
	<?php if ( $trusted_device_count > 0 ) : ?>
		<button type="button" id="smsentry-forget-devices" class="button button-secondary">
			<?php esc_html_e( 'Forget All Devices', 'smsentry' ); ?>
		</button>
		<p id="smsentry-forget-result" class="smsentry-result" style="display:none"></p>
	<?php endif; ?>
<?php endif; ?>
