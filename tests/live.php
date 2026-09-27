<?php
/**
 * Live chat + Bale/Telegram bot: the whole operator flow against a mocked
 * Bot API (no network). Required from tests/integration.php, which provides
 * check() and a disposable WordPress with the plugin loaded.
 */

global $wpdb;

$modules_before  = get_option( SSC_Modules::OPTION );
$settings_before = get_option( SSC_Settings::OPTION_KEY );

// Mocked Bale API: every call is recorded; sendMessage returns increasing ids.
$bot_calls  = array();
$next_msgid = 500;
$mock_bot   = function ( $pre, $args, $url ) use ( &$bot_calls, &$next_msgid ) {
	if ( 0 !== strpos( $url, 'https://tapi.bale.ai/bot' ) ) {
		return $pre;
	}
	$method      = substr( $url, strrpos( $url, '/' ) + 1 );
	$body        = json_decode( (string) $args['body'], true );
	$result = true;
	if ( 'sendMessage' === $method ) {
		$result = array(
			'message_id' => ++$next_msgid,
			'chat'       => array( 'id' => $body['chat_id'] ),
			'text'       => $body['text'],
		);
		$body['_sent_id'] = $next_msgid;
	} elseif ( 'getMe' === $method ) {
		$result = array( 'username' => 'nexa_test_bot' );
	}
	$bot_calls[] = array( $method, $body );
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( array( 'ok' => true, 'result' => $result ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $mock_bot, 30, 3 );

SSC_Settings::update( array( 'notify_platform' => 'bale', 'messenger_mode' => 'webhook', 'live_assign' => 'auto', 'notify_chat_id' => '' ) );
SSC_Settings::set_secret( 'notify_token', '123456:TEST' );
$live_was_booted = SSC_Modules::is_active( 'live' ); // Then its hooks are already registered.
check( true === SSC_Modules::activate( 'live' ), 'Live chat module activates (no dependencies)' );
$live = SSC_Modules::get( 'live' );
if ( ! $live_was_booted ) {
	$live->register();
}
SSC_Schema::install( false );

// The per-sender flood guard outlives a run: reset it for the test senders.
foreach ( array( '777', '555', '556', '12345' ) as $sender ) {
	delete_transient( 'ssc_msgr_rate_' . md5( $sender ) );
}
$conv    = md5( 'live-test-' . wp_rand() ); // Fresh conversation per run.
$upd     = wp_rand( 100000, 900000000 ); // Update ids are de-duplicated across runs.
$preview = md5( 'live-preview-' . wp_rand() );

// 1. Assistant-answered exchanges build the transcript.
do_action( 'ssc_chat_exchange', $conv, 'سلام، قیمت قرص الف؟', 'قیمت در سایت درج شده است.', 'ai', array( 'channel' => 'web', 'channel_chat' => '', 'page' => 'https://shop.test/p/1' ) );
$thread = SSC_Module_Live::thread_for( $conv );
check( $thread && 'bot' === $thread['status'] && 'https://shop.test/p/1' === $thread['page_url'] && 2 === count( SSC_Module_Live::messages( (int) $thread['id'] ) ), 'Assistant exchanges are recorded with the page the visitor was on' );
do_action( 'ssc_chat_exchange', $preview, 'preview', 'preview', 'ai', array( 'channel' => 'preview' ) );
check( null === SSC_Module_Live::thread_for( $preview ), 'Admin preview chats never reach the inbox' );

// 2. Nobody reachable: the visitor is offered the request form instead.
delete_user_meta( 1, 'ssc_live_seen' );
SSC_Messenger::unlink( 1 );
$offline = SSC_Module_Live::request_human( $conv );
check( false === $offline['available'] && 'bot' === SSC_Module_Live::thread_for( $conv )['status'], 'Without a reachable operator the chat stays with the assistant' );

// 3. An operator links their messenger account with a one-time code.
$code = SSC_Messenger::link_code( 1 );
SSC_Messenger::dispatch(
	array(
		'update_id' => $upd + 1,
		'message'   => array(
			'message_id' => 1,
			'from'       => array( 'id' => 777, 'first_name' => 'Ali' ),
			'chat'       => array( 'id' => 777, 'type' => 'private' ),
			'text'       => '/start ' . $code,
		),
	)
);
check( 1 === SSC_Messenger::operator_user( '777' ) && '777' === SSC_Messenger::operator_chat( 1 ), 'An operator links their messenger account with a one-time code' );
check( 0 === SSC_Messenger::operator_user( '999' ) && false === get_transient( 'ssc_msgr_link_' . $code ), 'Link codes are single-use and strangers are not operators' );

// 4. Now the visitor asks for a person: waiting, assigned, operator notified with a button.
$calls_before = count( $bot_calls );
$request      = SSC_Module_Live::request_human( $conv );
$thread       = SSC_Module_Live::thread_for( $conv );
$notice       = $bot_calls[ count( $bot_calls ) - 1 ];
check( $request['available'] && 'waiting' === $thread['status'] && 1 === (int) $thread['operator_id'], 'A waiting chat is assigned to the reachable operator' );
check( count( $bot_calls ) > $calls_before && 'sendMessage' === $notice[0] && '777' === (string) $notice[1]['chat_id'] && 'live:take:' . $thread['id'] === $notice[1]['reply_markup']['inline_keyboard'][0][0]['callback_data'], 'The operator gets the chat on their messenger with a "Take over" button' );
$notice_id = $next_msgid;

// 5. While waiting, the assistant stays quiet and visitor messages reach the operator.
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.9', $conv );
$result = $engine->chat( 'لطفاً با یک نفر صحبت کنم', 'general' );
$last   = $bot_calls[ count( $bot_calls ) - 1 ];
check( $result['ok'] && '' === $result['reply'] && 'live' === $result['source'] && 'waiting' === $result['flags']['live'], 'While a person is involved the assistant does not answer' );
check( 'sendMessage' === $last[0] && false !== strpos( $last[1]['text'], 'لطفاً با یک نفر صحبت کنم' ), 'Visitor messages are relayed to the operator' );

// 6. The operator answers by replying to the bot message: the chat becomes human.
SSC_Messenger::dispatch(
	array(
		'update_id' => $upd + 2,
		'message'   => array(
			'message_id'       => 2,
			'from'             => array( 'id' => 777 ),
			'chat'             => array( 'id' => 777, 'type' => 'private' ),
			'text'             => 'سلام، علی هستم. چه کمکی از دستم برمی‌آید؟',
			'reply_to_message' => array( 'message_id' => $notice_id ),
		),
	)
);
$thread  = SSC_Module_Live::thread_for( $conv );
$visible = SSC_Module_Live::messages( (int) $thread['id'], 0, array( 'operator' ) );
check( 'human' === $thread['status'] && 1 === count( $visible ) && 'telegram' !== $visible[0]['via'] && false !== strpos( $visible[0]['body'], 'علی' ), 'Replying to the bot message answers the visitor and takes the chat over' );
SSC_Messenger::dispatch(
	array(
		'update_id' => $upd + 2,
		'message'   => array(
			'message_id'       => 2,
			'from'             => array( 'id' => 777 ),
			'chat'             => array( 'id' => 777, 'type' => 'private' ),
			'text'             => 'duplicate delivery',
			'reply_to_message' => array( 'message_id' => $notice_id ),
		),
	)
);
check( 1 === count( SSC_Module_Live::messages( (int) $thread['id'], 0, array( 'operator' ) ) ), 'A retried webhook update is handled once' );

// 7. The widget poll sees the operator message; strangers cannot reply into chats.
$request_poll = new WP_REST_Request( 'POST', '/ssc/v1/live/poll' );
$request_poll->set_param( 'conv', $conv );
$request_poll->set_param( 'after', 0 );
$poll = SSC_Module_Live::rest_poll( $request_poll )->get_data();
check( 'human' === $poll['status'] && 'Ali' !== '' && in_array( 'operator', array_column( $poll['messages'], 'sender' ), true ) && ! in_array( 'visitor', array_column( $poll['messages'], 'sender' ), true ), 'The widget poll returns operator messages only, with the status' );
SSC_Messenger::dispatch(
	array(
		'update_id' => $upd + 3,
		'message'   => array(
			'message_id'       => 3,
			'from'             => array( 'id' => 12345 ),
			'chat'             => array( 'id' => 777, 'type' => 'private' ),
			'text'             => 'injected',
			'reply_to_message' => array( 'message_id' => $notice_id ),
		),
	)
);
check( 1 === count( SSC_Module_Live::messages( (int) $thread['id'], 0, array( 'operator' ) ) ), 'Only linked operators can answer a chat' );

// 8. "/bot" hands the chat back; the assistant answers again.
SSC_Messenger::dispatch(
	array(
		'update_id' => $upd + 4,
		'message'   => array(
			'message_id'       => 4,
			'from'             => array( 'id' => 777 ),
			'chat'             => array( 'id' => 777, 'type' => 'private' ),
			'text'             => '/bot',
			'reply_to_message' => array( 'message_id' => $notice_id ),
		),
	)
);
check( 'bot' === SSC_Module_Live::thread_for( $conv )['status'] && null === SSC_Module_Live::intercept( null, 'hi', $conv ), '"/bot" returns the chat to the assistant' );

// 9. Webhook secret.
$hook = new WP_REST_Request( 'POST', '/ssc/v1/messenger/hook' );
$hook->set_param( 'k', 'wrong' );
check( is_wp_error( SSC_Messenger::webhook_permission( $hook ) ), 'The webhook refuses requests without the secret' );
$hook->set_param( 'k', SSC_Messenger::secret() );
check( true === SSC_Messenger::webhook_permission( $hook ) && false !== strpos( SSC_Messenger::webhook_url(), 'k=' ), 'The webhook accepts the platform with the secret' );

// 10. Operator capability only for chosen users.
$editor = wp_insert_user( array( 'user_login' => 'live_editor_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
check( ! user_can( $editor, SSC_Module_Live::CAP ), 'Editors cannot answer chats unless chosen' );
SSC_Settings::update( array( 'live_operators' => array( $editor ) ) );
check( user_can( $editor, SSC_Module_Live::CAP ), 'Chosen team members can answer chats' );
wp_delete_user( $editor );

// 11. Retention.
$wpdb->update( SSC_Schema::live_table( 'threads' ), array( 'updated_at' => '2000-01-01 00:00:00' ), array( 'id' => (int) $thread['id'] ) );
SSC_Module_Live::purge();
check( null === SSC_Module_Live::thread( (int) $thread['id'] ) && ! SSC_Module_Live::messages( (int) $thread['id'] ), 'Old transcripts are deleted after the retention period' );

// 12. Messenger bot: a customer chats with the assistant inside Bale.
$setup_before = get_option( 'ssc_chatbot_setup' );
SSC_Setup::update_state( array( 'steps' => array_fill_keys( SSC_Setup::steps(), true ) ) );
SSC_Setup::publish();
SSC_Settings::update( array( 'enabled' => 'yes' ) );
check( SSC_Setup::is_live(), 'The assistant is published for the messenger test' );
$msgr_was_booted = SSC_Modules::is_active( 'messenger' );
check( true === SSC_Modules::activate( 'messenger' ), 'Messenger bot module activates' );
if ( ! $msgr_was_booted ) {
	SSC_Modules::get( 'messenger' )->register();
}
// The customer's conversation id is stable per chat: start from a clean thread.
$forget_thread = function ( $conv ) {
	global $wpdb;
	$old = SSC_Module_Live::thread_for( $conv );
	if ( $old ) {
		$wpdb->delete( SSC_Schema::live_table( 'messages' ), array( 'thread_id' => (int) $old['id'] ) );
		$wpdb->delete( SSC_Schema::live_table( 'threads' ), array( 'id' => (int) $old['id'] ) );
	}
	SSC_Conversation::forget( $conv );
};
$forget_thread( SSC_Module_Messenger::conversation_for( 'bale', '555' ) );
$sent_to = function ( $chat ) use ( &$bot_calls ) {
	return array_values(
		array_filter(
			$bot_calls,
			function ( $c ) use ( $chat ) {
				return 'sendMessage' === $c[0] && (string) $chat === (string) $c[1]['chat_id'];
			}
		)
	);
};
$customer = array(
	'from' => array( 'id' => 555, 'first_name' => 'Sara', 'username' => 'sara_k' ),
	'chat' => array( 'id' => 555, 'type' => 'private' ),
);
SSC_Settings::update( array( 'messenger_welcome' => 'سلام! به ربات فروشگاه خوش آمدید.' ) );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 20, 'message' => $customer + array( 'message_id' => 20, 'text' => '/start' ) ) );
$to_customer = $sent_to( 555 );
check( 1 === count( $to_customer ) && 'سلام! به ربات فروشگاه خوش آمدید.' === $to_customer[0][1]['text'] && 'msg:human' === $to_customer[0][1]['reply_markup']['inline_keyboard'][0][0]['callback_data'], '/start greets the customer with a "Talk to a person" button' );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 21, 'message' => $customer + array( 'message_id' => 21, 'text' => 'ساعت کاری شما؟' ) ) );
$to_customer = $sent_to( 555 );
$bot_conv    = SSC_Module_Messenger::conversation_for( 'bale', '555' );
$bot_thread  = SSC_Module_Live::thread_for( $bot_conv );
check( 2 === count( $to_customer ) && '' !== $to_customer[1][1]['text'], 'Customer questions are answered by the assistant in the messenger' );
check( $bot_thread && 'bale' === $bot_thread['channel'] && '555' === $bot_thread['channel_chat'] && false !== strpos( $bot_thread['visitor_label'], '@sara_k' ), 'Messenger chats appear in the Live inbox with the customer name' );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 22, 'message' => array( 'message_id' => 22, 'from' => array( 'id' => 556 ), 'chat' => array( 'id' => -100123, 'type' => 'group' ), 'text' => 'hello bot' ) ) );
check( ! $sent_to( -100123 ), 'The bot stays silent in group chats' );

