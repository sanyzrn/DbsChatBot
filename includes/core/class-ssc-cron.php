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
	}

	/**
	 * Hook the runner.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::RETRY_HOOK, array( 'SSC_Module_Notifications', 'retry_failed' ) );
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
			'display'  => __( 'NexaChatAI: every five minutes', 'smart-support-chatbot' ),
		);
		return $schedules;
	}

	/**
	 * Daily run: retention purges + notification retry queue.
	 */
	public static function run() {
		SSC_Schema::purge_old(
			(int) SSC_Settings::get( 'chatlog_retention_days', 90 ),
			(int) SSC_Settings::get( 'submissions_retention_days', 0 )
		);
		SSC_Notification_Queue::purge_completed();
	}
}
