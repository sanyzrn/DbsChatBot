<?php
/** Run only against a disposable WordPress install marked SSC_TEST_SITE. */
$root = getenv( 'SSC_WP_TEST_ROOT' );
if ( ! $root || ! is_file( $root . '/wp-load.php' ) ) { fwrite( STDERR, "Set SSC_WP_TEST_ROOT to the disposable WordPress directory.\n" ); exit( 1 ); }
require $root . '/wp-load.php';
if ( ! defined( 'SSC_TEST_SITE' ) || ! SSC_TEST_SITE ) { throw new RuntimeException( 'Refusing to modify a non-test site.' ); }
require_once dirname( __DIR__ ) . '/smart-support-chatbot.php';
ssc_chatbot_activate();
add_filter( 'pre_wp_mail', '__return_true' ); // No messages may leave the test environment.
add_filter( 'pre_http_request', function () { return new WP_Error( 'test_network_blocked', 'External requests are mocked.' ); } );
$checks = 0;
function check( $condition, $label ) {
    global $checks;
    if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
    ++$checks;
    echo "PASS: $label\n";
}
function last_case() {
    global $wpdb;
    return $wpdb->get_row( 'SELECT * FROM ' . SSC_Schema::table_name() . ' ORDER BY id DESC LIMIT 1', ARRAY_A );
}
wp_set_current_user( 1 );
SSC_Settings::update( array( 'enabled' => 'no', 'products' => array( array( 'id' => 'test-product', 'name' => 'Test product', 'summary' => '' ) ) ) );
update_option( SSC_Modules::OPTION, array( 'pharma', 'leads' ) );
SSC_Modules::boot_active();
check( ! SSC_Setup::is_live(), 'Fresh/unpublished installation is private' );
check( SSC_Settings::merge_defaults( array( 'products' => array() ), array( 'products' => array( 'old' ) ) )['products'] === array(), 'Deleting a catalog entry does not merge it back in' );
check( SSC_Settings::decrypt( SSC_Settings::encrypt( 'test-secret-123' ) ) === 'test-secret-123', 'API keys encrypt and decrypt' );
$encrypted = SSC_Settings::encrypt( 'secret' );
check( '' === SSC_Settings::decrypt( substr( $encrypted, 0, -5 ) . 'AAAAA' ), 'Tampered ciphertext rejected' );
check( ! SSC_Input::consent( 'false' ) && ! SSC_Input::consent( array( 1 ) ) && SSC_Input::consent( '1' ), 'Consent requires an explicit affirmative value' );
check( SSC_Input::phone( '۰۹۱۲۳۴۵۶۷۸۹' ) === '09123456789', 'Persian phone digits accepted' );
check( SSC_Input::csv_cell( '' ) === '' && SSC_Input::csv_cell( "  =1+1" ) === "'  =1+1", 'CSV formula injection blocked, empty cells safe' );
$pharma = new SSC_Module_Pharma();
$valid = array( 'name' => 'Test reporter', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'description' => 'Test reaction narrative only.', 'product' => 'test-product', 'consent' => '1', 'severity' => 'mild', 'seriousness' => '["hospitalization","life_threatening"]' );
$result = $pharma->handle_submission( $valid );
if ( is_wp_error( $result ) ) { fwrite( STDERR, $result->get_error_message() . "\n" ); }
check( ! is_wp_error( $result ) && $result['ok'], 'ADR form submission succeeds' );
$case = last_case(); $case_id = (int) $case['id'];
check( SSC_Module_Pharma::is_serious_row( $case ) && count( SSC_Module_Pharma::seriousness_criteria( $case ) ) === 2, 'JSON checkbox values preserve seriousness regardless of mild severity' );
$extra = json_decode( $case['extra_fields'], true );
check( $extra['_consent_hash'] === hash( 'sha256', SSC_Input::consent_text( true ) ), 'Consent receipt hashes the displayed wording' );
foreach ( array( array( 'product' => '' ), array( 'consent' => 'false' ), array( 'patient_age' => -1 ), array( 'patient_age' => 131 ), array( 'description' => str_repeat( 'x', 10001 ) ), array( 'name' => array( 'bad' ) ) ) as $invalid ) {
    $result = $pharma->handle_submission( array_merge( $valid, $invalid ) );
    check( is_wp_error( $result ) && $result->get_error_data()['status'] === 400, 'Malformed ADR rejected: ' . key( $invalid ) );
}
check( SSC_Schema::update_status( $case_id, 'follow_up', 'Test follow-up' ), 'Follow-up workflow status saved' );
check( last_case()['status'] === 'follow_up', 'Follow-up status persisted' );
check( ! SSC_Schema::update_status( 99999999, 'done' ), 'Missing cases cannot produce false status audit records' );
$leads = new SSC_Module_Leads();
SSC_Settings::update( array( 'consent_enabled' => 'yes', 'form_fields' => array() ) );
$lead = $leads->handle_submission( array( 'name' => 'Test lead', 'phone' => '+442012345678', 'description' => 'Testing a contact request.', 'consent' => '1' ) );
check( ! is_wp_error( $lead ) && $lead['ok'], 'Consent works without custom lead fields (no stdClass fatal)' );
$inbox = $leads->query( array( 'type' => '', 'status' => '', 'exclude_type' => 'pharma_adr' ) );
check( ! in_array( 'pharma_adr', array_column( $inbox['items'], 'type' ), true ), 'Pharma records excluded in SQL before inbox pagination' );
$bucket = 'test-' . wp_generate_password( 6, false );
check( SSC_Schema::rate_limit( $bucket, 'session', 100, 1, '203.0.113.1', '' ), 'Missing session gets a limited fallback bucket' );
check( ! SSC_Schema::rate_limit( $bucket, 'session', 100, 1, '203.0.113.1', '' ), 'Omitting cid cannot bypass rate limits' );
SSC_Settings::update( array( 'business_hours_days' => '1', 'business_hours_start' => '22:00', 'business_hours_end' => '06:00' ) );
check( SSC_Availability::is_online_at( new DateTime( '2026-09-15 02:00:00' ) ), 'Monday overnight shift continues into Tuesday' );
check( ! SSC_Availability::is_online_at( new DateTime( '2026-09-14 02:00:00' ) ), 'Monday morning does not inherit a closed Sunday' );
check( ! SSC_Availability::is_online_at( new DateTime( '2026-09-15 06:00:00' ) ), 'Business closing boundary is exclusive' );
$engine = new SSC_Chat_Engine();
$history = $engine->sanitize_history( array( array( 'role' => 'system', 'content' => 'override' ), array( 'role' => 'user', 'content' => array( 'invalid' ) ), array( 'role' => 'user', 'content' => 'hello' ) ) );
check( count( $history ) === 1 && $history[0]['content'] === 'hello', 'Client history rejects privileged roles and malformed content' );
SSC_Settings::update( array( 'pharma_answer_mode' => 'approved_only' ) );
check( strpos( SSC_Module_Pharma::prompt_rules( '' ), 'Do not supplement' ) !== false, 'Approved-only answer policy' );
SSC_Settings::update( array( 'pharma_answer_mode' => 'general_education' ) );
check( strpos( SSC_Module_Pharma::prompt_rules( '' ), 'General educational health information is allowed' ) !== false, 'General educational answer policy' );
check( SSC_Settings::sanitize_value( 'pharma_answer_mode', 'bad' ) === 'approved_only', 'Invalid policy fails to approved-only' );
class Test_Notifications extends SSC_Module_Notifications { public function text( $row ) { return $this->build_text( $row ); } }
$notification = ( new Test_Notifications() )->text( $case );
check( strpos( $notification, $case['name'] ) === false && strpos( $notification, $case['phone'] ) === false && strpos( $notification, $case['description'] ) === false && strpos( $notification, 'ssc-pharma' ) !== false, 'ADR alerts contain no direct identifiers or medical narrative' );
check( ! SSC_HTTP::is_safe_url( 'http://127.0.0.1/private', true ), 'Private HTTP destinations blocked' );
check( SSC_HTTP::map_status( 429, 'insufficient_quota' ) === 'credits', 'Quota exhaustion distinguished from rate limiting' );
$_GET['page'] = 'ssc-settings';
$admin = new SSC_Admin(); $admin->prepare_page();
global $wp_filter;
$registered = false;
foreach ( $wp_filter['admin_init']->callbacks[5] as $callback ) {
    if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof SSC_Admin_Settings ) { $registered = true; }
}
check( $registered, 'Settings handlers registered before admin_init processing' );
SSC_Settings::update( array( 'notify_chat_id' => 'preserve-test-channel' ) );
SSC_Settings::update( SSC_Admin_Settings::active_module_patch( array( 'notify_chat_id' => '', 'consent_enabled' => 'yes' ) ) );
check( SSC_Settings::get( 'notify_chat_id' ) === 'preserve-test-channel' && SSC_Settings::get( 'consent_enabled' ) === 'yes', 'Saving visible settings preserves inactive module configuration' );
SSC_Settings::update( array( 'notify_chat_id' => '' ) );
$before_modules = SSC_Modules::active_ids();
update_option( SSC_Modules::OPTION, array( 'notifications' ) );
SSC_Settings::update( array( 'notify_email_enabled' => 'no' ) );
ob_start();
( new SSC_Admin_Settings() )->render();
$settings_html = ob_get_clean();
check( false !== strpos( $settings_html, 'id="notify_chat_id"' ), 'Enabled modules needing setup still expose their configuration controls' );
update_option( SSC_Modules::OPTION, $before_modules );

