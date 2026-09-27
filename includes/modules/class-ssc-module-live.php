<?php
/**
 * Live chat & operator inbox.
 *
 * - Every conversation (website widget, and Bale/Telegram when the Messenger
 *   bot module is on) gets a thread in the inbox while this module is active.
 * - A visitor can ask for a person; an operator can also take over any
 *   running conversation. While a person is in charge, the assistant stays
 *   quiet (ssc_intercept_message) and messages flow between the widget
 *   (polling) and the operator.
 * - Operators answer in the admin inbox, or straight from Bale/Telegram by
 *   replying to the chat message the bot sent them.
 * - Waiting chats are assigned to an available operator (or left for anyone
 *   to take, in manual mode); an AI handover summary is written on demand.
 *
 * Transcripts stored here follow their own retention (live_retention_days).
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Module_Live
 */
class SSC_Module_Live extends SSC_Module {

	/** Capability granted to the chosen operators (admins always qualify). */
	const CAP = 'ssc_live_operator';

	/** Seconds after the last inbox heartbeat an operator counts as online. */
	const ONLINE_WINDOW = 120;

	/** Statuses. */
	const STATUSES = array( 'bot', 'waiting', 'human', 'closed' );

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'live';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Live chat & operator inbox', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'See conversations as they happen, take over from the assistant with one click, and answer from the dashboard or straight from Bale/Telegram. Visitors can ask for a person at any time.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'The assistant and your team in one chat: no lost customers when a question needs a person.', 'nexachat-ai' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'communication';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
	}

	/**
	 * Runtime hooks (module active).
	 */
	public function register() {
		SSC_Messenger::register();
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_cap' ), 10, 4 );
		add_filter( 'ssc_intercept_message', array( __CLASS__, 'intercept' ), 10, 4 );
		add_action( 'ssc_chat_exchange', array( __CLASS__, 'record_exchange' ), 10, 5 );
		add_action( 'ssc_messenger_message', array( __CLASS__, 'on_messenger' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'ssc_frontend_config', array( __CLASS__, 'widget_config' ) );
		add_action( SSC_Cron::HOOK, array( __CLASS__, 'purge' ) );
	}

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Operators.
	 * --------------------------------------------------------------
	 */

	/**
	 * Give the operator capability to the users chosen in the settings.
	 *
	 * @param array   $allcaps All caps.
	 * @param array   $caps    Required caps.
	 * @param array   $args    Args.
	 * @param WP_User $user    User.
	 * @return array
	 */
	public static function grant_cap( $allcaps, $caps, $args, $user ) {
		if ( in_array( self::CAP, (array) $caps, true ) && $user instanceof WP_User ) {
			if ( ! empty( $allcaps['manage_options'] ) || in_array( (int) $user->ID, array_map( 'intval', (array) SSC_Settings::get( 'live_operators', array() ) ), true ) ) {
				$allcaps[ self::CAP ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Can the current user work the inbox?
	 *
	 * @return bool
	 */
	public static function user_can() {
		return current_user_can( 'manage_options' ) || current_user_can( self::CAP );
	}

	/**
	 * Operator accounts (administrators plus the chosen users).
	 *
	 * @return WP_User[]
	 */
	public static function operators() {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 50,
			)
		);
		$extra  = array_filter( array_map( 'intval', (array) SSC_Settings::get( 'live_operators', array() ) ) );
		$users  = $admins;
		if ( $extra ) {
			$users = array_merge(
				$users,
				get_users(
					array(
						'include' => $extra,
						'number'  => 50,
					)
				)
			);
		}
		$out = array();
		foreach ( $users as $user ) {
			$out[ $user->ID ] = $user;
		}
		return array_values( $out );
	}

	/**
	 * Is the operator's inbox open right now (and not paused)?
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public static function is_online( $user_id ) {
		$seen = (int) get_user_meta( $user_id, 'ssc_live_seen', true );
		return $seen > time() - self::ONLINE_WINDOW && 'no' !== get_user_meta( $user_id, 'ssc_live_available', true );
	}

	/**
	 * Operators who can answer right now: inbox open, or linked messenger.
	 *
	 * @return int[] User ids.
	 */
	public static function reachable_operators() {
		$ids = array();
		foreach ( self::operators() as $user ) {
			if ( 'no' === get_user_meta( $user->ID, 'ssc_live_available', true ) ) {
				continue;
			}
			if ( self::is_online( $user->ID ) || ( SSC_Messenger::ready() && '' !== SSC_Messenger::operator_chat( $user->ID ) ) ) {
				$ids[] = (int) $user->ID;
			}
		}
		return $ids;
	}

	/**
	 * Is anyone able to pick up a chat?
	 *
	 * Business hours (when configured) apply: outside them nobody is
	 * expected, even if an inbox tab is open.
	 *
	 * @return bool
	 */
	public static function anyone_available() {
		if ( class_exists( 'SSC_Availability' ) && method_exists( 'SSC_Availability', 'live_status' ) ) {
			$status = SSC_Availability::live_status();
			if ( isset( $status['online'] ) && false === $status['online'] ) {
				return false;
			}
		}
		return (bool) self::reachable_operators() || ( SSC_Messenger::ready() && '' !== trim( (string) SSC_Settings::get( 'notify_chat_id', '' ) ) );
	}

	/**
	 * Pick an operator for a waiting chat (auto mode): the reachable one with
	 * the fewest open chats; 0 in manual mode or when nobody is reachable.
	 *
	 * @return int
	 */
	public static function pick_operator() {
		if ( 'manual' === SSC_Settings::get( 'live_assign', 'auto' ) ) {
			return 0;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'threads' );
		$best  = 0;
		$load  = PHP_INT_MAX;
		foreach ( self::reachable_operators() as $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- small indexed count.
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE operator_id = %d AND status IN ('waiting','human')", $user_id ) );
			// Prefer operators whose inbox is open right now.
			$count += self::is_online( $user_id ) ? 0 : 1000;
			if ( $count < $load ) {
				$load = $count;
				$best = $user_id;
			}
		}
		return $best;
	}

	/**
	 * Display name for an operator (first name when set).
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function operator_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Support team', 'nexachat-ai' );
		}
		return '' !== trim( (string) $user->first_name ) ? $user->first_name : $user->display_name;
	}

	/*
	 * --------------------------------------------------------------
	 * Threads and messages.
	 * --------------------------------------------------------------
	 */

	/**
	 * Stable, non-reversible key for a conversation id.
	 *
	 * @param string $conversation Conversation id (web) or channel key (messenger).
	 * @return string
	 */
	public static function conv_hash( $conversation ) {
		return hash_hmac( 'sha256', 'live|' . $conversation, wp_salt( 'auth' ) );
	}

	/**
	 * Thread row by id.
	 *
	 * @param int $id Thread id.
	 * @return array|null
	 */
	public static function thread( $id ) {
		global $wpdb;
		$table = SSC_Schema::live_table( 'threads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- primary key read.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Thread for a conversation, optionally created.
	 *
	 * @param string $conversation Conversation id.
	 * @param bool   $create       Create when missing.
	 * @param array  $context      channel, channel_chat, page, label.
	 * @return array|null
	 */
	public static function thread_for( $conversation, $create = false, $context = array() ) {
		global $wpdb;
		if ( '' === (string) $conversation ) {
			return null;
		}
		$table = SSC_Schema::live_table( 'threads' );
		$hash  = self::conv_hash( $conversation );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- unique key read.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE conv_hash = %s", $hash ), ARRAY_A );
		if ( $row || ! $create ) {
			if ( $row && 'closed' === $row['status'] && $create ) {
				// A closed chat that continues goes back to the assistant.
				self::set_status( $row, 'bot', 0, false );
				$row['status'] = 'bot';
			}
			return $row ? $row : null;
		}
		$now     = current_time( 'mysql' );
		$channel = isset( $context['channel'] ) && in_array( $context['channel'], array( 'web', 'bale', 'telegram' ), true ) ? $context['channel'] : 'web';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insert.
		$wpdb->insert(
			$table,
			array(
				'conv_hash'     => $hash,
				'channel'       => $channel,
				'channel_chat'  => isset( $context['channel_chat'] ) ? substr( (string) $context['channel_chat'], 0, 64 ) : '',
				'visitor_label' => isset( $context['label'] ) ? mb_substr( sanitize_text_field( (string) $context['label'] ), 0, 100 ) : '',
				'page_url'      => isset( $context['page'] ) ? substr( esc_url_raw( (string) $context['page'] ), 0, 255 ) : '',
				'status'        => 'bot',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- unique key read (race-safe: the insert may have lost).
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE conv_hash = %s", $hash ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Append a message.
	 *
	 * @param int    $thread_id Thread id.
	 * @param string $sender    visitor|bot|operator|system.
	 * @param string $body      Text.
	 * @param string $via       web|admin|bale|telegram.
	 * @param int    $operator  Operator user id.
	 * @return int Message id.
	 */
	public static function add_message( $thread_id, $sender, $body, $via = 'web', $operator = 0 ) {
		global $wpdb;
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return 0;
		}
		$messages = SSC_Schema::live_table( 'messages' );
		$threads  = SSC_Schema::live_table( 'threads' );
		$now      = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insert.
		$wpdb->insert(
			$messages,
			array(
				'thread_id'   => (int) $thread_id,
				'sender'      => in_array( $sender, array( 'visitor', 'bot', 'operator', 'system' ), true ) ? $sender : 'system',
				'operator_id' => (int) $operator,
				'body'        => mb_substr( $body, 0, 8000 ),
				'via'         => substr( sanitize_key( $via ), 0, 10 ),
				'created_at'  => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( 'visitor' === $sender ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- counter update.
			$wpdb->query( $wpdb->prepare( "UPDATE {$threads} SET updated_at = %s, unread = unread + 1 WHERE id = %d", $now, $thread_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- timestamp update.
			$wpdb->query( $wpdb->prepare( "UPDATE {$threads} SET updated_at = %s WHERE id = %d", $now, $thread_id ) );
		}
		wp_cache_delete( 'ssc_live_waiting', 'ssc' );
		return $id;
	}

	/**
	 * Messages of a thread after an id.
	 *
	 * @param int      $thread_id Thread id.
	 * @param int      $after     Message id.
	 * @param string[] $senders   Only these senders (empty = all).
	 * @param int      $limit     Max rows.
	 * @return array
	 */
	public static function messages( $thread_id, $after = 0, $senders = array(), $limit = 200 ) {
		global $wpdb;
		$table = SSC_Schema::live_table( 'messages' );
		$sql   = $wpdb->prepare( "SELECT id, sender, operator_id, body, via, created_at FROM {$table} WHERE thread_id = %d AND id > %d", $thread_id, $after ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $senders ) {
			$in   = implode( ',', array_map( array( $wpdb, 'prepare' ), array_fill( 0, count( $senders ), '%s' ), $senders ) );
			$sql .= " AND sender IN ({$in})";
		}
		$sql .= $wpdb->prepare( ' ORDER BY id ASC LIMIT %d', $limit );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- assembled from prepared parts above.
		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Change a thread's status (and owner), with a system note.
	 *
	 * @param array  $thread   Thread row.
	 * @param string $status  bot|waiting|human|closed.
	 * @param int    $operator Operator id (human status).
	 * @param bool   $note     Add a system message.
	 */
	public static function set_status( $thread, $status, $operator = 0, $note = true ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		$table = SSC_Schema::live_table( 'threads' );
		$data  = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( 'waiting' === $status && 'waiting' !== $thread['status'] ) {
			$data['waiting_since'] = current_time( 'mysql' );
		}
		if ( 'human' === $status || 'waiting' === $status ) {
			$data['operator_id'] = (int) $operator;
		}
		if ( 'bot' === $status || 'closed' === $status ) {
			$data['operator_id'] = 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
		$wpdb->update( $table, $data, array( 'id' => (int) $thread['id'] ) );
		wp_cache_delete( 'ssc_live_waiting', 'ssc' );
		if ( ! $note ) {
			return;
		}
		// Notes are part of the conversation: written in the widget's language.
		$text = SSC_I18n::in_widget_locale(
			function () use ( $status, $operator ) {
				$texts = array(
					'waiting' => __( 'The visitor asked to talk to a person.', 'nexachat-ai' ),
					/* translators: %s: operator name. */
					'human'   => sprintf( __( '%s joined the chat.', 'nexachat-ai' ), SSC_Module_Live::operator_name( $operator ) ),
					'bot'     => __( 'The assistant is answering again.', 'nexachat-ai' ),
					'closed'  => __( 'The chat was closed.', 'nexachat-ai' ),
				);
				return $texts[ $status ];
			}
		);
		self::add_message( (int) $thread['id'], 'system', $text, 'system', (int) $operator );
	}

	/**
	 * Number of chats waiting for a person (menu badge).
	 *
	 * @return int
	 */
	public static function waiting_count() {
		$cached = wp_cache_get( 'ssc_live_waiting', 'ssc' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'threads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed count, cached.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'waiting'" );
		wp_cache_set( 'ssc_live_waiting', $count, 'ssc', 30 );
		return $count;
	}

	/*
	 * --------------------------------------------------------------
	 * Conversation hooks.
	 * --------------------------------------------------------------
	 */

	/**
	 * While a person handles (or is about to handle) the chat, visitor
	 * messages go to them and the assistant stays quiet.
	 *
	 * @param null|array      $intercept    Previous value.
	 * @param string          $message      Visitor message.
	 * @param string          $conversation Conversation id.
	 * @param SSC_Chat_Engine $engine       Engine.
	 * @return null|array
	 */
	public static function intercept( $intercept, $message, $conversation, $engine = null ) {
		if ( null !== $intercept || '' === (string) $conversation || ! SSC_Modules::is_active( 'live' ) ) {
			return $intercept;
		}
		$thread = self::thread_for( $conversation );
		if ( ! $thread || ! in_array( $thread['status'], array( 'waiting', 'human' ), true ) ) {
			return null;
		}
		$via = 'web' === $thread['channel'] ? 'web' : $thread['channel'];
		self::add_message( (int) $thread['id'], 'visitor', $message, $via );
		self::forward_to_operators( $thread, $message );
		return array(
			'reply' => '',
			'flags' => array( 'live' => $thread['status'] ),
		);
	}

	/**
	 * Keep the transcript of assistant-answered exchanges.
	 *
	 * @param string $conversation Conversation id.
	 * @param string $question     Visitor message.
	 * @param string $reply        Assistant reply.
	 * @param string $source       Source.
	 * @param array  $context      Channel context.
	 */
	public static function record_exchange( $conversation, $question, $reply, $source, $context = array() ) {
		if ( ( isset( $context['channel'] ) && 'preview' === $context['channel'] ) || ! SSC_Modules::is_active( 'live' ) ) {
			return;
		}
		$thread = self::thread_for( $conversation, true, (array) $context );
		if ( ! $thread ) {
			return;
		}
		$via = isset( $context['channel'] ) && 'web' !== $context['channel'] ? $context['channel'] : 'web';
		self::add_message( (int) $thread['id'], 'visitor', $question, $via );
		self::add_message( (int) $thread['id'], 'bot', $reply, $via );
		if ( ! empty( $context['page'] ) && '' === $thread['page_url'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
			$wpdb->update( SSC_Schema::live_table( 'threads' ), array( 'page_url' => substr( esc_url_raw( (string) $context['page'] ), 0, 255 ) ), array( 'id' => (int) $thread['id'] ) );
		}
	}

	/**
	 * A visitor asks for a person.
	 *
	 * @param string $conversation Conversation id.
	 * @param array  $context      Channel context (+ label).
	 * @return array status, available, message.
	 */
	public static function request_human( $conversation, $context = array() ) {
		$thread = self::thread_for( $conversation, true, $context );
		if ( ! $thread ) {
			return array(
				'status'    => 'bot',
				'available' => false,
				'message'   => self::offline_text(),
			);
		}
		if ( ! empty( $context['label'] ) && '' === $thread['visitor_label'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
			$wpdb->update( SSC_Schema::live_table( 'threads' ), array( 'visitor_label' => mb_substr( sanitize_text_field( (string) $context['label'] ), 0, 100 ) ), array( 'id' => (int) $thread['id'] ) );
			$thread['visitor_label'] = $context['label'];
		}
		if ( 'human' === $thread['status'] ) {
			return array(
				'status'    => 'human',
				'available' => true,
				'message'   => '',
			);
		}
		$available = self::anyone_available();
		if ( ! $available ) {
			return array(
				'status'    => $thread['status'],
				'available' => false,
				'message'   => self::offline_text(),
			);
		}
		if ( 'waiting' !== $thread['status'] ) {
			self::set_status( $thread, 'waiting', self::pick_operator() );
			$thread = self::thread( (int) $thread['id'] );
			self::notify_waiting( $thread );
		}
		return array(
			'status'    => 'waiting',
			'available' => true,
			'message'   => __( 'I have asked a colleague to join. Please stay in this chat; you can keep writing in the meantime.', 'nexachat-ai' ),
		);
	}

	/**
	 * Message shown when nobody can answer.
	 *
	 * @return string
	 */
	public static function offline_text() {
		$text = trim( (string) SSC_Settings::get( 'live_offline_text', '' ) );
		return '' !== $text ? $text : __( 'Our team is not available right now. Leave your number and we will call you back.', 'nexachat-ai' );
	}

	/**
	 * Message shown when an operator joins.
	 *
	 * @param int $operator Operator id.
	 * @return string
	 */
	public static function join_text( $operator ) {
		$text = trim( (string) SSC_Settings::get( 'live_join_text', '' ) );
		if ( '' !== $text ) {
			return str_replace( '{name}', self::operator_name( $operator ), $text );
		}
		/* translators: %s: operator name. */
		return sprintf( __( 'Hi, this is %s from the support team. How can I help?', 'nexachat-ai' ), self::operator_name( $operator ) );
	}

	/**
	 * An operator answers (admin inbox or messenger). Takes the chat over.
	 *
	 * @param array  $thread   Thread row.
	 * @param string $text     Reply.
	 * @param int    $operator Operator id.
	 * @param string $via      admin|bale|telegram.
	 * @return int Message id.
	 */
	public static function operator_reply( $thread, $text, $operator, $via = 'admin' ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		if ( 'human' !== $thread['status'] || (int) $thread['operator_id'] !== (int) $operator ) {
			self::set_status( $thread, 'human', $operator );
		}
		$id = self::add_message( (int) $thread['id'], 'operator', $text, $via, $operator );
		self::mark_read( $thread );
		if ( 'web' !== $thread['channel'] && '' !== $thread['channel_chat'] ) {
			SSC_Messenger::send( $thread['channel_chat'], $text );
		}
		return $id;
	}

	/**
	 * Take over / release / close from the inbox or messenger.
	 *
	 * @param array  $thread   Thread.
	 * @param string $action   take|release|close.
	 * @param int    $operator Operator id.
	 * @return bool
	 */
	public static function act( $thread, $action, $operator ) {
		switch ( $action ) {
			case 'take':
				self::set_status( $thread, 'human', $operator );
				$greeting = SSC_I18n::in_widget_locale(
					function () use ( $operator ) {
						return SSC_Module_Live::join_text( $operator );
					}
				);
				self::add_message( (int) $thread['id'], 'operator', $greeting, 'admin', $operator );
				if ( 'web' !== $thread['channel'] && '' !== $thread['channel_chat'] ) {
					SSC_Messenger::send( $thread['channel_chat'], $greeting );
				}
				return true;
			case 'release':
				self::set_status( $thread, 'bot', $operator );
				if ( 'web' !== $thread['channel'] && '' !== $thread['channel_chat'] ) {
					SSC_Messenger::send(
						$thread['channel_chat'],
						SSC_I18n::in_widget_locale(
							function () {
								return __( 'The assistant is answering again.', 'nexachat-ai' );
							}
						)
					);
				}
				return true;
			case 'close':
				self::set_status( $thread, 'closed', $operator );
				return true;
		}
		return false;
	}

	/**
	 * Reset the unread counter.
	 *
	 * @param array $thread Thread.
	 */
	public static function mark_read( $thread ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
		$wpdb->update( SSC_Schema::live_table( 'threads' ), array( 'unread' => 0 ), array( 'id' => (int) $thread['id'] ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Messenger side.
	 * --------------------------------------------------------------
	 */

	/**
	 * Where to reach operators for a thread: its operator's private chat,
	 * else every linked operator, else the notification chat.
	 *
	 * @param array $thread Thread.
	 * @return string[] Chat ids.
	 */
	protected static function operator_chats( $thread ) {
		if ( ! SSC_Messenger::ready() ) {
			return array();
		}
		if ( ! empty( $thread['operator_id'] ) ) {
			$chat = SSC_Messenger::operator_chat( (int) $thread['operator_id'] );
			if ( '' !== $chat ) {
				return array( $chat );
			}
		}
		$chats = array();
		foreach ( self::operators() as $user ) {
			if ( 'no' === get_user_meta( $user->ID, 'ssc_live_available', true ) ) {
				continue;
			}
			$chat = SSC_Messenger::operator_chat( $user->ID );
			if ( '' !== $chat ) {
				$chats[] = $chat;
			}
		}
		if ( ! $chats ) {
			$group = trim( (string) SSC_Settings::get( 'notify_chat_id', '' ) );
			if ( '' !== $group ) {
				$chats[] = $group;
			}
		}
		return array_values( array_unique( $chats ) );
	}

	/**
	 * Remember which thread a messenger message belongs to (for replies).
	 *
	 * @param array|WP_Error $sent      Sent message.
	 * @param int            $thread_id Thread id.
	 */
	protected static function remember_ref( $sent, $thread_id ) {
		if ( ! is_array( $sent ) || empty( $sent['message_id'] ) || empty( $sent['chat']['id'] ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insert.
		$wpdb->replace(
			SSC_Schema::live_table( 'refs' ),
			array(
				'ref'        => SSC_Messenger::platform() . ':' . $sent['chat']['id'] . ':' . $sent['message_id'],
				'thread_id'  => (int) $thread_id,
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Thread a replied-to messenger message belongs to.
	 *
	 * @param string $chat       Chat id.
	 * @param int    $message_id Message id.
	 * @return array|null
	 */
	protected static function thread_by_ref( $chat, $message_id ) {
		global $wpdb;
		$table = SSC_Schema::live_table( 'refs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- unique key read.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT thread_id FROM {$table} WHERE ref = %s", SSC_Messenger::platform() . ':' . $chat . ':' . (int) $message_id ) );
		return $id ? self::thread( $id ) : null;
	}

	/**
	 * Short label for a thread in messenger texts.
	 *
	 * @param array $thread Thread.
	 * @return string
	 */
	protected static function label( $thread ) {
		$who = '' !== $thread['visitor_label'] ? $thread['visitor_label'] : __( 'Visitor', 'nexachat-ai' );
		return '#' . (int) $thread['id'] . ' · ' . $who;
	}

	/**
	 * Tell operators a chat is waiting (recent visitor messages included).
	 *
	 * @param array $thread Thread.
	 */
	public static function notify_waiting( $thread ) {
		$chats = self::operator_chats( $thread );
		if ( ! $chats ) {
			return;
		}
		$recent = array_slice( self::messages( (int) $thread['id'], 0, array( 'visitor' ) ), -3 );
		$lines  = array(
			/* translators: %s: chat label. */
			'🙋 ' . sprintf( __( 'A visitor wants to talk to a person (%s)', 'nexachat-ai' ), self::label( $thread ) ),
		);
		foreach ( $recent as $message ) {
			$lines[] = '• ' . mb_substr( $message['body'], 0, 300 );
		}
		if ( '' !== $thread['page_url'] ) {
			$lines[] = $thread['page_url'];
		}
		$lines[] = __( 'Reply to this message to answer the visitor.', 'nexachat-ai' );
		$markup  = array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => __( 'Take over', 'nexachat-ai' ),
						'callback_data' => 'live:take:' . (int) $thread['id'],
					),
				),
			),
		);
		foreach ( $chats as $chat ) {
			self::remember_ref( SSC_Messenger::send( $chat, implode( "\n", $lines ), array( 'reply_markup' => $markup ) ), (int) $thread['id'] );
		}
	}

	/**
	 * Relay a visitor message to the operators of a handled chat.
	 *
	 * @param array  $thread Thread.
	 * @param string $text   Visitor message.
	 */
	protected static function forward_to_operators( $thread, $text ) {
		foreach ( self::operator_chats( $thread ) as $chat ) {
			self::remember_ref( SSC_Messenger::send( $chat, '💬 ' . self::label( $thread ) . "\n" . $text ), (int) $thread['id'] );
		}
	}

	/**
	 * Operator messages from Bale/Telegram: replies, buttons and commands.
	 *
	 * @param array $message Message.
	 * @param array $context Messenger context.
	 */
	public static function on_messenger( $message, $context ) {
		$operator = (int) $context['operator'];
		if ( ! $operator || ! ( user_can( $operator, 'manage_options' ) || user_can( $operator, self::CAP ) ) ) {
			return; // Customers are the Messenger bot's business.
		}
		$text = (string) $context['text'];

		// Inline button: live:<action>:<thread id>.
		if ( preg_match( '/^live:(take|release|close):(\d+)$/', $text, $m ) ) {
			$thread = self::thread( (int) $m[2] );
			if ( $thread ) {
				self::act( $thread, $m[1], $operator );
				SSC_Messenger::send( $context['chat'], self::label( $thread ) . ' — ' . self::status_label( 'take' === $m[1] ? 'human' : ( 'release' === $m[1] ? 'bot' : 'closed' ) ) );
			}
			return;
		}

		$reply_to = isset( $message['reply_to_message']['message_id'] ) ? (int) $message['reply_to_message']['message_id'] : 0;
		$thread   = $reply_to ? self::thread_by_ref( $context['chat'], $reply_to ) : null;
		if ( ! $thread ) {
			// With the Messenger bot on, an operator's own messages are a normal chat (handy for testing).
			if ( $context['private'] && ! SSC_Modules::is_active( 'messenger' ) ) {
				SSC_Messenger::send( $context['chat'], __( 'Reply to a chat message (swipe or long-press it, then Reply) to answer that visitor.', 'nexachat-ai' ) );
			}
			return;
		}
		if ( '/bot' === $text || '/close' === $text ) {
			self::act( $thread, '/bot' === $text ? 'release' : 'close', $operator );
			SSC_Messenger::send( $context['chat'], self::label( $thread ) . ' — ' . self::status_label( '/bot' === $text ? 'bot' : 'closed' ) );
			return;
		}
		if ( '' !== $text ) {
			self::operator_reply( $thread, $text, $operator, $context['platform'] );
		}
	}

	/**
	 * Human status name.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'bot'     => __( 'Assistant answering', 'nexachat-ai' ),
			'waiting' => __( 'Waiting for a person', 'nexachat-ai' ),
			'human'   => __( 'With an operator', 'nexachat-ai' ),
			'closed'  => __( 'Closed', 'nexachat-ai' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/*
	 * --------------------------------------------------------------
	 * AI handover summary.
	 * --------------------------------------------------------------
	 */

	/**
	 * Write (and store) a short handover note for the operator.
	 *
	 * @param array $thread Thread.
	 * @return string|WP_Error
	 */
	public static function summarize( $thread ) {
		$provider = SSC_Providers::current();
		if ( null === $provider ) {
			return new WP_Error( 'ssc_no_ai', __( 'Configure an AI provider to get summaries.', 'nexachat-ai' ) );
		}
		$lines = array();
		foreach ( array_slice( self::messages( (int) $thread['id'] ), -40 ) as $m ) {
			if ( 'system' === $m['sender'] ) {
				continue;
			}
			$who     = array(
				'visitor'  => 'Visitor',
				'bot'      => 'Assistant',
				'operator' => 'Operator',
			);
			$lines[] = $who[ $m['sender'] ] . ': ' . mb_substr( $m['body'], 0, 600 );
		}
		if ( count( $lines ) < 1 ) {
			return new WP_Error( 'ssc_empty', __( 'There is nothing to summarize yet.', 'nexachat-ai' ) );
		}
		$system = 'You write handover notes for a customer-support operator who is about to join a chat. '
			. 'Answer in the language the visitor writes in. Give exactly three short bullet points: '
			. '1) what the visitor wants, 2) what they already asked or provided (names, numbers, orders), '
			. '3) where the assistant could not help or what is still open. No greeting, no advice to the operator.';
		$result = $provider->generate(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => implode( "\n", $lines ),
				),
			)
		);
		if ( empty( $result['ok'] ) || '' === trim( (string) $result['text'] ) ) {
			return new WP_Error( 'ssc_ai_failed', __( 'The summary could not be written right now.', 'nexachat-ai' ) );
		}
		$summary = mb_substr( trim( wp_strip_all_tags( (string) $result['text'] ) ), 0, 2000 );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
		$wpdb->update( SSC_Schema::live_table( 'threads' ), array( 'summary' => $summary ), array( 'id' => (int) $thread['id'] ) );
		return $summary;
	}

	/*
	 * --------------------------------------------------------------
	 * Widget.
	 * --------------------------------------------------------------
	 */

	/**
	 * Widget configuration for live chat.
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function widget_config( $config ) {
		$config['features']['live'] = true;
		$config['live']             = array(
			'waitMinutes' => (int) SSC_Settings::get( 'live_wait_minutes', 3 ),
			'i18n'        => array(
				'talkToPerson' => __( 'Talk to a person', 'nexachat-ai' ),
				'waiting'      => __( 'Waiting for a colleague to join…', 'nexachat-ai' ),
				'joined'       => __( 'joined the chat', 'nexachat-ai' ),
				'backToBot'    => __( 'Back to the assistant', 'nexachat-ai' ),
				'operator'     => __( 'Support team', 'nexachat-ai' ),
				'noAnswer'     => __( 'Nobody has picked up yet. Would you like to leave your number instead?', 'nexachat-ai' ),
				'leaveNumber'  => __( 'Leave my number', 'nexachat-ai' ),
				'keepWaiting'  => __( 'Keep waiting', 'nexachat-ai' ),
			),
		);
		return $config;
	}

	/*
	 * --------------------------------------------------------------
	 * REST.
	 * --------------------------------------------------------------
	 */

	/**
	 * Routes.
	 */
	public function routes() {
		$public = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( __CLASS__, 'public_permission' ),
		);
		register_rest_route( 'ssc/v1', '/live/request', array_merge( $public, array( 'callback' => array( __CLASS__, 'rest_request' ) ) ) );
		register_rest_route( 'ssc/v1', '/live/poll', array_merge( $public, array( 'callback' => array( __CLASS__, 'rest_poll' ) ) ) );
		register_rest_route( 'ssc/v1', '/live/leave', array_merge( $public, array( 'callback' => array( __CLASS__, 'rest_leave' ) ) ) );

		$admin = array(
			'permission_callback' => array( __CLASS__, 'admin_permission' ),
		);
		register_rest_route(
			'ssc/v1',
			'/live/admin/threads',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( __CLASS__, 'rest_threads' ),
				)
			)
		);
		register_rest_route(
			'ssc/v1',
			'/live/admin/thread/(?P<id>\d+)',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( __CLASS__, 'rest_thread' ),
				)
			)
		);
		foreach ( array( 'reply', 'action', 'summary' ) as $verb ) {
			register_rest_route(
				'ssc/v1',
				'/live/admin/thread/(?P<id>\d+)/' . $verb,
				array_merge(
					$admin,
					array(
						'methods'  => WP_REST_Server::CREATABLE,
						'callback' => array( __CLASS__, 'rest_' . $verb ),
					)
				)
			);
		}
		register_rest_route(
			'ssc/v1',
			'/live/admin/presence',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'rest_presence' ),
				)
			)
		);
		register_rest_route(
			'ssc/v1',
			'/live/admin/link',
			array_merge(
				$admin,
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'rest_link' ),
				)
			)
		);
	}

	/**
	 * Public routes: the assistant must be live; cheap per-IP flood guard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function public_permission( $request ) {
		if ( ! SSC_Setup::is_live() ) {
			return new WP_Error( 'ssc_not_live', __( 'The assistant is not available yet.', 'nexachat-ai' ), array( 'status' => 403 ) );
		}
		if ( strlen( $request->get_body() ) > 8192 ) {
			return new WP_Error( 'ssc_too_large', __( 'The request is too large.', 'nexachat-ai' ), array( 'status' => 413 ) );
		}
		$engine = new SSC_Chat_Engine();
		$key    = 'ssc_live_rate_' . md5( $engine->client_ip() );
		$count  = (int) get_transient( $key );
		if ( $count > 90 ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Please slow down and try again in a minute.', 'nexachat-ai' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Admin routes: inbox users only.
	 *
	 * @return true|WP_Error
	 */
	public static function admin_permission() {
		return self::user_can() ? true : new WP_Error( 'ssc_forbidden', __( 'Insufficient permissions.', 'nexachat-ai' ), array( 'status' => 403 ) );
	}

	/**
	 * Visitor asks for a person.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_request( $request ) {
		$conv = SSC_Conversation::sanitize_id( (string) $request->get_param( 'conv' ) );
		if ( '' === $conv ) {
			return new WP_Error( 'ssc_bad_request', __( 'The chat could not be identified. Please refresh the page.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		$result = self::request_human(
			$conv,
			array(
				'channel' => 'web',
				'page'    => (string) $request->get_param( 'page' ),
			)
		);
		return rest_ensure_response( $result );
	}

	/**
	 * Visitor polls for operator messages.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_poll( $request ) {
		$conv   = SSC_Conversation::sanitize_id( (string) $request->get_param( 'conv' ) );
		$thread = '' !== $conv ? self::thread_for( $conv ) : null;
		if ( ! $thread ) {
			return rest_ensure_response(
				array(
					'status'   => 'bot',
					'messages' => array(),
				)
			);
		}
		$after    = max( 0, (int) $request->get_param( 'after' ) );
		$messages = array();
		foreach ( self::messages( (int) $thread['id'], $after, array( 'operator', 'system' ), 50 ) as $m ) {
			$messages[] = array(
				'id'     => (int) $m['id'],
				'sender' => $m['sender'],
				'name'   => 'operator' === $m['sender'] ? self::operator_name( (int) $m['operator_id'] ) : '',
				'body'   => $m['body'],
			);
		}
		$waited   = 'waiting' === $thread['status'] && ! empty( $thread['waiting_since'] )
			? max( 0, current_time( 'timestamp' ) - strtotime( $thread['waiting_since'] ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with a local-time column.
			: 0;
		$response = rest_ensure_response(
			array(
				'status'   => $thread['status'],
				'operator' => (int) $thread['operator_id'] ? self::operator_name( (int) $thread['operator_id'] ) : '',
				'messages' => $messages,
				'waited'   => $waited,
			)
		);
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Visitor goes back to the assistant.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_leave( $request ) {
		$conv   = SSC_Conversation::sanitize_id( (string) $request->get_param( 'conv' ) );
		$thread = '' !== $conv ? self::thread_for( $conv ) : null;
		if ( $thread && in_array( $thread['status'], array( 'waiting', 'human' ), true ) ) {
			self::set_status( $thread, 'bot' );
		}
		return rest_ensure_response( array( 'status' => 'bot' ) );
	}

	/**
	 * Thread payload for the inbox.
	 *
	 * @param array $t Thread row.
	 * @return array
	 */
	protected static function thread_payload( $t ) {
		return array(
			'id'           => (int) $t['id'],
			'status'       => $t['status'],
			'statusLabel'  => self::status_label( $t['status'] ),
			'channel'      => $t['channel'],
			'label'        => '' !== $t['visitor_label'] ? $t['visitor_label'] : sprintf( /* translators: %d: chat number. */ __( 'Visitor #%d', 'nexachat-ai' ), (int) $t['id'] ),
			'page'         => $t['page_url'],
			'operator'     => (int) $t['operator_id'],
			'operatorName' => (int) $t['operator_id'] ? self::operator_name( (int) $t['operator_id'] ) : '',
			'unread'       => (int) $t['unread'],
			'updated'      => $t['updated_at'],
			'updatedHuman' => SSC_Date::display( $t['updated_at'] ),
			'summary'      => (string) $t['summary'],
		);
	}

	/**
	 * Inbox list (also the operator heartbeat, and a messenger poll in polling mode).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_threads( $request ) {
		update_user_meta( get_current_user_id(), 'ssc_live_seen', time() );
		if ( 'polling' === SSC_Messenger::mode() && ! get_transient( 'ssc_live_polled' ) ) {
			set_transient( 'ssc_live_polled', 1, 5 );
			SSC_Messenger::poll();
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'threads' );
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with local-time columns.
		if ( 'closed' === $scope ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed list.
			$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'closed' ORDER BY updated_at DESC LIMIT 50", ARRAY_A );
		} else {
			// Open chats first, then conversations active in the last day.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed list.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status IN ('waiting','human') OR ( status = 'bot' AND updated_at >= %s )
					ORDER BY FIELD(status,'waiting','human','bot'), updated_at DESC LIMIT 100",
					$since
				),
				ARRAY_A
			);
		}
		$items = array_map( array( __CLASS__, 'thread_payload' ), (array) $rows );
		return rest_ensure_response(
			array(
				'items'     => $items,
				'waiting'   => self::waiting_count(),
				'available' => 'no' !== get_user_meta( get_current_user_id(), 'ssc_live_available', true ),
			)
		);
	}

	/**
	 * One thread with messages.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_thread( $request ) {
		$thread = self::thread( (int) $request['id'] );
		if ( ! $thread ) {
			return new WP_Error( 'ssc_not_found', __( 'Chat not found.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		$after    = max( 0, (int) $request->get_param( 'after' ) );
		$messages = array();
		foreach ( self::messages( (int) $thread['id'], $after ) as $m ) {
			$messages[] = array(
				'id'     => (int) $m['id'],
				'sender' => $m['sender'],
				'name'   => 'operator' === $m['sender'] ? self::operator_name( (int) $m['operator_id'] ) : '',
				'body'   => $m['body'],
				'via'    => $m['via'],
				'at'     => SSC_Date::display( $m['created_at'] ),
			);
		}
		if ( (int) $thread['unread'] ) {
			self::mark_read( $thread );
		}
		return rest_ensure_response(
			array(
				'thread'   => self::thread_payload( $thread ),
				'messages' => $messages,
			)
		);
	}

	/**
	 * Operator reply from the inbox.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_reply( $request ) {
		$thread = self::thread( (int) $request['id'] );
		$text   = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'body' ) ), 0, 4000 );
		if ( ! $thread || '' === trim( $text ) ) {
			return new WP_Error( 'ssc_bad_request', __( 'Write a message first.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		if ( 'closed' === $thread['status'] && 'web' === $thread['channel'] ) {
			return new WP_Error( 'ssc_closed', __( 'This chat is closed; the visitor will not see new messages.', 'nexachat-ai' ), array( 'status' => 409 ) );
		}
		$id = self::operator_reply( $thread, $text, get_current_user_id(), 'admin' );
		return rest_ensure_response( array( 'id' => $id ) );
	}

	/**
	 * Take over / release / close.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_action( $request ) {
		$thread = self::thread( (int) $request['id'] );
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( ! $thread || ! self::act( $thread, $action, get_current_user_id() ) ) {
			return new WP_Error( 'ssc_bad_request', __( 'That action is not possible.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'thread' => self::thread_payload( self::thread( (int) $thread['id'] ) ) ) );
	}

	/**
	 * AI handover summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_summary( $request ) {
		$thread = self::thread( (int) $request['id'] );
		if ( ! $thread ) {
			return new WP_Error( 'ssc_not_found', __( 'Chat not found.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		$summary = self::summarize( $thread );
		if ( is_wp_error( $summary ) ) {
			$summary->add_data( array( 'status' => 400 ) );
			return $summary;
		}
		return rest_ensure_response( array( 'summary' => $summary ) );
	}

	/**
	 * Operator availability switch.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_presence( $request ) {
		$available = rest_sanitize_boolean( $request->get_param( 'available' ) );
		update_user_meta( get_current_user_id(), 'ssc_live_available', $available ? 'yes' : 'no' );
		update_user_meta( get_current_user_id(), 'ssc_live_seen', time() );
		return rest_ensure_response( array( 'available' => $available ) );
	}

	/**
	 * Messenger account linking for the current operator.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_link( $request ) {
		$user_id = get_current_user_id();
		if ( 'unlink' === sanitize_key( (string) $request->get_param( 'do' ) ) ) {
			SSC_Messenger::unlink( $user_id );
			return rest_ensure_response( array( 'linked' => false ) );
		}
		if ( ! SSC_Messenger::ready() ) {
			return new WP_Error( 'ssc_messenger_token', __( 'Add the bot token in Settings → Modules first.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		$code = SSC_Messenger::link_code( $user_id );
		return rest_ensure_response(
			array(
				'code'     => $code,
				'url'      => SSC_Messenger::link_url( $code ),
				'platform' => SSC_Messenger::platform_label(),
			)
		);
	}

	/*
	 * --------------------------------------------------------------
	 * Admin page.
	 * --------------------------------------------------------------
	 */

	/**
	 * Menu entry (with a waiting-chats badge).
	 */
	public function menu() {
		if ( ! self::user_can() ) {
			return;
		}
		$waiting = self::waiting_count();
		$badge   = $waiting ? ' <span class="awaiting-mod count-' . (int) $waiting . '"><span class="pending-count">' . (int) $waiting . '</span></span>' : '';
		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page( 'ssc-dashboard', __( 'Live chat', 'nexachat-ai' ), __( 'Live chat', 'nexachat-ai' ) . $badge, 'manage_options', 'ssc-live', array( $this, 'render_page' ) );
		} else {
			add_menu_page( __( 'Live chat', 'nexachat-ai' ), __( 'Live chat', 'nexachat-ai' ) . $badge, self::CAP, 'ssc-live', array( $this, 'render_page' ), 'dashicons-format-chat', 3 );
		}
	}

	/**
	 * Inbox assets.
	 *
	 * @param string $hook Page hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'ssc-live' ) ) {
			return;
		}
		wp_enqueue_style( 'ssc-admin', SSC_CHATBOT_URL . 'assets/css/admin.css', array(), SSC_CHATBOT_VERSION );
		wp_enqueue_script( 'ssc-live-inbox', SSC_CHATBOT_URL . 'assets/js/live-inbox.js', array(), SSC_CHATBOT_VERSION, true );
		$canned = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) SSC_Settings::get( 'live_canned', '' ) ) ), 'strlen' ) );
		wp_localize_script(
			'ssc-live-inbox',
			'SSCLiveInbox',
			array(
				'root'   => esc_url_raw( rest_url( 'ssc/v1/live/admin/' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'me'     => get_current_user_id(),
				'canned' => $canned,
				'linked' => '' !== SSC_Messenger::operator_chat( get_current_user_id() ),
				'bot'    => SSC_Messenger::ready(),
				'i18n'   => array(
					'empty'       => __( 'No conversations yet. They appear here as visitors chat.', 'nexachat-ai' ),
					'pick'        => __( 'Choose a conversation on the left.', 'nexachat-ai' ),
					'take'        => __( 'Take over', 'nexachat-ai' ),
					'release'     => __( 'Return to the assistant', 'nexachat-ai' ),
					'close'       => __( 'Close chat', 'nexachat-ai' ),
					'send'        => __( 'Send', 'nexachat-ai' ),
					'placeholder' => __( 'Write your reply… (Enter to send, Shift+Enter for a new line)', 'nexachat-ai' ),
					'summary'     => __( 'AI summary', 'nexachat-ai' ),
					'summarize'   => __( 'Write a summary', 'nexachat-ai' ),
					'summarizing' => __( 'Writing…', 'nexachat-ai' ),
					'canned'      => __( 'Saved replies', 'nexachat-ai' ),
					'visitor'     => __( 'Visitor', 'nexachat-ai' ),
					'assistant'   => __( 'Assistant', 'nexachat-ai' ),
					'page'        => __( 'Page', 'nexachat-ai' ),
					'available'   => __( 'Available', 'nexachat-ai' ),
					'away'        => __( 'Away', 'nexachat-ai' ),
					'link'        => __( 'Answer from my messenger', 'nexachat-ai' ),
					'linked'      => __( 'Messenger connected', 'nexachat-ai' ),
					'unlink'      => __( 'Disconnect', 'nexachat-ai' ),
					/* translators: %s: link code. */
					'linkHelp'    => __( 'Open the bot and send this code: %s (valid for 15 minutes).', 'nexachat-ai' ),
					'openBot'     => __( 'Open the bot', 'nexachat-ai' ),
					'error'       => __( 'Something went wrong. Please try again.', 'nexachat-ai' ),
					'closedNote'  => __( 'This chat is closed.', 'nexachat-ai' ),
					'open'        => __( 'Open', 'nexachat-ai' ),
					'closedTab'   => __( 'Closed', 'nexachat-ai' ),
				),
			)
		);
	}

	/**
	 * Render the inbox shell (the script fills it).
	 */
	public function render_page() {
		if ( ! self::user_can() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nexachat-ai' ) );
		}
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-live.php';
	}

	/*
	 * --------------------------------------------------------------
	 * Retention.
	 * --------------------------------------------------------------
	 */

	/**
	 * Delete transcripts older than the retention period.
	 */
	public static function purge() {
		global $wpdb;
		$days     = max( 1, (int) SSC_Settings::get( 'live_retention_days', 30 ) );
		$cutoff   = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local-time columns.
		$threads  = SSC_Schema::live_table( 'threads' );
		$messages = SSC_Schema::live_table( 'messages' );
		$refs     = SSC_Schema::live_table( 'refs' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- bounded retention purge.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$threads} WHERE updated_at < %s AND status IN ('bot','closed') LIMIT 1000", $cutoff ) );
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( "DELETE FROM {$messages} WHERE thread_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
			$wpdb->query( "DELETE FROM {$refs} WHERE thread_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
			$wpdb->query( "DELETE FROM {$threads} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
		}
		// phpcs:enable
	}
}
