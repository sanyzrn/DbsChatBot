# گزارش ممیزی عمیق کد پروژه Smart Support Chatbot

**تاریخ بررسی:** ۱۴۰۵/۰۶/۰۶ (2026-08-28)  
**نسخه اعلام‌شده افزونه:** 4.0.0  
**Commit بررسی‌شده:** `a6122ac` روی شاخه `main`  
**دامنه بررسی:** کد PHP، JavaScript، دیتابیس، REST/AJAX، پنل مدیریت، Elementor/Gutenberg، حریم خصوصی، امنیت، کارایی، CI و فرایند انتشار

> این گزارش بر اساس بررسی مستقیم کد و اجرای ابزارها تهیه شده است، نه README. فایل‌های `PROJECT_AUDIT.md` و `REVIEW-REPORT-FA.md` قدیمی‌اند و فقط پس از استخراج مستقل یافته‌ها برای مقایسه بررسی شدند.

## ۱. جمع‌بندی مدیریتی

پروژه از نظر پایه‌های امنیتی معمول وردپرس وضعیت خوبی دارد: nonce و capability در عملیات مدیریتی، prepared query در ورودی‌های پویا، escape مناسب در بیشتر viewها، محدودیت نرخ اتمیک، جلوگیری از CSV Formula Injection، محدودیت طول پیام، و رمزگذاری نسبی secretها پیاده شده‌اند. همچنین بسیاری از اشکالات گزارش قدیمی واقعاً رفع شده‌اند.

با این حال، نسخه فعلی را برای انتشار عمومی یا استفاده حساس دارویی «آماده تولیدِ قابل اتکا» ارزیابی نمی‌کنم. مهم‌ترین دلایل:

1. رضایت حریم خصوصی فقط در مرورگر کنترل می‌شود و API آن را enforce یا ثبت نمی‌کند.
2. تشخیص گزارش عارضه جدی ناقص است؛ outcomeهایی مثل بستری یا فوت به هشدار فوری تبدیل نمی‌شوند.
3. اعلان‌های حساس دارویی fire-and-forget هستند و شکست آن‌ها ثبت، retry یا نمایش داده نمی‌شود.
4. مهاجرت schema فقط در `admin_init` انجام می‌شود؛ پس auto-update می‌تواند تا اولین ورود مدیر، فرانت را روی schema قدیمی اجرا کند.
5. `composer.lock` ناسازگار و شامل سه advisory امنیتی است؛ دو advisory شدت High دارند.
6. nonce اجباری برای endpoint عمومی، با page cache و مصرف‌کننده headless/mobile تعارض عملی دارد.
7. تست خودکار وجود ندارد؛ در نتیجه منطق حساس فرم، matching، migration و providerها regression-safe نیست.

### توزیع شدت یافته‌ها

| شدت | تعداد | توضیح |
|---|---:|---|
| بحرانی | 0 | موردی با exploit مستقیم و بدون پیش‌شرط که کل سایت را در اختیار بگیرد مشاهده نشد |
| بالا | 7 | حریم خصوصی، ایمنی ADR، دسترس‌پذیری سرویس، migration و supply chain |
| متوسط | 11 | باگ‌های داده/تنظیمات، SSRF، webhook، مقیاس‌پذیری و چندنمونه‌ای |
| پایین/بهبود | 8 | i18n، release hygiene، UX، portability و نگهداشت‌پذیری |

## ۲. روش بررسی و شواهد اجرایی

- تمام فایل‌های PHP/JS و ساختار hookها، routeها، ورودی‌ها، queryها، خروجی‌ها و فراخوانی‌های شبکه بررسی شدند.
- `php -l` روی همه فایل‌های PHP: **موفق**.
- `node --check` روی همه فایل‌های JavaScript: **موفق**.
- `composer validate --strict`: **ناموفق**؛ lock با manifest همگام نیست.
- `composer audit --locked`: **۳ advisory امنیتی**.
- PHPCS روی checkout ویندوز: ۳۱ خطای CRLF؛ سپس روی کپی موقت LF با همان ruleset: **موفق و بدون خطای دیگر**.
- تست واحد/یکپارچه/E2E: **هیچ تستی در مخزن وجود ندارد**.
- محیط واقعی WordPress + MySQL برای تست integration در مخزن فراهم نشده؛ بنابراین یافته‌های وابسته به runtime وردپرس با تحلیل مسیر کد تأیید شده‌اند، نه اجرای end-to-end.

