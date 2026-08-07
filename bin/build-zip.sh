#!/usr/bin/env bash
#
# ساخت بستهٔ انتشار (zip) — فقط فایل‌های لازم برای اجرا، بدون فایل‌های توسعه.
#
# اجرا:  bash bin/build-zip.sh
# خروجی: dist/smart-support-chatbot-<version>.zip

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="smart-support-chatbot"
SRC="$ROOT/$SLUG"
DIST="$ROOT/dist"

VERSION="$(grep -oP "^\s*\*\s*Version:\s*\K[0-9.]+" "$SRC/$SLUG.php")"
[ -n "$VERSION" ] || { echo "نسخه در هدر افزونه پیدا نشد." >&2; exit 1; }

# هم‌خوانی نسخه‌ها (همان بررسی CI).
CONST="$(grep -oP "SSC_CHATBOT_VERSION',\s*'\K[0-9.]+" "$SRC/$SLUG.php")"
STABLE="$(grep -oP "^Stable tag:\s*\K[0-9.]+" "$SRC/readme.txt")"
if [ "$VERSION" != "$CONST" ] || [ "$VERSION" != "$STABLE" ]; then
	echo "ناهماهنگی نسخه: هدر=$VERSION ثابت=$CONST readme=$STABLE" >&2
	exit 1
fi

# هشدار در نبود فایل‌های فونت (افزونه کار می‌کند اما به فونت سیستمی برمی‌گردد).
if [ ! -f "$SRC/assets/fonts/vazirmatn-400.woff2" ]; then
	echo "⚠️  فایل‌های فونت موجود نیستند — ابتدا bash bin/fetch-fonts.sh را اجرا کنید." >&2
fi

rm -rf "$DIST/$SLUG" "$DIST/$SLUG-$VERSION.zip"
mkdir -p "$DIST"

# کپی با حذف فایل‌های توسعه.
rsync -a --delete \
	--exclude '.git*' \
	--exclude 'node_modules' \
	--exclude 'tests' \
	--exclude '*.map' \
	--exclude '.DS_Store' \
	"$SRC/" "$DIST/$SLUG/"

( cd "$DIST" && zip -qr "$SLUG-$VERSION.zip" "$SLUG" )
rm -rf "$DIST/$SLUG"

echo "✓ ساخته شد: dist/$SLUG-$VERSION.zip ($(du -h "$DIST/$SLUG-$VERSION.zip" | cut -f1))"