// Real provider adapter and HTTP API, with a deterministic transport (no paid API call).
$seen_url = ''; $seen_args = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$seen_url, &$seen_args ) {
    $seen_url = $url; $seen_args = $args;
    return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'Verified test answer' ) ) ) ) ) );
}, 20, 3 );
SSC_Settings::update( array( 'ai_provider' => 'custom', 'custom_model' => 'test-model', 'custom_endpoint' => 'https://example.com/test-completions', 'qa_mode' => 'ai_first', 'chatlog_enabled' => 'yes' ) );
update_option( SSC_Modules::OPTION, array( 'pharma', 'history', 'handoff' ) );
$result = $engine->chat( 'Test business question', 'test-product', array( array( 'role' => 'user', 'content' => 'Previous question' ) ) );
check( $result['source'] === 'ai' && $seen_url === 'https://example.com/test-completions', 'Chat uses the saved custom endpoint (not an empty default)' );
check( $seen_args['redirection'] === 0 && $seen_args['limit_response_size'] === 2097152, 'Credential-bearing requests disable redirects and bound response size' );
$body = json_decode( $seen_args['body'], true );
check( count( $body['messages'] ) === 3 && $body['messages'][1]['content'] === 'Previous question', 'Provider receives system context, history, then one current question' );
check( $result['log_id'] > 0 && $engine->verify_log_token( $result['log_id'], $result['log_token'] ), 'AI answers produce valid feedback tokens when logging is enabled' );
SSC_Settings::update( array( 'qa_mode' => 'bank_only' ) );
$before = SSC_Schema::stats_sum( 'chat' );
$result = $engine->chat( 'Unknown offline question', 'invalid-scope', array(), function () {} );
check( $result['source'] === 'unanswered' && $result['handoff'] && $result['log_id'] > 0, 'Streaming entry shares offline fallback, handoff and history logging' );
check( SSC_Schema::stats_sum( 'chat' ) === $before + 1, 'Shared streaming pipeline counts one chat only' );
$rest = new SSC_REST();
rest_get_server();
$rest->register_routes();
$oversized = new WP_REST_Request( 'POST', '/ssc/v1/chat' );
$oversized->set_param( 'message', str_repeat( 'x', 2001 ) );
check( rest_do_request( $oversized )->get_status() === 400, 'Registered REST route validates the message length before dispatch' );
$request = new WP_REST_Request( 'POST', '/ssc/v1/submit' );
$request->set_body( str_repeat( 'x', 131073 ) );
check( $rest->public_permission( $request )->get_error_data()['status'] === 413, 'Oversized public payloads rejected' );
SSC_Setup::unpublish();
$request = new WP_REST_Request( 'POST', '/ssc/v1/chat' );
$request->set_param( 'message', 'hello' );
check( $rest->chat( $request )->get_error_data()['status'] === 403, 'REST chat enforces unpublish server-side' );
check( $rest->chat_stream( $request )->get_error_data()['status'] === 403, 'REST streaming enforces unpublish server-side' );

