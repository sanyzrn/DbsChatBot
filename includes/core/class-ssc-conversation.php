<?php
/**
 * Server-side conversation memory.
 *
 * The model's context used to be rebuilt from history the BROWSER sent,
 * including "assistant" turns. Anyone could therefore put words in the
 * assistant's mouth ("earlier you promised a 90% discount") and the model
 * would treat them as its own statements. The context now comes from what
 * this server actually answered, keyed by an unguessable conversation id the
 * widget generates; client-sent history is ignored when an id is present.
 *
 * Memory lives in its own table so a visitor who comes back tomorrow
 * continues the same conversation, and a signed-in user finds it on any
 * device. Only the last messages go to the model as they are; everything
 * older is folded into a short rolling summary (see refresh_summary()), so a
 * long conversation never "forgets" how it started.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversation store.
 */
class SSC_Conversation {

	/**
	 * Maximum stored messages (user + assistant) per conversation.
	 */
	const MAX_MESSAGES = 40;

	/**
	 * Messages that left the model's window before the summary is refreshed.
	 * Smaller means more calls and a fresher summary; six is about one extra
	 * call every three exchanges once a conversation is long.
	 */
	const REFRESH_AFTER = 6;

	/**
	 * Summary length cap: a long summary recreates the window problem one
	 * layer down.
	 */
	const MAX_SUMMARY_CHARS = 1200;

	/**
	 * Background summary job.
	 */
	const SUMMARY_HOOK = 'ssc_conversation_summary';

	/**
	 * Validate a conversation id (128-bit hex from the widget).
	 *
	 * @param mixed $id Raw id.
	 * @return string '' when invalid.
	 */
	public static function sanitize_id( $id ) {
		$id = is_scalar( $id ) ? strtolower( (string) $id ) : '';
		return preg_match( '/^[a-f0-9]{24,64}$/', $id ) ? $id : '';
	}

	/**
	 * Stored key; salted so raw ids never appear in the database for visitors.
	 *
	 * @param string $id Conversation id.
	 * @return string 64 hex characters.
	 */
	public static function hash( $id ) {
		return hash_hmac( 'sha256', (string) $id, wp_salt( 'nonce' ) );
	}

	/**
	 * How long an idle conversation is remembered, in days. Health
	 * conversations are kept for the shortest useful time.
	 *
	 * @return int
	 */
	public static function days() {
		$days = SSC_Modules::is_active( 'pharma' ) ? 1 : (int) SSC_Settings::get( 'memory_days', 7 );
		return max( 1, (int) apply_filters( 'ssc_conversation_days', $days ) );
	}

