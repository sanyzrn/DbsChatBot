<div align="center">

# NexaChatAI

**A professional AI assistant for WordPress — Persian/RTL-first, with full LTR support.**

[![CI](https://github.com/sanyzrn/DbsChatBot/actions/workflows/ci.yml/badge.svg)](https://github.com/sanyzrn/DbsChatBot/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/sanyzrn/DbsChatBot)](https://github.com/sanyzrn/DbsChatBot/releases/latest)
[![License: GPL v2](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![Requires PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](composer.json)
[![Requires WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b.svg)](readme.txt)

[فارسی](#فارسی) · [Download](#download--installation) · [Features](#features) · [Architecture](#architecture) · [Development](#development) · [Releasing](#releasing)

</div>

---

Install it, run the five-step setup wizard, and publish an assistant that actually
knows your business. NexaChatAI connects to **your own** AI provider account — there
is no middleman, no per-message fee, and no data routed through a third-party service.

**Simple by default, powerful by choice.** Advanced capabilities ship as modules that
stay switched off until you enable them.

## Download & installation

1. Open the [latest release](https://github.com/sanyzrn/DbsChatBot/releases/latest) and
   download **`nexachat-ai-<version>.zip`** (optionally check it against the `.sha256`
   file next to it).
   > The **"Source code (zip / tar.gz)"** archives that GitHub adds to every release are
   > the raw repository, not an installable plugin. Always use `nexachat-ai-<version>.zip`.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, then **Activate**.
3. The setup wizard opens automatically. Complete the five steps and press **Publish** —
   until then the assistant stays invisible to visitors.
4. Place it with the floating widget (automatic), the `[ssc_chatbot]` shortcode, the
   Gutenberg block, or the Elementor widget.

**Requirements:** WordPress 5.6+, PHP 7.4+ with `mbstring` and `openssl`. An AI provider
API key is optional — offline modes (FAQ bank only, or no AI engine) send nothing
outside your server.

### Upgrading from 1.0.x

Since 1.1 the plugin lives in the `nexachat-ai` folder (it used to be
`smart-support-chatbot`). Upload and activate the new ZIP: the old copy is deactivated
automatically, all settings and data are kept, and its "remove data on uninstall"
policy is switched off so deleting the old copy afterwards cannot erase shared data.
Back up first and clear page/CDN caches after upgrading.

## Features

### Core engine

| | |
|---|---|
| **Setup wizard** | Five resumable steps with auto-saved progress, a real connection test, a business-identity test and a private preview. Invisible to visitors until published — enforced server-side. |
| **Multi-provider AI** | OpenAI, Gemini, Claude, OpenRouter, any OpenAI-compatible endpoint, or a signed webhook. Real connection tests map failures to clear causes. |
| **Answer scope** | *Only from my knowledge*, *my business and its field* (default), or *any question*, plus an optional reply for unrelated questions. Organization facts always come only from your knowledge. |
| **Web search** | Optional, through the provider's own tool (OpenAI Responses, Claude, Gemini Google Search, OpenRouter), with a domain allow-list and cited pages under the answer. |
| **Business knowledge** | Profile, knowledge entries, product catalog and document import (URL, `.txt`, `.md`, `.csv`, `.json`). Keyword retrieval plus optional semantic retrieval (embeddings), with source citations under answers. |
| **Streaming replies** | Server-Sent Events for OpenAI, Claude, Gemini and compatible providers; an interrupted stream keeps what was already shown. |
| **Conversation memory** | Kept server-side under a random conversation id — the browser never supplies the model's context. |
| **Widget** | Multi-line composer, safe Markdown answers, per-message text direction, copy / feedback, tap-to-call, main menu, full-screen on phones, light/dark themes. |
| **Security** | API keys encrypted at rest (AES-256-CBC, encrypt-then-MAC), SSRF-guarded requests, rate limits (object-cache aware), honeypot, payload caps. |
| **Privacy** | Server transcripts opt-in, configurable retention, visitor IPs anonymized by default, suggested privacy-policy text. |
| **Targeting** | Page path, device and login rules; business hours with a live (cache-safe) online/offline status. |
| **Admin** | Complete Persian translation with RTL layout and Solar Hijri dates; tabbed settings; the Appearance preview is the real widget. |

### Optional modules

Voice input/output · Proactive invitation (delay / scroll / exit intent, per-page
messages) · Notifications (Bale / Telegram / email) · Human handoff · Analytics ·
CSAT survey · FAQ answer bank · Consultation forms (custom fields, CSV export) ·
Pharmaceutical ADR reporting (configurable form: short/standard presets, own questions)

Added in the 2.0 development line, also off by default:

| Module | What it does |
|---|---|
| Live chat | Operator inbox: take over from the bot, queue and assignment, online/offline, canned replies, AI summary; operators can answer from Bale/Telegram |
| Messenger bot | Customers chat with the assistant inside Bale or Telegram |
| WooCommerce sales assistant | Product cards (price, stock, add to cart), order tracking, comparison, smart coupon, SMS cart reminder |
| SMS gateway | Kavenegar, Melipayamak, IPPanel, SMS.ir |
| Learn from my website | Pages, posts and products become knowledge automatically and stay in sync |

The knowledge base also imports PDF and Word files, and can draft FAQs and a
persona with AI for review. The wizard offers six industry templates.

### Pharmaceutical extension

An independent layer for pharmacovigilance: a structured adverse-reaction form,
emergency guidance, capability-gated case records with an append-only audit trail,
per-record privacy actions (export, remove identifiers, purge), and two answer
policies (approved company content only — default — or general educational). While it
is active, unrelated topics are always declined and web search is off.

Setup guide (Persian): [`docs/PHARMA-SETUP-fa.md`](docs/PHARMA-SETUP-fa.md)

> **Note.** A language-model prompt is not a clinical validation system, and neither
> answer policy authorizes diagnosis, prescribing, or dose changes. Review real
> answers with your medical team before production use.

## Architecture

Three layers in one plugin, each independently gated:

```
Layer A — Core Engine     chat, identity, knowledge, providers, appearance, security
Layer B — Optional        modular capabilities, disabled by default
Layer C — Industry        independent extensions (pharmaceutical ADR reporting)
```

```
nexachat-ai.php  thin loader (hands over from the pre-1.1 folder safely)
includes/
  bootstrap.php  constants, autoloader, activation hooks
  core/          engine, REST + AJAX transports, streaming, settings, schema, HTTP,
                 conversation memory, embeddings, availability, dates
  core/providers/ one adapter per AI provider (pure request/parse primitives)
  admin/         admin controllers and view templates
  modules/       optional capabilities, each self-registering
assets/          vanilla JS and CSS — no build step, no runtime dependencies
blocks/          Gutenberg block (server-rendered)
widgets/         Elementor widget
languages/       translations (complete Persian catalog)
```

Provider adapters implement pure `request_parts()`, `extract_text()`,
`parse_stream_event()` and `extract_sources()` primitives, so payload shapes are
unit-testable without any network access.

## Development

```bash
composer install          # dev tooling only — the plugin has no runtime dependencies
composer test             # PHP behaviour checks + frontend tests
vendor/bin/phpcs          # WordPress Coding Standards (must be clean)
vendor/bin/phpcbf         # auto-fix formatting
```

### Tests

| Suite | Command | Needs |
|---|---|---|
| PHP behaviour | `php tests/unit.php` | nothing |
| Frontend + admin | `node --test tests/frontend.test.cjs tests/admin.test.cjs` | Node 18+ |
| WordPress integration | `php tests/integration.php` | a disposable WordPress + MySQL (see [`tests/README.md`](tests/README.md)) |

CI runs every suite on PHP 7.4 through 8.5, plus a packaging check.

### Translations

`languages/fa_IR.json` is the source of the Persian catalog. After changing strings:

```bash
python tools/make-pot.py            # refresh languages/nexachat-ai.pot
python tools/build-translations.py  # compile fa_IR.json -> .po/.mo
```

## Releasing

Releases are built by GitHub Actions — never upload a ZIP by hand.

1. Bump `SSC_CHATBOT_VERSION` and the `Version:` header in `nexachat-ai.php`, and
   `Stable tag` in `readme.txt`.
2. Add a `= x.y.z =` section under `== Changelog ==` in `readme.txt` (it becomes the
   release notes).
3. Merge to `main`, then either push a tag (`git tag vX.Y.Z && git push origin vX.Y.Z`)
   or run **Actions → Release → Run workflow** on `main` with the tag `vX.Y.Z` — the
   workflow then creates the tag on the commit it built.

The **Release** workflow checks that the tag matches the plugin version, builds the ZIP
from an allowlist, verifies it contains no development files or credentials, and
publishes it with its SHA-256 checksum. If a release for that tag was already created
in the GitHub UI, the workflow attaches the ZIP to it instead of failing. It can also be
re-run for an existing tag from **Actions → Release → Run workflow** to repair it.

To build locally: `python tools/build-release.py && python tools/verify-release.py`
(output in `dist/`).

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
`ssc_http_timeout`, `ssc_pharma_capability`, `ssc_adr_case_purged`,
`ssc_emergency_notice`, `ssc_emergency_terms`, `ssc_conversation_ttl`,
`ssc_embedding_model`, `ssc_kb_semantic_threshold`, `ssc_use_jalali`,
`ssc_claude_web_search_tool`, `ssc_openai_web_search_tool`.

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
مصنوعی انجام می‌شود. واسطه‌ای در میان نیست، هزینهٔ پیامی وجود ندارد و داده‌ای از
مسیر سرویس ثالث عبور نمی‌کند.

### دریافت و نصب

۱. از [آخرین انتشار](https://github.com/sanyzrn/DbsChatBot/releases/latest) فایل
**`nexachat-ai-<نسخه>.zip`** را دریافت کنید.

> فایل‌های **«Source code»** که GitHub به هر انتشار اضافه می‌کند، کد خام مخزن‌اند و
> قابل نصب در وردپرس نیستند. همیشه فایل `nexachat-ai-<نسخه>.zip` را نصب کنید.

۲. در وردپرس: **افزونه‌ها ← افزودن ← بارگذاری افزونه**، فایل را انتخاب و سپس فعال کنید.

۳. جادوگر راه‌اندازی خودکار باز می‌شود. پنج مرحله را کامل کنید و «انتشار» را بزنید.
تا پیش از آن دستیار برای بازدیدکنندگان نامرئی است.

۴. دستیار را با ویجت شناور (خودکار)، کد کوتاه `[ssc_chatbot]`، بلوک گوتنبرگ یا ویجت
المنتور روی سایت قرار دهید.

**ارتقا از ۱٫۰:** از نسخهٔ ۱٫۱ پوشهٔ افزونه `nexachat-ai` است. با فعال کردن نسخهٔ
جدید، نسخهٔ قدیمی خودکار غیرفعال می‌شود و همهٔ داده‌ها حفظ می‌شوند. سپس نسخهٔ قدیمی
را حذف کنید. پیش از ارتقا پشتیبان بگیرید و بعد از آن کش صفحه/CDN را پاک کنید.

**پیش‌نیازها:** وردپرس ۵٫۶ به بالا و PHP نسخهٔ ۷٫۴ به بالا به‌همراه افزونه‌های
`mbstring` و `openssl`. کلید API اختیاری است. حالت‌های آفلاین (فقط بانک پرسش‌وپاسخ،
یا بدون موتور هوش مصنوعی) هیچ داده‌ای به بیرون از سرور شما نمی‌فرستند.

### قابلیت‌ها

- **جادوگر راه‌اندازی:** پنج مرحله با ذخیرهٔ خودکار، آزمون واقعی اتصال، آزمون هویت
  کسب‌وکار و پیش‌نمایش خصوصی. تا «انتشار» نزنید، ویجت در سمت سرور غیرفعال است.
- **چند سرویس هوش مصنوعی:** OpenAI، Gemini، Claude، OpenRouter، هر سرویس سازگار با
  OpenAI، یا وب‌هوک امضاشده.
- **دامنهٔ پاسخ:** «فقط از دانش من»، «کسب‌وکار من و حوزهٔ آن» (پیش‌فرض) یا «هر
  پرسشی»، به‌همراه پاسخ دلخواه برای پرسش‌های نامرتبط. اطلاعات سازمان همیشه فقط از
  دانش شما گرفته می‌شود.
- **جست‌وجوی اینترنتی (اختیاری):** با ابزار خود سرویس‌دهنده، امکان محدود کردن به
  دامنه‌های مشخص و نمایش صفحه‌های منبع زیر پاسخ.
- **دانش کسب‌وکار:** مشخصات، موارد دانش، کاتالوگ محصولات و ورود سند. جست‌وجوی
  کلیدواژه‌ای به‌همراه جست‌وجوی معنایی اختیاری و نمایش منابع زیر پاسخ.
- **پاسخ جریانی:** برای OpenAI، Claude، Gemini و سرویس‌های سازگار.
- **حافظهٔ گفتگو در سرور:** مرورگر هرگز زمینهٔ گفتگوی مدل را تعیین نمی‌کند.
- **ویجت:** ورودی چندخطی، پاسخ قالب‌بندی‌شده، جهت متن جداگانه برای هر پیام، کپی و
  بازخورد، دکمهٔ تماس، منوی اصلی، تمام‌صفحه در موبایل، تم روشن و تیره.
- **امنیت و حریم خصوصی:** رمزنگاری کلیدها، محافظت SSRF، محدودیت نرخ، ناشناس‌سازی IP
  به‌صورت پیش‌فرض و مدت نگهداری قابل تنظیم.
- **هدف‌گیری نمایش:** مسیر صفحه، دستگاه، وضعیت ورود کاربر و ساعات کاری با وضعیت زنده.
- **پنل مدیریت فارسی:** ترجمهٔ کامل، راست‌چین، تاریخ شمسی، تنظیمات تب‌بندی‌شده و
  پیش‌نمایش ظاهر با همان ویجت واقعی.

### ماژول‌های اختیاری

گفتار · دعوت فعال (تأخیر، پیمایش، قصد خروج، پیام ویژهٔ هر صفحه) · اعلان‌ها (بله،
تلگرام، ایمیل) · ارجاع به کارشناس · تحلیل‌ها · نظرسنجی رضایت · بانک پرسش‌وپاسخ ·
فرم‌های مشاوره (فیلد سفارشی، خروجی CSV) · گزارش عوارض دارویی (فرم قابل تنظیم: کوتاه/استاندارد، سؤال دلخواه)

ماژول‌های جدید نسخهٔ ۲ (همه پیش‌فرض خاموش):
- **گفتگوی زنده:** صندوق پیام اپراتور، تحویل گفتگو از ربات، صف و تخصیص، وضعیت آنلاین/آفلاین، پاسخ‌های آماده، خلاصهٔ هوشمند؛ پاسخ از بله/تلگرام
- **ربات پیام‌رسان:** گفتگوی مشتری با دستیار داخل بله یا تلگرام
- **دستیار فروش ووکامرس:** کارت محصول با قیمت و موجودی واقعی و افزودن به سبد، پیگیری سفارش، مقایسه، کد تخفیف هوشمند، یادآوری پیامکی سبد
- **درگاه پیامک:** کاوه‌نگار، ملی‌پیامک، آی‌پی‌پنل، sms.ir
- **یادگیری از سایت:** صفحه‌ها، نوشته‌ها و محصولات خودکار به دانش دستیار تبدیل و به‌روز می‌شوند

همچنین: ورود PDF و Word به پایگاه دانش، پیشنهاد پرسش‌های متداول و پرسونا با هوش مصنوعی، و شش قالب صنفی در جادوگر راه‌اندازی.

### افزونهٔ دارویی

لایه‌ای مستقل برای ایمنی دارو (فارماکوویژیلانس):
- فرم ساختاریافتهٔ گزارش عارضه و هشدار فوری در موارد اورژانسی
- پرونده‌های دسترسی‌کنترل‌شده با سابقهٔ ممیزی غیرقابل‌ویرایش
- عملیات حریم خصوصی روی هر پرونده
- دو سیاست پاسخ‌گویی

راهنمای کامل راه‌اندازی: [`docs/PHARMA-SETUP-fa.md`](docs/PHARMA-SETUP-fa.md)

> **توجه:** دستور دادن به یک مدل زبانی جایگزین سامانهٔ اعتبارسنجی بالینی نیست و
> هیچ‌کدام از دو سیاست، مجوز تشخیص، تجویز یا تغییر دوز نمی‌دهد. پیش از استفادهٔ
> عملیاتی، پاسخ‌های واقعی را با تیم پزشکی شرکت ارزیابی کنید.

### انتشار نسخهٔ جدید (برای توسعه‌دهنده)

۱. شمارهٔ نسخه را در `nexachat-ai.php` (هر دو جا) و `Stable tag` در `readme.txt`
بالا ببرید.

۲. بخش `= x.y.z =` را در changelog فایل `readme.txt` بنویسید. این متن یادداشت انتشار
می‌شود.

۳. تغییرات را در `main` ادغام کنید. سپس یا تگ `vX.Y.Z` را push کنید، یا از
**Actions ← Release ← Run workflow** روی `main` با تگ `vX.Y.Z` اجرا کنید. GitHub
Actions فایل ZIP را می‌سازد، بررسی و منتشر می‌کند.

ZIP را دستی بارگذاری نکنید. اگر انتشار را از صفحهٔ GitHub ساخته باشید، workflow فایل
ZIP را به همان انتشار اضافه می‌کند.

### پروانه

GPL-2.0-or-later — فایل [LICENSE](LICENSE).

</div>
