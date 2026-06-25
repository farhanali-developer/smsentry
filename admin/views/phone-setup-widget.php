<?php
/**
 * Reusable phone verification widget.
 * Included from user-profile-field.php — no standalone access.
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="smsentry-phone-setup">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="smsentry-new-phone"><?php esc_html_e( 'Phone Number', 'smsentry' ); ?></label>
			</th>
			<td>
				<div class="smsentry-phone-row">
					<div class="smsentry-phone-group">
						<?php SMSentry_Countries::render_picker( 'smsentry-new-phone-country', 'smsentry_phone_country' ); ?>
						<input type="tel"
						       id="smsentry-new-phone"
						       name="smsentry_pending_phone"
						       class="smsentry-phone-number"
						       placeholder="4155551234"
						       autocomplete="tel" />
					</div>
					<button type="button" id="smsentry-send-otp" class="button button-secondary smsentry-phone-action">
						<?php esc_html_e( 'Send Verification Code', 'smsentry' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'Select your country, then enter your number without the country code or leading zero — or paste a full number like +14155551234 and the country will be detected automatically.', 'smsentry' ); ?>
				</p>
				<p class="description">
					<strong><?php esc_html_e( 'Click "Send Verification Code" above to save this number — the "Update Profile" button at the bottom of the page will not save it.', 'smsentry' ); ?></strong>
				</p>
			</td>
		</tr>
	</table>

	<div id="smsentry-otp-row" style="display:none">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="smsentry-otp-input"><?php esc_html_e( 'Verification Code', 'smsentry' ); ?></label>
				</th>
				<td>
					<input type="text"
					       id="smsentry-otp-input"
					       class="small-text smsentry-otp-input"
					       inputmode="numeric"
					       maxlength="6"
					       placeholder="------"
					       autocomplete="one-time-code" />
					<button type="button" id="smsentry-verify-otp" class="button button-primary" style="margin-left:8px">
						<?php esc_html_e( 'Verify Code', 'smsentry' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Enter the 6-digit code sent to your phone. Valid for 5 minutes.', 'smsentry' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<p id="smsentry-setup-result" class="smsentry-result" style="display:none"></p>
</div>
