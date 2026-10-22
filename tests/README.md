# Running the audit checks

Requirements: PHP 7.4+ with mbstring/OpenSSL, Node.js 18+, Python 3 for HTTP smoke checks. Local verification used PHP 8.5.1 and WordPress 7.1 with SQLite Database Integration. The MySQL GitHub Actions job is configured but has not been executed in this local audit.

```text
php tests/unit.php
node --test tests/frontend.test.cjs tests/admin.test.cjs
```

`php tests/unit.php` needs no WordPress, but it does need the `mbstring` and `openssl` extensions to be loaded in the CLI. A bare `php.ini` (a fresh Windows download, a minimal container) stops with "Call to undefined function mb_substr()"; that is the environment, not the plugin. Load them for one run with `php -d extension=mbstring -d extension=openssl tests/unit.php`.

For integration checks, install WordPress in a **disposable database and directory**, link this repository into `wp-content/plugins/nexachat-ai`, and activate it. Set `define('SSC_TEST_SITE', true);` in that installation's `wp-config.php`. Set the `SSC_WP_TEST_ROOT` environment variable to the WordPress directory and run `php tests/integration.php`. The script refuses installations without the marker, creates synthetic records/users, changes plugin settings, and mocks outgoing HTTP/mail. Do not run it on a production copy containing sensitive records. `tests/integration.php` is the entry point for every check that needs WordPress. It requires these files, none of which is meant to be run on its own:

| File | What it checks |
|---|---|
| `live.php` | Live chat: hand-over to a person, messenger operators, the inbox |
| `woo.php` | Catalogue, order tracking and coupons (reported as SKIP without WooCommerce) |
| `sitesync.php` | "Learn from my website": indexing, exclusions, all-or-nothing replacement |
| `memory.php` | Server-side conversation memory, summaries, retention |
| `notification-queue.php` | The persistent notification queue: leases, retries, back-off |
| `transfer.php` | Export and import: round trip, hostile files, undo |
| `screens.php` | The admin screens render and carry what they should |
| `consult.php` | The consultation request form and its offer in the chat |
| `audit.php`, `audit2.php`, `audit3.php`, `audit4.php`, `audit5.php` | The regression checks of the external reviews of 1.5.0, 1.7.0, 1.7.1, 1.7.2 and 1.7.3; each block names the finding it closes |

`tests/http-smoke.py` is the only separate script; it is described below.

HTTP and mail are mocked, but a few checks resolve real host names (for example `api.openai.com`) to decide whether an address is public, so a sandbox with no DNS fails them for that reason alone; give the test host a working resolver or hosts-file entries. With WooCommerce active on the test site the catalogue, order and coupon checks run too; without it they are reported as SKIP. If using the Windows SQLite fixture, PHP also needs `-d extension=pdo_sqlite -d extension=sqlite3`.

The integration suite leaves the chatbot unpublished. For `python tests/http-smoke.py`, republish the disposable site, listen only on `127.0.0.1:8097`, set provider to `none`, QA mode to `bank_only`, disable business hours and Notifications, activate Pharma/Leads/History/Handoff, and add a synthetic product with ID `demo`. Keep the same environment variable and marker. This test deliberately creates synthetic reports over REST and legacy AJAX. It verifies the published frontend config, explicit validation, and offline SSE transport. It does not contact an AI provider or certify streamed provider output.

The browser audit additionally covered English/Persian rendering, the complete Persian ADR submission on a 390×844 viewport, fallback chat, and resetting the visible conversation with the Twenty Twenty-Five theme. No automatic browser suite or exhaustive theme compatibility claim is implied.

`tools/build-translations.py` compiles the maintained Persian JSON catalog to PO/MO. `tools/build-release.py` creates the installable ZIP and SHA-256 from a file allowlist, excluding tests, local WordPress data, and credentials.
