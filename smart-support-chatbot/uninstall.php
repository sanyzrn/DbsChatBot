<?php
/**
 * حذف کامل افزونه: پاکسازی گزینه‌ها و جدول دیتابیس.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * اجرای پاکسازی کامل (در یک تابع تا متغیرها سراسری نشوند).
 */
function ssc_chatbot_run_uninstall() {
	// حذف گزینه‌ها (شامل کلیدهای قدیمی nafas_ برای پاک‌سازی کامل نصب‌های ارتقایافته).
	$ssc_options = array(
		'ssc_chatbot_settings',
		'ssc_chatbot_chat_stats',
		'ssc_chatbot_db_version',
		'ssc_chatbot_qa_migrated',
		'ssc_chatbot_csat',
		'ssc_chatbot_stats_migrated',
		'ssc_chatbot_ns_migrated',
		'nafas_chatbot_settings',
		'nafas_chatbot_chat_stats',
		'nafas_chatbot_db_version',
		'nafas_chatbot_qa_migrated',
		'nafas_chatbot_csat',
		'nafas_chatbot_stats_migrated',
	);
	foreach ( $ssc_options as $ssc_opt ) {
		delete_option( $ssc_opt );
	}

	// پاک‌سازی کرون (جدید و قدیمی).
	wp_clear_scheduled_hook( 'ssc_chatbot_daily_cleanup' );
	wp_clear_scheduled_hook( 'nafas_chatbot_daily_cleanup' );

	// حذف جداول (جدید و هرگونه بازماندهٔ قدیمی).
	global $wpdb;
	$ssc_suffixes = array( 'submissions', 'chatlog', 'qa', 'kb', 'stats' );
	foreach ( array( 'ssc_chatbot_', 'nafas_chatbot_' ) as $ssc_table_prefix ) {
		foreach ( $ssc_suffixes as $ssc_suffix ) {
			$ssc_table = $wpdb->prefix . $ssc_table_prefix . $ssc_suffix;
			$wpdb->query( "DROP TABLE IF EXISTS `{$ssc_table}`" ); // phpcs:ignore WordPress.DB
		}
	}
}
ssc_chatbot_run_uninstall();