## ۳. نقاط قوت تأییدشده

- عملیات مدیریتی به `manage_options` و nonce متکی‌اند (`class-smart-support-chatbot-admin.php:265-375`).
- ورودی‌های query پویا عمدتاً prepare شده‌اند و `orderby` allowlist دارد (`class-smart-support-chatbot-db.php:366-436`).
- CSV Formula Injection خنثی شده است (`class-smart-support-chatbot-admin.php:870-880`, `944-961`).
- rate limit از شمارنده اتمیک دیتابیس استفاده می‌کند (`class-smart-support-chatbot-db.php:628-650`).
- بازخورد هر پاسخ token امضاشده دارد و فقط یک بار ثبت می‌شود (`class-smart-support-chatbot-ajax.php:170-180`, `392-405`).
- تاریخچه ارسالی به AI از نظر نقش، تعداد و طول محدود می‌شود (`class-smart-support-chatbot-ajax.php:414-453`).
- خروجی پاسخ AI پیش از Markdown render ابتدا HTML-escape می‌شود (`assets/js/smart-support-chatbot.js:104-158`).
- کلید Gemini در header ارسال می‌شود، نه query string (`class-smart-support-chatbot-ajax.php:1135-1156`).
- Q&A bulk save تراکنشی است و `usage_count` را حفظ می‌کند (`class-smart-support-chatbot-db.php:1015-1105`).
- فایل CSV چندخطی با stream parser خوانده می‌شود (`class-smart-support-chatbot-admin.php:525-564`).

## ۴. یافته‌های با شدت بالا

### H-01 — دورزدن رضایت حریم خصوصی از API و نبود مدرک رضایت

**شواهد:**

- کنترل رضایت فقط در JavaScript است: `assets/js/smart-support-chatbot.js:805-813`.
- payload ارسالی اصلاً فیلد consent ندارد: `assets/js/smart-support-chatbot.js:817-833`.
- route REST و handler AJAX نیز consent دریافت نمی‌کنند: `class-smart-support-chatbot-rest.php:287-309` و `class-smart-support-chatbot-ajax.php:1394-1415`.
- سرویس `submit()` بدون بررسی تنظیم `consent_enabled` داده را ذخیره می‌کند: `class-smart-support-chatbot-ajax.php:1425-1534`.

**اثر:** هر کلاینت می‌تواند مستقیماً REST/AJAX را صدا بزند و بدون رضایت، نام، تلفن، شرح، IP و داده‌های سلامت را ثبت کند. حتی برای ارسال‌های UI نیز timestamp، نسخه متن رضایت یا منبع رضایت ذخیره نمی‌شود؛ بنابراین مدرک audit وجود ندارد.

**راهکار:** فیلد boolean رضایت را به هر دو transport اضافه کنید؛ وقتی `consent_enabled=yes` است، server-side آن را الزامی کنید. `consent_at`، hash/نسخه متن رضایت و policy URL را کنار submission ذخیره کنید. برای داده‌های ADR سیاست حقوقی جداگانه تعیین شود.

### H-02 — گزارش‌های «بستری» یا «فوت» ممکن است هشدار جدی نگیرند

**شواهد:** گزینه‌های `منجر به بستری شد` و `فوت` در آرایه `outcome` هستند (`class-smart-support-chatbot-settings.php:395-409`)، اما `is_serious_adr()` فقط `$row['severity']` را با فهرستی مقایسه می‌کند که این outcomeها را هم در خود دارد (`class-smart-support-chatbot-ajax.php:1604-1607`). داشبورد نیز فقط ستون `severity` را query می‌کند (`class-smart-support-chatbot-admin.php:216-223` و `class-smart-support-chatbot-db.php:950-966`).

**اثر:** گزارشی با severity «متوسط» و outcome «فوت» یا «منجر به بستری شد» هشدار فوری نمی‌گیرد و در شمارنده serious ADR هم نمی‌آید. برای محصول دارویی این نقص ایمنی و عملیاتی مهم است.

**راهکار:** معیار seriousness را مدل‌سازی کنید: severityهای جدی **یا** outcomeهای بستری/فوت/ناتوانی، به‌علاوه فیلدهای استاندارد seriousness. یک ستون boolean/indexed مثل `is_serious` هنگام ثبت محاسبه و تست شود.

