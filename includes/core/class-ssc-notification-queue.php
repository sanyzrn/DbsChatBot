<?php
/** Durable, independently claimed notification jobs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SSC_Notification_Queue {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ssc_chatbot_notifications';
	}

	/** Persist before attempting delivery; a case/channel pair is idempotent. */
	public static function enqueue( $channel, $submission_id, $attempts = 0, $next_at = 0 ) {
		global $wpdb;
		if ( ! in_array( $channel, array( 'email', 'messenger' ), true ) || $submission_id < 1 ) { return 0; }
		$table = self::table();
		$attempts = max( 0, min( 5, (int) $attempts ) );
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (submission_id, channel, status, attempts, next_at, updated_at, last_error) VALUES (%d, %s, %s, %d, %d, %d, %s)",
			$submission_id, $channel, $attempts >= 5 ? 'failed' : 'pending', $attempts, $next_at, time(), $attempts ? 'delivery-failed' : ''
		) );
		if ( false === $result ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE submission_id = %d AND channel = %s", $submission_id, $channel ) );
	}

	/** Atomic lease: two workers cannot own the same current attempt. */
	public static function claim( $id ) {
		global $wpdb;
		$table = self::table();
		$lease = wp_generate_uuid4();
		$now = time();
		$changed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'processing', lease = %s, locked_until = %d WHERE id = %d AND ((status = 'pending' AND next_at <= %d) OR (status = 'processing' AND locked_until < %d))",
			$lease, $now + 300, $id, $now, $now
		) );
		if ( 1 !== $changed ) { return null; }
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND lease = %s", $id, $lease ), ARRAY_A );
	}

	/** Record a result only while the worker still owns its lease. */
	public static function finish( $job, $ok, $error = '' ) {
		global $wpdb;
		$attempts = (int) $job['attempts'] + 1;
		$status = $ok ? 'sent' : ( $attempts >= 5 ? 'failed' : 'pending' );
		if ( 'missing-row' === $error ) { $status = 'cancelled'; }
		$allowed_errors = array( 'auth', 'model', 'credits', 'rate_limit', 'region', 'network', 'timeout', 'endpoint', 'malformed', 'missing-row', 'delivery-exception', 'mail-rejected', 'messenger-rejected' );
		$safe_error = in_array( $error, $allowed_errors, true ) ? $error : 'delivery-failed';
		return $wpdb->update( self::table(), array(
			'status' => $status,
			'attempts' => $attempts,
			'next_at' => 'pending' === $status ? time() + min( DAY_IN_SECONDS, 300 * pow( 2, $attempts - 1 ) ) : 0,
			'locked_until' => 0,
			'lease' => '',
			// Do not retain provider payloads, tokens or medical content in errors.
			'last_error' => $ok ? '' : $safe_error,
			'updated_at' => time(),
		), array( 'id' => (int) $job['id'], 'lease' => $job['lease'], 'status' => 'processing' ) );
	}

	public static function due_ids( $limit = 20 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE (status = 'pending' AND next_at <= %d) OR (status = 'processing' AND locked_until < %d) ORDER BY next_at, id LIMIT %d",
			time(), time(), max( 1, min( 100, (int) $limit ) )
		) );
	}

	public static function pending_count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending', 'processing')" );
	}

	public static function failed_count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
	}

	public static function last_failure() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( "SELECT channel, last_error AS error, attempts FROM {$table} WHERE status IN ('pending', 'failed') AND last_error <> '' ORDER BY updated_at DESC, id DESC LIMIT 1", ARRAY_A );
	}

	/** Explicit administrator action after checking delivery configuration. */
	public static function retry_exhausted() {
		global $wpdb;
		$table = self::table();
		return $wpdb->query( "UPDATE {$table} SET status = 'pending', attempts = 0, next_at = 0 WHERE status = 'failed'" );
	}

	/** Preserve old pending jobs before deleting the legacy shared option. */
	public static function migrate_legacy() {
		$legacy = get_option( 'ssc_notify_queue', array() );
		if ( ! is_array( $legacy ) || ! $legacy ) { return; }
		foreach ( $legacy as $job ) {
			if ( ! is_array( $job ) || empty( $job['channel'] ) || empty( $job['submission_id'] ) ) { return; }
			if ( ! self::enqueue( $job['channel'], (int) $job['submission_id'], isset( $job['attempts'] ) ? $job['attempts'] : 1, isset( $job['next_at'] ) ? $job['next_at'] : 0 ) ) { return; }
		}
		delete_option( 'ssc_notify_queue' );
	}

	public static function purge_completed() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status IN ('sent', 'cancelled') AND updated_at < %d", time() - 30 * DAY_IN_SECONDS ) );
	}
}