// 13. The customer asks for a person; the operator answers from their own messenger.
$code = SSC_Messenger::link_code( 1 );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 23, 'message' => array( 'message_id' => 23, 'from' => array( 'id' => 777 ), 'chat' => array( 'id' => 777, 'type' => 'private' ), 'text' => '/start ' . $code ) ) );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 24, 'callback_query' => array( 'id' => 'cb1', 'from' => $customer['from'], 'data' => 'msg:human', 'message' => array( 'message_id' => 24, 'chat' => $customer['chat'] ) ) ) );
$bot_thread = SSC_Module_Live::thread_for( $bot_conv );
$alert      = $sent_to( 777 );
$alert_id   = end( $alert )[1]['_sent_id'];
check( 'waiting' === $bot_thread['status'] && $alert && false !== strpos( end( $alert )[1]['text'], '@sara_k' ), 'The "Talk to a person" button puts the customer in the operator queue' );
$before = count( $sent_to( 555 ) );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 25, 'message' => $customer + array( 'message_id' => 25, 'text' => 'منتظرم' ) ) );
check( count( $sent_to( 555 ) ) === $before, 'While waiting for a person the bot does not answer the customer' );
SSC_Messenger::dispatch( array( 'update_id' => $upd + 26, 'message' => array( 'message_id' => 26, 'from' => array( 'id' => 777 ), 'chat' => array( 'id' => 777, 'type' => 'private' ), 'text' => 'سلام سارا، در خدمتم.', 'reply_to_message' => array( 'message_id' => $alert_id ) ) ) );
$to_customer = $sent_to( 555 );
check( 'سلام سارا، در خدمتم.' === end( $to_customer )[1]['text'] && 'human' === SSC_Module_Live::thread_for( $bot_conv )['status'], 'The operator reply from the messenger reaches the customer in the messenger' );

// Clean up.
$forget_thread( $bot_conv );
update_option( 'ssc_chatbot_setup', $setup_before );
remove_filter( 'pre_http_request', $mock_bot, 30 );
SSC_Messenger::unlink( 1 );
SSC_Settings::set_secret( 'notify_token', '' );
update_option( SSC_Modules::OPTION, $modules_before, false );
update_option( SSC_Settings::OPTION_KEY, $settings_before );