### H-03 — شکست خاموش اعلان‌های حساس و نبود retry/audit trail

**شواهد:** پاسخ `wp_remote_post()` اعلان پیام‌رسان نادیده گرفته می‌شود (`class-smart-support-chatbot-ajax.php:1673-1696`) و نتیجه `wp_mail()` نیز بررسی نمی‌شود (`1703-1712`). ارسال‌ها synchronous و پس از insert انجام می‌شوند، اما وضعیت delivery در دیتابیس وجود ندارد.

**اثر:** token اشتباه، قطعی شبکه، محدودیت Telegram/Bale، mail misconfiguration یا timeout می‌تواند اعلان ADR جدی را بی‌صدا از بین ببرد. مدیر هیچ وضعیت، خطا یا امکان retry ندارد. هم‌زمان کاربر ممکن است تا ۸ ثانیه بیشتر منتظر بماند.

**راهکار:** notification outbox بسازید؛ submission را ذخیره و job را با Action Scheduler/WP-Cron صف‌بندی کنید. status، attempts، last_error و delivered_at را ثبت کنید؛ retry با backoff و هشدار admin/Site Health اضافه شود.

### H-04 — schema upgrade فقط پس از ورود مدیر اجرا می‌شود

**شواهد:** `maybe_upgrade` فقط روی `admin_init` ثبت شده است (`class-smart-support-chatbot.php:74-81`). در bootstrap عمومی فقط migration نام قدیمی اجرا می‌شود (`smart-support-chatbot.php:76-81`).

**اثر:** بعد از auto-update یا deploy فایل‌ها، اولین درخواست‌های frontend ممکن است کد جدید را روی جدول/ستون قدیمی اجرا کنند. این موضوع می‌تواند به خطای query، شکست chat/submit یا ثبت ناقص داده تا زمان ورود یک مدیر منجر شود.

**راهکار:** یک migration runner سبک و idempotent در `plugins_loaded`/`init` با lock اتمیک اجرا کنید؛ schema version را قبل از ساخت serviceها بررسی کنید. migrationهای سنگین را chunk/queue کنید و نتیجه را log کنید.

### H-05 — سه dependency آسیب‌پذیر و lock ناسازگار

**شواهد اجرایی:**

- `composer validate --strict`: lock به‌روز نیست.
- `squizlabs/php_codesniffer 3.13.5`: advisory `CVE-2026-67434`، شدت High، OS Command Injection.
- `wp-coding-standards/wpcs 3.3.0`: advisory `CVE-2026-45293`، شدت High، Arbitrary Code Execution.
- `phpcsstandards/phpcsutils 1.2.2`: advisory `CVE-2026-65954`، Arbitrary Code Execution.

**اثر:** این بسته‌ها runtime افزونه نیستند، اما در CI روی محتوای branch/PR اجرا می‌شوند؛ بنابراین supply-chain و CI runner در معرض ریسک‌اند. ناسازگاری lock نیز build تکرارپذیر را مخدوش می‌کند.

**راهکار:** constraints را به نسخه‌های patched ارتقا دهید، `composer update` کنترل‌شده اجرا کنید، `composer audit` و `composer validate --strict` را job اجباری CI کنید و Dependabot/Renovate فعال شود.

### H-06 — nonce عمومی با page cache و headless/mobile ناسازگار است

**شواهد:** هر route عمومی REST نیازمند nonce `wp_rest` است (`class-smart-support-chatbot-rest.php:54-68`, `72-188`) و nonce داخل HTML/JS صفحه تزریق می‌شود (`class-smart-support-chatbot-frontend.php:113-120`). fallback AJAX هم nonce مشابه دارد (`assets/js/smart-support-chatbot.js:200-239`).

**اثر:** صفحه cacheشده می‌تواند nonce منقضی‌شده را ساعت‌ها/روزها تحویل دهد و هر دو REST و AJAX با 403 شکست بخورند. همچنین ادعای مصرف از اپ موبایل/کلاینت دیگر با nonce وابسته به صفحه وردپرس عملی نیست. برای مهمان، nonce منتشرشده در صفحه authorization محسوب نمی‌شود و دفاع اصلی همچنان rate limit است.

