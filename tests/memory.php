<?php
/**
 * Conversation memory: stored across visits, private to its owner, rolling
 * summary of what left the model's window, conversation list for signed-in
 * users, chat log grouped by conversation, logging on by default.
 * Required from tests/integration.php (check() and a disposable site).
 */

global $wpdb;
$settings_before = get_option( SSC_Settings::OPTION_KEY );
$modules_before  = get_option( SSC_Modules::OPTION );
$setup_before    = get_option( 'ssc_chatbot_setup' );
$memory          = SSC_Schema::live_table( 'memory' );
$ai_bodies       = array();
$ai_reply        = 'Reply';
$ai_fail         = false;
$mock_ai         = function ( $pre, $args, $url ) use ( &$ai_bodies, &$ai_reply, &$ai_fail ) {
	if ( false === strpos( $url, 'api.openai.com/v1/chat' ) ) {
		return $pre;
	}
	$ai_bodies[] = json_decode( $args['body'], true );
	if ( $ai_fail ) {
		return array( 'headers' => array(), 'body' => '{"error":{"message":"down"}}', 'response' => array( 'code' => 503, 'message' => 'Unavailable' ), 'cookies' => array(), 'filename' => null );
	}
	return array( 'headers' => array(), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $ai_reply ), 'finish_reason' => 'stop' ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $mock_ai, PHP_INT_MAX, 3 ); // Last word over earlier tests' mocks.
