# Full audit prompt for NexaChatAI

Paste everything below the line into a new agent session (Claude Code or similar) that has shell access, a browser (Playwright/Chromium) and this repository. The prompt is in English because agents follow long technical instructions most reliably in English. The report itself is requested in Persian; change the "Report language" line if needed.

---

You are a senior WordPress QA engineer and security reviewer. Audit the **NexaChatAI** plugin (folder `nexachat-ai`, this repository) end to end **by really installing and using it**: not only by reading code. Your output is one Markdown report.

## Ground rules (read first)

1. **Evidence or it did not happen.** Every finding and every "works" claim needs evidence: the exact steps, the expected and actual result, and at least one of a screenshot path, log excerpt, HTTP request/response, or DB query result. Anything you could not run goes under "Not tested", with the reason. Never write "should work", "likely fine" or invented output.
2. **Test the shipped artifact.** Build the ZIP with `python tools/build-release.py` and verify it with `python tools/verify-release.py`. Or download the latest `nexachat-ai-<version>.zip` from GitHub Releases. Install that ZIP through the real WordPress upload flow (Plugins → Add New → Upload), not by symlinking the repo. Use a symlinked copy only for later re-runs of the automated test suites.
3. **Audit only.** Do not change plugin code, push commits, open PRs or create releases. Scratch scripts belong outside the repository.
4. **Isolation and safety.**
   - Use disposable WordPress installs and databases only.
   - Block real e-mail: log `wp_mail` with a mu-plugin, or use MailHog/Mailpit.
   - Never send data to real messengers (Bale/Telegram).
   - Use real AI API keys only if they are provided in environment variables (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `OPENROUTER_API_KEY`). Otherwise use the mock provider described below.
   - Never print keys into the report.
5. **Debug on.** Every test site runs with `WP_DEBUG`, `WP_DEBUG_LOG` and `SCRIPT_DEBUG` set to true, and `WP_DEBUG_DISPLAY` set to false. After every test block, read `wp-content/debug.log`. Any PHP notice, warning, deprecation or fatal error coming from the plugin is a finding. For every browser page, also collect `console.error`, `pageerror` and failed network requests.
6. **Be exhaustive but finish.** If something blocks you (missing tool, network), work around it or record it. Do not stop the audit.

## 1. Environment

Build the matrix below. Docker (e.g. `wordpress:*` images plus MariaDB) or local PHP with MariaDB/MySQL are both fine. Use SQLite only if a MySQL-family database is truly unavailable, and say so.

| Site | PHP | WordPress | Site language | Purpose |
|---|---|---|---|---|
| A | 8.3 (or newest available) | latest | fa_IR | Main functional run (Persian, RTL) |
| B | 8.3 | latest | en_US | Widget-language independence, LTR admin |
| C | 7.4 | 5.6 (the declared minimum), else the oldest you can run | en_US | Minimum-requirements smoke test; classic translation loading (< 6.5) |
| D | 8.3 | latest **multisite** | fa_IR | Network activation, per-site data, uninstall |

- Install WP-CLI and use it for setup and for inspecting options and tables. Use the UI for everything a real admin would click.
- Themes: test the widget on at least Twenty Twenty-Five (block theme) and one classic theme (e.g. Astra or GeneratePress).
- Record the exact versions (PHP, WP, DB, browser, themes) in the report.

### Mock AI provider

Start a tiny local HTTP server that speaks the **OpenAI-compatible** chat-completions API. It must support:
- normal JSON responses;
- **streaming** (`stream: true`, SSE `data:` chunks ending with `[DONE]`), including one chunk that splits a multi-byte Persian character;
- configurable failures: 401, 429, 500, a timeout, and malformed JSON;
- a fake embeddings endpoint (`/v1/embeddings`) for semantic search.

Log every request body it receives, so you can verify what the plugin sends: system prompt contents, knowledge injection, answer-scope instructions, history length, and that no secrets or personal data leak into it. Point the plugin's "OpenAI-compatible / custom" provider at this server.

If real keys exist, additionally run a short smoke test per provider: Claude, OpenAI, Gemini and OpenRouter, both normal and streaming. Also run web search if it is available for that key.

## 2. Install, first run, upgrade, uninstall

1. **Fresh install:** upload the ZIP. Activation must produce no errors or notices. Check the created options, custom tables (schema, indexes, charset `utf8mb4`) and scheduled cron events.
2. **Setup wizard:** complete every step as a new admin would, including skipping steps, going back, a failed identity test and a failed page import. The final "check & launch" step must:
   - show a correct checklist;
   - enable Publish only when all items are ready;
   - make every action button do something visible;
   - enforce readiness on the server too. Try publishing with a crafted POST while items are missing.