**راهکار:** قرارداد endpoint عمومی را روشن کنید. برای guest endpoint از nonce به‌عنوان authorization استفاده نکنید؛ Origin/CSRF model مناسب، rate limit، anti-bot و در صورت نیاز token کوتاه‌عمر قابل refresh طراحی کنید. fallback باید هنگام 403 nonce نیز refresh/retry کنترل‌شده داشته باشد. سناریوی full-page cache تست شود.

### H-07 — privacy-by-default ضعیف و نبود WordPress exporter/eraser

**شواهد:** chatlog پیش‌فرض فعال و retention آن ۹۰ روز است؛ submissionها پیش‌فرض برای همیشه نگهداری می‌شوند (`class-smart-support-chatbot-settings.php:138-140`). IP در هر دو جدول ذخیره می‌شود (`class-smart-support-chatbot-db.php:119`, `137`, `352`, `769`). consent پیش‌فرض خاموش است (`class-smart-support-chatbot-settings.php:154-156`). هیچ hook از خانواده `wp_privacy_personal_data_exporters/erasers` وجود ندارد.

**اثر:** اطلاعات تماس، متن گفتگو، IP و احتمالاً داده سلامت بدون مسیر استاندارد export/erase وردپرس نگهداری می‌شوند. برای داده ADR ممکن است retention قانونی لازم باشد، اما «نگهداری همیشگی بدون سیاست تفکیک‌شده» انتخاب امنی نیست.

**راهکار:** exporter/eraser وردپرس، anonymization IP، retention جداگانه برای chat/consult/ADR، purge batch‌شده و صفحه privacy status اضافه شود. پیش‌فرض‌ها بر اساس کمینه‌سازی داده بازنگری شوند.

## ۵. یافته‌های با شدت متوسط

### M-01 — حذف همه محصولات از پنل واقعاً ذخیره نمی‌شود

در `save_settings()` فقط وقتی `$products` خالی نیست، `products` بازنویسی می‌شود (`class-smart-support-chatbot-admin.php:733-769`). اگر مدیر آخرین محصول را حذف کند، مقدار قبلی در option باقی می‌ماند. `product_knowledge` نیز در نبود آرایه `product_id` پاک نمی‌شود. همین الگو برای خالی‌کردن کامل quick replies وجود دارد (`811-829`).

**راهکار:** همیشه `products=[]`، `product_knowledge=[]` و `quick_replies=[]` را در submission معتبر فرم ذخیره کنید؛ یک hidden sentinel برای تشخیص POST کامل اضافه کنید.

### M-02 — API نوع submission و feature flags را enforce نمی‌کند

`type` ورودی آزاد است و فقط sanitize می‌شود (`class-smart-support-chatbot-ajax.php:1448-1452`). تشخیص ADR با وجود substring «عوارض» انجام می‌شود (`1479`)؛ فعال‌بودن `show_adr`، `business_mode` یا حتی `enabled` در service بررسی نمی‌شود.

**اثر:** caller مستقیم می‌تواند دسته‌های دلخواه بسازد، گزارش ADR را وقتی UI خاموش است ثبت کند، آمار را آلوده کند و workflow مدیریتی را دور بزند.

**راهکار:** type را به enum سروری (`consultation`, `adr`) تبدیل کنید؛ label فارسی فقط presentation باشد. product ID و feature availability را server-side allowlist کنید.

### M-03 — امضای پاسخ Webhook «اختیاری با سکوت» است

وقتی secret تنظیم شده، اگر هدر `x-ssc-signature` وجود داشته باشد بررسی می‌شود، اما نبود هدر پذیرفته می‌شود (`class-smart-support-chatbot-ajax.php:1367-1377`).

**راهکار:** وقتی secret فعال است، نبودن یا نامعتبر بودن signature هر دو باید fail-closed باشند. نام header درخواست و پاسخ نیز مستند و یکسان شود.

### M-04 — SSRF و ارسال credential به endpoint ناامن

`custom_endpoint` و `ai_webhook_url` فقط با `esc_url_raw` ذخیره و سپس با `wp_remote_post` فراخوانی می‌شوند (`class-smart-support-chatbot-admin.php:696-698`; `class-smart-support-chatbot-ajax.php:1273-1288`, `1328-1356`). HTTPS اجباری نیست و private/loopback address منع نشده است.

**اثر:** مدیر یا مهاجمی که capability مدیر را به‌دست آورده می‌تواند server-side request به شبکه داخلی بفرستد؛ custom API key نیز ممکن است روی HTTP ارسال شود.

