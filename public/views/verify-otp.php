<?php
/**
 * OTP verification page — rendered inside WordPress's login_header/login_footer wrapper.
 *
 * Variables injected by SMSentry_Login_Handler::render_verify_page():
 *
 * @var string|null $error            Error message to display, or null.
 * @var string      $masked_phone     Masked phone number e.g. ***1234.
 * @var string      $masked_email     Masked email e.g. jo***@example.com.
 * @var bool        $can_resend       Whether the resend cooldown has expired.
 * @var int         $resend_remaining Seconds until resend is allowed.
 * @var string      $redirect_to      URL to redirect after successful login.
 * @var string      $resend_nonce     Nonce for the resend AJAX call.
 * @var string      $ajax_url         admin-ajax.php URL.
 * @var bool        $has_backup_codes Whether this user has any unused backup codes.
 * @var bool        $can_remember_device Whether the remember-device option is enabled site-wide.
 */
defined( 'ABSPATH' ) || exit;
?>

<div id="login">

	<?php if ( ! empty( $error ) ) : ?>
		<div id="login_error" class="smsentry-login-error">
			<?php echo wp_kses_post( $error ); ?>
		</div>
	<?php endif; ?>

	<h1 class="smsentry-verify-title">
		<?php esc_html_e( 'Verify your identity', 'smsentry' ); ?>
	</h1>

	<p class="smsentry-verify-hint">
		<?php if ( ! empty( $masked_email ) ) : ?>
			<?php
			printf(
				/* translators: masked email address */
				esc_html__( 'A 6-digit code was sent to %s. Enter it below to complete sign-in.', 'smsentry' ),
				'<strong>' . esc_html( $masked_email ) . '</strong>'
			);
			?>
		<?php elseif ( ! empty( $masked_phone ) ) : ?>
			<?php
			printf(
				/* translators: masked phone number */
				esc_html__( 'A 6-digit code was sent to %s. Enter it below to complete sign-in.', 'smsentry' ),
				'<strong>' . esc_html( $masked_phone ) . '</strong>'
			);
			?>
		<?php else : ?>
			<?php esc_html_e( 'Enter the 6-digit code that was sent to you.', 'smsentry' ); ?>
		<?php endif; ?>
	</p>

	<form name="smsentry_verify" id="smsentry-verify-form"
	      action="<?php echo esc_url( add_query_arg( 'action', 'smsentry_verify', wp_login_url() ) ); ?>"
	      method="post">

		<?php wp_nonce_field( 'smsentry_verify_otp', 'smsentry_nonce' ); ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
		<input type="hidden" name="smsentry_mode" id="smsentry_mode" value="otp" />

		<p>
			<label for="smsentry_otp" id="smsentry_otp_label">
				<?php esc_html_e( 'Verification Code', 'smsentry' ); ?>
			</label>
			<input type="text"
			       name="smsentry_otp"
			       id="smsentry_otp"
			       class="input smsentry-otp-input"
			       inputmode="numeric"
			       maxlength="6"
			       placeholder="------"
			       autocomplete="one-time-code"
			       autofocus
			       required />
		</p>

		<p class="submit">
			<input type="submit"
			       name="wp-submit"
			       id="wp-submit"
			       class="button button-primary button-large"
			       value="<?php esc_attr_e( 'Verify', 'smsentry' ); ?>" />
		</p>

		<p class="smsentry-remember">
			<label>
				<input type="checkbox" name="rememberme" id="rememberme" value="forever" />
				<?php esc_html_e( 'Remember me', 'smsentry' ); ?>
			</label>
		</p>

		<?php if ( $can_remember_device ) : ?>
			<p class="smsentry-remember">
				<label>
					<input type="checkbox" name="smsentry_remember_device" id="smsentry_remember_device" value="1" />
					<?php esc_html_e( 'Trust this device for 30 days (skip the code next time)', 'smsentry' ); ?>
				</label>
			</p>
		<?php endif; ?>

	</form>

	<?php if ( $has_backup_codes ) : ?>
		<p class="smsentry-backup-toggle-row">
			<button type="button" id="smsentry-use-backup-toggle" class="button-link">
				<?php esc_html_e( 'Use a backup code instead', 'smsentry' ); ?>
			</button>
		</p>
	<?php endif; ?>

	<p class="smsentry-resend-row">
		<?php if ( $can_resend ) : ?>
			<button type="button"
			        id="smsentry-resend-btn"
			        class="button-link"
			        data-nonce="<?php echo esc_attr( $resend_nonce ); ?>"
			        data-ajax="<?php echo esc_url( $ajax_url ); ?>">
				<?php esc_html_e( 'Resend code', 'smsentry' ); ?>
			</button>
		<?php else : ?>
			<span id="smsentry-resend-timer"
			      data-remaining="<?php echo esc_attr( $resend_remaining ); ?>">
				<?php
				printf(
					/* translators: countdown seconds */
					esc_html__( 'Resend available in %s seconds', 'smsentry' ),
					'<span id="smsentry-countdown">' . esc_html( (string) $resend_remaining ) . '</span>'
				);
				?>
			</span>
			<button type="button"
			        id="smsentry-resend-btn"
			        class="button-link"
			        style="display:none"
			        data-nonce="<?php echo esc_attr( $resend_nonce ); ?>"
			        data-ajax="<?php echo esc_url( $ajax_url ); ?>">
				<?php esc_html_e( 'Resend code', 'smsentry' ); ?>
			</button>
		<?php endif; ?>
	</p>

	<p id="smsentry-resend-result" class="smsentry-result" style="display:none"></p>

	<p class="smsentry-back-link">
		<a href="<?php echo esc_url( wp_login_url() ); ?>">&larr;
			<?php esc_html_e( 'Back to login', 'smsentry' ); ?>
		</a>
	</p>

</div>