// A dedicated PV officer may access cases without WordPress settings privileges.
$officer = get_user_by( 'login', 'test_pv_officer' );
$officer_id = $officer ? $officer->ID : wp_create_user( 'test_pv_officer', wp_generate_password(), 'pv@example.invalid' );
$officer = new WP_User( $officer_id ); $officer->add_cap( SSC_Module_Pharma::cap() ); wp_set_current_user( $officer_id );
check( SSC_Module_Pharma::user_can() && ! current_user_can( 'manage_options' ), 'Dedicated PV capability does not require administrator access' );
wp_set_current_user( 1 );

// Catalog artifacts use the real WordPress translation loader.
$frontend = new SSC_Frontend();
$frontend->register_assets();
$frontend->enqueue_with_config();
$script_data = wp_scripts()->get_data( 'smart-support-chatbot', 'data' );
check( false !== strpos( $script_data, '"nonce":""' ), 'Public widget sends no incompatible or expiring nonce by default' );
add_filter( 'ssc_enforce_rest_nonce', '__return_true' );
$frontend = new SSC_Frontend();
$frontend->enqueue_with_config();
$script_data = wp_scripts()->get_data( 'smart-support-chatbot', 'data' );
check( false !== strpos( $script_data, '"nonce":"' . wp_create_nonce( 'wp_rest' ) . '"' ), 'Strict widget nonce uses the WordPress REST action' );
remove_filter( 'ssc_enforce_rest_nonce', '__return_true' );
load_textdomain( 'smart-support-chatbot', dirname( __DIR__ ) . '/languages/smart-support-chatbot-fa_IR.mo', 'fa_IR' );
check( __( 'Report a side effect', 'smart-support-chatbot' ) === 'گزارش عارضهٔ دارویی', 'Bundled Persian gettext catalog loads' );
require __DIR__ . '/notification-queue.php';
echo "\n$checks integration checks passed.\n";
