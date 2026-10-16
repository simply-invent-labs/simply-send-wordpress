=== Simply Send by Simply Invent Labs ===
Contributors: simplysend
Tags: email, transactional email, api, deliverability, simply send
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress email through the Simply Send transactional API — contact forms, notifications, and plugin mail from your verified domain.

== Description ==

This is the official WordPress integration for the Simply Send email service, a product of Simply Invent Labs LLC. It is designed for site owners who already use, or want to use, a Simply Send account for transactional email.

The plugin routes WordPress `wp_mail` messages to the Simply Send transactional HTTPS API using an Account ID and transactional API key. It does not configure a generic SMTP server or offer a choice of third-party mail providers.

Service-specific features include separate checks for Simply Send company-address, unsubscribe, and report-abuse template markers, encrypted storage of the API key, and recipient/attachment validation that matches the Simply Send public API. This connects WordPress password resets, site notifications, and contact forms that use `wp_mail` to the user's Simply Send account. Plugins that use their own email service need separate configuration.

Service publisher: [Simply Invent Labs LLC](https://simplyinventlabs.com/).

Simply Send replaces unreliable PHP `mail()` by routing `wp_mail` through the [Simply Send](https://www.simplysend.email) transactional HTTP API.

* Paste a transactional API key + Account ID
* Works even when hosts block SMTP ports
* Force a verified From address
* Adds missing Simply Send compliance placeholders without duplicating existing ones
* Send a test email from Settings → Simply Send

== External service ==

This plugin requires a Simply Send account, a transactional API key, and a verified transactional sending domain. Email usage is subject to your Simply Send plan; the plugin does not bill separately.

After you configure and enable the plugin, each intercepted wp_mail call sends the sender and recipient addresses and names, subject, HTML and plain-text message bodies, Reply-To, supported CC/BCC recipients, and any supported attachments to https://tapi.simplysend.email/send over HTTPS. Your API key and Account ID are sent as authentication headers. Sending a test email makes the same request. Activation alone does not contact the service. No separate telemetry is collected by this plugin.

Service: https://www.simplysend.email
Terms of Service: https://www.simplysend.email/terms
Privacy Policy: https://www.simplysend.email/privacy

== Delivery limits ==

* Use either To/CC recipients or BCC recipients, not both in one message.
* Attachments require exactly one To recipient, with no CC or BCC.
* At most 10 readable local file attachments are supported. Unsupported messages fail before an API request; recipients and attachments are never silently dropped.
* OpenSSL with AES-256-GCM is required to save an API key. Older stored keys must be re-entered after upgrading. Changing WordPress authentication salts also requires re-entering the key.
* The API endpoint is fixed; custom API URLs are not supported.

== Installation ==

1. After directory approval, install the plugin from Plugins → Add New. For review or manual installation, upload the official ZIP via Plugins → Add New → Upload Plugin.
2. Activate **Simply Send by Simply Invent Labs**.
3. In Simply Send, create a **transactional API key** and copy your **Account ID**.
4. Go to Settings → Simply Send in WordPress and paste the API key, Account ID, and From email.
5. Enable Simply Send and send a test email.

== Frequently Asked Questions ==

= Do I still need WP Mail SMTP? =

No. This plugin intercepts `wp_mail` and sends via the Simply Send API. Disable other SMTP/email plugins to avoid conflicts.

= Where do I pay for email volume? =

In your Simply Send account (workspace plan / usage). The WordPress plugin does not bill separately.

= Why was my email rejected? =

The From address must be on a verified transactional domain, and the API key must be a transactional key with a valid Account ID (`X-Id`).

= Can I use my SMTP username and password? =

No. This plugin uses the HTTP API and requires a transactional API key and Account ID. SMTP credentials are for a separate SMTP mailer integration.

= How are compliance placeholders handled? =

The plugin checks the HTML body for {{company_address_html}}, {{unsubscribe_email_html}}, and {{report_abuse_email_html}} separately and adds only the missing placeholders. Simply Send replaces the markers with your configured templates during sending. Configure any required compliance templates in your Simply Send account before sending.

== Support ==

For account setup, API credentials, or delivery questions, visit https://www.simplysend.email/contact. Include the error message and your WordPress and PHP versions. Do not share API keys or passwords in public support posts.

== Changelog ==

= 1.0.6 =
* Initial release.
