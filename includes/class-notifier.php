<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sends a security alert email to the account owner when their 2FA
 * configuration changes — phone verified, 2FA disabled, backup codes
 * regenerated, etc. Closes the gap where an attacker who already has a
 * password could quietly turn off 2FA without the real user noticing.
 */
class SMSentry_Notifier {

	/**
	 * Event types worth emailing the user about. Routine login activity
	 * (success/failure/backup code use) is intentionally excluded to avoid
	 * notification fatigue — only security-posture changes are emailed.
	 */
	private const NOTIFIABLE_EVENTS = array(
		'phone_verified',
		'2fa_enabled',
		'2fa_disabled',
		'email_2fa_enabled',
		'email_2fa_disabled',
		'backup_codes_generated',
		'lockout',
	);

	public function register(): void {
		add_action( 'smsentry_audit_logged', array( $this, 'maybe_notify' ), 10, 3 );
	}

	public function maybe_notify( int $user_id, string $event_type, string $details ): void {
		if ( ! in_array( $event_type, self::NOTIFIABLE_EVENTS, true ) ) {
			return;
		}

		if ( ! (bool) get_option( 'smsentry_security_emails_enabled', true ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$site = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: site name */
			__( '[%s] Security alert: your two-factor authentication settings changed', 'smsentry' ),
			$site
		);

		$message = sprintf(
			/* translators: 1: event description, 2: site name, 3: date/time */
			__( "%2\$s security notice:\n\n%1\$s\n\nWhen: %3\$s\n\nIf this wasn't you, secure your account immediately — change your password and review your two-factor settings.", 'smsentry' ),
			$this->describe_event( $event_type, $details ),
			$site,
			current_time( 'mysql' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	private function describe_event( string $event_type, string $details ): string {
		$descriptions = array(
			'phone_verified'         => __( 'A phone number was verified for SMS two-factor authentication.', 'smsentry' ),
			'2fa_enabled'            => __( 'Two-factor authentication was enabled on your account.', 'smsentry' ),
			'2fa_disabled'           => __( 'Two-factor authentication was disabled or removed from your account.', 'smsentry' ),
			'email_2fa_enabled'      => __( 'Email-based two-factor authentication was enabled.', 'smsentry' ),
			'email_2fa_disabled'     => __( 'Email-based two-factor authentication was disabled.', 'smsentry' ),
			'backup_codes_generated' => __( 'Backup codes were (re)generated. Any previous codes no longer work.', 'smsentry' ),
			'lockout'                => __( 'Your account was temporarily locked after several incorrect login codes.', 'smsentry' ),
		);

		$text = $descriptions[ $event_type ] ?? $event_type;

		if ( '' !== $details ) {
			$text .= ' (' . $details . ')';
		}

		return $text;
	}
}
