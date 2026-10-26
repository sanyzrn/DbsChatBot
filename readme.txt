=== NexaChatAI ===
Contributors: DbsStudio
Tags: chatbot, ai, support, persian, rtl
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.7
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
* **Export & Import** of all settings, products, knowledge, FAQ, documents and (password-protected) API keys, with review and undo
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

== External services ==

NexaChatAI contacts an outside service only after the site owner has chosen and configured it. Nothing is sent to the plugin's author: there is no telemetry, no licence check and no update ping.

= AI provider (the one the owner selects) =
Used to write answers, to build the optional knowledge index (embeddings), to summarise long conversations, and for the optional web search. Called on each chat message, on each indexing job and on the administrator's connection test. Sent: the visitor's message, the recent conversation or its summary, the business profile and the knowledge excerpts found for the question, and the assistant's instructions. In pharmaceutical mode the visitor's text can describe a health problem; the owner should have a data-processing agreement with the provider they choose. The owner's API key goes only to that provider.

* OpenAI: https://openai.com/policies/terms-of-use and https://openai.com/policies/privacy-policy
* Anthropic (Claude): https://www.anthropic.com/legal/commercial-terms and https://www.anthropic.com/legal/privacy
* Google (Gemini): https://ai.google.dev/gemini-api/terms and https://policies.google.com/privacy
* OpenRouter: https://openrouter.ai/terms and https://openrouter.ai/privacy
* Any OpenAI-compatible endpoint or webhook the owner enters: governed by that service's own terms.

= Bale and Telegram (optional) =
Used for administrator notifications, for live-chat operator replies and for the messenger bot. Called when the Notifications, Live chat or Messenger module is on and the owner has connected a bot. Sent: the notification text, or the visitor's chat message and the answer. Bale: https://bale.ai/ . Telegram: https://telegram.org/tos and https://telegram.org/privacy

= SMS panel (optional) =
Used for cart reminders and administrator alerts. Called only when the SMS module is on and a message is sent. Sent: the recipient's mobile number and the message text. Kavenegar: https://kavenegar.com/ . Melipayamak: https://www.melipayamak.com/ . IPPanel / Faraz SMS: https://ippanel.com/ . SMS.ir: https://sms.ir/

= Website pages (optional) =
"Import from a web address" (Knowledge and the setup wizard) downloads the page the administrator types, from the server that runs the site. "Learn from my website" reads the site's own posts and pages and contacts no one.

== Installation ==

1. Download the installable `nexachat-ai-<version>.zip` from the marketplace or account where you purchased the plugin.
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

= 1.7.7 =
* Changed: on phones the open chat is a floating card with side margins instead of a full-screen sheet. It rests where the launcher was (above a bottom menu), a soft scrim behind it closes the chat when tapped, and it rises above the on-screen keyboard so the message box stays visible.
* Changed: About shows the release notes as a timeline: newest first, the installed version tagged, each note labelled (Fixed, New, Changed, Security, Privacy, Docs), older versions folded away.
* Fixed: unfinished items in the dashboard checklist read as actions ("Complete the business profile") instead of the finished-state wording that was shown next to a "Finish" button.

= 1.7.6 =
* Fixed: consent text uses a regular font weight, and checkboxes remain 22px wide without shrinking beside long Persian text. The entire label remains clickable with a minimum 44px row height and a visible keyboard focus ring.
* Changed: the mascot fur, highlights and shadows follow Primary brand color in the launcher, header and live preview, including shorthand hex colors.
* Changed: About displays the bundled changelog locally instead of linking to the private repository; problem reports go to https://dbsstudio.ir/issues.
* Changed: installation instructions and translation/security issue destinations no longer require private repository access.
* Docs: added an internal, scoped RTL-Theme marketplace readiness review (excluded from the installable ZIP).

= 1.7.5 =
* Fixed: product, menu and brochure buttons keep readable brand-color hover, focus and pressed states instead of looking disabled under host-theme styles.
* Fixed: the floating chat button stays opaque and circular, and becomes darker on hover/focus/press.
* Fixed: the empty Persian message composer and its placeholder align to the right while typed text keeps automatic direction.
* Changed: the WordPress sidebar displays «نکساچت» for a Persian administrator language, independently of the widget language.
* Changed: restored the original NexaHR furred mascot, including its face, tuft, feet and pointer-driven gaze. Each SVG has isolated gradient IDs so the header and launcher render independently.

