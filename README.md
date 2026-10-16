# Simply Send by Simply Invent Labs

Official WordPress plugin for [Simply Send](https://www.simplysend.email). Intercepts `wp_mail` and sends through the **transactional HTTP API**.

## Features

- `pre_wp_mail` interception → `POST https://tapi.simplysend.email/send`
- Settings under **Settings → Simply Send**
- Authenticated API key encryption (OpenSSL AES-256-GCM + WordPress salts required)
- Force From address on a verified domain
- Adds each missing compliance placeholder without duplicating existing placeholders
- Test email from the admin UI

## Install (zip)

From this repository root:

```bash
mkdir -p dist/simply-send/assets
cp simply-send.php uninstall.php readme.txt dist/simply-send/
cp -R includes dist/simply-send/
cp -R assets/. dist/simply-send/assets/
cd dist && zip -r simply-send-1.0.6.zip simply-send
```

Upload `simply-send-1.0.6.zip` in WordPress: **Plugins → Add New → Upload Plugin**.

## Configure

1. Create a **transactional API key** at https://app.simplysend.email/api-keys
2. Copy your **Account ID** from https://app.simplysend.email/account
3. In WordPress → **Settings → Simply Send**, paste API key, Account ID, and From email
4. Enable and send a test email

Billing for sends is on the Simply Send workspace plan — not in WordPress.

Docs: https://www.simplysend.email/docs/guide/integrations/wordpress-plugin

## Requirements

- WordPress 5.8+ (uses `pre_wp_mail`)
- PHP 7.4+ with OpenSSL AES-256-GCM
- Simply Send account with a verified transactional domain

## Delivery limits and upgrades

The API endpoint is fixed to HTTPS and redirects are disabled. Custom endpoints are no longer supported. Previously stored API keys must be re-entered after upgrading to authenticated encryption; changing WordPress authentication salts also requires re-entering the key. An enabled plugin with unavailable credentials fails rather than silently falling back to PHP mail.

Use either To/CC or BCC, not both. Attachments require exactly one To recipient, no CC/BCC, and at most 10 readable local files. Unsupported combinations fail before the API request without dropping recipients or attachments. Failures from intercepted mail trigger `wp_mail_failed`.

## Local regression checks

These tests stub WordPress functions and HTTP requests; they do not send email:

```bash
php tests/regression.php
php -d disable_functions=openssl_encrypt,openssl_decrypt,openssl_get_cipher_methods tests/regression.php --no-openssl
```

See `readme.txt` for the external-service data disclosure and service policy links.
