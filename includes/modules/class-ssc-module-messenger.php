<?php
/**
 * Messenger bot: customers chat with the same assistant inside Bale or
 * Telegram (same knowledge, same rules, same rate limits as the website).
 *
 * With the Live chat module on, these conversations also appear in the
 * operator inbox and customers can ask for a person with /human.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Module_Messenger
 */
class SSC_Module_Messenger extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'messenger';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Messenger bot (Bale / Telegram)', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Customers can talk to your assistant inside Bale or Telegram, with the same knowledge and rules as the website chat.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Be where your customers already are: one assistant, on your site and in their messenger.', 'nexachat-ai' );
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
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>';
	}

	/**
	 * Needs the bot token.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured = a bot token is stored.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return SSC_Messenger::ready();
	}

	/**
	 * Runtime hooks.
	 */
	public function register() {
		SSC_Messenger::register();
		add_action( 'ssc_messenger_message', array( __CLASS__, 'on_message' ), 20, 2 );
	}

	/**
	 * Conversation id for a messenger chat (stable, never the raw chat id).
	 *
	 * @param string $platform Platform.
	 * @param string $chat     Chat id.
	 * @return string 32 hex characters.
	 */
	public static function conversation_for( $platform, $chat ) {
		return substr( hash_hmac( 'sha256', 'msgr|' . $platform . '|' . $chat, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Is this an operator's message meant for the Live inbox?
	 *
	 * @param array $message Message.
	 * @param array $context Context.
	 * @return bool
	 */
	protected static function is_operator_business( $message, $context ) {
		if ( empty( $context['operator'] ) ) {
			return false;
		}
		$text = (string) $context['text'];
		return isset( $message['reply_to_message'] ) || 0 === strpos( $text, 'live:' ) || in_array( $text, array( '/bot', '/close' ), true );
	}

	/**
	 * Answer a customer message.
	 *
	 * @param array $message Message.
	 * @param array $context Context (platform, from, chat, text, private, operator).
	 */
	public static function on_message( $message, $context ) {
		if ( ! SSC_Modules::is_active( 'messenger' ) || empty( $context['private'] ) || self::is_operator_business( $message, $context ) ) {
			return; // Group chats and operator replies are not customer conversations.
		}
		$chat     = (string) $context['chat'];
		$platform = (string) $context['platform'];
		$text     = trim( (string) $context['text'] );
		$conv     = self::conversation_for( $platform, $chat );
		$live     = SSC_Modules::is_active( 'live' );

		if ( ! SSC_Setup::is_live() ) {
			SSC_Messenger::send( $chat, self::t( 'not_live' ) );
			return;
		}
		if ( '' === $text ) {
			SSC_Messenger::send( $chat, self::t( 'text_only' ) );
			return;
		}

		$name = trim( ( isset( $message['from']['first_name'] ) ? $message['from']['first_name'] : '' ) . ' ' . ( isset( $message['from']['last_name'] ) ? $message['from']['last_name'] : '' ) );
		if ( ! empty( $message['from']['username'] ) ) {
			$name .= ' (@' . $message['from']['username'] . ')';
		}
		$channel = array(
			'channel'      => $platform,
			'channel_chat' => $chat,
			'label'        => '' !== trim( $name ) ? trim( $name ) : SSC_Messenger::platform_label(),
		);
		if ( $live ) {
			SSC_Module_Live::thread_for( $conv, true, $channel );
		}

		if ( 0 === strpos( $text, '/start' ) ) {
			self::welcome( $chat, $live );
			return;
		}
		if ( '/human' === $text || 'msg:human' === $text ) {
			if ( ! $live ) {
				SSC_Messenger::send( $chat, self::t( 'no_live' ) );
				return;
			}
			$result = SSC_I18n::in_widget_locale(
				function () use ( $conv, $channel ) {
					return SSC_Module_Live::request_human( $conv, $channel );
				}
			);
			if ( '' !== (string) $result['message'] ) {
				SSC_Messenger::send( $chat, $result['message'] );
			}
			return;
		}

		$engine = new SSC_Chat_Engine();
		if ( ! $engine->allow_request( 'chat', $conv, 'msgr-' . md5( $platform . $chat ) ) ) {
			SSC_Messenger::send( $chat, self::t( 'limit' ) );
			return;
		}
		SSC_Messenger::api(
			'sendChatAction',
			array(
				'chat_id' => $chat,
				'action'  => 'typing',
			),
			5
		);
		$engine->set_context( '', $conv );
		$engine->set_channel(
			array(
				'channel'      => $platform,
				'channel_chat' => $chat,
			)
		);
		$result = SSC_I18n::in_widget_locale(
			function () use ( $engine, $text ) {
				return $engine->chat( $text, 'general' );
			}
		);
		if ( 'live' === $result['source'] ) {
			return; // A person is handling the chat: the message went to them.
		}
		$reply = SSC_Messenger::plain( (string) $result['reply'] );
		if ( '' === $reply ) {
			$reply = self::t( 'error' );
		}
		$extra = array();
		if ( $live && ! empty( $result['handoff'] ) ) {
			$extra['reply_markup'] = self::human_button();
		}
		SSC_Messenger::send( $chat, $reply, $extra );
	}

	/**
	 * Greeting for /start.
	 *
	 * @param string $chat Chat id.
	 * @param bool   $live Live chat available.
	 */
	protected static function welcome( $chat, $live ) {
		$text = trim( (string) SSC_Settings::get( 'messenger_welcome', '' ) );
		if ( '' === $text ) {
			$title = trim( (string) SSC_Settings::get( 'welcome_title', '' ) );
			$body  = trim( wp_strip_all_tags( (string) SSC_Settings::get( 'welcome_text', '' ) ) );
			$text  = trim( $title . "\n" . $body );
		}
		if ( '' === $text ) {
			$text = self::t( 'hello' );
		}
		SSC_Messenger::send( $chat, $text, $live ? array( 'reply_markup' => self::human_button() ) : array() );
	}

	/**
	 * Inline "Talk to a person" button.
	 *
	 * @return array
	 */
	protected static function human_button() {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => self::t( 'human' ),
						'callback_data' => 'msg:human',
					),
				),
			),
		);
	}

	/**
	 * Customer-facing texts in the widget language.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	protected static function t( $key ) {
		return SSC_I18n::in_widget_locale(
			function () use ( $key ) {
				switch ( $key ) {
					case 'not_live':
						return __( 'The assistant is not available yet. Please try again later.', 'nexachat-ai' );
					case 'text_only':
						return __( 'I can read text messages only. Please type your question.', 'nexachat-ai' );
					case 'no_live':
						return __( 'Talking to a person is not available here. Please leave your question and contact details, or call us.', 'nexachat-ai' );
					case 'limit':
						return __( 'You have reached the daily usage limit. Please try again tomorrow.', 'nexachat-ai' );
					case 'human':
						return __( 'Talk to a person', 'nexachat-ai' );
					case 'hello':
						return __( 'Hello! Ask me anything about our products and services.', 'nexachat-ai' );
					default:
						return __( 'Sorry, something went wrong. Please try again.', 'nexachat-ai' );
				}
			}
		);
	}
}
