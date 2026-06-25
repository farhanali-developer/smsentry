<?php
defined( 'ABSPATH' ) || exit;

interface SMSentry_SMS_Provider {

	/**
	 * Send an SMS message.
	 *
	 * @param string $to      Recipient phone number in E.164 format (e.g. +14155551234).
	 * @param string $message Message body.
	 * @return true|WP_Error  True on success, WP_Error on failure.
	 */
	public function send( string $to, string $message ): true|WP_Error;

	/**
	 * Validate the stored credentials against the provider's API.
	 *
	 * @return true|WP_Error
	 */
	public function validate_credentials(): true|WP_Error;

	/** Machine-readable identifier, e.g. "twilio". */
	public function get_name(): string;

	/** Human-readable label shown in the admin UI. */
	public function get_label(): string;
}
