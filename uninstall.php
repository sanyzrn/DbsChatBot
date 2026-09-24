<?php
/**
 * Uninstall.
 *
 * Data-safety contract:
 * - By default ALL business data (settings, knowledge, requests, ADR cases,
 *   chat logs) is PRESERVED when the plugin is deleted.
 * - Destructive removal happens ONLY when the administrator explicitly
 *   enabled "Remove all data on uninstall" in Settings → Data tools
 *   (option ssc_chatbot_delete_on_uninstall).
 * - Transients and scheduled events are always cleaned up.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Run uninstall cleanup (scoped function: no global leakage).
 *
 * @param int $site_id Site id (multisite loop).
 */
function ssc_chatbot_run_uninstall( $site_id = 0 ) {
	global $wpdb;

	if ( $site_id && function_exists( 'switch_to_blog' ) ) {
		switch_to_blog( $site_id );
	}

	// Always-safe cleanup: transients + cron (never business data).
	$transient_names = array( 'ssc_chatbot_notify_error', 'ssc_chatbot_upgrade_lock' );
	foreach ( $transient_names as $name ) {
		delete_transient( $name );
	}
	// AI response cache (prefix-based delete).
	if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->options ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall-time transient purge.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ssc\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ssc\\_%'" );
	}
	wp_clear_scheduled_hook( 'ssc_daily_cleanup' );
	wp_clear_scheduled_hook( 'ssc_notification_retry' );
	wp_clear_scheduled_hook( 'ssc_chatbot_daily_cleanup' );
	wp_clear_scheduled_hook( 'nafas_chatbot_daily_cleanup' );

	// Destructive path: ONLY with explicit prior opt-in.
	$delete_data = get_option( 'ssc_chatbot_delete_on_uninstall', 'no' );
	if ( 'yes' !== $delete_data ) {
		if ( $site_id && function_exists( 'restore_current_blog' ) ) {
			restore_current_blog();
		}
		return;
	}

	$options = array(
		'ssc_chatbot_settings',
		'ssc_chatbot_setup',
		'ssc_chatbot_modules',
		'ssc_chatbot_db_version',
		'ssc_chatbot_qa_migrated',
		'ssc_chatbot_stats_migrated',
		'ssc_chatbot_ns_migrated',
		'ssc_chatbot_chat_stats',
		'ssc_chatbot_csat',
		'ssc_chatbot_delete_on_uninstall',
		'ssc_notify_queue',
		'ssc_pharma_setup',
		// Legacy namespace leftovers.
		'nafas_chatbot_settings',
		'nafas_chatbot_chat_stats',
		'nafas_chatbot_db_version',
		'nafas_chatbot_qa_migrated',
		'nafas_chatbot_csat',
		'nafas_chatbot_stats_migrated',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$suffixes = array( 'submissions', 'chatlog', 'qa', 'kb', 'stats', 'audit', 'notifications' );
	foreach ( array( 'ssc_chatbot_', 'nafas_chatbot_' ) as $prefix ) {
		foreach ( $suffixes as $suffix ) {
			$table = $wpdb->prefix . $prefix . $suffix;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- opt-in destructive uninstall.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}

	if ( $site_id && function_exists( 'restore_current_blog' ) ) {
		restore_current_blog();
	}
}

if ( is_multisite() ) {
	// Clean every site, not just the current one. The loop variable is named
	// away from $id: WordPress uses that global and overwriting it here would
	// corrupt anything reading it later in the uninstall request.
	$ssc_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $ssc_site_ids as $ssc_site_id ) {
		ssc_chatbot_run_uninstall( (int) $ssc_site_id );
	}
	unset( $ssc_site_ids, $ssc_site_id );
} else {
	ssc_chatbot_run_uninstall();
}
