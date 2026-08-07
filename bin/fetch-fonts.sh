#!/usr/bin/env bash
#
# واکشی فونت‌های همراه افزونه به assets/fonts/.
#
# چرا: افزونه هیچ فونتی را از CDN خارجی بارگذاری نمی‌کند (پایداری در شبکه‌های محدود +
# پرهیز از ریسک حریم خصوصی بارگذاری Google Fonts از سرور گوگل). فایل‌های فونت باید
# پیش از ساخت بستهٔ انتشار، یک‌بار به‌صورت محلی وارد شوند.
#
# اجرا:  bash bin/fetch-fonts.sh
#
# اگر فایل‌ها موجود نباشند، افزونه به‌صورت خودکار به فونت سیستمی برمی‌گردد
# (بدون خطا و بدون درخواست خارجی) — پس این اسکریپت اختیاری اما توصیه‌شده است.

set -euo pipefail

DEST="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/smart-support-chatbot/assets/fonts"
mkdir -p "$DEST"

VAZIR_BASE="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts"

fetch() {
	local url="$1" out="$2"
	if curl -sSL --fail --max-time 60 -o "$DEST/$out" "$url"; then
		printf '  ✓ %s (%s bytes)\n' "$out" "$(stat -c%s "$DEST/$out" 2>/dev/null || stat -f%z "$DEST/$out")"
	else
		printf '  ✗ %s — دانلود نشد\n' "$out" >&2
		return 1
	fi
}

echo "واکشی Vazirmatn…"
fetch "$VAZIR_BASE/Vazirmatn-Regular.woff2" "vazirmatn-400.woff2"
fetch "$VAZIR_BASE/Vazirmatn-Medium.woff2"  "vazirmatn-500.woff2"
fetch "$VAZIR_BASE/Vazirmatn-Bold.woff2"    "vazirmatn-700.woff2"

cat <<'NOTE'

Vazirmatn وارد شد.

Inter و Roboto:
  این دو فونت با لایسنس SIL OFL / Apache 2.0 منتشر می‌شوند و باید مستقیماً از منبع
  رسمی دریافت و با نام‌های زیر در همین پوشه قرار گیرند:
      inter-400.woff2   inter-700.woff2
      roboto-400.woff2  roboto-700.woff2
  منابع:  https://github.com/rsms/inter/releases
          https://fonts.google.com/specimen/Roboto  (دانلود مستقیم، نه لینک CSS)

  تا زمانی که این فایل‌ها موجود نباشند، انتخاب Inter/Roboto در تنظیمات به فونت
  سیستمی برمی‌گردد و هیچ درخواست خارجی ارسال نمی‌شود.

یادآوری لایسنس: فایل پروانهٔ هر فونت را کنار فایل‌ها نگه دارید (الزام SIL OFL).
NOTE
