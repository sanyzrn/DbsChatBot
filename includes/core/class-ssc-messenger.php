<?php
/**
 * Bale / Telegram bot connection shared by the Live inbox (operators reply
 * from their messenger) and the Messenger bot (customers chat with the
 * assistant inside Bale or Telegram).
 *
 * Both platforms speak the same Bot API (Bale mirrors Telegram), so one
 * client serves both. Updates arrive either by webhook (fast; the platform
 * must reach this site) or by polling getUpdates from WP-Cron and from open
 * inbox screens (for hosts the platform cannot reach, e.g. local sites).
 *
 * The bot token and platform are the ones configured for Notifications
 * (settings notify_platform / secret notify_token), so a site configures its
 * bot once.
 *
 * Nothing here runs unless a module that needs it is active: modules call
 * SSC_Messenger::register() from their own register().
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Messenger
 */
class SSC_Messenger {

	const POLL_HOOK      = 'ssc_messenger_poll';
	const SECRET_OPTION  = 'ssc_messenger_secret';
	const OFFSET_OPTION  = 'ssc_messenger_offset';
	const USER_META_CHAT = 'ssc_messenger_user';

	/**
	 * Registered once per request.
	 *
	 * @var bool
	 */
	protected static $registered = false;

	/**
	 * Wire the webhook route and the polling job (idempotent).
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::POLL_HOOK, array( __CLASS__, 'poll' ) );
		// Polling mode only: a site the messenger cannot reach has no faster way to receive replies.
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- polling mode needs one-minute checks.
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Modules that use the connection.
	 *
	 * @return bool
	 */
	public static function needed() {
		return SSC_Modules::is_active( 'live' ) || SSC_Modules::is_active( 'messenger' );
	}

	/**
	 * Platform: bale | telegram.
	 *
	 * @return string
	 */
	public static function platform() {
		return 'telegram' === SSC_Settings::get( 'notify_platform', 'bale' ) ? 'telegram' : 'bale';
	}

	/**
	 * Platform display name.
	 *
	 * @return string
	 */
	public static function platform_label() {
		return 'telegram' === self::platform() ? __( 'Telegram', 'nexachat-ai' ) : __( 'Bale', 'nexachat-ai' );
	}

	/**
	 * Is a bot token stored?
	 *
	 * @return bool
	 */
	public static function ready() {
		return SSC_Settings::has_secret( 'notify_token' );
	}

	/**
	 * Receive updates by webhook (default) or polling.
	 *
	 * @return string
	 */
	public static function mode() {
		return 'polling' === SSC_Settings::get( 'messenger_mode', 'webhook' ) ? 'polling' : 'webhook';
	}

	/**
	 * Call a Bot API method.
	 *
	 * @param string $method  Method name (sendMessage, getUpdates…).
	 * @param array  $params  Parameters.
	 * @param int    $timeout Seconds.
	 * @return mixed|WP_Error The "result" field, or an error.
	 */
	public static function api( $method, $params = array(), $timeout = 10 ) {
		if ( ! self::ready() ) {
			return new WP_Error( 'ssc_messenger_token', __( 'No bot token is configured.', 'nexachat-ai' ) );
		}
		$base     = 'telegram' === self::platform() ? 'https://api.telegram.org' : 'https://tapi.bale.ai';
		$url      = $base . '/bot' . rawurlencode( SSC_Settings::get_secret( 'notify_token' ) ) . '/' . rawurlencode( $method );
		$response = SSC_HTTP::post_json( $url, array(), (object) $params, array( 'timeout' => $timeout ) );
		if ( empty( $response['ok'] ) ) {
			$message = isset( $response['error']['message'] ) ? $response['error']['message'] : __( 'The messenger did not answer.', 'nexachat-ai' );
			return new WP_Error( 'ssc_messenger_http', $message );
		}
		$data = isset( $response['data'] ) ? $response['data'] : array();
		if ( empty( $data['ok'] ) ) {
			$description = isset( $data['description'] ) ? (string) $data['description'] : __( 'The messenger rejected the request.', 'nexachat-ai' );
			return new WP_Error( 'ssc_messenger_api', $description );
		}
		return isset( $data['result'] ) ? $data['result'] : true;
	}

	/**
	 * Send a plain-text message (split when over the platform limit).
	 *
	 * @param string|int $chat_id Chat id.
	 * @param string     $text    Text.
	 * @param array      $extra   Extra parameters (reply_markup, reply_to_message_id…).
	 * @return array|WP_Error The last sent message.
	 */
	public static function send( $chat_id, $text, $extra = array() ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new WP_Error( 'ssc_messenger_empty', 'empty' );
		}
		$parts  = self::split( $text, 3900 );
		$result = null;
		foreach ( $parts as $i => $part ) {
			$params = array(
				'chat_id' => $chat_id,
				'text'    => $part,
			);
			if ( count( $parts ) - 1 === $i ) {
				$params = array_merge( $params, $extra );
			}
			$result = self::api( 'sendMessage', $params );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return $result;
	}