3. **Upgrade path:**
   - Install the previous release ZIP from GitHub Releases and create data (settings, knowledge, products, conversations, ADR reports). Then upgrade by uploading the new ZIP ("Replace current with uploaded"). All data must survive, and there must be no duplicate tables or cron events.
   - If feasible, repeat from the legacy folder name the plugin migrates from; see `nexachat-ai.php` / `includes/bootstrap.php`.
4. **Deactivate / reactivate:** cron events are cleaned up and restored, and there are no errors.
5. **Uninstall (delete):** check what `uninstall.php` removes. Report any leftover options, tables, transients, cron events or uploaded files. On multisite, check every site.

## 3. Functional tests (do all of them, in the browser)

For each item, note what you did and what happened.

**Admin pages:** Dashboard, Business Knowledge, AI Connection, Appearance, Modules, Settings, the ADR cases list, Conversations, and the setup wizard. For every form:
- save, reload, and confirm the values persisted;
- try empty, very long (10,000+ chars), Persian, emoji, HTML/script and RTL/LTR-mixed values;
- check validation messages and that notices appear in the right place;
- check nothing is lost when a save fails;
- use "Add/Remove row" repeaters, including on an empty list and after deleting middle rows.

**Knowledge:**
- Business profile fields and knowledge entries.
- Products with attributes, including Persian attribute names.
- Long-document import from URL and from `.txt/.md/.csv/.json` files. Also test oversized, wrong-type and empty files, and a URL returning 404 / a non-HTML page / a private IP (SSRF must be refused).
- The chunk counts shown must match the database.
- Semantic search on/off with the mock embeddings, plus the "index now" action.

**AI connection:** every provider option. Check the test-connection feedback, invalid keys, timeouts, model selection including a manually typed model id, answer scope (knowledge-only / business / open), web search on/off, and the web-search domain list.

**Chat widget (visitor side, logged out)**, on desktop 1440×900 and mobile 390×844:
- Open/close via the launcher, the header ✕, Escape, Alt+C, and a click outside the widget.
- Welcome message, main menu, chips, product picker.
- Sending messages; streaming output; the typing indicator; markdown rendering (lists, links, code); source citations; related questions; copy and thumbs-up/down feedback.
- "New conversation"; conversation persistence across page loads; server-side history limits.
- Rate limiting: send many messages quickly and check the message shown.
- Offline / business-hours behaviour and the offline message.
- Voice input/output where the browser supports it; otherwise record it as not testable.
- Proactive invitation rules and dismissal.
- CSAT prompt after closing.
- Lead form and handoff: submit valid and invalid data, then check the admin view and the notification mail/messenger payload (captured, not sent).
- Pharmacovigilance ADR form: required fields, consent checkbox, submission, and case appearance in the admin list with correct data.
- Shortcode `[ssc_chatbot]`, the block, and the Elementor widget if Elementor can be installed.
- Device rules (hide on mobile/desktop) and page-targeting rules.

**Language and direction:**
- On site B (English WP), set "Chat widget language" to Persian. All widget text — buttons, placeholders, forms, errors from REST responses, CSAT — must be Persian, while the admin stays English.
- Repeat the reverse on site A.
- "Automatic" mode follows the answer language.
- On site C (WP < 6.5), Persian translations must load at all.
- List every untranslated or awkward Persian string you see.
- RTL layout, and mixed English/Persian text inside bubbles (`dir="auto"`).

**Appearance:**
- Light, dark and auto themes. Test with custom primary, user and bot bubble colours, including white, yellow and very dark values.
- All text must stay readable. Measure contrast; WCAG AA is 4.5:1 for body text.
- Launcher and window sizes, fonts including a custom font URL, avatar, positions left/right.
- The live preview in admin must match what visitors see.

**Modules:** enable and disable each module (analytics, CSAT, FAQ, handoff, history, leads, notifications, pharma, proactive, voice). When a module is off, its UI, REST routes and cron events must be inactive. Check the notification queue and retries, and the history/analytics data shown on the dashboard.

## 4. Security review (test it, don't just read it)

- **Access control:**
  - Every admin action and admin REST route (`test-connection`, `test-identity`, `preview-chat`) must be denied to logged-out users, subscribers and editors (test with real accounts).
  - Nonces must be checked on every state-changing form and AJAX action. Replay a request without a nonce and with a stale nonce.
- **Public REST routes** (`/wp-json/ssc/v1/chat`, `chat-stream`, `submit`, `suggest`, `feedback`, `csat`, `status`) and the admin-ajax fallback:
  - Send fuzzed input: huge bodies, wrong types, arrays instead of strings, invalid UTF-8, null bytes, SQL-looking and script payloads.
  - Check the rate limits cannot be bypassed trivially, e.g. by rotating the `X-Forwarded-For` header.
