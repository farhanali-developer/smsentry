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
- One-time backup codes for account recovery if a phone is lost
- Emergency break-glass bypass (`wp-config.php` constant + WP-CLI) if SMS delivery fails site-wide
- Email OTP fallback for users without a verified phone (voluntary opt-in or admin-enforced by role)
- Audit log of logins, failed attempts, lockouts, and 2FA changes, visible to admins
- 2FA status column and a per-user "Enforce 2FA" bulk action on the Users list
- Security alert emails when 2FA/phone settings change, so a user notices if an attacker disables their 2FA
- Per-IP rate limiting, in addition to per-user, to catch credential-stuffing across many accounts
- "Remember this device" for 30 days, with a profile-page "Forget All Devices" control
- First-run setup checklist and a 2FA adoption widget on the settings page
- Paste a full international number (e.g. `+442071234567`) into any phone field and the country is auto-detected
- Zero third-party PHP SDKs — just `wp_remote_post()` and `wp_mail()`

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

Instead of an SMS code, a user can also enter one of their **backup codes** generated on the profile page — useful if their phone is lost or out of signal.

## Backup codes

Once a phone is verified, users can generate 10 one-time backup codes from their profile page (**Profile → Backup Codes**). Each code:

- Is shown in plain text exactly once, immediately after generation — only a bcrypt hash is stored afterward
- Can be used exactly once, as a drop-in replacement for the SMS code on the login screen ("Use a backup code instead")
- Is invalidated (along with the rest of the set) if the user regenerates the list

## Emergency access (break-glass)

Two recovery mechanisms exist for when SMS delivery is broken or a user is otherwise locked out:

**Site-wide bypass** — add to `wp-config.php`:

```php
define( 'SMSENTRY_DISABLE_2FA', true );
```

This disables all 2FA enforcement immediately, without touching the database. Useful when the SMS provider account is suspended/out of credit and admins can't log in at all.

**Per-user reset via WP-CLI**:

```bash
wp smsentry list              # show every user with 2FA configured
wp smsentry reset <user>      # clear 2FA for one user (ID, login, or email)
wp smsentry reset --all       # clear 2FA for every user on the site
```

## Email OTP fallback

For users without a verified phone number, SMSentry can deliver the OTP by email instead, via `wp_mail()`:

- **Voluntary**: from the profile page, a user without a phone can click "Use Email Instead" to enable email-based codes immediately (no verification needed — it's the account's existing, already-trusted email).
- **Admin-enforced**: if a role is marked "Require 2FA" but a user under that role hasn't verified a phone yet, they're automatically challenged via email instead of being skipped entirely.
- Verifying a phone number later automatically switches the account back to SMS.
- Controlled site-wide via **SMSentry → Security → Email Fallback** (on by default).

## Audit log

A dedicated `wp_smsentry_audit_log` table records logins, failed code attempts, lockouts, phone/email 2FA changes, and backup code usage, with timestamp, user, event type, free-text details, and IP address. View and filter it under **SMSentry → Audit Log**. Entries older than 90 days are pruned automatically via WP-Cron (configurable with the `smsentry_audit_log_retention_days` filter).

## Per-user enforcement (Users list)

The Users list table shows a **2FA** column (method + an "Enforced" badge) for every user. Select one or more users and use the bulk actions dropdown:

- **Enforce 2FA** — requires 2FA for these specific users on their next login, regardless of role
- **Remove 2FA enforcement** — undoes it

This complements role-based enforcement (Security tab) for one-off cases — e.g. a single contractor account that needs 2FA without making it mandatory for their entire role.

## Per-IP rate limiting

In addition to the per-user lockout, failed code attempts are also tracked per IP address. The IP lockout threshold is `max_attempts × 4` (using whatever you've set on the Security tab), high enough to tolerate several genuine users behind shared NAT/Wi-Fi, but low enough to stop an attacker spraying codes across many different accounts from a single IP — something per-user limiting alone can't catch.

## Security alert emails

When a user's phone is verified, 2FA is enabled/disabled, email 2FA is toggled, backup codes are regenerated, or the account is locked out, SMSentry emails the account owner. This closes the gap where an attacker who already has a password could quietly turn off 2FA or swap the phone number without the real user finding out. Toggle this under **SMSentry → Security → Security Alert Emails** (on by default).

## Remember this device

After a successful code entry, a user can check "Trust this device for 30 days" to skip the code on that browser next time. A random token is stored in a cookie; only its SHA-256 hash lives in user meta, so the cookie itself is the only proof of trust. Users can revoke all trusted devices from their profile page ("Forget All Devices"), and admins can disable the feature site-wide under **SMSentry → Security → Remember Device**.

## Setup checklist & adoption widget

A dismissible checklist appears above the settings tabs until you've added provider credentials and sent a successful test message. The Security tab also shows a live breakdown of 2FA adoption — how many users have it active (by method), and how many users under a required role still haven't set it up.

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
│   ├── class-cli-command.php             # WP-CLI: list / reset (emergency recovery)
│   ├── class-audit-log.php               # Audit log table, queries, pruning
│   ├── class-stats.php                   # 2FA adoption summary (cached 5 min)
│   ├── class-notifier.php                # Security alert emails on audit events
│   ├── class-device-trust.php            # "Remember this device" cookie/meta logic
│   └── providers/
│       ├── interface-sms-provider.php
│       ├── class-twilio-provider.php
│       └── class-vonage-provider.php
├── admin/
│   ├── class-admin.php                   # Settings page, test SMS, validate
│   ├── class-user-profile.php            # Profile 2FA section + AJAX
│   ├── class-users-list.php              # 2FA column + bulk enforce/unenforce
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

- OTPs and backup codes are hashed with `password_hash()` before being stored — never logged or stored in plain text
- API credentials (Twilio Auth Token, Vonage API Secret) are encrypted with AES-256-CBC using a key derived from the site's `AUTH_KEY`
- OTP entry is rate-limited per user **and** per IP; a configurable number of failed attempts locks out either
- All AJAX endpoints are nonce-protected and capability-checked
- Audit log entries are never deletable via the WP personal-data eraser, so a malicious actor can't use a data-erasure request to wipe evidence of an attack
- Trusted-device cookies store a random token client-side; only its SHA-256 hash is kept server-side, so nothing useful is exposed if the database leaks
- 2FA configuration changes trigger an email to the account owner, so a password-only compromise can't silently disable 2FA unnoticed

## License

GPL-2.0-or-later. See [readme.txt](readme.txt) for the full WordPress.org plugin description.
