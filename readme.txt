=== Simply Invent Labs — Simply Send ===
Contributors: simplysend
Tags: smtp, api, transactional email, simplysend, simply send
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress site email through Simply Send’s transactional API. Connect your site to a verified sender and deliver mail from one selected region.

== Description ==

[Simply Send](https://www.simplysend.dev) is an email delivery platform from [Simply Invent Labs](https://simplyinventlabs.com/). This official WordPress plugin connects your site to the Simply Send transactional API, so messages sent through WordPress `wp_mail` can use your Simply Send account for delivery.

Use it for WordPress notifications, password resets, contact forms, and messages from other plugins that rely on `wp_mail`. Plugins that use their own email provider or bypass `wp_mail` need to be configured separately.

**Set up the Simply Send WordPress plugin:** Follow the [plugin setup guide](https://www.simplysend.dev/docs/guide/integrations/wordpress-plugin) for detailed instructions on connecting your site to Simply Send.

**Looking for SMTP?** This plugin sends through the API and does not configure SMTP. To connect a compatible SMTP plugin, follow Simply Send’s [WordPress SMTP setup guide](https://www.simplysend.dev/docs/guide/integrations/wordpress).

=== Key features ===

* Send WordPress site email through the Simply Send transactional API over HTTPS.
* Choose the region where your Simply Send workspace is located:
  * 🇺🇸 United States (East)
  * 🇦🇺 Australia (Sydney)
  * 🇨🇦 Canada (Central)
  * 🇪🇺 Europe (Frankfurt)
  * Don’t see your region? [Contact us](https://www.simplysend.dev/support) and let us know which region you need.

* Connect with a transactional API key and Account ID.
* Choose a From email address on a verified transactional sending domain and an optional sender name. Optionally use this sender for all site email.
* Send a test email from the plugin settings.
* Send messages with attachments, subject to your account’s email size limit and approval.
* Store the API key encrypted in WordPress options. The plugin does not collect separate telemetry.

One plugin installation connects to one Simply Send region at a time. Select the region that contains your workspace and matches your data residency requirements. API credentials, sender-domain verification, templates, subscriptions, and account limits are region-specific. If you need another region, [contact Simply Send](https://www.simplysend.dev/support).

== Plans and pricing ==

Simply Send offers these plans:

* **Free:** Up to 1,000 emails per month.
* **Paid:** Plans start at US$5 per month.
* **Enterprise:** Custom plans for organizational needs.

The plugin is free to install; email usage and account limits are determined by your Simply Send plan and selected region. Availability and eligibility can vary by plan and region.

* [Compare plans and pricing](https://www.simplysend.dev/pricing)
* [Estimate costs with the pricing calculator](https://www.simplysend.dev/pricing/calculator)

== Installation ==

**Connect your site to Simply Send**

Choose your workspace region, add your API credentials and verified sender, then complete setup and send a test email.

1. Install and activate **Simply Invent Labs — Simply Send** from Plugins → Add New.
2. In WordPress, open Settings → Simply Send and select the region where your Simply Send workspace is set up.
3. Use the dashboard links in the plugin to create a transactional API key and find your Account ID. Use credentials from the same region you selected.
4. Enter your API key, Account ID, and a From email address verified for transactional sending in that region. The From name is optional.
5. Enable sending, complete setup, and send a test email to an inbox you can check.

For screenshots and step-by-step instructions, visit the [Simply Send WordPress plugin setup guide](https://www.simplysend.dev/docs/guide/integrations/wordpress-plugin).

== Requirements and account limits ==

* WordPress 5.8 or later and PHP 7.4 or later.
* A Simply Send account and a transactional API key and Account ID from the selected region.
* A verified transactional sending domain and sender address in that region.
* Outbound HTTPS access from your WordPress host to the selected region’s API.
* OpenSSL with AES-256-GCM support to store the API key securely.

The default Simply Send limit is 2 MB for the complete email, including message content and attachments. If your emails need to exceed that limit, request approval for a higher limit in the Simply Send User Portal under **Accounts → select your account → Limits**. Your account’s Limits page also shows other sending limits. PHP memory and execution limits may impose a lower practical limit.

Email usage is billed under your Simply Send account and subscription; the WordPress plugin itself does not provide a subscription. Subscribe in the region where your workspace is located, especially if you have data residency requirements. Switching the plugin’s region changes the API endpoint and dashboard links; enter that region’s credentials and sender details before saving the new setup.

== Frequently Asked Questions ==

= What does the plugin do? =

When enabled, the plugin routes WordPress `wp_mail` messages through the Simply Send transactional API. This can cover site notifications, password resets, and messages from plugins that use WordPress mail. It does not take over delivery for plugins that use a separate provider or send outside `wp_mail`.

= Which regions are supported? =

🇺🇸 United States (East), 🇦🇺 Australia (Sydney), 🇨🇦 Canada (Central), and 🇪🇺 Europe (Frankfurt). One installation connects to one region at a time. Choose the region where your Simply Send workspace and subscription are located, and where your data should be processed. If you need another region, please [contact support](https://www.simplysend.dev/support).

= Can I use SMTP credentials with this plugin? =

No. This plugin uses the transactional HTTP API and requires an API key and Account ID. If you prefer SMTP, use a compatible WordPress SMTP plugin such as WP Mail SMTP or FluentSMTP with Simply Send SMTP credentials. Follow the [WordPress SMTP setup guide](https://www.simplysend.dev/docs/guide/integrations/wordpress).

= What is the default email size limit? =

The default Simply Send limit is 2 MB for the complete email, including message content and attachments. To request approval for a higher limit or review other sending limits, open the Simply Send User Portal and go to Accounts → select your account → Limits.

= Where do I manage subscriptions and usage? =

Subscriptions, billing, and email usage are managed in your Simply Send account, not through WordPress.org. Subscribe to the region where your workspace is located.

= Why was an email rejected? =

Check that the selected region matches your workspace and that the API key, Account ID, verified From address, compliance templates, and account limits are correct for that region. See the [setup guide](https://www.simplysend.dev/docs/guide/integrations/wordpress-plugin) for troubleshooting.

= How are compliance placeholders handled? =

The plugin checks the HTML body for the Simply Send company address, unsubscribe, and report-abuse template markers, and adds any missing markers. Simply Send replaces these markers using templates configured for your account. Configure any required templates before sending.

== Support ==

Choose the support option that best fits your question:

* [Simply Send Support](https://www.simplysend.dev/support): Setup guides, FAQs, account questions, and direct support.
* [WordPress.org plugin support forum](https://wordpress.org/support/plugin/simply-invent-labs-simply-send/): Questions about installing, configuring, or using the plugin.

When requesting help, include the error message and your WordPress and PHP versions. Never post API keys, passwords, or message contents publicly.

== External services ==

This plugin connects to Simply Send, a hosted email delivery service operated by Simply Invent Labs LLC. You need a Simply Send account, a transactional API key, and a verified transactional sending domain. Email usage is subject to your Simply Send subscription and account limits; the plugin does not bill separately.

After you configure and enable delivery, each intercepted `wp_mail` request sends the sender and recipient addresses and names, subject, HTML and plain-text message bodies, Reply-To, supported CC/BCC recipients, and supported attachments over HTTPS to the endpoint for your selected region:

* United States: https://tapi.simplysend.dev/send
* Australia: https://au-tapi.simplysend.dev/send
* Canada: https://ca-tapi.simplysend.dev/send
* Europe: https://eu-tapi.simplysend.dev/send

Your API key and Account ID are sent to the selected Simply Send API as authentication headers. When a message includes attachments, attachment details are sent to the API and file contents are transferred over HTTPS to temporary storage in the selected region for delivery. API credentials are not sent to the file-storage URLs. Your account’s total email size limit applies to messages with attachments.

Sending a test email makes a normal send request. Plugin activation alone does not contact Simply Send. The plugin collects no separate telemetry.

* Service: https://www.simplysend.dev
* [Terms of Service](https://www.simplysend.dev/terms)
* [Privacy Policy](https://www.simplysend.dev/privacy)
* [Acceptable Use Policy](https://www.simplysend.dev/legal/acceptable-use)
* [Data Processing Addendum](https://www.simplysend.dev/legal/dpa)
* [Subprocessors and data processing information](https://www.simplysend.dev/legal/subprocessors)

== Delivery details ==

* Use either To/CC recipients or BCC recipients, not both in one message.
* Attachments require exactly one To recipient, with no CC or BCC, and support up to 10 readable local files.
* Unsupported messages fail before an API request; recipients and attachments are not silently dropped.
* Email size limits apply to the complete email, including message content and attachments. Review your account’s Limits page for current limits and approval status.
* API endpoints are fixed for the selected region. Custom API URLs and automatic cross-region fallback are not supported.

== Changelog ==

= Contribute to development =

Contributions are welcome. To work on the plugin:

1. Clone the [Simply Send WordPress plugin repository](https://github.com/simply-invent-labs/simply-send-wordpress).
2. Create a branch for your change and make the update there.
3. Run the local regression checks documented in the repository’s README.
4. Open a pull request on GitHub describing the change and how you verified it. For larger changes, open an issue first to discuss the approach.

Do not include API keys, passwords, customer information, or other secrets in code, issues, or pull requests. Please report suspected security vulnerabilities privately through [Simply Send Support](https://www.simplysend.dev/support) instead of posting them publicly.

= Version history =

= 1.0.8 =
* First release.
