<?php
/**
 * هارنس تست منطق خالص برای افزونه Smart Support Chatbot.
 *
 * این فایل فقط توابع وردپرس را شبیه‌سازی (mock) می‌کند تا منطق خالص — بدون نیاز
 * به نصب وردپرس — قابل راستی‌آزمایی باشد. برای اجرای محلی/CI سبک:
 *
 *   php tests/logic-test.php
 *
 * @package SmartSupportChatbot
 */

// ---------- mock توابع وردپرس ----------
define( 'ABSPATH', __DIR__ . '/' );
define( 'AUTH_KEY', 'test-auth-key-1234567890' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['__options']   = array();
$GLOBALS['__now_hour']  = 12;
$GLOBALS['__now_wday']  = 6; // شنبه.

function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d;
}
function update_option( $k, $v, $a = null ) {
	$GLOBALS['__options'][ $k ] = $v;
	return true;
}
function add_option( $k, $v, $x = '', $a = null ) {
	if ( ! array_key_exists( $k, $GLOBALS['__options'] ) ) {
		$GLOBALS['__options'][ $k ] = $v;
	}
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['__options'][ $k ] );
	return true;
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function apply_filters( $tag, $value ) {
	return $value;
}
function current_time( $type ) {
	if ( 'G' === $type ) {
		return $GLOBALS['__now_hour'];
	}
	if ( 'w' === $type ) {
		return $GLOBALS['__now_wday'];
	}
	if ( 'Y-m-d' === $type ) {
		return '2026-01-01';
	}
	if ( 'mysql' === $type ) {
		return '2026-01-01 12:00:00';
	}
	return '';
}
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}
function __( $s, $d = null ) {
	return $s;
}
function esc_html__( $s, $d = null ) {
	return $s;
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_title( $t ) {
	return trim( preg_replace( '/\s+/', '-', (string) $t ) );
}
function wp_rand() {
	return 42;
}
function wp_salt( $s = 'auth' ) {
	return 'salt-' . $s;
}
function wp_unslash( $v ) {
	return $v;
}

// WP HTTP mock: از private IPها را نامعتبر می‌شمارد (شبیه‌سازی wp_http_validate_url).
function wp_http_validate_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return false;
	}
	$host = $parts['host'];
	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return false;
	}
	if ( preg_match( '/^(10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host ) ) {
		return false;
	}
	// آدرس‌های عمومی (نام دامنه) پذیرفته می‌شوند.
	return true;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, -1 === $component ? -1 : $component );
}
function is_email( $e ) {
	return false !== filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false;
}
function wp_rand_seed() {}

// ---------- بارگذاری کلاس تنظیمات ----------
require __DIR__ . '/../smart-support-chatbot/includes/class-smart-support-chatbot-settings.php';

// ---------- چارچوب تست ----------
$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check( $label, $cond ) {
	if ( $cond ) {
		++$GLOBALS['__pass'];
		echo "  ✓ $label\n";
	} else {
		++$GLOBALS['__fail'];
		echo "  ✗ $label\n";
	}
}

echo "== رمزنگاری (M-05) ==\n";
$secret = 'sk-test-API-KEY-1234567890';
$enc    = SSC_Chatbot_Settings::encrypt( $secret );
check( 'خروجی رمزنگاری‌شده است (شروع با enc::v2::)', 0 === strpos( $enc, 'enc::v2::' ) );
check( 'مقدار اصیل بازگردانی می‌شود', $secret === SSC_Chatbot_Settings::decrypt( $enc ) );
check( 'ciphertext دست‌کاری‌شده رد می‌شود', '' === SSC_Chatbot_Settings::decrypt( 'enc::v2::' . base64_encode( 'garbagegarbagegarbagegarbage' ) ) );
// سازگاری عقب‌رو: مقدار v1 (CBC) قدیمی باید خوانده شود.
$legacy_iv  = openssl_random_pseudo_bytes( 16 );
$legacy_enc = openssl_encrypt( 'legacy-secret', 'aes-256-cbc', hash( 'sha256', AUTH_KEY, true ), OPENSSL_RAW_DATA, $legacy_iv );
$legacy     = 'enc::v1::' . base64_encode( $legacy_iv . $legacy_enc );
check( 'مقدار قدیمی v1 (CBC) خوانده می‌شود', 'legacy-secret' === SSC_Chatbot_Settings::decrypt( $legacy ) );
check( 'مقدار plaintext قدیمی دست‌نخورده برگردانده می‌شود', 'plain-old' === SSC_Chatbot_Settings::decrypt( 'plain-old' ) );
check( 'رشتهٔ خالی بدون تغییر می‌ماند', '' === SSC_Chatbot_Settings::encrypt( '' ) );

