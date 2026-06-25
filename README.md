# SMSentry

Two-factor authentication for WordPress via SMS. Supports **Twilio** and **Vonage** through a swappable provider interface — no bundled SDKs, built entirely on the WordPress HTTP API.

## Features

- SMS one-time password (OTP) on login, sent via Twilio or Vonage
- Pluggable provider interface — add a new SMS provider by implementing one PHP interface
- Per-user opt-in **and** admin-enforced 2FA by role (Administrator, Editor, etc.)
- Secure interim session between password verification and OTP verification
- Bcrypt-hashed OTP storage — codes are never stored in plain text
- AES-256-CBC encrypted API credentials at rest
- Rate limiting with configurable lockout after repeated failed attempts
- Resend-code cooldown timer
- Phone number verification flow on profile setup
- Custom country picker (flag + dial code) for phone number fields
- Zero third-party PHP SDKs — just `wp_remote_post()`

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A Twilio or Vonage account (both offer free trial credit)

## Installation

1. Clone or download this repository into `wp-content/plugins/smsentry`
2. Activate **SMSentry** from the WordPress Plugins screen
3. Go to **SMSentry → SMS Provider** and enter your Twilio or Vonage credentials
4. Use the **Test & Validate** tab to confirm delivery works end-to-end
5. Visit any user's **Profile** page and verify a phone number to enable 2FA for that account

## How it works

1. User submits username + password on the normal WordPress login form
2. WordPress authenticates the credentials as usual
3. If the user has 2FA enabled (or their role requires it), SMSentry intercepts the `authenticate` filter, generates a 6-digit OTP, and sends it via SMS
4. The user is redirected to a verification screen (still inside `wp-login.php`)
5. On a correct code, login completes; incorrect codes are rate-limited and the session locks out after a configurable number of attempts

## Architecture

```
smsentry/
├── smsentry.php                          # Bootstrap
├── uninstall.php                         # Cleanup on uninstall
├── includes/
│   ├── class-plugin.php                  # Singleton — wires everything together
│   ├── class-authenticator.php           # OTP generate / hash / verify
│   ├── class-session.php                 # Interim cookie session
│   ├── class-rate-limiter.php            # Attempt tracking + lockout
│   ├── class-crypto.php                  # AES-256-CBC for stored secrets
│   ├── class-countries.php               # Country/dial-code dataset + picker UI
│   └── providers/
│       ├── interface-sms-provider.php
│       ├── class-twilio-provider.php
│       └── class-vonage-provider.php
├── admin/
│   ├── class-admin.php                   # Settings page, test SMS, validate
│   ├── class-user-profile.php            # Profile 2FA section + AJAX
│   └── views/
├── public/
│   ├── class-login-handler.php           # authenticate / wp_login_failed hooks
│   └── views/
└── assets/
    ├── css/smsentry.css
    └── js/smsentry.js
```

## Adding a new SMS provider

Implement `SMSentry_SMS_Provider`:

```php
interface SMSentry_SMS_Provider {
    public function send( string $to, string $message ): true|WP_Error;
    public function validate_credentials(): true|WP_Error;
    public function get_name(): string;
    public function get_label(): string;
}
```

Then wire it up in `SMSentry_Plugin::resolve_provider()`.

## Security notes

- OTPs are hashed with `password_hash()` before being stored in a transient — never logged or stored in plain text
- API credentials (Twilio Auth Token, Vonage API Secret) are encrypted with AES-256-CBC using a key derived from the site's `AUTH_KEY`
- OTP entry is rate-limited; a configurable number of failed attempts locks the session for a cooldown period
- All AJAX endpoints are nonce-protected and capability-checked

## License

GPL-2.0-or-later. See [readme.txt](readme.txt) for the full WordPress.org plugin description.
