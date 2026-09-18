# Running the audit checks

Requirements: PHP 7.4+ with mbstring/OpenSSL, Node.js 18+, Python 3 for HTTP smoke checks. Local verification used PHP 8.5.1 and WordPress 7.1 with SQLite Database Integration. The MySQL GitHub Actions job is configured but has not been executed in this local audit.

```text
php tests/unit.php
node --test tests/frontend.test.cjs
```

For integration checks, install WordPress in a **disposable database and directory**, link this repository into `wp-content/plugins/smart-support-chatbot`, and activate it. Set `define('SSC_TEST_SITE', true);` in that installation's `wp-config.php`. Set the `SSC_WP_TEST_ROOT` environment variable to the WordPress directory and run `php tests/integration.php`. The script refuses installations without the marker, creates synthetic records/users, changes plugin settings, and mocks outgoing HTTP/mail. Do not run it on a production copy containing sensitive records. If using the Windows SQLite fixture, PHP also needs `-d extension=pdo_sqlite -d extension=sqlite3`.

The integration suite leaves the chatbot unpublished. For `python tests/http-smoke.py`, republish the disposable site, listen only on `127.0.0.1:8097`, set provider to `none`, QA mode to `bank_only`, disable business hours and Notifications, activate Pharma/Leads/History/Handoff, and add a synthetic product with ID `demo`. Keep the same environment variable and marker. This test deliberately creates synthetic reports over REST and legacy AJAX. It verifies the published frontend config, explicit validation, and offline SSE transport. It does not contact an AI provider or certify streamed provider output.

The browser audit additionally covered English/Persian rendering, the complete Persian ADR submission on a 390×844 viewport, fallback chat, and resetting the visible conversation with the Twenty Twenty-Five theme. No automatic browser suite or exhaustive theme compatibility claim is implied.

`tools/build-translations.py` compiles the maintained Persian JSON catalog to PO/MO. `tools/build-release.py` creates the installable ZIP and SHA-256 from a file allowlist, excluding tests, local WordPress data, and credentials.