echo "\n== عارضهٔ جدی (H-02) ==\n";
check( 'شدت «شدید» جدی است', SSC_Chatbot_Settings::is_serious_adr( 'شدید', 'بهبود کامل یافت' ) );
check( 'شدت «تهدیدکننده حیات» جدی است', SSC_Chatbot_Settings::is_serious_adr( 'تهدیدکننده حیات', '' ) );
check( 'outcome «فوت» با شدت خفیف جدی است', SSC_Chatbot_Settings::is_serious_adr( 'خفیف', 'فوت' ) );
check( 'outcome «منجر به بستری شد» با شدت متوسط جدی است', SSC_Chatbot_Settings::is_serious_adr( 'متوسط', 'منجر به بستری شد' ) );
check( 'outcome «عارضه ماندگار/ناتوانی» جدی است', SSC_Chatbot_Settings::is_serious_adr( 'خفیف', 'عارضه ماندگار/ناتوانی' ) );
check( 'خفیف + بهبود کامل، جدی نیست', ! SSC_Chatbot_Settings::is_serious_adr( 'خفیف', 'بهبود کامل یافت' ) );
$values = SSC_Chatbot_Settings::serious_adr_values();
check( 'serious_adr_values شامل «فوت» است', in_array( 'فوت', $values, true ) );
check( 'serious_adr_values شامل «شدید» است', in_array( 'شدید', $values, true ) );

echo "\n== ساعات کاری (L-06) ==\n";
// بازهٔ شبانه ۲۲ تا ۶.
SSC_Chatbot_Settings::update( array( 'office_enabled' => 'yes', 'office_start' => 22, 'office_end' => 6, 'office_days' => array( 6 ) ) );
$GLOBALS['__now_hour'] = 23;
$GLOBALS['__now_wday'] = 6;
check( 'ساعت ۲۳ در روز کاری = آنلاین', SSC_Chatbot_Settings::is_online() );
$GLOBALS['__now_hour'] = 3;
$GLOBALS['__now_wday'] = 0; // یکشنبه (روز بعد از شنبه).
check( 'ساعت ۳ بامداد روز بعد = آنلاین', SSC_Chatbot_Settings::is_online() );
$GLOBALS['__now_hour'] = 3;
$GLOBALS['__now_wday'] = 1; // دوشنبه (روز قبل = یکشنبه غیرکاری).
check( 'ساعت ۳ بامداد پس از روز غیرکاری = آفلاین', ! SSC_Chatbot_Settings::is_online() );
$GLOBALS['__now_hour'] = 12;
$GLOBALS['__now_wday'] = 6;
check( 'ساعت ۱۲ در بازهٔ شبانه = آفلاین', ! SSC_Chatbot_Settings::is_online() );
// بازهٔ عادی ۸ تا ۱۶.
SSC_Chatbot_Settings::update( array( 'office_start' => 8, 'office_end' => 16 ) );
$GLOBALS['__now_hour'] = 10;
check( 'بازهٔ عادی: ساعت ۱۰ = آنلاین', SSC_Chatbot_Settings::is_online() );
$GLOBALS['__now_hour'] = 20;
check( 'بازهٔ عادی: ساعت ۲۰ = آفلاین', ! SSC_Chatbot_Settings::is_online() );

echo "\n== نتیجه ==\n";
echo "موفق: {$GLOBALS['__pass']}  |  ناموفق: {$GLOBALS['__fail']}\n";
exit( $GLOBALS['__fail'] > 0 ? 1 : 0 );
