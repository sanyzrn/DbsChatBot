# پاک‌سازی محدود فایل‌های بلااستفاده

تاریخ: ۲۰۲۶-۱۱-۰۲. اجازهٔ مالک: حذف فایل اضافی و قدیمیِ بلااستفاده؛ نه حذف تاریخچهٔ پروژه یا تست سالم.

## حذف شد

- `docs/internal/audit-2026-09-18/01-desktop-launcher.png`
- `docs/internal/audit-2026-09-18/02-desktop-chat-open.png`
- `docs/internal/audit-2026-09-18/03-product-picker.png`
- `docs/internal/audit-2026-09-18/04-answer.png`
- `docs/internal/audit-2026-09-18/05-adr-form-mobile.png`
- `docs/internal/audit-2026-09-18/06-fa-mobile-chat.png`
- `docs/internal/audit-2026-09-18/07-adr-filled-fa.png`
- `docs/internal/audit-2026-09-18/08-adr-submitted.png`
- `docs/internal/audit-2026-09-18/09-admin-dashboard.png`
- `docs/internal/audit-2026-09-18/10-adr-case-view.png`
- `docs/internal/audit-2026-09-18/11-settings.png`
- `docs/internal/audit-2026-09-18/12-settings-narrow.png`
- `docs/internal/audit-2026-09-18/adversarial-suite-results.json`
- `docs/internal/audit-2026-09-18/transcripts.json`
- `docs/internal/audit-2026-09-18/adversarial_suite.py`
- `docs/internal/audit-2026-09-18/audit-mock-must-use.php`

این‌ها artifactهای یک ممیزی 0.6.1-beta بودند. اسکریپت آن به مسیر ثابت `/home/z/my-project/...` و وردپرس موقت همان ممیزی وابسته بود. جست‌وجوی ارجاع نشان داد از runtime، tests جاری، CI یا builder فراخوانی نمی‌شوند؛ فقط گزارش تاریخی به نامشان اشاره داشت. از اجرای اسکریپت قدیمی برای نسخهٔ امروز جلوگیری شد. فایل‌ها از تاریخچهٔ Git قابل بازیابی‌اند.

## حفظ شد

- گزارش متنی همان ممیزی برای سابقهٔ یافته‌ها، با هشدار تاریخی و توضیح حذف پیوست‌ها.
- تمام `tests/` و fixtureهای جاری، source runtime، مجوز فونت‌ها، ابزارهای build/release و schema migrationها.
- ROADMAP-HARDENING، ROADMAP-IDEAS و اسناد مفید دیگر؛ به‌دلیل داشتن تصمیم/شاهد/پیشنهاد استفاده‌پذیر حذف نشدند و در index نقش مرجع گرفتند.

هیچ کلید یا دادهٔ واقعی از dumpها به اسناد جدید کپی نشد. حذف این پیوست‌ها اثبات رفع تمام یافته‌های تاریخی نیست؛ وضعیت فعلی از کد/تست جاری مشخص می‌شود.