update_option( SSC_Modules::OPTION, array( 'history' ) );
SSC_Settings::update( array( 'ai_provider' => 'openai', 'ai_cache_enabled' => 'no', 'qa_mode' => 'ai_first', 'streaming_enabled' => 'no', 'kb_semantic' => 'no', 'web_search' => 'no', 'ai_history_limit' => 4, 'memory_days' => 7, 'chatlog_enabled' => 'yes' ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-test' );

// 1. Stored across requests (a new engine = a new page load or visit).
wp_set_current_user( 0 );
$conv = str_repeat( 'c1', 16 );
SSC_Conversation::forget( $conv );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.20', $conv );
$engine->chat( 'My budget is 40 million and I want a laptop', 'general', array() );
$row = SSC_Conversation::row( $conv );
check( $row && 2 === count( $row['messages'] ) && 0 === (int) $row['user_id'] && '' === $row['conv_id'] && 64 === strlen( $row['conv_hash'] ), 'A conversation is stored server-side under a hash (guests: never the raw id)' );
check( 'My budget is 40 million and I want a laptop' === $row['title'], 'A conversation is titled by its first question' );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.20', $conv );
$engine->chat( 'Which one do you recommend?', 'general', array() );
$last = end( $ai_bodies );
check( false !== strpos( wp_json_encode( $last['messages'] ), '40 million' ), 'A returning visitor continues the same conversation (the model still has the context)' );

// 2. Lifetime.
$wpdb->update( $memory, array( 'updated_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 8 * DAY_IN_SECONDS ) ), array( 'conv_hash' => SSC_Conversation::hash( $conv ) ) );
check( array() === SSC_Conversation::load( $conv ), 'A conversation idle longer than the memory setting is forgotten' );
check( SSC_Conversation::purge() >= 1 && null === $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$memory} WHERE conv_hash = %s", SSC_Conversation::hash( $conv ) ) ), 'Expired conversations are purged' );
update_option( SSC_Modules::OPTION, array( 'history', 'pharma' ) );
check( 1 === SSC_Conversation::days(), 'Pharmaceutical mode keeps conversations for one day at most' );
update_option( SSC_Modules::OPTION, array( 'history' ) );

// 3. Ownership: a signed-in user's conversation is theirs alone.
$owner = str_repeat( 'd2', 16 );
SSC_Conversation::forget( $owner );
wp_set_current_user( 0 );
SSC_Conversation::append( $owner, 'guest question', 'answer one' );
wp_set_current_user( 1 );
SSC_Conversation::append( $owner, 'signed in now', 'answer two' );
$row = SSC_Conversation::row( $owner );
check( 1 === (int) $row['user_id'] && $owner === $row['conv_id'] && 4 === count( $row['messages'] ), 'A visitor who signs in keeps the conversation, now linked to the account' );
$other = wp_insert_user( array( 'user_login' => 'mem_other_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $other );
SSC_Conversation::append( $owner, 'intruder', 'x' );
check( array() === SSC_Conversation::load( $owner ) && ! SSC_Conversation::forget( $owner ), 'Another user cannot read, extend or delete it' );
wp_set_current_user( 0 );
check( array() === SSC_Conversation::load( $owner ), 'Nor can a guest holding the same id' );
wp_set_current_user( 1 );
check( 4 === count( SSC_Conversation::load( $owner ) ), 'The owner still has the untouched conversation' );

// 4. Conversation list over REST (signed-in users).
SSC_Setup::update_state( array( 'steps' => array_fill_keys( SSC_Setup::steps(), true ), 'published' => true ) );
SSC_Settings::update( array( 'enabled' => 'yes', 'chat_threads' => 'yes' ) );
$rest = new SSC_REST();
$list = $rest->threads()->get_data();
check( in_array( $owner, wp_list_pluck( $list['threads'], 'id' ), true ), 'Signed-in users list their conversations' );
$one = $rest->thread( array( 'id' => $owner ) );
check( ! is_wp_error( $one ) && 4 === count( $one->get_data()['messages'] ), 'and reopen one on any device' );
wp_set_current_user( $other );
check( is_wp_error( $rest->thread( array( 'id' => $owner ) ) ) && is_wp_error( $rest->forget_thread( array( 'id' => $owner ) ) ), 'Other users get nothing' );
wp_set_current_user( 0 );
check( is_wp_error( $rest->threads_permission() ), 'Guests have no conversation list' );
wp_set_current_user( 1 );
SSC_Settings::update( array( 'chat_threads' => 'no' ) );
check( is_wp_error( $rest->threads_permission() ), 'The list can be switched off' );
SSC_Settings::update( array( 'chat_threads' => 'yes' ) );
check( ! is_wp_error( $rest->forget_thread( array( 'id' => $owner ) ) ) && null === SSC_Conversation::row( $owner ), 'Users can delete their conversation' );
wp_delete_user( $other );

// 5. Rolling summary: older messages are condensed, never simply dropped.
$long = str_repeat( 'e3', 16 );
SSC_Conversation::forget( $long );
$wpdb->delete( SSC_Schema::chatlog_table_name(), array( 'conv_hash' => SSC_Conversation::hash( $long ) ) );
wp_clear_scheduled_hook( SSC_Conversation::SUMMARY_HOOK, array( $long ) );
SSC_Conversation::append( $long, 'My order number is 5521 and my name is Sara', 'Thanks Sara' );
for ( $i = 2; $i <= 4; ++$i ) {
	SSC_Conversation::append( $long, 'question ' . $i, 'answer ' . $i );
}
check( 4 === SSC_Conversation::pending_count( $long ) && false === wp_next_scheduled( SSC_Conversation::SUMMARY_HOOK, array( $long ) ), 'No summary work until enough messages left the window' );
SSC_Conversation::append( $long, 'question 5', 'answer 5' );
check( 6 === SSC_Conversation::pending_count( $long ) && false !== wp_next_scheduled( SSC_Conversation::SUMMARY_HOOK, array( $long ) ), 'The summary is refreshed in the background once six messages left the window' );
$ai_bodies = array();
$ai_reply  = 'Sara, order 5521, asked about delivery.';
check( SSC_Conversation::refresh_summary( $long ), 'The summary is written' );
$asked = wp_json_encode( $ai_bodies[0] );
check( false !== strpos( $asked, '5521' ) && false === strpos( $asked, 'question 5' ), 'Only the messages outside the window are summarized' );
check( 'Sara, order 5521, asked about delivery.' === SSC_Conversation::summary( $long ) && 0 === SSC_Conversation::pending_count( $long ), 'The summary is stored and the backlog cleared' );
SSC_Conversation::append( $long, 'question 6', 'answer 6' );
SSC_Conversation::append( $long, 'question 7', 'answer 7' );
SSC_Conversation::append( $long, 'question 8', 'answer 8' );
$ai_fail = true;
check( ! SSC_Conversation::refresh_summary( $long ) && 'Sara, order 5521, asked about delivery.' === SSC_Conversation::summary( $long ), 'A failed summary call keeps the previous summary' );
$ai_fail   = false;
$ai_bodies = array();
$ai_reply  = 'Merged summary';
SSC_Conversation::refresh_summary( $long );
check( false !== strpos( wp_json_encode( $ai_bodies[0] ), 'PREVIOUS SUMMARY' ) && 'Merged summary' === SSC_Conversation::summary( $long ), 'Each refresh merges the previous summary instead of starting over' );
$ai_bodies = array();
$ai_reply  = 'Reply';
$engine    = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.20', $long );
$engine->chat( 'and when will it arrive?', 'general', array() );
$system = (string) $ai_bodies[0]['messages'][0]['content'];
check( false !== strpos( $system, 'EARLIER IN THIS CONVERSATION' ) && false !== strpos( $system, '【SUMMARY' ) && false !== strpos( $system, 'Merged summary' ), 'The model gets the summary, fenced as data' );
check( 4 === count( $ai_bodies[0]['messages'] ) - 2, 'and only the window of recent messages word for word' );
wp_clear_scheduled_hook( SSC_Conversation::SUMMARY_HOOK, array( $long ) );

// 6. Chat log grouped by conversation.
$rows = SSC_Schema::get_chatlog( array( 'conv' => SSC_Conversation::hash( $long ) ) );
check( 1 === (int) $rows['total'] && 'and when will it arrive?' === $rows['items'][0]['question'], 'Logged exchanges carry their conversation, so an admin can read it whole' );
SSC_Conversation::forget( $long );

// 7. Provider failures: precise reason for admins, dashboard warning until recovered.
delete_option( SSC_Chat_Engine::FAILURE_OPTION );
$ai_fail = true;
$engine  = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.21', str_repeat( 'f4', 16 ) );
$failed  = $engine->chat( 'hello?', 'general', array() );
$notice  = $engine->admin_error_notice();
$recent  = SSC_Chat_Engine::recent_failures();
check( 'unanswered' === $failed['source'] && false === strpos( $failed['reply'], 'down' ), 'Visitors get the polite fallback, never the provider error' );
check( '' !== $engine->last_error_code && false !== strpos( $notice, SSC_HTTP::friendly_error( $engine->last_error_code ) ) && false !== strpos( $notice, 'down' ), 'Administrators see the reason in plain words plus the provider detail' );
check( $recent && 1 === (int) $recent['count'], 'Failures are counted for the dashboard' );
$ai_fail = false;
$engine->chat( 'hello again', 'general', array() );
check( null === SSC_Chat_Engine::recent_failures(), 'The warning clears after a successful answer' );
SSC_Conversation::forget( str_repeat( 'f4', 16 ) );

// 8. Logging is on by default: fresh installs and a one-time switch on upgrade.
check( array( 'history' ) === SSC_Modules::migrate_v4_state(), 'Fresh installs start with the History module' );
check( 'yes' === SSC_Settings::defaults()['chatlog_enabled'] && 30 === SSC_Settings::defaults()['chatlog_retention_days'], 'Conversation logging is on by default, kept 30 days' );
delete_option( 'ssc_chatlog_default_applied' );
update_option( SSC_Modules::OPTION, array() );
SSC_Settings::update( array( 'chatlog_enabled' => 'no' ) );
SSC_Schema::migrate_chatlog_default();
check( SSC_Modules::is_active( 'history' ) && 'yes' === SSC_Settings::get( 'chatlog_enabled' ), 'Upgrading sites get conversation logging switched on once' );
SSC_Settings::update( array( 'chatlog_enabled' => 'no' ) );
SSC_Schema::migrate_chatlog_default();
check( 'no' === SSC_Settings::get( 'chatlog_enabled' ), 'An administrator who turns it off afterwards is never overruled' );

remove_filter( 'pre_http_request', $mock_ai, PHP_INT_MAX );
update_option( SSC_Settings::OPTION_KEY, $settings_before );
update_option( SSC_Modules::OPTION, $modules_before );
update_option( 'ssc_chatbot_setup', $setup_before );
wp_set_current_user( 1 );