**راهکار:** `wp_safe_remote_post`، `wp_http_validate_url`، HTTPS-only برای endpoint دارای secret، منع IPهای private/link-local و allowlist اختیاری host اضافه شود.

### M-05 — رمزگذاری secret فاقد authentication و fallback آن plaintext است

AES-256-CBC بدون MAC استفاده شده (`class-smart-support-chatbot-settings.php:259-306`). اگر OpenSSL یا `AUTH_KEY` موجود نباشد، مقدار خام بازگردانده و ذخیره می‌شود (`264-266`). base64 decode نیز strict نیست (`286`).

**راهکار:** AES-GCM یا libsodium با nonce و tag؛ fail کردن ذخیره secret در نبود primitive امن؛ versioned envelope و migration برای `enc::v1::`.

### M-06 — چند instance از shortcode/Elementor عملاً پشتیبانی نمی‌شود

`rendered` پس از اولین render همه renderهای بعدی را خالی می‌کند (`class-smart-support-chatbot-frontend.php:332-341`). root ID و config نیز global و singleton است (`assets/js/smart-support-chatbot.js:8`, `262-267`). `enqueue_with_config()` فقط اولین override را localize می‌کند (`class-smart-support-chatbot-frontend.php:76-84`).

**اثر:** در صفحه‌ای با چند ویجت/shortcode فقط اولین نمونه کار می‌کند و تنظیم نمونه بعدی نادیده گرفته می‌شود. رفتار shortcode برخلاف انتظار component-based است.

**راهکار:** یا limitation «فقط یک instance در هر صفحه» را صریح enforce/document کنید، یا config را per-element در data attribute/JSON script و IDها را یکتا کنید.

### M-07 — باگ شناسه فارسی در محصولات override شده Elementor بازگشته است

در تنظیمات اصلی برای ID فارسی `sanitize_title` fallback وجود دارد، اما widget override مستقیماً `sanitize_key` می‌زند (`widgets/class-smart-support-chatbot-widget.php:417-430`) و شناسه فارسی را خالی می‌کند.

**راهکار:** helper مشترک public برای تولید product ID بسازید و هر دو مسیر admin و Elementor را از همان عبور دهید.

### M-08 — pipeline چت و autocomplete در مقیاس CPU/DB زیادی مصرف می‌کند

برای دیتاست زیر threshold یا fallback، تا ۸۰۰ ردیف بارگیری و در PHP tokenize می‌شود (`class-smart-support-chatbot-db.php:1117-1137`, `1292-1312`). `bank_reply`، `related_questions` و autocomplete کار مشابه را تکرار می‌کنند. autocomplete با debounce فقط ۲۵۰ms درخواست می‌فرستد (`assets/js/smart-support-chatbot.js:1530-1553`).

**راهکار:** corpus tokenized cache، cache نتیجه prefix، query جداگانه سبک برای suggest، reuse candidateها در طول request و benchmark با 1k/10k/100k رکورد.

### M-09 — timeoutهای synchronous ظرفیت PHP worker را تهدید می‌کنند

AI تا ۶۰ ثانیه worker را نگه می‌دارد (`class-smart-support-chatbot-ajax.php:1273-1288`) و پس از submit، اعلان پیام‌رسان تا ۸ ثانیه دیگر synchronous است (`1686-1695`). retry/circuit breaker هم وجود ندارد.

**راهکار:** timeout اتصال/کل جدا، سقف محافظ، circuit breaker، queue برای notification و در صورت امکان streaming/async gateway برای AI.

### M-10 — export کامل CSV بدون pagination/stream query حافظه را مصرف می‌کند

`get_all_for_export()` یا filtered export تمام ردیف‌ها را یکجا با `get_results` وارد حافظه می‌کند (`class-smart-support-chatbot-db.php:494-548`) و بعد stream خروجی ساخته می‌شود.

**اثر:** روی جدول بزرگ ممکن است memory limit یا timeout رخ دهد؛ داشتن `php://output` این مشکل را حل نمی‌کند چون collection قبلاً کامل load شده است.

**راهکار:** export را keyset-paginated (مثلاً batch 500) یا با cursor/CLI job انجام دهید.

### M-11 — cache پاسخ با تغییر model/temperature/max tokens invalidate نمی‌شود