- **XSS:**
  - Inject payloads through every visitor-controlled field: chat message, lead/ADR fields, names, feedback.
  - Inject payloads through the AI reply itself (the mock returns `<img src=x onerror=alert(1)>`, markdown links with `javascript:` URLs, and so on).
  - Check both the widget and every admin screen that displays that data.
- **SSRF:** URL import and any server-side fetch must refuse private, loopback and link-local addresses, `file://`, and redirects to internal hosts.
- **SQL injection:** review every `$wpdb` query for `prepare()`, and try the injection payloads through filters and list tables.
- **Secrets:** confirm API keys are never:
  - printed in HTML or JS config;
  - returned by REST;
  - stored unencrypted if the plugin claims encryption;
  - sent to the wrong provider;
  - written to logs or `debug.log`.
- **Privacy:**
  - IP storage modes (anonymize / full / none) actually behave as described.
  - Consent is enforced server-side.
  - Personal-data export/erasure hooks exist, or record that they are missing.
  - Retention and cleanup work.
- **Files:** uploads are restricted by type and size; no directory listing or direct PHP access to plugin files (every folder must have `index.php` or equivalent guards).
- Run `phpcs` with the repo's ruleset (`vendor/bin/phpcs`). Run the official **Plugin Check** plugin (`wp plugin install plugin-check`, then `wp plugin check nexachat-ai`) and summarise its errors and warnings.

## 5. Performance and compatibility

- Front-end cost with the widget enabled:
  - number and size of CSS/JS files and whether they load on pages where the widget is hidden;
  - extra DB queries per page (use the Query Monitor plugin);
  - Lighthouse performance and accessibility scores with and without the plugin.
- Admin cost: slow pages or heavy queries with 5,000 conversations and 500 ADR cases seeded via WP-CLI.
- Caching: test with a page-cache plugin (e.g. WP Super Cache or LiteSpeed Cache). Nonces must not break for cached visitors, and the chat must still work.
- Test alongside a persistent object cache (Redis) if you can run one; the rate-limit and cache-version paths depend on it.
- Conflicts: activate with WooCommerce, Contact Form 7, Elementor and a security plugin (e.g. Wordfence). Look for JS or CSS clashes and z-index problems with theme headers or cookie banners.

## 6. Accessibility

Run axe-core (via `@axe-core/playwright`) on the open widget, the forms and the admin pages. Test keyboard-only use:
- tab order and the focus trap inside the widget;
- visible focus;
- Escape closes, and focus returns to the launcher.

Check screen-reader labels (`aria-*`, live regions for new messages) and zoom to 200%.

## 7. Automated suites in the repo

Run what the repository already provides and report the results verbatim. How to run each is in `tests/README.md`:
- `php tests/unit.php`
- `node --test tests/*.cjs`
- `php tests/integration.php`, on a disposable site with `SSC_TEST_SITE`
- `python tests/http-smoke.py`

Add nothing to the repo. If a suite fails, investigate the cause and report it.

## 8. The report

Write the report in **Persian** (technical terms, code and paths may stay in English), as a single Markdown file `AUDIT-REPORT-<YYYY-MM-DD>.md` in your scratch directory. Keep screenshots in a `screenshots/` folder next to it and link them relatively. Use this structure:

1. **Executive summary:**
   - an overall verdict: ready for sale / ready with fixes / not ready;
   - the top 5 risks;
   - a count of findings by severity.
2. **Environment:** the matrix above with exact versions, and what could not be set up.
3. **Findings table:** ID, severity, area, title, and status (confirmed / needs confirmation). Severity scale:
   - **Critical:** data loss, security hole, fatal error, the plugin unusable.
   - **High:** a main feature broken or wrong for many users.
   - **Medium:** a feature partly broken, or a significant UX or accessibility problem.
   - **Low:** cosmetic issues, wording, minor inconsistencies.
   - **Info:** suggestions.
4. **Finding details**, one section per finding:
   - where it is (screen, URL, or `file:line`);
   - steps to reproduce;
   - expected vs. actual result;
   - evidence (screenshot, log or request);
   - why it matters;
   - a concrete suggested fix.
5. **What works:** a checklist of the tested features that passed, each with a one-line note of how it was verified.
6. **Not tested:** each item with the reason.
7. **Security summary:** a table of each check (access control, nonces, XSS, SSRF, SQL injection, secrets, privacy, uploads) with pass/fail and evidence.
8. **Performance and compatibility results:** numbers, not adjectives.
9. **Accessibility results:** axe violations grouped by rule, and the keyboard test results.
10. **Translation issues:** untranslated strings and wording suggestions.
11. **Recommendations:** fixes ordered by impact/effort, then features that would help sales. Keep them apart from the defects.
12. **Appendix:** automated suite outputs, the Plugin Check summary, and the key debug.log excerpts.

Before finishing, re-read the report:
- Remove any claim without evidence.
- Make sure every finding can be reproduced from its steps.
- Check that the counts in the summary match the table.
