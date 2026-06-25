=== SMSentry ===
Contributors:      farhanalidev
Tags:              two-factor authentication, 2fa, sms, twilio, vonage
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Add SMS-based two-factor authentication to your WordPress login. Supports Twilio and Vonage with a swappable provider interface.

== Description ==

SMSentry adds a second layer of protection to your WordPress site by requiring users to verify their identity via a one-time SMS code after entering their password.

**Key features:**

* SMS-based OTP (one-time password) sent on every login
* Supports **Twilio** and **Vonage** — swap providers without changing your setup
* Clean provider abstraction — add new SMS providers via a PHP interface
* Per-user opt-in **and** admin-enforced 2FA by role (Administrator, Editor, etc.)
* Secure interim session between password check and OTP verification
* Bcrypt-hashed OTP storage — codes are never stored in plain text
* AES-256-CBC encryption for API credentials stored in the database
* Rate limiting and lockout after configurable failed attempts
* Resend code with 60-second cooldown timer
* Phone number verification during profile setup
* No third-party PHP SDKs required — uses WordPress HTTP API throughout
* Full i18n support with `.pot` file

**How it works:**

1. User enters username and password on the login form
2. If 2FA is active for that user, an SMS code is sent to their verified phone number
3. User enters the 6-digit code on the verification screen
4. Login completes — or the session is locked after too many incorrect attempts

**Supported SMS providers:**

| Provider | Notes |
|----------|-------|
| Twilio   | Recommended — most reliable global delivery |
| Vonage   | Good alternative with free trial credit |

== Installation ==

1. Upload the `smsentry` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. Go to **Settings → SMSentry** and enter your SMS provider credentials
4. Use the **Test & Validate** tab to confirm delivery works
5. Go to any user's **Profile** page and add a verified phone number to enable 2FA

== Frequently Asked Questions ==

= Which SMS provider should I use? =

Twilio is recommended for reliability and global coverage. Both providers offer free trial credit to get started.

= Can I force 2FA for administrators only? =

Yes. Go to **Settings → SMSentry → Security** and check **Administrator** under "Require 2FA for Roles". Users without a verified phone who fall under a required role will be prompted to set one up on their profile.

= What happens if the SMS fails to send? =

The login is blocked and an error message is shown. A `smsentry_otp_send_failed` action is fired with the user ID and WP_Error, allowing you to hook in custom logging or fallback behaviour.

= Can users disable 2FA themselves? =

By default yes. You can disable this under **Settings → SMSentry → Security → User Self-Management**.

= Is my Twilio / Vonage API key stored securely? =

Yes. API secrets are encrypted with AES-256-CBC before being stored in the database. The encryption key is derived from your WordPress `AUTH_KEY`.

= What is the OTP code expiry? =

Default is 5 minutes (300 seconds). This is configurable under **Settings → SMSentry → Security**.

== Screenshots ==

1. The SMS verification screen shown after a successful password entry.
2. The SMSentry settings page — Provider tab.
3. The SMSentry settings page — Security tab.
4. The 2FA section on a user's profile page.

== Changelog ==

= 1.0.0 =
* Initial release.
* Twilio and Vonage provider support.
* Per-user opt-in and admin role enforcement.
* OTP rate limiting and lockout.
* Phone number verification on profile setup.
* AES-256-CBC encryption for stored API secrets.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
