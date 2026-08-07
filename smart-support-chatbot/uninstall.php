<?php
/**
 * حذف کامل افزونه: پاکسازی گزینه‌ها و جدول دیتابیس.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// حذف گزینه‌ها (شامل کلیدهای قدیمی nafas_ برای پاک‌سازی کامل نصب‌های ارتقایافته).
$options = array(
	'ssc_chatbot_settings', 'ssc_chatbot_chat_stats', 'ssc_chatbot_db_version',
	'ssc_chatbot_qa_migrated', 'ssc_chatbot_csat', 'ssc_chatbot_stats_migrated',
	'ssc_chatbot_ns_migrated',
	'nafas_chatbot_settings', 'nafas_chatbot_chat_stats', 'nafas_chatbot_db_version',
	'nafas_chatbot_qa_migrated', 'nafas_chatbot_csat', 'nafas_chatbot_stats_migrated',
);
foreach ( $options as $opt ) {
	delete_option( $opt );
}

// پاک‌سازی کرون (جدید و قدیمی).
wp_clear_scheduled_hook( 'ssc_chatbot_daily_cleanup' );
wp_clear_scheduled_hook( 'nafas_chatbot_daily_cleanup' );

// حذف جداول (جدید و هرگونه بازماندهٔ قدیمی).
global $wpdb;
$suffixes = array( 'submissions', 'chatlog', 'qa', 'kb', 'stats' );
foreach ( array( 'ssc_chatbot_', 'nafas_chatbot_' ) as $prefix ) {
	foreach ( $suffixes as $suffix ) {
		$t = $wpdb->prefix . $prefix . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS `{$t}`" ); // phpcs:ignore WordPress.DB
	}
}