= 1.7.4 =

* Fixed: a site that had been customised on version 5 and still carried an old 4.x setting key (for example company_id) had its settings reset to the defaults by the upgrade routine (AI provider, IP storage, answer scope, business hours and phone). The migration now runs once and leaves a mark.
* Fixed: importing an FAQ file with no usable rows in "Replace" mode emptied the whole answer bank. It now stops with a message and changes nothing; a JSON row needs both a question and an answer.
* Fixed: the Elementor widget never rendered because its guard looked for a function instead of the class.
* Fixed: PDF text with Arabic presentation forms is folded to ordinary Persian letters on hosts without the PHP intl extension, so Persian PDFs are searchable there too.
* Privacy: rate-limit and coupon counters are keyed with a salted HMAC of the visitor instead of a plain MD5 of the IP address. Daily coupon counters are transients instead of one option per day; old ones are cleaned up daily and on uninstall, and uninstall now also removes the AI failure record and the migration marks.
* Security: the custom webhook, the SMS panels and the setup wizard's page fetch no longer follow redirects and cap the size of the answer; secret fields are refused by the general settings sanitizer; successful product reminders now count toward the rate limit too.
* Changed: the uninstall question is two plain questions, so Cancel always means the plugin is not deleted.
* Docs: readme.txt has five tags, "Tested up to" 7.1 and an External services section; SECURITY.md notes that the Bale webhook secret travels in the query string.

= 1.7.3 =

* New: the plugin declares compatibility with WooCommerce's high-performance order storage (HPOS, "custom order tables"). It reads orders only through WooCommerce's own order functions, so WooCommerce no longer lists it as "compatibility unknown" on stores that use HPOS.
* Docs: SECURITY.md described conversation history as supplied by the browser. Since 1.1 the assistant's context comes from the conversation the server stored, and a browser can never add an assistant turn; the clause now says so. It also states a verified limit: an endpoint or webhook on a host that publishes only IPv6 (AAAA) records is refused, as it is by WordPress core's own address check.
* Docs: tests/README.md lists every check file of the integration suite and what a bare command-line PHP needs to run the unit tests.

= 1.7.2 =

* Fixed: the FAQ bank no longer gives one answer to questions that differ in a unit, a sign or a number. 10 GB and 10 Gb (bytes and bits), 10 gigabyte and 10 gigabit, -20 and 20 degrees, 5 millimeter and 5 milligram, 2.5 and 25 are all told apart, in English and in Persian. The same quantity spelled another way (10 GB and 10 گیگ, 25 and 25.0, 1,000 and 1000, +20 and 20) still matches.
* Fixed: an import file whose documents hold a list where text belongs, no id or no text is refused whole, naming the document and the field. Nothing is stored as the text "Array" any more. Documents are read in full before anything is written, each one is replaced all or nothing, and "Replace" removes the documents that are not in the file only after the ones that are in it are in place; a document that fails is reported and the previous one stays.
* Fixed: an import file whose settings hold a value of the wrong kind inside a list (a form field whose label is a list, a bad option, a bad safety-report form entry) is refused whole, naming the exact place, for example form_fields[0].label.
* Fixed: a visitor who asks for a consultation and then takes it back ("actually I do not want one", "never mind", "ولش کن") is no longer offered the request form, and neither is a wish that waits for a condition ("call me if I need help later", "اگر نیاز داشتم تماس بگیرید"). "No particular problem, but I want a consultation" is still recognised.
* Fixed: when Gemini stops an answer for safety reasons, or refuses a request before writing anything, the result is reported as a content-policy stop and not as an unexpected response. No blocked text and no Continue button are shown, and a streamed safety stop no longer triggers a second, billed request.
* Persian translation of the new text.

= 1.7.1 =