	/**
	 * The stored row, or null when absent, expired or not this user's.
	 *
	 * A conversation a signed-in user started belongs to that user: another
	 * visitor holding the same id (it would have to be stolen) gets nothing.
	 *
	 * @param string $id Conversation id.
	 * @return array|null
	 */
	public static function row( $id ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id ) {
			return null;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'memory' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE conv_hash = %s", self::hash( $id ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( strtotime( (string) $row['updated_at'] ) < strtotime( current_time( 'mysql' ) ) - self::days() * DAY_IN_SECONDS ) {
			return null;
		}
		if ( (int) $row['user_id'] > 0 && get_current_user_id() !== (int) $row['user_id'] ) {
			return null;
		}
		$messages        = json_decode( (string) $row['messages'], true );
		$row['messages'] = is_array( $messages ) ? $messages : array();
		return $row;
	}

	/**
	 * Stored messages (role/content pairs, oldest first).
	 *
	 * @param string $id Conversation id.
	 * @return array
	 */
	public static function load( $id ) {
		$row = self::row( $id );
		return $row ? $row['messages'] : array();
	}

	/**
	 * Rolling summary of what fell out of the model's window ('' = none).
	 *
	 * @param string $id Conversation id.
	 * @return string
	 */
	public static function summary( $id ) {
		$row = self::row( $id );
		return $row ? (string) $row['summary'] : '';
	}

	/**
	 * Record one completed exchange.
	 *
	 * @param string $id       Conversation id.
	 * @param string $question User message.
	 * @param string $answer   Assistant reply actually sent.
	 * @param string $channel  web|bale|telegram.
	 */
	public static function append( $id, $question, $answer, $channel = 'web' ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id || '' === trim( (string) $answer ) ) {
			return;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'memory' );
		$hash  = self::hash( $id );
		$now   = current_time( 'mysql' );
		$user  = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$raw = $wpdb->get_row( $wpdb->prepare( "SELECT id, user_id, updated_at FROM {$table} WHERE conv_hash = %s", $hash ), ARRAY_A );
		if ( $raw && (int) $raw['user_id'] > 0 && (int) $raw['user_id'] !== $user ) {
			return; // Someone else's conversation: never written to.
		}
		$row      = $raw ? self::row( $id ) : null;
		$messages = $row ? $row['messages'] : array();
		$pair     = array(
			array(
				'role'    => 'user',
				'content' => (string) $question,
			),
			array(
				'role'    => 'assistant',
				'content' => (string) $answer,
			),
		);
		$messages = array_slice( array_merge( $messages, $pair ), -self::MAX_MESSAGES );
		$data     = array(
			'messages'   => wp_json_encode( $messages ),
			'updated_at' => $now,
		);
		if ( $raw && ! $row ) {
			// Expired: start over in the same row.
			$data += array(
				'total'      => 2,
				'summary'    => '',
				'summarized' => 0,
				'title'      => self::title_of( $question ),
				'created_at' => $now,
			);
		} elseif ( $row ) {
			$data['total'] = (int) $row['total'] + 2;
		}
		if ( $user && ( ! $raw || 0 === (int) $raw['user_id'] ) ) {
			// A visitor who signs in keeps the conversation; only signed-in
			// users' raw ids are stored, so they can reopen it elsewhere.
			$data['user_id'] = $user;
			$data['conv_id'] = $id;
		}
		if ( $raw ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
			$wpdb->update( $table, $data, array( 'id' => (int) $raw['id'] ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
			$wpdb->insert(
				$table,
				$data + array(
					'conv_hash'  => $hash,
					'channel'    => sanitize_key( $channel ) ? sanitize_key( $channel ) : 'web',
					'title'      => self::title_of( $question ),
					'total'      => 2,
					'created_at' => $now,
				)
			);
		}
		if ( self::pending_count( $id ) >= self::REFRESH_AFTER && ! wp_next_scheduled( self::SUMMARY_HOOK, array( $id ) ) ) {
			wp_schedule_single_event( time(), self::SUMMARY_HOOK, array( $id ) );
		}
	}

	/**
	 * A conversation's title: the first thing the visitor asked. Naming it
	 * with a second model call would cost money to say what the opening
	 * question already says.
	 *
	 * @param string $question First question.
	 * @return string
	 */
	protected static function title_of( $question ) {
		$title = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $question ) ) );
		return mb_strlen( $title ) > 80 ? mb_substr( $title, 0, 79 ) . '…' : $title;
	}

	/**
	 * Messages the model no longer sees: older than its window.
	 *
	 * @return int
	 */
	protected static function window() {
		return max( 0, min( 20, (int) SSC_Settings::get( 'ai_history_limit', 10 ) ) );
	}

	/**
	 * Messages outside the window that the summary does not cover yet.
	 *
	 * @param string $id Conversation id.
	 * @return int
	 */
	public static function pending_count( $id ) {
		$row = self::row( $id );
		if ( ! $row || 0 === self::window() ) {
			return 0;
		}
		return max( 0, (int) $row['total'] - self::window() - (int) $row['summarized'] );
	}

	/**
	 * Fold the messages that left the window into the rolling summary.
	 *
	 * Runs in the background after an answer (and inline only as a safety
	 * net). A failure never breaks a reply: the previous summary stays and
	 * the model simply sees what it saw before.
	 *
	 * @param string $id    Conversation id.
	 * @param bool   $force Summarize even below REFRESH_AFTER.
	 * @return bool Updated.
	 */
	public static function refresh_summary( $id, $force = false ) {
		$row = self::row( $id );
		if ( ! $row ) {
			return false;
		}
		$pending = self::pending_count( $id );
		if ( $pending < ( $force ? 1 : self::REFRESH_AFTER ) ) {
			return false;
		}
		$provider = SSC_Providers::current();
		if ( null === $provider ) {
			return false;
		}
		// Absolute positions: the stored list holds the last MAX_MESSAGES.
		$first    = (int) $row['total'] - count( $row['messages'] );
		$from     = max( 0, (int) $row['summarized'] - $first );
		$to       = (int) $row['total'] - self::window() - $first;
		$slice    = array_slice( $row['messages'], $from, max( 0, $to - $from ) );
		$lines    = array();
		$visitor  = __( 'Visitor', 'nexachat-ai' );
		$reply_by = __( 'Assistant', 'nexachat-ai' );
		foreach ( $slice as $message ) {
			$lines[] = ( 'user' === $message['role'] ? $visitor : $reply_by ) . ': ' . mb_substr( (string) $message['content'], 0, 1500 );
		}
		if ( ! $lines ) {
			return false;
		}
		$previous = trim( (string) $row['summary'] );
		$body     = ( '' !== $previous ? "PREVIOUS SUMMARY:\n" . $previous . "\n\n" : '' ) . "NEW PART:\n" . implode( "\n", $lines );
		$system   = 'This is an older part of a customer-support chat. Summarize it in at most 100 words, in the language the conversation is written in: what the visitor wants, facts they gave (names, numbers, products, order numbers, budget), what was decided and what is still open. '
			. 'Write only from this text and guess nothing. If a previous summary is given, merge it with the new part into one text. Reply with the summary only.';
		$result   = $provider->generate(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => $body,
				),
			)
		);
		if ( empty( $result['ok'] ) || '' === trim( (string) $result['text'] ) ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->update(
			SSC_Schema::live_table( 'memory' ),
			array(
				'summary'    => mb_substr( trim( wp_strip_all_tags( (string) $result['text'] ) ), 0, self::MAX_SUMMARY_CHARS ),
				'summarized' => (int) $row['total'] - self::window(),
			),
			array( 'id' => (int) $row['id'] )
		);
		return true;
	}

	/**
	 * A signed-in user's conversations, newest first.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Max rows.
	 * @return array[] id, title, updated_at, count.
	 */
	public static function threads( $user_id, $limit = 20 ) {
		if ( ! $user_id ) {
			return array();
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'memory' );
		$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::days() * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT conv_id, title, total, updated_at FROM {$table} WHERE user_id = %d AND channel = 'web' AND conv_id <> '' AND updated_at >= %s ORDER BY updated_at DESC LIMIT %d", $user_id, $since, max( 1, min( 50, (int) $limit ) ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'      => (string) $row['conv_id'],
				'title'   => (string) $row['title'],
				'count'   => (int) $row['total'],
				'updated' => SSC_Date::display( $row['updated_at'] ),
			);
		}
		return $out;
	}

	/**
	 * Forget a conversation.
	 *
	 * @param string $id Conversation id.
	 * @return bool
	 */
	public static function forget( $id ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id ) {
			return false;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'memory' );
		$where = array( 'conv_hash' => self::hash( $id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE conv_hash = %s", $where['conv_hash'] ) );
		if ( null !== $owner && (int) $owner > 0 && get_current_user_id() !== (int) $owner ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		return false !== $wpdb->delete( $table, $where );
	}

	/**
	 * Delete idle conversations past their lifetime (daily cron).
	 *
	 * @return int Rows deleted.
	 */
	public static function purge() {
		global $wpdb;
		$table = SSC_Schema::live_table( 'memory' );
		$until = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - self::days() * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE updated_at < %s", $until ) );
	}
}
