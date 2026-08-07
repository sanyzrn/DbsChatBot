<?php
/**
 * Plugin Name:       Smart Support Chatbot
 * Plugin URI:        https://dbsgraphic.ir/
 * Description:       دستیار هوشمند گفتگو، پشتیبانی و ثبت درخواست برای سایت‌های وردپرسی. چند موتور هوش مصنوعی، پایگاه دانش آفلاین، سازگار با المنتور و دارای پنل مدیریت کامل. حالت اختیاری «داروسازی» برای ثبت استاندارد عوارض دارویی.
 * Version:           4.0.0
 * Author:            DBS
 * Author URI:        https://dbsgraphic.ir/
 * Text Domain:       smart-support-chatbot
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // جلوگیری از دسترسی مستقیم.
}

/**
 * ثابت‌های افزونه.
 */
define( 'SSC_CHATBOT_VERSION', '4.0.0' );
define( 'SSC_CHATBOT_FILE', __FILE__ );
define( 'SSC_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSC_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'SSC_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * بارگذاری فایل‌های هسته.
 */
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-settings.php';
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-db.php';
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-ajax.php';
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-frontend.php';
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-admin.php';
require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot.php';

/**
 * فعال‌سازی افزونه: ساخت جداول و مقادیر پیش‌فرض.
 */
function ssc_chatbot_activate() {
	require_once SSC_CHATBOT_DIR . 'includes/class-smart-support-chatbot-db.php';
	// مهاجرت داده‌های نصب‌های قدیمی (نام‌فضای nafas_) پیش از ساخت جداول جدید.
	SSC_Chatbot_DB::migrate_legacy_namespace();
	SSC_Chatbot_DB::create_table();
	SSC_Chatbot_Settings::set_defaults();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ssc_chatbot_activate' );

/**
 * غیرفعال‌سازی افزونه.
 */
function ssc_chatbot_deactivate() {
	wp_clear_scheduled_hook( 'ssc_chatbot_daily_cleanup' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ssc_chatbot_deactivate' );

/**
 * بارگذاری ترجمه‌ها.
 */
function ssc_chatbot_load_textdomain() {
	load_plugin_textdomain( 'smart-support-chatbot', false, dirname( SSC_CHATBOT_BASENAME ) . '/languages' );
}
add_action( 'init', 'ssc_chatbot_load_textdomain' );

/**
 * راه‌اندازی افزونه.
 */
function ssc_chatbot_run() {
	// مهاجرت یک‌بارهٔ داده از نام‌فضای قدیمی (برای به‌روزرسانی درجا) — پیش از هر خواندن گزینه/جدول.
	SSC_Chatbot_DB::migrate_legacy_namespace();
	return SSC_Chatbot::instance();
}
add_action( 'plugins_loaded', 'ssc_chatbot_run' );
