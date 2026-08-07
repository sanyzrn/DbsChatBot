<?php
/**
 * Plugin Name:       Smart Support Chatbot
 * Plugin URI:        https://dbsgraphic.ir/
 * Description:       دستیار هوشمند گفتگو، پشتیبانی و ثبت درخواست برای سایت‌های وردپرسی. چند موتور هوش مصنوعی، پایگاه دانش آفلاین، سازگار با المنتور و دارای پنل مدیریت کامل. حالت اختیاری «داروسازی» برای ثبت استاندارد عوارض دارویی.
 * Version:           3.2.0
 * Author:            DBS
 * Author URI:        https://dbsgraphic.ir/
 * Text Domain:       nafas-chatbot
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.2
 *
 * @package NafasChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // جلوگیری از دسترسی مستقیم.
}

/**
 * ثابت‌های افزونه.
 */
define( 'NAFAS_CHATBOT_VERSION', '3.2.0' );
define( 'NAFAS_CHATBOT_FILE', __FILE__ );
define( 'NAFAS_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAFAS_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'NAFAS_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * بارگذاری فایل‌های هسته.
 */
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-settings.php';
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-db.php';
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-ajax.php';
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-frontend.php';
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-admin.php';
require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot.php';

/**
 * فعال‌سازی افزونه: ساخت جداول و مقادیر پیش‌فرض.
 */
function nafas_chatbot_activate() {
	require_once NAFAS_CHATBOT_DIR . 'includes/class-nafas-chatbot-db.php';
	Nafas_Chatbot_DB::create_table();
	Nafas_Chatbot_Settings::set_defaults();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nafas_chatbot_activate' );

/**
 * غیرفعال‌سازی افزونه.
 */
function nafas_chatbot_deactivate() {
	wp_clear_scheduled_hook( 'nafas_chatbot_daily_cleanup' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nafas_chatbot_deactivate' );

/**
 * بارگذاری ترجمه‌ها.
 */
function nafas_chatbot_load_textdomain() {
	load_plugin_textdomain( 'nafas-chatbot', false, dirname( NAFAS_CHATBOT_BASENAME ) . '/languages' );
}
add_action( 'init', 'nafas_chatbot_load_textdomain' );

/**
 * راه‌اندازی افزونه.
 */
function nafas_chatbot_run() {
	return Nafas_Chatbot::instance();
}
add_action( 'plugins_loaded', 'nafas_chatbot_run' );