کلید cache شامل provider، product، message و system است، اما model، temperature و max token را ندارد (`class-smart-support-chatbot-ajax.php:566-594`). cache hit نیز log/feedback ندارد (`class-smart-support-chatbot-ajax.php:329-347`).

**اثر:** بعد از تغییر تنظیمات AI تا پایان TTL پاسخ تنظیم قدیمی برمی‌گردد و UX بازخورد برای همان پاسخ بسته به cache hit متفاوت است.

**راهکار:** fingerprint همه تنظیمات مؤثر و revision تنظیمات/KB را وارد کلید کنید؛ cache management و clear button اضافه شود؛ سیاست log/feedback برای cache روشن شود.

## ۶. یافته‌های پایین‌تر و فرصت‌های بهبود

### L-01 — هیچ تست خودکاری وجود ندارد

CI فقط syntax، PHPCS و چند grep-based sanity check دارد. برای matcher فارسی، chunking، migration، consent، ADR seriousness، REST/AJAX parity، encryption و cache هیچ regression test وجود ندارد.

### L-02 — PHPCS روی checkout استاندارد ویندوز fail می‌شود

Git blobها LF هستند ولی `.gitattributes` فقط `text=auto` دارد؛ checkout فعلی CRLF است و ruleset LF را الزام می‌کند. PHPCS محلی ۳۱ خطا داد، در حالی که همان فایل‌ها پس از LF شدن صفر خطا داشتند. `*.php text eol=lf` و `*.css text eol=lf` اضافه شود.

### L-03 — نسخه و artifactهای ترجمه با تاریخچه توسعه هماهنگ نیستند

هدر/ثابت/readme هنوز 4.0.0 هستند، در حالی که commitها قابلیت‌ها را با برچسب 4.5 معرفی می‌کنند. POT نیز `Project-Id-Version: 3.2.0` دارد. این موضوع upgrade cache busting، پشتیبانی و release traceability را مبهم می‌کند.

### L-04 — فایل‌های فونت پیش‌فرض در بسته موجود نیستند

پیش‌فرض `vazirmatn` است، اما پوشه fonts فقط `index.php` دارد. کد به فونت سیستم fallback می‌کند، پس crash رخ نمی‌دهد، ولی ظاهر ادعاشده بسته انتشار قابل تکرار نیست. build فقط warning می‌دهد و fail نمی‌کند.

### L-05 — i18n و LTR ناقص است

بخش بزرگی از متن‌های frontend JavaScript سخت‌کد شده‌اند و root همیشه `dir="rtl"` است (`class-smart-support-chatbot-frontend.php:341`). README نیز کامل‌نبودن LTR را می‌پذیرد. برای فروش عمومی، strings باید از i18n map/JS translations بیایند و direction از locale/theme تعیین شود.

### L-06 — hours scheduling بازه شبانه را پشتیبانی نمی‌کند

منطق `now >= start && now < end` برای بازه‌ای مثل ۲۲ تا ۶ همیشه false است (`class-smart-support-chatbot-settings.php:374-388`). ورودی فقط ساعت صحیح است و دقیقه ندارد.

### L-07 — ذخیره گفتگو در sessionStorage نیازمند سیاست روشن‌تر است

تا ۵۰ پیام، log token و محصول برای دو ساعت در `sessionStorage` ذخیره می‌شود (`assets/js/smart-support-chatbot.js:423-487`). فرم نیمه‌پر ذخیره نمی‌شود که نکته مثبتی است، اما متن مکالمه ممکن است داده سلامت باشد. opt-out، توضیح privacy و clear-on-consent/revoke وجود ندارد.

### L-08 — بدهی معماری و تکرار منطق بالاست

`class-smart-support-chatbot-ajax.php` حدود ۱۷۱۴ خط، DB حدود ۱۳۵۶ خط و JS حدود ۱۶۱۸ خط است. provider، matcher، submission، notification و transport در یک کلاس جمع شده‌اند؛ query builder export/list هم تکراری است. این وضعیت تست و تغییر امن را دشوار می‌کند.

## ۷. موارد گزارش قدیمی که در نسخه فعلی رفع شده‌اند

برای جلوگیری از تکرار یافته‌های منسوخ، موارد زیر در کد فعلی رفع‌شده یا به‌شکل محسوسی بهتر شده‌اند:

- CSV Formula Injection رفع شده است.
- rate limit از counter اتمیک استفاده می‌کند و session mode سقف پشتیبان IP دارد.
- poisoning ساده feedback با log token و single-vote update محدود شده است.
- کلید Gemini از query string به header منتقل شده است.
- مشکل `max_input_vars` برای Q&A detect می‌شود و save غیرمخرب شده است.
- `usage_count` در save معمولی حفظ می‌شود.
- CSV چندخطی درست parse می‌شود.
- شناسه محصول فارسی در مسیر تنظیمات اصلی اصلاح شده است؛ فقط مسیر Elementor هنوز مشکل دارد.
- local conversation persistence، ARIA dialog، focus trap، labels و reduced-motion اضافه شده‌اند.
- CI و REST layer اضافه شده‌اند.

## ۸. نقشه اصلاح پیشنهادی

### فاز صفر — قبل از انتشار بعدی

1. اصلاح server-side consent و ثبت evidence.
2. اصلاح مدل serious ADR و افزودن تست برای فوت/بستری/تهدید حیات.
3. ثبت وضعیت اعلان، retry و هشدار شکست.
4. ارتقای dependencyها، بازسازی lock و اجباری‌کردن audit در CI.
5. انتقال schema migration به مسیر عمومی idempotent و lock‌شده.
6. رفع حذف آخرین product/quick reply و stale knowledge.
7. bump نسخه واقعی و بازسازی POT.

### فاز یک — hardening تولید

1. بازطراحی guest API و nonce برای page cache/headless.
2. enum سروری برای submission type و feature gating.
3. fail-closed کردن webhook signature.
4. امن‌سازی endpointهای سفارشی در برابر SSRF/HTTP.
5. WordPress privacy exporter/eraser و retention تفکیک‌شده.
6. تست integration روی WordPress multisite/single-site و MySQL/MariaDB پشتیبانی‌شده.

### فاز دو — مقیاس و نگهداشت

1. تفکیک Provider/Matcher/Submission/Notification/Transport به سرویس‌های جدا.
2. unit test برای pure logic و integration test برای DB/REST/AJAX.
3. cache corpus و benchmark دیتاست بزرگ.
4. export batch‌شده و purge batch‌شده.
5. پشتیبانی واقعی multi-instance یا مستندسازی محدودیت singleton.

## ۹. حداقل تست‌های پذیرش لازم

- ارسال مستقیم REST/AJAX بدون consent وقتی consent فعال است باید 400/403 بدهد.
- ADR با outcome «فوت» یا «منجر به بستری شد» باید `is_serious=1` و notification job فوری بسازد.
- شکست Telegram/Bale/mail باید در admin قابل مشاهده و قابل retry باشد.
- upgrade از هر DB version قبلی، بدون ورود مدیر و با دو درخواست هم‌زمان، باید idempotent باشد.
- صفحه cacheشده با nonce قدیمی باید مسیر recovery مشخص داشته باشد.
- حذف آخرین product، quick reply و product knowledge باید پس از reload پابرجا بماند.
- webhook دارای secret و فاقد signature باید رد شود.
- endpoint سفارشی `http://127.0.0.1`، metadata IP و HTTP ساده باید رد شوند.
- تست matcher فارسی برای ی/ي، ک/ك، اعراب، مترادف و threshold.
- benchmark autocomplete و chat با حداقل ۱۰هزار Q&A/KB chunk.
- export صدها هزار submission نباید memory را متناسب با کل جدول مصرف کند.

## ۱۰. نتیجه نهایی

کد فعلی نسبت به ممیزی قبلی پیشرفت واقعی و قابل توجهی دارد و بسیاری از ضعف‌های کلاسیک افزونه‌های وردپرس را درست مدیریت می‌کند. مشکل اصلی دیگر «نبود پایه امنیتی» نیست؛ مشکل، شکاف بین یک افزونه خوش‌ساخت و یک سامانه قابل اتکا برای داده‌های حساس/دارویی است. اولویت باید روی enforce شدن قواعد در سرور، تحویل قابل رهگیری اعلان‌ها، migration امن، privacy lifecycle و تست خودکار باشد. پس از رفع موارد H-01 تا H-07 و ساخت تست‌های پذیرش، پروژه می‌تواند وارد مرحله hardening و انتشار عمومی شود.