* Fixed (security): a proxy on a private network (10.x, 172.16–31.x, 192.168.x) is no longer believed automatically. This server itself and the proxies you list are; private networks are an explicit choice in Settings → Protection, and shared carrier-grade addresses (100.64.x) are never believed unless you list them. A site that had already chosen a header and listed no proxies keeps working as before until you decide otherwise.
* Fixed: when a model stops because it ran out of answer length, the answer is no longer shown as a finished one. It says it was cut short, is not cached, and a "Continue the answer" button lets the visitor ask for the rest; nothing is requested automatically. A provider stopping an answer under its content policy, or a connection that broke off, is shown as its own case. A model that spends its whole budget before writing anything is reported with that reason instead of "unexpected response".
* Fixed: the FAQ bank no longer answers a question whose numbers differ (10 users and 5 users, 5 mg and 10 mg) because they share one number.
* Fixed: a wrong email in the consultation form (or in a custom email field) is refused instead of being quietly repaired into a different address; a list sent where a value belongs is refused too.
* Fixed: Persian and Arabic digits are accepted in custom number fields, and a value that is not a number is refused with the field named.
* Fixed: "I have no particular problem but I want a consultation" now offers the request form; a negation only counts inside the part of the sentence it belongs to. "نیاز به مشاوره دارم" is recognised too.
* Fixed: an import file whose settings hold a value of the wrong kind (a list where text belongs) is refused whole, naming the setting; nothing is applied.
* Fixed: the Connection screen no longer says that API keys "never leave the server". They are stored encrypted, never shown in the browser, and sent only to the provider you choose when a request is made.
* Fixed (safety): trouble breathing said in other words, such as "نفس کشیدن برام غیرممکن شده", gets emergency guidance at once in pharmaceutical mode; denials and teaching questions stay quiet.
* Persian translation of the new text.

= 1.7.0 =

* New: the consultation request form has an optional email field. It appears in the Requests inbox, the CSV export and the notification, and Settings → Request form can switch it off. A wrong address is refused with a clear message; leaving it empty is fine.
* New: when a visitor clearly asks for a consultation or a call back ("I want a consultation", "call me", "می‌خوام مشاوره بگیرم"), the chat answers with a button that opens the form instead of calling the AI. Questions about what a consultation costs, negated sentences and emergencies in pharmaceutical mode are left alone. Nothing is offered while the module is off.
* Changed: the Persian name of the module is now «ثبت درخواست مشاوره».
* Persian translation of the new text.

= 1.6.0 =

* New: several places (head office, factory, branches…) with an address, phone and map link each. The assistant shares them when someone asks where you are.
* New: your working hours, phone numbers, email addresses and addresses are written once, in Contact & hours. Knowledge entries that repeat or contradict them are flagged, and the assistant quotes the chat schedule when no hours are written.
* New: the custom OpenAI-compatible endpoint has an optional API key field (sent as a Bearer token).
* New: the Knowledge screen shows how much of the 4,000-character reading budget the entries use and marks the entries that do not fit.
* New: saving the Knowledge form from an older tab no longer undoes what another tab saved. Changes are merged, and when both tabs changed the same thing you choose which version to keep.
* New: the Connection screen and the Dashboard show the last connection test, with its time and the provider's request id, instead of a stale "verified" after a newer failure.
* New: Trusted proxies in Settings (with a Cloudflare preset).
* Fixed: the Appearance page and the setup wizard were left-to-right in Persian.
* Fixed (safety): an urgent message such as "I can't breathe" gets emergency guidance at once, before the AI and without the side-effect form.
* Fixed (safety): a stored FAQ answer is no longer given to the opposite question ("allowed" / "not allowed", "stop" / "do not stop", before / after food, adults / children, pregnancy / breastfeeding, different doses).
* Fixed (security): a visitor can no longer choose their own address through a forwarded header to get around the rate limits; headers are believed only from listed proxies.
* Fixed (privacy): in pharmaceutical mode a returning visitor's conversation is restored from the server, and no transcript is kept in the browser.
* Fixed: switching the FAQ module off now stops the answer bank everywhere, including the fallback after an AI failure.
* Fixed: a 403 from the AI provider is no longer always reported as a wrong key. Invalid key, security policy, missing permission, a refusal without a reason, rate limits and timeouts each have their own message.
* Fixed: Word and PDF files that unpack to far more data than their size suggests are refused with a clear message instead of using the server's memory; nothing is added.
* Fixed: a long text without punctuation is cut into pieces under a hard ceiling, never in the middle of a word.
* Fixed: large answer banks and knowledge bases no longer miss entries that fall outside the newest rows.
* Fixed: a form or import file with a list where text belongs is refused whole, naming the place, instead of a fatal error.
* Fixed: the patient's age in the adverse-reaction form accepts Persian and Arabic digits.
* Fixed: two requests of one conversation arriving together no longer lose a turn, and a conversation summary is made once.
* Fixed: a page learned from the website is replaced all or nothing, so a failure keeps its previous version; failures are counted in the sync panel.

