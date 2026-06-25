<?php
/**
 * Backup codes section — shared by the SMS and email 2FA states.
 * Included from user-profile-field.php.
 *
 * @var bool $can_edit
 * @var int  $backup_codes_remaining
 */
defined( 'ABSPATH' ) || exit;
?>

<?php if ( $can_edit ) : ?>
	<hr style="margin: 20px 0;">
	<p><strong><?php esc_html_e( 'Backup Codes', 'smsentry' ); ?></strong></p>
	<p class="description">
		<?php esc_html_e( 'Use a backup code to log in if you lose access to your phone or email. Each code works once.', 'smsentry' ); ?>
	</p>
	<p>
		<?php
		printf(
			/* translators: number of unused backup codes */
			esc_html__( '%d unused backup code(s) remaining.', 'smsentry' ),
			(int) $backup_codes_remaining
		);
		?>
	</p>
	<button type="button" id="smsentry-generate-backup-codes" class="button button-secondary">
		<?php echo $backup_codes_remaining > 0
			? esc_html__( 'Regenerate Backup Codes', 'smsentry' )
			: esc_html__( 'Generate Backup Codes', 'smsentry' ); ?>
	</button>
	<div id="smsentry-backup-codes-display" style="display:none"></div>
<?php endif; ?>
