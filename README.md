# Simply Invent Labs — Simply Send

Official WordPress plugin for [Simply Send](https://www.simplysend.dev). Intercepts `wp_mail` and sends through the **transactional HTTP API**.

## Features

- `pre_wp_mail` interception → `POST https://tapi.simplysend.dev/send`
- Settings under **Settings → Simply Send**
- Authenticated API key encryption (OpenSSL AES-256-GCM + WordPress salts required)
- Force From address on a verified domain
- Adds each missing compliance placeholder without duplicating existing placeholders
- Test email from the admin UI

## Install (zip)

From this repository root:

```bash
mkdir -p dist/simply-invent-labs-simply-send/assets
cp simply-send.php uninstall.php readme.txt dist/simply-invent-labs-simply-send/
cp -R includes dist/simply-invent-labs-simply-send/
cp -R assets/. dist/simply-invent-labs-simply-send/assets/
cd dist && zip -r simply-invent-labs-simply-send-1.0.8.zip simply-invent-labs-simply-send
```

Upload `simply-invent-labs-simply-send-1.0.8.zip` in WordPress: **Plugins → Add New → Upload Plugin**.

## Configure

1. Create a **transactional API key** at https://app.simplysend.dev/api-keys
2. Copy your **Account ID** from https://app.simplysend.dev/account
3. In WordPress → **Settings → Simply Send**, paste API key, Account ID, and From email
4. Enable and send a test email

Billing for sends is on the Simply Send workspace plan — not in WordPress.

Docs: https://www.simplysend.dev/docs/guide/integrations/wordpress-plugin

## Requirements

- WordPress 5.8+ (uses `pre_wp_mail`)
- PHP 7.4+ with OpenSSL AES-256-GCM
- Simply Send account with a verified transactional domain

## Delivery limits and upgrades

The API endpoint is fixed to HTTPS for the selected workspace region (US, Australia, Canada, or Europe), and redirects are disabled. Changing regions requires entering the selected region’s API key and Account ID. Custom endpoints are no longer supported. Previously stored API keys must be re-entered after upgrading to authenticated encryption; changing WordPress authentication salts also requires re-entering the key. An enabled plugin with unavailable credentials fails rather than silently falling back to PHP mail.

Messages with attachments estimated at 1.5 MiB or larger use regional `POST /attachments`, checksum-verified signed HTTPS uploads, and `POST /send` with `attachmentSubmissionId`. Authorization and send share one idempotency key. Small attachments remain inline. The backend enforces approved workspace limits up to 40 MB of final MIME; local size estimates and PHP memory checks can reject earlier.

Use either To/CC or BCC, not both. Attachments require exactly one To recipient, no CC/BCC, and at most 10 readable local files. Unsupported combinations fail before the API request without dropping recipients or attachments. Failures from intercepted mail trigger `wp_mail_failed`.

## Development and contributing

Contributions are welcome. Please open a GitHub issue before starting a substantial feature so the scope and approach can be discussed. Small bug fixes and documentation improvements can go directly into a pull request.

### Repository layout

- `simply-send.php`: plugin metadata, constants, and startup hooks.
- `includes/class-simply-send-settings.php`: settings, supported regions, fixed API endpoints, and encrypted API-key storage.
- `includes/class-simply-send-mailer.php`: `wp_mail` handling, API requests, and attachment processing.
- `includes/class-simply-send-admin.php`: WordPress admin screens and actions.
- `assets/`: settings-page styles, JavaScript, and plugin images.
- `tests/`: PHP regression checks that stub WordPress functions and mock HTTP requests.
- `readme.txt`: WordPress.org listing content, setup instructions, and external-service disclosures.

### Make a change

1. Clone this repository and create a focused branch for your change:

   ```bash
   git clone https://github.com/simply-invent-labs/simply-send-wordpress.git
   cd simply-send-wordpress
   git switch -c your-change-name
   ```

2. Make the smallest focused change that solves the issue. Keep user-facing strings translation-ready and follow the formatting of the surrounding PHP, JavaScript, and CSS.
3. Run the relevant local regression checks below. They use mocked WordPress and HTTP behavior; they do not send email.
4. Open a pull request describing the problem, your change, and the checks you ran. Include screenshots for visible admin-page changes.

### Security and customer data

- Never commit API keys, passwords, WordPress salts, customer information, email bodies, or real signed attachment-upload URLs. Use fake values in examples and tests.
- Keep regional API destinations restricted to the allowlist in the settings class. Preserve HTTPS certificate verification and disabled redirects for authenticated API and signed-upload requests.
- If a change adds a new external service or sends new categories of data, update the external-services disclosure in `readme.txt` as part of the same change.
- Report suspected security vulnerabilities privately through [Simply Send Support](https://www.simplysend.dev/support), not in a public issue.

## Local regression checks

These tests stub WordPress functions and HTTP requests; they do not send email:

```bash
php tests/regression.php
php tests/attachments.php
php -d disable_functions=openssl_encrypt,openssl_decrypt,openssl_get_cipher_methods tests/regression.php --no-openssl
```

See `readme.txt` for the external-service data disclosure and service policy links.