= 1.5.0 =

* New: the Business Knowledge redesign now runs through the whole plugin. Every screen shares one look: a progress ring, colour-coded icon tiles, sections that open one at a time, collapsible cards and a sticky save bar that tells you when something is unsaved.
* New: Dashboard with a ring for how much of the setup is done, three status cards, notices as cards with their own action, icon tiles for the numbers, and a readiness list whose Finish button goes straight to the screen where each item is fixed.
* New: AI Connection in four sections (AI engine, what it may answer, web search, advanced) with a card for each provider and each answer scope, and a ring for how far the connection is set up.
* New: Settings with a ring for the health of your privacy and protection choices and the next things worth fixing, five sections (privacy, display and hours, protection, modules, data tools), and each module's options in its own collapsible card.
* New: Appearance with a brand strip that follows the colour and name as you type, and five collapsible sections next to the live preview.
* New: Modules with a ring for how many are on, a filter by status, colour-coded groups, and Configure links that open the module's own card in Settings.
* New: Requests, FAQ Bank, Conversations, ADR Cases, Analytics and Live chat with summary tiles (some with rings), filter chips that show counts, a colour accent for each status and friendly empty states that say what to do next.
* New: the setup wizard's knowledge step shows a progress ring and one-tap starter topics (opening hours, prices, return policy, shipping, how to contact you), so it never opens as an empty form.
* Fixed: when a field failed the browser's own check inside a hidden section or a folded card, saving did nothing and said nothing. The section now opens and the field is highlighted.
* Fixed: table headings, badges and dates no longer break in the middle of a word in the Requests and Conversations lists.
* Complete Persian translation of every new screen, checked in right-to-left on desktop and phone widths.

= 1.4.0 =

* New: move the floating button. Appearance has a new "Button position & spacing" card with the distance from the bottom and from the side, separately for phones and for desktop/tablet, and small drawings that show where it will sit. A "Above a bottom menu" shortcut clears a typical navigation bar.
* New: the button lifts itself above a fixed bar at the bottom of the page on phones and tablets (an app-style menu, a cookie notice) and drops back when the bar goes away. It can be switched off. The chat window and the invitation bubble follow the button.
* New: Export & Import. One JSON file holds the business profile, settings and appearance, active modules, products with their specifications, knowledge entries, the FAQ bank, uploaded documents and, optionally, the API keys. Keys are encrypted with a password you choose and are never written in plain text. Importing shows what the file contains first, lets you pick what to import, can add and update or replace, never publishes the assistant and can be undone. Conversations, requests, safety reports and statistics are never exported. Settings that decide where information is sent (the AI endpoint, notification recipients, the SMS alert number) are imported only together with the password-protected keys, so a file that is not yours cannot redirect your data. A file cannot plant pages that "Learn from my website" owns, and every value is sanitized like a normal save.
* New: Business Knowledge redesigned. A progress ring shows how well the assistant knows your business and suggests the best next steps; sections (your business, contact & hours, persona, knowledge entries, products, documents, your website, AI helper) open one at a time with their own progress; entries and products are collapsible cards with ready-made examples (hours, return policy, shipping…) instead of one long form; the page tells you that a business name and one fact are all you need to go live; unsaved changes are flagged.
* New: an About page with the maker (DbsStudio, DbsStudio.ir), technical details, a system check and a copyable support report that holds no keys or personal data.
* Fixed: with the button on the left, the open chat on phones was shifted sideways.
* Fixed: an empty message bubble appeared under an answer when human handoff was on without a handoff text.
* Fixed: the "conversations are saved" note touched the line above it when no disclaimer was set.
* Fixed: new knowledge entries were saved without an id; they now get a stable one.
* Complete Persian translation of every new screen.

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

