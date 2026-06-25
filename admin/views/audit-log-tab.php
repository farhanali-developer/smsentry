<?php
/**
 * Audit Log tab content — included from settings-page.php.
 *
 * @var array $audit_log Prepared by SMSentry_Admin::prepare_audit_log_data():
 *                        entries, total, per_page, paged, log_user, log_event, event_labels.
 */
defined( 'ABSPATH' ) || exit;

$base_url    = add_query_arg( array( 'page' => 'smsentry', 'tab' => 'audit_log' ), admin_url( 'admin.php' ) );
$total_pages = (int) ceil( $audit_log['total'] / $audit_log['per_page'] );
?>

<h2><?php esc_html_e( 'Audit Log', 'smsentry' ); ?></h2>
<p class="description">
	<?php
	printf(
		/* translators: number of days */
		esc_html__( 'Logins, failed attempts, lockouts, and 2FA changes. Entries older than %d days are pruned automatically.', 'smsentry' ),
		(int) apply_filters( 'smsentry_audit_log_retention_days', 90 )
	);
	?>
</p>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="smsentry-audit-filters">
	<input type="hidden" name="page" value="smsentry" />
	<input type="hidden" name="tab" value="audit_log" />
	<div class="smsentry-audit-filters-inner">
	<input type="text" name="log_user" placeholder="<?php esc_attr_e( 'Filter by user (login, email, or ID)', 'smsentry' ); ?>"
	       value="<?php echo esc_attr( $audit_log['log_user'] ); ?>" />

	<select name="log_event">
		<option value=""><?php esc_html_e( 'All events', 'smsentry' ); ?></option>
		<?php foreach ( $audit_log['event_labels'] as $event_key => $event_label ) : ?>
			<option value="<?php echo esc_attr( $event_key ); ?>" <?php selected( $audit_log['log_event'], $event_key ); ?>>
				<?php echo esc_html( $event_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'smsentry' ); ?></button>
	<?php if ( $audit_log['log_user'] || $audit_log['log_event'] ) : ?>
		<a href="<?php echo esc_url( $base_url ); ?>" class="button-link"><?php esc_html_e( 'Clear filters', 'smsentry' ); ?></a>
	<?php endif; ?>
	</div>
</form>

<?php if ( empty( $audit_log['entries'] ) ) : ?>

	<p class="smsentry-audit-empty"><?php esc_html_e( 'No matching log entries.', 'smsentry' ); ?></p>

<?php else : ?>

	<table class="wp-list-table widefat fixed striped smsentry-audit-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date/Time', 'smsentry' ); ?></th>
				<th><?php esc_html_e( 'User', 'smsentry' ); ?></th>
				<th><?php esc_html_e( 'Event', 'smsentry' ); ?></th>
				<th><?php esc_html_e( 'Details', 'smsentry' ); ?></th>
				<th><?php esc_html_e( 'IP Address', 'smsentry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $audit_log['entries'] as $entry ) : ?>
				<?php
				$entry_user = get_userdata( (int) $entry['user_id'] );
				$user_label = $entry_user ? $entry_user->user_login : sprintf(
					/* translators: user ID */
					__( 'User #%d (deleted)', 'smsentry' ),
					(int) $entry['user_id']
				);
				$event_label = $audit_log['event_labels'][ $entry['event_type'] ] ?? $entry['event_type'];
				?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $entry['created_at'], 'Y-m-d H:i:s' ) ); ?></td>
					<td><?php echo esc_html( $user_label ); ?></td>
					<td><?php echo esc_html( $event_label ); ?></td>
					<td><?php echo esc_html( $entry['details'] ); ?></td>
					<td><?php echo esc_html( $entry['ip_address'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns pre-escaped markup.
					'base'      => add_query_arg( 'paged', '%#%', $base_url ) . ( $audit_log['log_user'] ? '&log_user=' . rawurlencode( $audit_log['log_user'] ) : '' ) . ( $audit_log['log_event'] ? '&log_event=' . rawurlencode( $audit_log['log_event'] ) : '' ),
					'format'    => '',
					'current'   => $audit_log['paged'],
					'total'     => $total_pages,
					'prev_text' => __( '&laquo; Previous', 'smsentry' ),
					'next_text' => __( 'Next &raquo;', 'smsentry' ),
				) );
				?>
			</div>
		</div>
	<?php endif; ?>

<?php endif; ?>