	/**
	 * Split long text on paragraph/line boundaries.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Characters per part.
	 * @return string[]
	 */
	public static function split( $text, $limit ) {
		$parts = array();
		while ( mb_strlen( $text ) > $limit ) {
			$slice   = mb_substr( $text, 0, $limit );
			$cut     = max( mb_strrpos( $slice, "\n" ), mb_strrpos( $slice, ' ' ) );
			$cut     = ( false === $cut || $cut < $limit / 2 ) ? $limit : $cut;
			$parts[] = trim( mb_substr( $text, 0, $cut ) );
			$text    = trim( mb_substr( $text, $cut ) );
		}
		if ( '' !== $text ) {
			$parts[] = $text;
		}
		return $parts;
	}

	/**
	 * Markdown-ish assistant text to readable plain text for messengers.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = (string) $text;
		$text = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '$1 ($2)', $text );
		$text = preg_replace( '/\*\*([^*\n]+)\*\*/', '$1', $text );
		$text = preg_replace( '/^#{1,6}\s*/m', '', $text );
		$text = preg_replace( '/`([^`\n]+)`/', '$1', $text );
		return trim( wp_strip_all_tags( (string) $text ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Receiving updates.
	 * --------------------------------------------------------------
	 */

	/**
	 * Webhook secret (random, stored once).
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = (string) get_option( self::SECRET_OPTION, '' );
		if ( strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 40, false, false );
			update_option( self::SECRET_OPTION, $secret, false );
		}
		return $secret;
	}

	/**
	 * Webhook address given to the platform.
	 *
	 * @return string
	 */
	public static function webhook_url() {
		return add_query_arg( 'k', self::secret(), rest_url( 'ssc/v1/messenger/hook' ) );
	}

	/**
	 * REST route for the webhook.
	 */
	public static function register_routes() {
		register_rest_route(
			'ssc/v1',
			'/messenger/hook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'webhook_permission' ),
				'callback'            => array( __CLASS__, 'rest_webhook' ),
			)
		);
	}

	/**
	 * Only the platform knows the secret (query key, or Telegram's header).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function webhook_permission( $request ) {
		$given  = (string) $request->get_param( 'k' );
		$header = (string) $request->get_header( 'x_telegram_bot_api_secret_token' );
		$secret = self::secret();
		if ( ( '' !== $given && hash_equals( $secret, $given ) ) || ( '' !== $header && hash_equals( self::header_secret(), $header ) ) ) {
			return true;
		}
		return new WP_Error( 'ssc_forbidden', 'Forbidden', array( 'status' => 403 ) );
	}

	/**
	 * Header secret for Telegram (only A-Z, a-z, 0-9, _ and - are allowed).
	 *
	 * @return string
	 */
	protected static function header_secret() {
		return substr( preg_replace( '/[^A-Za-z0-9_-]/', '', self::secret() ), 0, 64 );
	}

	/**
	 * Webhook callback: always 200 so the platform does not retry forever.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_webhook( $request ) {
		$update = $request->get_json_params();
		if ( is_array( $update ) && self::needed() ) {
			self::dispatch( $update );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Fetch pending updates (polling mode, or a manual refresh).
	 *
	 * @param bool $force Poll even in webhook mode (never while a webhook is set: the API refuses).
	 * @return int|WP_Error Number of updates handled.
	 */
	public static function poll( $force = false ) {
		if ( ! self::ready() || ! self::needed() || ( 'polling' !== self::mode() && ! $force ) ) {
			return 0;
		}
		// One poller at a time (cron and open inbox screens can overlap).
		if ( get_transient( 'ssc_messenger_polling' ) ) {
			return 0;
		}
		set_transient( 'ssc_messenger_polling', 1, 30 );
		$offset  = (int) get_option( self::OFFSET_OPTION, 0 );
		$updates = self::api(
			'getUpdates',
			array(
				'offset'          => $offset,
				'timeout'         => 0,
				'allowed_updates' => array( 'message', 'callback_query' ),
			),
			15
		);
		delete_transient( 'ssc_messenger_polling' );
		if ( is_wp_error( $updates ) ) {
			return $updates;
		}
		$count = 0;
		foreach ( (array) $updates as $update ) {
			if ( ! is_array( $update ) || ! isset( $update['update_id'] ) ) {
				continue;
			}
			update_option( self::OFFSET_OPTION, (int) $update['update_id'] + 1, false );
			self::dispatch( $update );
			++$count;
		}
		return $count;
	}

	/**
	 * Route one update: operator linking first, then the modules.
	 *
	 * @param array $update Update object.
	 */
	public static function dispatch( $update ) {
		// Webhooks can be retried: handle each update once.
		$id = isset( $update['update_id'] ) ? (int) $update['update_id'] : 0;
		if ( $id ) {
			$key = 'ssc_msgr_upd_' . $id;
			if ( get_transient( $key ) ) {
				return;
			}
			set_transient( $key, 1, DAY_IN_SECONDS );
		}

		$message = self::message_of( $update );
		if ( ! $message ) {
			return;
		}
		$from    = isset( $message['from']['id'] ) ? (string) $message['from']['id'] : '';
		$chat    = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text    = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
		$private = isset( $message['chat']['type'] ) && 'private' === $message['chat']['type'];
		if ( '' === $from || '' === $chat ) {
			return;
		}

		// Per-sender flood guard (cheap; the engine has its own limits).
		$flood = 'ssc_msgr_rate_' . md5( $from );
		$count = (int) get_transient( $flood );
		if ( $count > 30 ) {
			return;
		}
		set_transient( $flood, $count + 1, MINUTE_IN_SECONDS );

		// "/start CODE" (or the bare code) links an operator's account.
		if ( $private && preg_match( '/^(?:\/start\s+)?(op-[a-z0-9]{10})$/i', $text, $m ) ) {
			self::complete_link( strtolower( $m[1] ), $from, $chat, $message );
			return;
		}

		$user_id = self::operator_user( $from );
		/**
		 * One messenger update, after linking and flood checks.
		 *
		 * @param array $message  The message (or callback query message with 'data').
		 * @param array $context  platform, from, chat, text, private, operator (WP user id or 0).
		 */
		do_action(
			'ssc_messenger_message',
			$message,
			array(
				'platform' => self::platform(),
				'from'     => $from,
				'chat'     => $chat,
				'text'     => $text,
				'private'  => $private,
				'operator' => $user_id,
			)
		);
	}

	/**
	 * The message inside an update (plain message or button press).
	 *
	 * @param array $update Update.
	 * @return array|null
	 */
	protected static function message_of( $update ) {
		if ( isset( $update['message'] ) && is_array( $update['message'] ) ) {
			return $update['message'];
		}
		if ( isset( $update['callback_query']['message'] ) && is_array( $update['callback_query']['message'] ) ) {
			$message         = $update['callback_query']['message'];
			$message['from'] = isset( $update['callback_query']['from'] ) ? $update['callback_query']['from'] : array();
			$message['text'] = isset( $update['callback_query']['data'] ) ? (string) $update['callback_query']['data'] : '';
			if ( isset( $update['callback_query']['id'] ) ) {
				self::api( 'answerCallbackQuery', array( 'callback_query_id' => $update['callback_query']['id'] ), 5 );
			}
			return $message;
		}
		return null;
	}

	/*
	 * --------------------------------------------------------------
	 * Operator accounts.
	 * --------------------------------------------------------------
	 */

	/**
	 * One-time code an operator sends to the bot to link their account.
	 *
	 * @param int $user_id WP user id.
	 * @return string
	 */
	public static function link_code( $user_id ) {
		$code = 'op-' . strtolower( wp_generate_password( 10, false, false ) );
		set_transient( 'ssc_msgr_link_' . $code, (int) $user_id, 15 * MINUTE_IN_SECONDS );
		return $code;
	}

	/**
	 * Deep link that opens the bot with the code (bot username required).
	 *
	 * @param string $code Link code.
	 * @return string '' when the bot username is unknown.
	 */
	public static function link_url( $code ) {
		$me = self::bot_info();
		if ( empty( $me['username'] ) ) {
			return '';
		}
		$host = 'telegram' === self::platform() ? 'https://t.me/' : 'https://ble.ir/';
		return $host . rawurlencode( $me['username'] ) . '?start=' . rawurlencode( $code );
	}

	/**
	 * Finish linking: the code maps to a user who can operate the inbox.
	 *
	 * @param string $code    Code.
	 * @param string $from    Messenger user id.
	 * @param string $chat    Private chat id.
	 * @param array  $message Message.
	 */
	protected static function complete_link( $code, $from, $chat, $message ) {
		$user_id = (int) get_transient( 'ssc_msgr_link_' . $code );
		if ( ! $user_id || ( ! user_can( $user_id, SSC_Module_Live::CAP ) && ! user_can( $user_id, 'manage_options' ) ) ) {
			self::send( $chat, __( 'This link code is invalid or expired. Create a new one in the Live inbox.', 'nexachat-ai' ) );
			return;
		}
		delete_transient( 'ssc_msgr_link_' . $code );
		// One messenger account per user, and one user per messenger account.
		foreach ( get_users(
			array(
				'meta_key'   => self::USER_META_CHAT, // phpcs:ignore WordPress.DB.SlowDBQuery -- tiny, admin-triggered.
				'meta_value' => self::platform() . ':' . $from, // phpcs:ignore WordPress.DB.SlowDBQuery -- tiny, admin-triggered.
				'fields'     => 'ID',
			)
		) as $other ) {
			delete_user_meta( (int) $other, self::USER_META_CHAT );
		}
		update_user_meta( $user_id, self::USER_META_CHAT, self::platform() . ':' . $from );
		update_user_meta( $user_id, 'ssc_messenger_chat', $chat );
		$name = isset( $message['from']['first_name'] ) ? (string) $message['from']['first_name'] : '';
		self::send(
			$chat,
			sprintf(
				/* translators: %s: operator first name. */
				__( 'Connected, %s. New chats waiting for a person will arrive here. Reply to a chat message to answer the visitor. Commands: /bot returns the chat to the assistant, /close ends it.', 'nexachat-ai' ),
				$name
			)
		);
	}

	/**
	 * WP user linked to a messenger account (0 = none).
	 *
	 * @param string $from Messenger user id.
	 * @return int
	 */
	public static function operator_user( $from ) {
		$users = get_users(
			array(
				'meta_key'   => self::USER_META_CHAT, // phpcs:ignore WordPress.DB.SlowDBQuery -- indexed meta lookup, one row.
				'meta_value' => self::platform() . ':' . $from, // phpcs:ignore WordPress.DB.SlowDBQuery -- indexed meta lookup, one row.
				'fields'     => 'ID',
				'number'     => 1,
			)
		);
		return $users ? (int) $users[0] : 0;
	}

	/**
	 * Private chat id of a linked operator ('' = not linked on this platform).
	 *
	 * @param int $user_id WP user id.
	 * @return string
	 */
	public static function operator_chat( $user_id ) {
		$link = (string) get_user_meta( $user_id, self::USER_META_CHAT, true );
		if ( 0 !== strpos( $link, self::platform() . ':' ) ) {
			return '';
		}
		return (string) get_user_meta( $user_id, 'ssc_messenger_chat', true );
	}

	/**
	 * Unlink an operator.
	 *
	 * @param int $user_id WP user id.
	 */
	public static function unlink( $user_id ) {
		delete_user_meta( $user_id, self::USER_META_CHAT );
		delete_user_meta( $user_id, 'ssc_messenger_chat' );
	}

	/*
	 * --------------------------------------------------------------
	 * Connection management (admin).
	 * --------------------------------------------------------------
	 */

	/**
	 * Bot identity (cached for an hour).
	 *
	 * @param bool $refresh Skip the cache.
	 * @return array Empty when unavailable.
	 */
	public static function bot_info( $refresh = false ) {
		$cached = get_transient( 'ssc_messenger_me' );
		if ( ! $refresh && is_array( $cached ) ) {
			return $cached;
		}
		$me = self::api( 'getMe', array(), 8 );
		$me = is_array( $me ) ? $me : array();
		set_transient( 'ssc_messenger_me', $me, HOUR_IN_SECONDS );
		return $me;
	}

	/**
	 * Apply the receiving mode: set the webhook, or remove it for polling.
	 *
	 * @return true|WP_Error
	 */
	public static function connect() {
		delete_transient( 'ssc_messenger_me' );
		if ( 'polling' === self::mode() ) {
			$result = self::api( 'deleteWebhook', array() );
			return is_wp_error( $result ) ? $result : true;
		}
		$params = array(
			'url'             => self::webhook_url(),
			'allowed_updates' => array( 'message', 'callback_query' ),
		);
		if ( 'telegram' === self::platform() ) {
			$params['secret_token'] = self::header_secret();
		}
		$result = self::api( 'setWebhook', $params );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Current webhook state reported by the platform.
	 *
	 * @return array|WP_Error
	 */
	public static function webhook_info() {
		return self::api( 'getWebhookInfo', array(), 8 );
	}

	/*
	 * --------------------------------------------------------------
	 * Scheduling.
	 * --------------------------------------------------------------
	 */

	/**
	 * Every-minute schedule for polling.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['ssc_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => did_action( 'init' ) ? __( 'NexaChatAI: every minute', 'nexachat-ai' ) : 'NexaChatAI: every minute',
		);
		return $schedules;
	}

	/**
	 * Keep the polling job in line with the mode.
	 */
	public static function schedule() {
		$want = self::needed() && self::ready() && 'polling' === self::mode();
		$next = wp_next_scheduled( self::POLL_HOOK );
		if ( $want && ! $next ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'ssc_every_minute', self::POLL_HOOK );
		} elseif ( ! $want && $next ) {
			wp_clear_scheduled_hook( self::POLL_HOOK );
		}
	}
}
