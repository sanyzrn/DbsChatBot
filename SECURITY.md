# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.0.x   | Yes |
| 0.6.x beta and earlier | No — please upgrade |

## Reporting a vulnerability

Please report security issues **privately**, not through a public issue.

- Open a [private security advisory](https://github.com/sanyzrn/DbsChatBot/security/advisories/new) on GitHub, or
- email **kaspersky.sany@gmail.com** with `NexaChatAI security` in the subject.

Helpful details: affected version, WordPress and PHP versions, reproduction steps,
and what an attacker gains. A proof of concept is welcome but not required.

You can expect an acknowledgement within a few days and an assessment shortly
after. Please give us a reasonable window to ship a fix before disclosing
publicly; we will credit you in the changelog unless you prefer otherwise.

Please do **not** test against sites you do not own.

## What the plugin protects

Understanding these boundaries helps judge whether a finding is a real issue.

**Credentials.** Provider API keys and messenger tokens are encrypted at rest with
AES-256-CBC using encrypt-then-MAC, keyed from the site's `AUTH_KEY` and
`LOGGED_IN_KEY` salts. If encryption is unavailable the secret is refused rather
than stored in plaintext. Keys are never included in the frontend config payload,
never logged, and never returned by any endpoint.

**Outbound requests.** Every provider, webhook and document-import request passes
through an SSRF guard that resolves DNS and rejects private, loopback and
link-local targets; HTTPS is required whenever credentials travel; redirects are
refused so a redirect target cannot receive a key; and response size is bounded.
The streaming transport pins the validated IP so cURL cannot perform a second
lookup.

**Public endpoints.** Public chat routes are intentionally nonce-less so
full-page caches can serve them. They are protected instead by server-side rate
limits per bucket and identity, request-size caps, module gates, a honeypot, and
a server-enforced "is the assistant published" check. Sites that prefer strict
CSRF enforcement can enable it:

```php
add_filter( 'ssc_enforce_rest_nonce', '__return_true' );
```

**Admin actions.** Every state-changing admin action requires both
`manage_options` (or the dedicated `ssc_pharma_cases` capability for case
records) and a nonce.

**Output.** Model output is escaped before rendering; only `**bold**`, `http(s)`
links and line breaks survive as markup. CSV exports neutralize formula-injection
prefixes.

**Data at rest.** Deleting the plugin preserves business data by default;
destructive removal happens only if an administrator opted in beforehand.

## Known limitations

These are documented deliberately rather than presented as fixed.

- **DNS rebinding (TOCTOU).** The SSRF guard resolves DNS once; WordPress resolves
  again when the request is issued. A hostname could in principle rebind between
  the two. WordPress core applies a second layer of protection, so practical risk
  is low, but the window is real.
- **Anonymization is not redaction.** Removing direct identifiers from a safety
  report does not anonymize free-text clinical narratives or audit notes.
- **Provider trust.** Messages are sent to the AI provider the site owner
  configures. That provider's handling of the data is outside this plugin's
  control.
- **Mail delivery.** Acceptance by a mail server is not proof of inbox delivery.
