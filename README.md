<div align="center">

# NexaChatAI

**A professional AI assistant for WordPress — Persian/RTL-first, with full LTR support.**

[![CI](https://github.com/sanyzrn/DbsChatBot/actions/workflows/ci.yml/badge.svg)](https://github.com/sanyzrn/DbsChatBot/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![Requires PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](composer.json)
[![Requires WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b.svg)](readme.txt)

[فارسی](#فارسی) · [Features](#features) · [Installation](#installation) · [Architecture](#architecture) · [Development](#development)

</div>

---

Install it, run the five-step setup wizard, and publish an assistant that actually
knows your business. NexaChatAI connects to **your own** AI provider account — there
is no middleman, no per-message fee, and no data routed through a third-party service.

**Simple by default, powerful by choice.** Advanced capabilities ship as modules that
stay switched off until you enable them.

## Features

### Core engine

| | |
|---|---|
| **Setup wizard** | Five resumable steps with auto-saved progress. The widget stays invisible to visitors until you explicitly publish it — enforced server-side, not just hidden in the UI. |
| **Multi-provider AI** | OpenAI, Gemini, Claude, OpenRouter, any OpenAI-compatible endpoint, or a signed webhook. Real connection tests that perform an actual generation and map failures to clear causes. |
| **Business knowledge** | Structured identity and knowledge entries, plus document import (URL, `.txt`, `.md`, `.csv`, `.json`) chunked for offline retrieval. |
| **Streaming replies** | Server-Sent Events for OpenAI-compatible providers, with automatic fallback when the server or provider cannot stream. |
| **Security** | API keys encrypted at rest (AES-256-CBC, encrypt-then-MAC), SSRF-guarded outbound requests, per-bucket rate limits, honeypot, payload caps. |
| **Privacy** | Server-side transcript logging is opt-in. Configurable retention. Suggested privacy-policy text contributed to WordPress core's privacy tool. |
| **Placement** | Floating widget, `[ssc_chatbot]` shortcode, native Gutenberg block, or Elementor widget. |
| **Targeting** | Show or hide by page path, device, and login state. Business hours with an offline message. |

### Optional modules

Voice input/output · Proactive invite · Notifications (Bale / Telegram / email) ·
Human handoff · Analytics · CSAT survey · FAQ answer bank · Consultation forms ·
Pharmaceutical ADR reporting

### Pharmaceutical extension

An independent layer for pharmacovigilance: a structured adverse-reaction form,
capability-gated case records with an append-only audit trail, per-record privacy
actions (export, anonymize, purge), and two selectable answer policies
(approved-company-content-only by default, or general educational).

> **Note.** A language-model prompt is not a clinical validation system, and neither
> answer policy authorizes diagnosis, prescribing, or dose changes. Review real
> answers with your medical team before production use.

## Installation

1. Upload the plugin folder to `wp-content/plugins/`, or install the ZIP from
   **Plugins → Add New → Upload Plugin**.
2. Activate it — the setup wizard opens automatically.
3. Complete the five steps and press **Publish**.
4. Place the assistant using the floating widget, the `[ssc_chatbot]` shortcode,
   the Gutenberg block, or the Elementor widget.

**Requirements:** WordPress 5.6+, PHP 7.4+, with the `mbstring` and `openssl`
extensions. An AI provider API key is optional — offline modes (FAQ bank only, or
no AI engine) work without sending anything outside your server.

## Architecture

Three layers in one plugin, each independently gated:

```
Layer A — Core Engine     chat, identity, knowledge, providers, appearance, security
Layer B — Optional        modular capabilities, disabled by default
Layer C — Industry        independent extensions (pharmaceutical ADR reporting)
```

```
includes/
  core/          engine, REST + AJAX transports, streaming, settings, schema, HTTP
  core/providers/ one adapter per AI provider (pure request/parse primitives)
  admin/         admin controllers and view templates
  modules/       optional capabilities, each self-registering
assets/          vanilla JS and CSS — no build step, no runtime dependencies
blocks/          Gutenberg block (server-rendered)
widgets/         Elementor widget
languages/       translations (Persian included)
```

Provider adapters implement pure `request_parts()` and `extract_text()` primitives,
so payload shapes are unit-testable without any network access.

## Development

```bash
composer install          # dev tooling only — the plugin has no runtime dependencies
composer test             # PHP behaviour checks + frontend tests
vendor/bin/phpcs          # WordPress Coding Standards (must be clean)
vendor/bin/phpcbf         # auto-fix formatting
```

Build a release ZIP:

```bash
python tools/build-release.py && python tools/verify-release.py
```

`verify-release.py` re-derives what a correct package looks like rather than trusting
the build script, so it will fail the build if tests, tooling, internal notes, or
anything credential-shaped ever reach the archive.

### Tests

| Suite | Command | Needs |
|---|---|---|
| PHP behaviour | `php tests/unit.php` | nothing |
| Frontend + admin | `node --test tests/frontend.test.cjs tests/admin.test.cjs` | Node 18+ |
| WordPress integration | `php tests/integration.php` | a WordPress install + MySQL |

CI runs every suite on PHP 7.4 through 8.5, plus a packaging check.

## Extending

```php
// Replace the whole reply pipeline.
add_filter( 'ssc_pre_reply', function ( $reply, $message, $product, $engine ) {
    return $reply;
}, 10, 4 );

// Register your own provider adapter (must extend SSC_Provider).
add_filter( 'ssc_providers', function ( $providers ) {
    $providers[] = new My_Provider();
    return $providers;
} );

// Require a nonce on public chat endpoints (disables page-cache compatibility).
add_filter( 'ssc_enforce_rest_nonce', '__return_true' );
```

Other hooks: `ssc_frontend_config`, `ssc_prompt_extra`, `ssc_ai_cache_ttl`,
`ssc_ip_header`, `ssc_trusted_proxy_headers`, `ssc_submit_rate_limit`,
`ssc_http_timeout`, `ssc_pharma_capability`, `ssc_adr_case_purged`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Bundled fonts (Vazirmatn, Inter, Roboto) ship under the SIL Open Font License —
see [`assets/fonts/LICENSE.txt`](assets/fonts/LICENSE.txt).

---

<a id="فارسی"></a>

<div dir="rtl">

## فارسی

**نکسا‌چت‌ای‌آی** یک دستیار هوش مصنوعی حرفه‌ای برای وردپرس است که از ابتدا برای
فارسی و راست‌به‌چپ طراحی شده و از چپ‌به‌راست هم کامل پشتیبانی می‌کند.

افزونه را نصب کنید، جادوگر پنج‌مرحله‌ای راه‌اندازی را کامل کنید و دستیاری منتشر
کنید که واقعاً کسب‌وکار شما را می‌شناسد. اتصال به حساب **خودتان** نزد سرویس هوش
مصنوعی انجام می‌شود؛ واسطه‌ای در میان نیست، هزینهٔ پیامی وجود ندارد و داده‌ای از
مسیر سرویس ثالث عبور نمی‌کند.

### قابلیت‌ها

- **جادوگر راه‌اندازی:** پنج مرحله با ذخیرهٔ خودکار. تا وقتی خودتان «انتشار» را
  نزنید، ویجت برای بازدیدکننده نامرئی می‌ماند و این محدودیت در سمت سرور اعمال
  می‌شود، نه فقط در ظاهر.
- **چند سرویس هوش مصنوعی:** OpenAI، Gemini، Claude، OpenRouter، هر سرویس سازگار با
  OpenAI، یا وبهوک امضاشده. آزمون اتصال واقعی انجام می‌شود و خطاها با علت روشن
  گزارش می‌شوند.
- **دانش کسب‌وکار:** هویت و دانش ساختاریافته، به‌همراه درون‌ریزی سند از نشانی وب و
  فایل‌های `.txt`، `.md`، `.csv` و `.json`.
- **پاسخ جریانی:** نمایش تدریجی پاسخ برای سرویس‌های سازگار با OpenAI، با بازگشت
  خودکار به حالت عادی هرجا سرور یا سرویس پشتیبانی نکند.
- **امنیت:** کلیدهای API رمزنگاری‌شده ذخیره می‌شوند، درخواست‌های خروجی در برابر
  SSRF محافظت شده‌اند، و محدودیت نرخ، هانی‌پات و سقف حجم درخواست اعمال می‌شود.
- **حریم خصوصی:** ذخیرهٔ متن گفتگو روی سرور اختیاری و به‌صورت پیش‌فرض خاموش است و
  مدت نگهداری قابل تنظیم است.
- **محل نمایش:** ویجت شناور، کد کوتاه `[ssc_chatbot]`، بلوک گوتنبرگ، یا ویجت
  المنتور.
- **هدف‌گذاری نمایش:** بر اساس مسیر صفحه، نوع دستگاه و وضعیت ورود کاربر، به‌همراه
  ساعات کاری و پیام خارج از ساعت کاری.

### ماژول‌های اختیاری

گفتار (ورودی و خروجی صوتی) · دعوت هوشمند · اعلان‌ها (بله، تلگرام، ایمیل) ·
ارجاع به کارشناس انسانی · تحلیل‌ها · نظرسنجی رضایت · بانک پرسش‌های متداول ·
فرم‌های مشاوره · گزارش عوارض دارویی

### افزونهٔ دارویی

لایه‌ای مستقل برای فارماکوویژیلانس: فرم ساختاریافتهٔ گزارش عارضه، پروندهٔ
دسترسی‌کنترل‌شده با ردّ ممیزی فقط-افزودنی، عملیات حریم خصوصی روی هر پرونده
(برون‌ریزی، ناشناس‌سازی، حذف کامل) و دو سیاست پاسخ‌گویی: «فقط محتوای تأییدشده»
(پیش‌فرض) یا «آموزش عمومی».

> **توجه:** دستور دادن به یک مدل زبانی جایگزین سامانهٔ اعتبارسنجی بالینی نیست و
> هیچ‌کدام از دو سیاست، مجوز تشخیص، تجویز یا تغییر دوز نمی‌دهد. پیش از استفادهٔ
> عملیاتی، پاسخ‌های واقعی را با تیم پزشکی شرکت ارزیابی کنید.

### نصب

۱. پوشهٔ افزونه را در `wp-content/plugins/` قرار دهید، یا فایل ZIP را از مسیر
**افزونه‌ها ← افزودن ← بارگذاری افزونه** نصب کنید.
۲. افزونه را فعال کنید؛ جادوگر راه‌اندازی خودکار باز می‌شود.
۳. پنج مرحله را کامل و سپس «انتشار» را بزنید.
۴. دستیار را با ویجت شناور، کد کوتاه، بلوک گوتنبرگ یا ویجت المنتور روی سایت قرار
دهید.

**پیش‌نیازها:** وردپرس ۵٫۶ به بالا، PHP نسخهٔ ۷٫۴ به بالا به‌همراه افزونه‌های
`mbstring` و `openssl`. داشتن کلید API اختیاری است؛ حالت‌های آفلاین (فقط بانک
پرسش‌های متداول، یا بدون موتور هوش مصنوعی) بدون ارسال هیچ داده‌ای به بیرون از
سرور شما کار می‌کنند.

راهنمای راه‌اندازی نسخهٔ دارویی: [`docs/pharma-setup-fa.md`](docs/pharma-setup-fa.md)

### پروانه

GPL-2.0-or-later — فایل [LICENSE](LICENSE).

</div>
