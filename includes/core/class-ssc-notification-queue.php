<?php
/**
 * Durable, independently claimed notification jobs.
 *
 * Deliveries are persisted BEFORE they are attempted, so a crashed or
 * timed-out worker cannot lose a notification. Each job is claimed under a
 * lease, retried with exponential backoff, and left visible for manual retry
 * once its attempts are exhausted.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification job queue.
 */
class SSC_Notification_Queue {

	/**
	 * Fully-qualified queue table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ssc_chatbot_notifications';
	}

	/**
	 * Persist before attempting delivery; a case/channel pair is idempotent.
	 *
	 * @param string $channel       'email' or 'messenger'.
	 * @param int    $submission_id Submission the notification belongs to.
	 * @param int    $attempts      Attempts already spent (legacy migration).
	 * @param int    $next_at       Earliest delivery time as a Unix timestamp.
	 * @return int Job id, or 0 when the job could not be stored.
	 */
	public static function enqueue( $channel, $submission_id, $attempts = 0, $next_at = 0 ) {
		global $wpdb;
		if ( ! in_array( $channel, array( 'email', 'messenger' ), true ) || $submission_id < 1 ) {
			return 0;
		}
		$table    = self::table();
		$attempts = max( 0, min( 5, (int) $attempts ) );
		$result   = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (submission_id, channel, status, attempts, next_at, updated_at, last_error) VALUES (%d, %s, %s, %d, %d, %d, %s)",
				$submission_id,
				$channel,
				$attempts >= 5 ? 'failed' : 'pending',
				$attempts,
				$next_at,
				time(),
				$attempts ? 'delivery-failed' : ''
			)
		);
		if ( false === $result ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE submission_id = %d AND channel = %s", $submission_id, $channel ) );
	}

	/**
	 * Atomic lease: two workers cannot own the same current attempt.
	 *
	 * @param int $id Job id.
	 * @return array|null The claimed job row, or null when another worker holds it.
	 */
	public static function claim( $id ) {
		global $wpdb;
		$table   = self::table();
		$lease   = wp_generate_uuid4();
		$now     = time();
		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'processing', lease = %s, locked_until = %d WHERE id = %d AND ((status = 'pending' AND next_at <= %d) OR (status = 'processing' AND locked_until < %d))",
				$lease,
				$now + 300,
				$id,
				$now,
				$now
			)
		);
		if ( 1 !== $changed ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND lease = %s", $id, $lease ), ARRAY_A );
	}

	/**
	 * Record a result only while the worker still owns its lease.
	 *
	 * @param array  $job   The row returned by claim().
	 * @param bool   $ok    Whether delivery succeeded.
	 * @param string $error Canonical failure code; anything unrecognized is
	 *                      replaced so provider payloads never persist.
	 * @return int|false Rows updated, or false when the lease has moved on.
	 */
	public static function finish( $job, $ok, $error = '' ) {
		global $wpdb;
		$attempts = (int) $job['attempts'] + 1;
		$status   = $ok ? 'sent' : ( $attempts >= 5 ? 'failed' : 'pending' );
		if ( 'missing-row' === $error ) {
			$status = 'cancelled';
		}
		$allowed_errors = array( 'auth', 'model', 'credits', 'rate_limit', 'region', 'network', 'timeout', 'endpoint', 'malformed', 'missing-row', 'delivery-exception', 'mail-rejected', 'messenger-rejected' );
		$safe_error     = in_array( $error, $allowed_errors, true ) ? $error : 'delivery-failed';
		return $wpdb->update(
			self::table(),
			array(
				'status'       => $status,
				'attempts'     => $attempts,
				'next_at'      => 'pending' === $status ? time() + min( DAY_IN_SECONDS, 300 * pow( 2, $attempts - 1 ) ) : 0,
				'locked_until' => 0,
				'lease'        => '',
				// Do not retain provider payloads, tokens or medical content in errors.
				'last_error'   => $ok ? '' : $safe_error,
				'updated_at'   => time(),
			),
			array(
				'id'     => (int) $job['id'],
				'lease'  => $job['lease'],
				'status' => 'processing',
			)
		);
	}

	/**
	 * Ids of jobs that are due, including leases that have expired.
	 *
	 * @param int $limit Maximum ids to return (1-100).
	 * @return string[]
	 */
	public static function due_ids( $limit = 20 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE (status = 'pending' AND next_at <= %d) OR (status = 'processing' AND locked_until < %d) ORDER BY next_at, id LIMIT %d",
				time(),
				time(),
				max( 1, min( 100, (int) $limit ) )
			)
		);
	}

	/**
	 * Jobs still waiting or in flight.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending', 'processing')" );
	}

	/**
	 * Jobs that exhausted every attempt and need an administrator.
	 *
	 * @return int
	 */
	public static function failed_count() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
	}

	/**
	 * Most recent failure, for the admin notice.
	 *
	 * @return array|null channel, error, attempts.
	 */
	public static function last_failure() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( "SELECT channel, last_error AS error, attempts FROM {$table} WHERE status IN ('pending', 'failed') AND last_error <> '' ORDER BY updated_at DESC, id DESC LIMIT 1", ARRAY_A );
	}

	/**
	 * Reopen exhausted jobs. Explicit administrator action, taken after the
	 * delivery configuration has been checked.
	 *
	 * @return int|false Rows reopened.
	 */
	public static function retry_exhausted() {
		global $wpdb;
		$table = self::table();
		return $wpdb->query( "UPDATE {$table} SET status = 'pending', attempts = 0, next_at = 0 WHERE status = 'failed'" );
	}

	/**
	 * Preserve old pending jobs before deleting the legacy shared option.
	 *
	 * Unreadable entries are skipped, never fatal: one malformed row used to
	 * abort the whole migration, stranding every job queued after it. The
	 * option is dropped only once no job is left behind, so a genuine write
	 * failure retries on the next boot instead of losing deliveries.
	 */
	public static function migrate_legacy() {
		$legacy = get_option( 'ssc_notify_queue', array() );
		if ( ! is_array( $legacy ) || ! $legacy ) {
			return;
		}
		$unmigrated = array();
		foreach ( $legacy as $job ) {
			if ( ! is_array( $job ) || empty( $job['channel'] ) || empty( $job['submission_id'] ) ) {
				continue; // Not a job we can replay; dropping it loses nothing.
			}
			$stored = self::enqueue(
				$job['channel'],
				(int) $job['submission_id'],
				isset( $job['attempts'] ) ? $job['attempts'] : 1,
				isset( $job['next_at'] ) ? $job['next_at'] : 0
			);
			if ( ! $stored ) {
				$unmigrated[] = $job; // Database refused it — keep it for the next run.
			}
		}
		if ( $unmigrated ) {
			update_option( 'ssc_notify_queue', $unmigrated, false );
			return;
		}
		delete_option( 'ssc_notify_queue' );
	}

	/**
	 * Drop delivered and cancelled jobs older than 30 days.
	 *
	 * @return void
	 */
	public static function purge_completed() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status IN ('sent', 'cancelled') AND updated_at < %d", time() - 30 * DAY_IN_SECONDS ) );
	}
}
