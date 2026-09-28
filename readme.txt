=== NexaChatAI ===
Contributors: DbsStudio
Tags: chatbot, ai, support, elementor, persian, rtl, consultation, assistant
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI assistant for WordPress that knows your business: multi-provider AI, answer scope, web search, citations, Persian/RTL-first.

== Description ==

NexaChatAI is a professional AI assistant for WordPress: install it, run the setup wizard, and publish an assistant that actually knows your business.

= Product philosophy =

**Simple by default, powerful by choice.** Advanced capabilities are modules that stay off until you enable them.

= Core features =

* **5-step setup wizard** with auto-saved progress
* **Invisible until published** — server-side gate, not just hidden UI
* **Real connection tests** with clear error mapping
* **Business knowledge base** with document chunks, keyword + optional semantic (embeddings) retrieval and source citations
* **Multi-provider AI:** OpenAI, Gemini, Claude, OpenRouter, OpenAI-compatible, signed Webhook
* **Security:** AES-256-CBC + HMAC encrypted keys, rate limits, honeypot, SSRF guard
* **Privacy:** server chat history is opt-in; short-lived browser transcripts outside pharma mode; configurable server retention
* **RTL + LTR** widget with per-message text direction
* **Live streaming** answers (SSE) for OpenAI, Claude, Gemini and OpenAI-compatible providers
* **Server-side conversation memory** (the browser never supplies the model's context)
* **Answer scope** (knowledge only / your business / any question) and optional **web search** with citations
* **Persian admin** (complete translation, RTL layout, Solar Hijri dates)
* **Display rules** by page path, device, and login state
* **Business hours / offline mode**

= Optional modules =

Voice, proactive invite, notifications (Bale/Telegram/email), handoff, analytics, CSAT, FAQ bank, consultation forms, and a pharmaceutical ADR (pharmacovigilance) extension.

All of the following are off by default and appear only after you switch them on under Modules:

* **Live chat:** an operator inbox to take over from the assistant, with queue and assignment, online/offline status, canned replies and an AI summary of the conversation. Operators can also answer from Bale or Telegram.
* **Messenger bot:** customers chat with the same assistant inside Bale or Telegram.
* **WooCommerce sales assistant:** product cards with real price and stock and an add-to-cart button, order tracking by order number and mobile, product comparison, a capped smart coupon on exit or hesitation, and an SMS cart reminder.
* **SMS gateway:** Kavenegar, Melipayamak, IPPanel or SMS.ir, for cart reminders and new-request alerts.
* **Learn from my website:** published pages, posts and products become knowledge automatically and stay up to date.

== Installation ==

1. Download `nexachat-ai-<version>.zip` from the project's GitHub Releases page. The "Source code" archives GitHub adds to each release are not installable plugins.
2. Plugins → Add New → Upload Plugin, choose the ZIP and activate — the setup wizard opens automatically.
3. Complete the five steps and publish.
4. Use the floating widget, shortcode `[ssc_chatbot]`, Gutenberg block, or Elementor widget.

Upgrading from 1.0.x (folder `smart-support-chatbot`): install and activate the new ZIP. The old copy is deactivated automatically and all data is kept; then delete the old copy.

== Frequently Asked Questions ==

= Do I need an API key? =

No. Offline modes (FAQ bank only / no AI engine) work without sending data outside your server.

= Who pays for AI usage? =

You do. NexaChatAI uses your own API keys — no middleman.

= What happens to data when I delete the plugin? =

Business data is preserved by default. Before deactivating/deleting the plugin, set the deletion policy under Settings → Data tools. An inactive plugin cannot display its own deletion prompt. Removing direct identifiers from a safety report does not anonymize free-text clinical narratives or audit notes.

= Does the assistant answer any question? =

You choose under AI Connection → "What the assistant may answer": only from your knowledge, your business and its field (default for new sites), or any question. You can also write the reply used for unrelated questions. Facts about your organization always come only from your knowledge. The rule is enforced through the model's instructions, so test it with real questions.

= Can the assistant search the internet? =

Optionally. Turn on web search under AI Connection; it uses your provider's own tool (OpenAI, Claude, Gemini, OpenRouter — not custom endpoints or webhooks), which the provider bills separately. You can restrict searches to your own domains (OpenAI and Claude). The pages used are listed under the answer. Web search is always off in knowledge-only and pharmaceutical modes.

= Persian / English and pharmaceutical use =

The widget, the adverse-reaction form and the whole admin panel are translated into Persian (RTL layout, Solar Hijri dates); language follows the WordPress locale. Custom company texts must be supplied in the desired language. A Persian setup guide for pharmaceutical companies ships in `docs/PHARMA-SETUP-fa.md`.

Enable the Pharma module to select either approved-company-content-only answers (default) or general educational answers. Neither mode authorizes personalized diagnosis, prescribing, or dose changes. A language-model prompt is not a clinical validation system; review actual answers with the company's medical team before production.

PHP mbstring and OpenSSL extensions are needed. Notifications require the Notifications module and working email/messenger delivery. WP-Cron depends on site traffic unless a system scheduler is configured. Notification jobs are persisted before delivery, claimed per worker, and retried by a five-minute WP-Cron schedule with backoff. After five failed attempts they remain visible for administrator retry. Mail acceptance is not proof of inbox delivery; monitor your mail service and safety-report inbox.

== Changelog ==

= 1.3.0 =

* New: conversations survive closing the tab. A visitor who comes back within the "remember for" period (default 7 days; pharmaceutical mode: 1 day and never stored in the browser) continues the same conversation, and the assistant still knows what was said.
* New: long conversations are no longer cut off. The latest messages (default 10) go to the AI word for word; older ones are folded into a short rolling summary, refreshed in the background. A failed summary never breaks an answer.
* New: signed-in users can list, reopen and delete their previous conversations on any device (header button in the chat).
* Changed: conversation logging is on by default (History module, 30-day retention) and the chat window says so. Sites updating from an earlier version get it switched on once; turning it off afterwards is respected. The Conversations page can show one whole conversation.
* Improved: answer quality rules for every provider. The assistant answers in the first sentence, does not greet again in an ongoing chat, asks at most one clarifying question, never claims it booked, saved or sent something, uses no tables, keeps Bale/Telegram replies short and follows Persian writing conventions (Persian digits, Solar Hijri dates, no Latin labels).
* Improved: when the AI fails, administrators see the reason in plain words (rejected key, no credit, missing model, rate limit…) with the provider detail, and the dashboard warns until answers work again. Visitors still get the polite fallback.
* New: an animated assistant character for the chat button and header, painted in your brand colour. It breathes, blinks, follows the pointer, hops on hover, winks on click and looks up while it thinks; reduced-motion settings switch the animation off. Choose "Simple chat icon" under Appearance to keep the old button.
* Fixed: on phones, the WordPress toolbar covered the chat header for signed-in users.

= 1.2.0 =

* New module, Live chat: operators take over a conversation from the assistant in a two-pane inbox. Includes a waiting queue, automatic or manual assignment, online/offline status with an offline message, canned replies, an AI summary for the operator and transcript retention. Operators can reply from Bale or Telegram.
* New: one shared Bale / Telegram bot connection (webhook with a secret, or polling when the site cannot receive webhooks). Used by notifications, live chat and the messenger bot.
* New module, Messenger bot: customers talk to the assistant inside Bale or Telegram and can ask for a human.
* New module, WooCommerce sales assistant: product cards with real price and stock and an add-to-cart button, and order tracking by order number plus the order's mobile number. It can also compare products, offer a smart coupon on exit or hesitation (with an amount, a minimum cart, validity and a daily cap), and send a cart reminder SMS when the visitor leaves a mobile number.
* New module, SMS gateway: Kavenegar, Melipayamak, IPPanel and SMS.ir; optional admin alert for new requests.
* New module, Learn from my website: published pages, posts and products become knowledge automatically. They are re-indexed within a minute of an edit, removed when trashed or unpublished, and fully re-synced daily.
* New: PDF and Word (.docx) import in the knowledge base, including Persian PDFs.
* New: AI-suggested FAQs and assistant persona, written only from your own material and added only after you review them.
* New: six industry templates in the setup wizard (online store, clinic, school, pharmaceutical, real estate, services). They fill only empty fields and recommend modules without switching them on.
* New: the pharmaceutical side-effect form is configurable. Choose a short or standard preset, or switch questions on/off, make them optional, reword them and add your own questions. Optional questions are folded under "More details" so the form looks short.
* Fixed: side-effect reports for products with non-Latin (e.g. Persian) names were refused. Existing product ids are migrated.

= 1.1.3 =

* New: "Chat widget language" setting (Appearance and the setup wizard). The chat's buttons, forms and messages can be Persian on an English WordPress, or the other way round. Automatic follows the assistant's answer language. Admin screens keep the admin's language.
* Fixed: the Persian translation did not load at all on WordPress versions before 6.5. The bundled .mo file had an invalid header for the classic reader.
* Fixed: the chat could not be closed by clicking the launcher again. A click outside the chat now closes it too.
* Fixed: the header "Main menu" button looked dead when the menu was already shown. It now moves the menu to the end of the conversation with a caption and highlights it.
* Fixed: "+ Add product" did nothing while the product list was empty. Product rows have a proper layout and a "+ Add attribute" button.
* Fixed: product attributes were never saved, and non-Latin attribute names (e.g. Persian) were stripped.
* Fixed: importing a web page reported "0 chunks" although the page was imported.
* Fixed: dark mode. A light custom bot bubble (e.g. white) no longer shows near-white text; dark mode uses its own card colour instead. Text on custom colours is picked by contrast. Native checkboxes, selects and scrollbars follow the widget theme.
* Fixed: the message box showed a scrollbar on a single line of text.
* Improved: knowledge-entry and product text areas span the full width.

= 1.1.2 =

* Fixed: the setup wizard's "Final check & launch" step could leave Publish disabled for good. The privacy acknowledgement was only saved by Publish itself, and a passing identity test did not update the checklist. The checklist now updates live, and Publish enables as soon as every item is done. The server still re-checks everything on publish.
* Improved: each unfinished checklist item now has a clear, coloured action button ("Run the test", "Confirm below", "Complete this step") instead of a small "Fix" link, and a hint under Publish lists what is still missing.
* Fixed: the live preview in the admin no longer scrolls the page to the chat input on load.

= 1.1.1 =

* Fixed: the release pipeline now attaches the installable ZIP even when the release was created in the GitHub UI first, and can be re-run for an existing tag.
* Fixed: a "translation loaded too early" notice (WordPress 6.7+) during activation; cron events are now scheduled on init.
* Docs: README, installation steps (download `nexachat-ai-<version>.zip`, not "Source code") and the Persian pharmaceutical setup guide updated for 1.1.

= 1.1.0 =

* New: semantic knowledge retrieval (OpenAI / Gemini / compatible embeddings) blended with keyword search, and source citations under AI answers.
* New: streaming for Claude and Gemini; an interrupted stream keeps the partial answer instead of paying for a second request.
* New: server-side conversation memory keyed by a random conversation id — forged "assistant" turns from the browser are ignored.
* New: multi-line composer, tap-to-call and main-menu buttons, radio fields, proactive invitation triggers (scroll / exit intent) and per-page messages.
* New: live business-hours status that works with page caches; object-cache rate-limit counters; AI cache generation keys.
* New: settings tabs; the Appearance preview is now the real widget; complete Persian translation with RTL admin and Solar Hijri dates.
* New: visitor IP anonymization (default) for logs and requests.
* New: answer scope — only from your knowledge, your business and its field (default for new sites), or any question — with an optional reply for unrelated questions. Replaces the old "strict mode" checkbox; existing sites keep their behaviour.
* New: optional web search through the provider's own tool (OpenAI Responses, Claude, Gemini Google Search, OpenRouter), with an optional domain allow-list and web citations under answers. Always off in knowledge-only and pharmaceutical modes.
* Changed: plugin folder, main file and text domain are now `nexachat-ai`. An active pre-rename copy is detected and deactivated automatically; settings and data are shared and kept.
* Fixed: "bottom right" appeared bottom-left on RTL sites; chat questions containing "<" were truncated; checkbox/radio label spacing.

= 1.0.0 =

First stable release. Everything from the 0.6.x beta line, plus:

* Fix the Add button on knowledge items, products and form fields — the row
  builder used a relative CSS selector that browsers reject, so nothing happened
  on click.
* Fix repeating a row after deleting one in the middle, which reused an index
  that was still in use and silently overwrote the surviving entry on save.
* Fix manual model IDs saving as empty. The typed value is now materialized as a
  real option before submit and resolved server-side, so it also survives with
  JavaScript disabled.
* Fix the manual model field binding to the first provider block instead of its
  own, so a model typed for one provider could be read for another.
* Fix legacy notification migration aborting on the first malformed entry and
  stranding every job queued behind it.
* **Security — prompt injection through knowledge content.** The delimiters that
  mark reference data closed immediately after a document TITLE, leaving every
  document body outside the fence the system prompt declares to be data-only, and
  a delimiter typed into a knowledge entry, product name or imported page could
  re-pair the fence. Instructions planted in a knowledge document could therefore
  reach the model as trusted text. Titles and bodies are now enclosed together and
  delimiters in untrusted content are neutralized.
* **Safety — emergency escalation.** A message describing a life-threatening
  reaction that also mentioned a side effect was intercepted before the model ran
  and received only the report offer, with no guidance to seek urgent care. Such
  messages now lead with a bilingual emergency notice before the report offer.
  This is a broad keyword screen for harm reduction, not clinical triage; sites
  outside Iran should adjust it with the ssc_emergency_notice and
  ssc_emergency_terms filters.
* Stop re-billing the AI provider for a completed stream that hit a late
  transport error while closing the connection.
* Fix automatic text direction, which chose between two identical branches; it
  now follows WordPress's own RTL flag and a wider RTL locale list.
* Validate FAQ imports by extension and report unsupported types instead of
  failing during parsing.
* Harden custom font names, SSE event names, request paths and the multisite
  uninstall loop; guard the block asset file against direct access.
* Full WordPress Coding Standards compliance across every shipped PHP file, with
  each deliberate deviation documented in the ruleset.
* Reproducible release packaging with an independent verifier that refuses to
  ship tests, tooling, internal notes or stray credentials.

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

= 1.1.3 =

Widget language setting, Persian translation fix for WordPress < 6.5, dark-mode colours and several admin fixes. No data changes.

= 1.1.2 =

Fixes the setup wizard's Publish button staying disabled on the final step. No data changes.

= 1.1.1 =

Documentation and release-packaging update; no data changes. Upgrading from 1.0.x? See the 1.1.0 notice below.

= 1.1.0 =

The plugin now lives in the `nexachat-ai` folder. Install the new ZIP and activate it: the old `smart-support-chatbot` copy is deactivated automatically and all data is kept. Its "remove data on uninstall" policy is switched off during the hand-over so deleting the old copy cannot erase shared data. Back up first, then clear page/CDN caches.

= 1.0.0 =

First stable release; the data format is unchanged from 0.6.x, so settings,
knowledge, requests and ADR cases are preserved. Back up your database first and
clear page/CDN caches after upgrading. If you had entered a model ID manually, it
was not being saved — re-enter it once after upgrading and confirm the connection
test passes.

= 0.6.0-beta =

Back up your database first. Review pharma answer mode, approved content, consent wording, notification recipient, and retention. Clear page/CDN caches after upgrading. Existing saved model IDs are not automatically replaced; select a supported model if an old one has retired. This beta has been checked locally on WordPress 7.1 with SQLite and PHP 8.5; production MySQL, real provider streaming, other themes, and external delivery still require staging verification.


= 0.5.1-beta =

Beta rebrand of the 5.x line. Data and settings are preserved. Review Appearance and delete-data policy after upgrade.
