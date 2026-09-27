<?php
/**
 * Scheduled maintenance (daily cleanup).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron class.
 */
class SSC_Cron {

	const HOOK       = 'ssc_daily_cleanup';
	const RETRY_HOOK = 'ssc_notification_retry';

	/**
	 * Schedule the daily event if missing.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
		if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) {
			wp_schedule_event( time() + 300, 'ssc_five_minutes', self::RETRY_HOOK );
		}
	}

	/**
	 * Unschedule (deactivation).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		wp_clear_scheduled_hook( SSC_Embeddings::CRON_HOOK );
	}

	/**
	 * Hook the runner.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::RETRY_HOOK, array( 'SSC_Module_Notifications', 'retry_failed' ) );
		add_action( SSC_Embeddings::CRON_HOOK, array( 'SSC_Embeddings', 'cron_run' ) );
		add_action( SSC_Conversation::SUMMARY_HOOK, array( 'SSC_Conversation', 'refresh_summary' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
	}

	/**
	 * Register the notification retry interval.
	 *
	 * Five minutes is deliberately shorter than the 15-minute floor WPCS
	 * suggests: the hook only drains a small, already-persisted queue with
	 * exponential backoff, and a slower cadence would leave safety-report
	 * notifications sitting undelivered.
	 *
	 * @param array $schedules Registered cron schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['ssc_five_minutes'] = array(
			'interval' => 300,
			// Other plugins may run this filter before init; never load
			// translations that early.
			'display'  => did_action( 'init' ) ? __( 'NexaChatAI: every five minutes', 'nexachat-ai' ) : 'NexaChatAI: every five minutes',
		);
		return $schedules;
	}

	/**
	 * Daily run: retention purges + notification retry queue.
	 */
	public static function run() {
		SSC_Schema::purge_old(
			(int) SSC_Settings::get( 'chatlog_retention_days', 30 ),
			(int) SSC_Settings::get( 'submissions_retention_days', 0 )
		);
		SSC_Notification_Queue::purge_completed();
		SSC_Conversation::purge();
		// Provider/model changes leave chunks without a current vector.
		SSC_Embeddings::schedule();
	}
}
