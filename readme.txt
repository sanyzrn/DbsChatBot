=== NexaChatAI ===
Contributors: DbsStudio
Tags: chatbot, ai, support, elementor, persian, rtl, consultation, assistant
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.1-beta
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NexaChatAI — Professional AI assistant for WordPress. Setup wizard, multi-provider AI, modular architecture.

== Description ==

NexaChatAI (beta) is a professional AI assistant for WordPress: install it, run the setup wizard, and publish an assistant that actually knows your business.

= Product philosophy =

**Simple by default, powerful by choice.** Advanced capabilities are modules that stay off until you enable them.

= Core features =

* **5-step setup wizard** with auto-saved progress
* **Invisible until published** — server-side gate, not just hidden UI
* **Real connection tests** with clear error mapping
* **Business knowledge base** with document chunks (offline RAG-style)
* **Multi-provider AI:** OpenAI, Gemini, Claude, OpenRouter, OpenAI-compatible, signed Webhook
* **Security:** AES-256-CBC + HMAC encrypted keys, rate limits, honeypot, SSRF guard
* **Privacy:** server chat history is opt-in; short-lived browser transcripts outside pharma mode; configurable server retention
* **RTL + LTR** widget with logical CSS
* **Live streaming** answers (SSE) for OpenAI-compatible providers
* **Display rules** by page path, device, and login state
* **Business hours / offline mode**

= Optional modules =

Voice, proactive invite, notifications (Bale/Telegram/email), handoff, analytics, CSAT, FAQ bank, consultation forms, and a pharmaceutical ADR (pharmacovigilance) extension.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate — the setup wizard opens automatically
3. Complete the five steps and publish
4. Use the floating widget, shortcode `[ssc_chatbot]`, Gutenberg block, or Elementor widget

== Frequently Asked Questions ==

= Do I need an API key? =

No. Offline modes (FAQ bank only / no AI engine) work without sending data outside your server.

= Who pays for AI usage? =

You do. NexaChatAI uses your own API keys — no middleman.

= What happens to data when I delete the plugin? =

Business data is preserved by default. Before deactivating/deleting the plugin, set the deletion policy under Settings → Data tools. An inactive plugin cannot display its own deletion prompt. Removing direct identifiers from a safety report does not anonymize free-text clinical narratives or audit notes.

= Persian / English and pharmaceutical use =

The widget and structured adverse-reaction form include Persian translations and English source strings; language follows the WordPress site locale. Administrative translation coverage is partial. Custom company texts must be supplied in the desired language.

Enable the Pharma module to select either approved-company-content-only answers (default) or general educational answers. Neither mode authorizes personalized diagnosis, prescribing, or dose changes. A language-model prompt is not a clinical validation system; review actual answers with the company's medical team before production.

PHP mbstring and OpenSSL extensions are needed. Notifications require the Notifications module and working email/messenger delivery. WP-Cron depends on site traffic unless a system scheduler is configured. Notification jobs are persisted before delivery, claimed per worker, and retried by a five-minute WP-Cron schedule with backoff. After five failed attempts they remain visible for administrator retry. Mail acceptance is not proof of inbox delivery; monitor your mail service and safety-report inbox.

== Changelog ==

= 0.6.1-beta =

* Make every admin page and setup wizard use the available WordPress content width.
* Adapt form grids, repeaters, cards and dashboard readiness to narrow screens.
* Keep wide tables readable with keyboard-accessible scrolling inside their own regions.

= 0.6.0-beta =

* Fix public REST nonce mismatch, explicit payload validation, and duplicate POST replay.
* Fix admin save-handler timing, hidden setup controls, preservation of inactive-module configuration, lead form fatal errors, ADR product form crash, follow-up status, and inbox pagination.
* Validate consent, Persian phone numbers, products, patient age, and JSON seriousness criteria.
* Add two configurable pharma answer policies and 143 bundled Persian translations.
* Unify streaming and standard reply processing, history, fallback, and feedback tokens.
* Escape model output attributes, protect all CSV exports, bound HTTP responses and prohibit credential redirects.
* Disable shared AI cache and browser transcript persistence in pharma mode; minimize ADR notification content.
* Fix overnight business hours, restored browser conversations, widget stacking, and new conversation control.
* Refresh suggested Gemini/Claude models; preserve existing saved model choices.
* Add durable, idempotent notification jobs, worker leases, legacy queue migration, and visible exhausted failures with manual retry.
* Add reproducible PHP, JavaScript, WordPress, and local HTTP tests.


= 0.5.1-beta =

* Rebrand: NexaChatAI by DbsStudio
* Admin layout uses full width on large monitors
* Delete-time prompt: erase all stored data or keep it for reinstall
* Streaming AI replies, display rules, business hours, live dark/light appearance preview
* Forced LTR admin UI on RTL sites

= 5.1.0 =

* Streaming (SSE), display targeting, business hours, unread badge, sound, Alt+C
* Security hardening, module isolation, pharma consent, trusted proxy header
* composer / PHPCS / CI scaffolding

== Upgrade Notice ==

= 0.6.0-beta =

Back up your database first. Review pharma answer mode, approved content, consent wording, notification recipient, and retention. Clear page/CDN caches after upgrading. Existing saved model IDs are not automatically replaced; select a supported model if an old one has retired. This beta has been checked locally on WordPress 7.1 with SQLite and PHP 8.5; production MySQL, real provider streaming, other themes, and external delivery still require staging verification.


= 0.5.1-beta =

Beta rebrand of the 5.x line. Data and settings are preserved. Review Appearance and delete-data policy after upgrade.
