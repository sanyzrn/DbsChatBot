<?php
/**
 * Regression tests for the findings of the second external review (1.7.0, 2026-10-05).
 * Each block states the acceptance criterion of its finding.
 * Required from tests/integration.php (check() and a disposable site).
 */

global $wpdb;
$audit2_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
);
$audit2_server = $_SERVER;
wp_set_current_user( 1 );

// F04: the bank never gives an answer for other numbers (10 users / 5 users), however many numbers they share.
update_option( SSC_Modules::OPTION, array( 'faq' ) );
SSC_Settings::update( array( 'qa_mode' => 'bank_only', 'ai_provider' => 'none' ) );
SSC_Schema::qa_import(
	array(
		array( 'question' => 'آیا بسته ۱۰ کاربر با ۲ گیگابایت شامل پشتیبانی است؟', 'keywords' => 'بسته پشتیبانی', 'answer' => 'PLAN-10-USERS', 'product_id' => 'general' ),
		array( 'question' => 'دوز ۵ میلی‌گرم روزی ۲ بار مناسب است؟', 'keywords' => 'دوز', 'answer' => 'DOSE-5MG', 'product_id' => 'general' ),
	),
	true
);
$ask = function ( $q ) {
	$row = SSC_Knowledge::bank_answer( 'general', $q );
	return $row ? $row['answer'] : null;
};
check( 'PLAN-10-USERS' === $ask( 'آیا بسته ۱۰ کاربر با ۲ گیگابایت شامل پشتیبانی است؟' ), 'F04: the exact same question gets its answer' );
check( 'PLAN-10-USERS' === $ask( 'آیا بسته 10 کاربر با 2 گیگابایت شامل پشتیبانی است' ), 'F04: the same question with Latin digits gets its answer' );
check( null === $ask( 'آیا بسته ۵ کاربر با ۲ گیگابایت شامل پشتیبانی است؟' ), 'F04: 5 users with the same 2 GB does not get the 10-user answer' );
check( null === $ask( 'آیا بسته ۲ کاربر با ۱۰ گیگابایت شامل پشتیبانی است؟' ), 'F04: the same numbers swapped between what they count do not share an answer' );
check( 'DOSE-5MG' === $ask( 'دوز ۵ میلی‌گرم روزی ۲ بار مناسب است؟' ) && null === $ask( 'دوز ۱۰ میلی‌گرم روزی ۲ بار مناسب است؟' ), 'F04: a different dose with the same number of times a day is not answered' );
SSC_Schema::qa_import( array(), true );

// F02: a header is believed only from a listed proxy, this machine, or (when the owner allows it) a private network; never from carrier-grade addresses on their own.
SSC_Settings::update( array( 'trusted_proxy_header' => 'HTTP_X_FORWARDED_FOR', 'trusted_proxies' => '', 'trust_private_proxies' => 'no' ) );
$engine_ip = function ( $remote ) {
	$_SERVER['REMOTE_ADDR']          = $remote;
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
	return ( new SSC_Chat_Engine() )->client_ip();
};
check( '192.168.1.25' === $engine_ip( '192.168.1.25' ) && '100.64.1.25' === $engine_ip( '100.64.1.25' ) && '10.0.0.7' === $engine_ip( '10.0.0.7' ) && '203.0.113.50' === $engine_ip( '203.0.113.50' ), 'F02: with no proxy listed, a forged header is ignored from LAN, carrier-grade and public addresses' );
check( '198.51.100.9' === $engine_ip( '127.0.0.1' ), 'F02: a proxy on this machine is believed without being listed' );
SSC_Settings::update( array( 'trust_private_proxies' => 'yes' ) );
check( '198.51.100.9' === $engine_ip( '192.168.1.25' ) && '100.64.1.25' === $engine_ip( '100.64.1.25' ) && '203.0.113.50' === $engine_ip( '203.0.113.50' ), 'F02: once the owner allows private networks, LAN is believed, carrier-grade and public addresses still are not' );
SSC_Settings::update( array( 'trust_private_proxies' => 'no', 'trusted_proxies' => "203.0.113.0/24\n100.64.0.0/10" ) );
check( '198.51.100.9' === $engine_ip( '203.0.113.50' ) && '198.51.100.9' === $engine_ip( '100.64.1.25' ) && '192.168.1.25' === $engine_ip( '192.168.1.25' ), 'F02: a listed proxy (carrier-grade included) is believed; an unlisted LAN address is not' );
check( 'no' === SSC_Settings::sanitize_value( 'trust_private_proxies', 'junk' ) && 'yes' === SSC_Settings::sanitize_value( 'trust_private_proxies', 'yes' ), 'F02: the private-network setting accepts only yes or no' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-settings' ), function () {
	( new SSC_Admin_Settings() )->render();
} );
check( false !== strpos( $html, 'name="trust_private_proxies"' ) && false !== strpos( $html, '100.64.x' ), 'F02: Settings has the private-network choice and says what it does not include' );
// A site that had chosen a header and listed no proxies keeps the behaviour it relied on, once.
delete_option( 'ssc_private_proxy_trust_applied' );
SSC_Settings::update( array( 'trusted_proxy_header' => 'HTTP_X_FORWARDED_FOR', 'trusted_proxies' => '', 'trust_private_proxies' => 'no' ) );
SSC_Schema::migrate_private_proxy_trust();
check( 'yes' === SSC_Settings::get( 'trust_private_proxies', 'no' ), 'F02: an existing site that chose a header and listed no proxies keeps trusting its private proxy until it decides otherwise' );
SSC_Settings::update( array( 'trust_private_proxies' => 'no' ) );
SSC_Schema::migrate_private_proxy_trust();
check( 'no' === SSC_Settings::get( 'trust_private_proxies', 'yes' ), 'F02: the migration runs only once, so a later choice is never overruled' );
delete_option( 'ssc_private_proxy_trust_applied' );
SSC_Settings::update( array( 'trusted_proxy_header' => '', 'trusted_proxies' => '', 'trust_private_proxies' => 'no' ) );
SSC_Schema::migrate_private_proxy_trust();
check( 'no' === SSC_Settings::get( 'trust_private_proxies', 'yes' ), 'F02: a site with no proxy header gets the new default (off)' );
$_SERVER = $audit2_server;
SSC_Settings::update( array( 'trusted_proxy_header' => '', 'trusted_proxies' => '', 'trust_private_proxies' => 'no' ) );

// N02: a wrong email is refused, never repaired into a different address; a list or object is refused; empty stays fine.
update_option( SSC_Modules::OPTION, array( 'leads' ) );
SSC_Settings::update( array( 'consult_email' => 'optional', 'consent_enabled' => 'no', 'form_fields' => array() ) );
$leads    = new SSC_Module_Leads();
$base     = array( 'type' => 'consult', 'name' => 'Review user', 'phone' => '09123456789', 'description' => 'A synthetic request for the review.' );
$rows     = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SSC_Schema::table_name() );
};
$before   = $rows();
foreach ( array( 'bad address@example.invalid', 'a b@example.com', 'sara@@example.com', 'sara@example', "sara@example.com\nBcc: x@y.z", array(), array( 'a@b.ir' ), (object) array( 'a' => 1 ), 'سارا@example.com', str_repeat( 'a', 200 ) . '@example.com' ) as $bad ) {
	$res = $leads->handle_submission( array_merge( $base, array( 'email' => $bad ) ), '203.0.113.70' );
	check( is_wp_error( $res ) && 400 === $res->get_error_data()['status'], 'N02: refused email: ' . ( is_scalar( $bad ) ? substr( json_encode( $bad, JSON_UNESCAPED_UNICODE ), 0, 50 ) : gettype( $bad ) ) );
}
check( $before === $rows(), 'N02: no record is stored for a refused email' );
foreach ( array( 'sara@example.com', '  sara@example.com  ', 'Sara.Ahmadi+news@Example.co.ir' ) as $good ) {
	$res = $leads->handle_submission( array_merge( $base, array( 'email' => $good ) ), '203.0.113.70' );
	check( ! is_wp_error( $res ) && trim( $good ) === SSC_Module_Leads::email_of( last_case() ), 'N02: a valid email is stored exactly as typed: ' . trim( $good ) );
}
foreach ( array( '', '   ', null ) as $none ) {
	$res = $leads->handle_submission( array_merge( $base, array( 'email' => $none ) ), '203.0.113.70' );
	check( ! is_wp_error( $res ) && '' === SSC_Module_Leads::email_of( last_case() ), 'N02: an empty email is fine' );
}
check( SSC_Input::strict_email( 'a@b.ir' ) === 'a@b.ir' && false === SSC_Input::strict_email( 'a b@c.ir' ) && '' === SSC_Input::strict_email( '' ) && false === SSC_Input::strict_email( array() ), 'N02: strict_email() decides the same everywhere' );
// The same rule for a custom email field, and for a list where text belongs.
SSC_Settings::update( array( 'form_fields' => array(
	array( 'key' => 'work_email', 'label' => 'Work email', 'type' => 'email', 'required' => false, 'options' => array(), 'placeholder' => '' ),
	array( 'key' => 'team', 'label' => 'Team size', 'type' => 'number', 'required' => false, 'options' => array(), 'placeholder' => '' ),
	array( 'key' => 'note', 'label' => 'Note', 'type' => 'text', 'required' => false, 'options' => array(), 'placeholder' => '' ),
	array( 'key' => 'plan', 'label' => 'Plan', 'type' => 'select', 'required' => false, 'options' => array( 'basic', 'pro' ), 'placeholder' => '' ),
) ) );
$extra = function ( $values ) use ( $leads ) {
	return $leads->validate_extra_fields( $values );
};
foreach ( array( 'bad address@example.com', 'a@b', array( 'x@y.ir' ) ) as $bad ) {
	$res = $extra( array( 'work_email' => $bad ) );
	check( is_wp_error( $res ), 'N02: a custom email field refuses ' . ( is_array( $bad ) ? 'a list' : $bad ) );
}
check( '{"work_email":"a@b.ir"}' === $extra( array( 'work_email' => ' a@b.ir ' ) ) && '' === (string) ( json_decode( $extra( array( 'work_email' => '' ) ), true )['work_email'] ?? '' ), 'N02: a custom email field takes a good address and an empty one' );
$res = $extra( array( 'note' => array( 'a', 'b' ) ) );
check( is_wp_error( $res ), 'N02: a list where a single value belongs is refused, not quietly emptied' );

// N03: Persian and Arabic digits are numbers in a custom number field; text, lists and out-of-range values are refused with a message.
foreach ( array( '2' => 2.0, '۲' => 2.0, '٢' => 2.0, '25.5' => 25.5, '۲۵٫۵' => 25.5, ' ۱۲ ' => 12.0, '0' => 0.0 ) as $typed => $number ) {
	$res  = $extra( array( 'team' => (string) $typed ) );
	$data = is_wp_error( $res ) ? array() : json_decode( $res, true );
	check( ! is_wp_error( $res ) && isset( $data['team'] ) && abs( $number - (float) $data['team'] ) < 0.0001, 'N03: the number "' . $typed . '" is read as ' . $number );
}
foreach ( array( 'abc', '12abc', '1e9', '1.2.3', '۱۲۳۴۵۶۷۸۹۰۱۲۳', array( '2' ), '-' ) as $bad ) {
	$res = $extra( array( 'team' => $bad ) );
	check( is_wp_error( $res ) && false !== strpos( $res->get_error_message(), 'Team size' ), 'N03: a bad number is refused naming the field: ' . ( is_array( $bad ) ? 'list' : $bad ) );
}
check( ! is_wp_error( $extra( array() ) ), 'N03: an optional number may be left empty' );
SSC_Settings::update( array( 'form_fields' => array( array( 'key' => 'team', 'label' => 'Team size', 'type' => 'number', 'required' => true, 'options' => array(), 'placeholder' => '' ) ) ) );
$res = $extra( array( 'team' => '۲' ) );
check( ! is_wp_error( $res ) && 2.0 === (float) json_decode( $res, true )['team'], 'N03: a required number typed with Persian digits is accepted (it used to fail as "required")' );
check( is_wp_error( $extra( array( 'team' => '' ) ) ), 'N03: a required number still cannot be empty' );
SSC_Settings::update( array( 'form_fields' => array( array( 'key' => 'plan', 'label' => 'Plan', 'type' => 'select', 'required' => false, 'options' => array( 'basic', 'pro' ), 'placeholder' => '' ) ) ) );
check( is_wp_error( $extra( array( 'plan' => 'enterprise' ) ) ) && ! is_wp_error( $extra( array( 'plan' => 'pro' ) ) ) && ! is_wp_error( $extra( array() ) ), 'N03: a choice that is not on the list is refused, a listed one and none are fine' );
SSC_Settings::update( array( 'form_fields' => array() ) );

// N04: an answer the model did not finish is marked, flagged and never cached; going on is the visitor's choice.
update_option( SSC_Modules::OPTION, array() );
SSC_Settings::update( array( 'ai_provider' => 'openai', 'openai_model' => 'gpt-4o-mini', 'qa_mode' => 'ai_first', 'ai_cache_enabled' => 'yes', 'streaming_enabled' => 'no', 'show_sources' => 'no', 'web_search' => 'no', 'kb_semantic' => 'no' ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-n04-test-key' );
$n04_calls   = 0;
$n04_fixture = null;
$n04_mock    = function () use ( &$n04_calls, &$n04_fixture ) {
	++$n04_calls;
	return $n04_fixture;
};
add_filter( 'pre_http_request', $n04_mock, 30 );
$n04_set = function ( $content, $finish ) use ( &$n04_fixture ) {
	$n04_fixture = array(
		'headers'  => array(),
		'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $content ), 'finish_reason' => $finish ) ) ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
$n04_ask = function ( $question ) {
	$engine = new SSC_Chat_Engine();
	$engine->set_context( '203.0.113.90', '' );
	return $engine->chat( $question, 'general', array() );
};
$n04_set( 'برای قیمت و موجودی', 'length' );
$calls_before = $n04_calls;
$res          = $n04_ask( 'N04 price question one' );
check( 'ai' === $res['source'] && 'length' === ( $res['flags']['incomplete'] ?? '' ), 'N04: an answer that stopped on length is flagged incomplete, not shown as a finished one' );
check( 0 === strpos( $res['reply'], 'برای قیمت و موجودی' ) && false !== strpos( $res['reply'], SSC_Chat_Engine::incomplete_note( 'length' ) ), 'N04: the text itself says it was cut short (every channel and the log see it)' );
check( 1 === $n04_calls - $calls_before, 'N04: the plugin does not ask the model to continue by itself' );
$res = $n04_ask( 'N04 price question one' );
check( 2 === $n04_calls - $calls_before && 'length' === ( $res['flags']['incomplete'] ?? '' ), 'N04: a cut answer is not cached, so asking again gets a fresh attempt' );
$n04_set( 'A complete answer.', 'stop' );
$calls_before = $n04_calls;
$res          = $n04_ask( 'N04 normal question' );
check( 'ai' === $res['source'] && ! isset( $res['flags']['incomplete'] ) && 'A complete answer.' === $res['reply'], 'N04: a normal stop is shown as it is, with no flag and no note' );
$n04_ask( 'N04 normal question' );
check( 1 === $n04_calls - $calls_before, 'N04: a complete answer is still cached' );
$n04_set( 'A partial answer', 'content_filter' );
$res = $n04_ask( 'N04 filtered question' );
check( 'filtered' === ( $res['flags']['incomplete'] ?? '' ) && false !== strpos( $res['reply'], SSC_Chat_Engine::incomplete_note( 'filtered' ) ) && $res['reply'] !== 'A partial answer', 'N04: a content-filter stop is its own state, worded differently from a length stop' );
check( SSC_Chat_Engine::incomplete_note( 'filtered' ) !== SSC_Chat_Engine::incomplete_note( 'length' ) && SSC_Chat_Engine::incomplete_note( 'interrupted' ) !== SSC_Chat_Engine::incomplete_note( 'length' ), 'N04: length, policy and interruption each have their own wording' );
$n04_set( 'Half of an answ', 'error' );
$res = $n04_ask( 'N04 error question' );
check( 'interrupted' === ( $res['flags']['incomplete'] ?? '' ), 'N04: a provider that stopped on an error is flagged interrupted' );
$n04_set( '', 'length' );
$res = $n04_ask( 'N04 empty reasoning question' );
$why = SSC_Chat_Engine::recent_failures();
check( 'unanswered' === $res['source'] && ! isset( $res['flags']['incomplete'] ) && is_array( $why ) && 'length' === $why['code'], 'N04: a model that used its whole budget before writing anything is reported with its real reason, not as a bad response format' );
check( false !== strpos( SSC_HTTP::friendly_error( 'length' ), 'Max answer length' ) && 'Unknown connection error.' !== SSC_HTTP::friendly_error( 'filtered' ), 'N04: both reasons have a plain-language explanation for the administrator' );
check( '' === SSC_Chat_Engine::incomplete_kind( array( 'finish' => 'stop' ) ) && '' === SSC_Chat_Engine::incomplete_kind( array() ) && 'length' === SSC_Chat_Engine::incomplete_kind( array( 'finish' => 'length' ) ) && 'filtered' === SSC_Chat_Engine::incomplete_kind( array( 'finish' => 'filtered', 'partial' => true ) ) && 'interrupted' === SSC_Chat_Engine::incomplete_kind( array( 'partial' => true, 'finish' => 'error' ) ), 'N04: a stream that broke off mid-answer is interrupted, one stopped by policy is filtered, a normal end is neither' );
delete_option( SSC_Chat_Engine::FAILURE_OPTION );
remove_filter( 'pre_http_request', $n04_mock, 30 );
SSC_Settings::set_secret( 'openai_api_key', '' );

// N01: a negation belongs to its own clause; "no particular problem, but I want a consultation" still asks for one.
foreach ( array(
	'می‌خواهم مشاوره بگیرم',
	'مشکل خاصی ندارم ولی می‌خواهم مشاوره بگیرم',
	'مشکل خاصی ندارم اما میخوام مشاوره بگیرم',
	'مشکل خاصی ندارم و می‌خواهم مشاوره بگیرم',
	'سوال خاصی ندارم، فقط مشاوره می‌خوام',
	'هیچ مشکلی نیست ولی نیاز به مشاوره دارم',
	'I have no specific problem but I want a consultation',
	'No problem, I would like a consultation',
	'I do not have a question however I need a call back',
	'Nothing is wrong although I want to book a consultation',
) as $message ) {
	check( SSC_Module_Leads::wants_consultation( $message ), 'N01: a clear request is recognised despite an unrelated negation: ' . $message );
}
foreach ( array(
	'مشاوره نمی‌خوام',
	'مشاوره نمی‌خواهم',
	'نمی‌خوام مشاوره بگیرم',
	'مشکل خاصی ندارم ولی نمی‌خواهم مشاوره بگیرم',
	'مشاوره نمی‌خوام و فقط سوال دارم',
	'مشاوره نمی‌خوام ولی هزینه‌اش چقدره',
	'مشکل خاصی ندارم',
	'هزینه مشاوره چقدر است؟',
	'I do not want a consultation',
	'I do not want a consultation, I only have a question',
	"don't call me",
	'I have no problem but I do not need a consultation',
	'what are your opening hours?',
) as $message ) {
	check( ! SSC_Module_Leads::wants_consultation( $message ), 'N01: a refusal, a price question or no request is still not an offer: ' . $message );
}
update_option( SSC_Modules::OPTION, array( 'leads' ) );
SSC_Modules::boot_active();
SSC_Settings::update( array( 'ai_provider' => 'none', 'qa_mode' => 'bank_only' ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.91', '' );
$offer = $engine->chat( 'مشکل خاصی ندارم ولی می‌خواهم مشاوره بگیرم', 'general', array() );
check( ! empty( $offer['flags']['consult_offer'] ), 'N01: the chat offers the request form for the report\'s sentence' );

// F01: the pharmaceutical router answers "breathing has become impossible" without any model.
update_option( SSC_Modules::OPTION, array( 'pharma' ) );
SSC_Modules::boot_active();
$pharma_module = new SSC_Module_Pharma();
$stub          = (object) array( 'flags' => array() );
foreach ( array( 'نفس کشیدن برام غیرممکن شده', 'نفس کشیدن برایم ناممکن شده', 'هوا بهم نمی‌رسه', 'نفسم تنگ شده' ) as $urgent ) {
	$stub->flags = array();
	$reply       = $pharma_module->route_adr_intent( null, $urgent, 'general', $stub );
	check( is_string( $reply ) && ! empty( $stub->flags['emergency'] ), 'F01: emergency guidance without a model for: ' . $urgent );
}
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.92', '' );
$result = $engine->chat( 'نفس کشیدن برام غیرممکن شده', 'general', array() );
check( ! empty( $result['flags']['emergency'] ) && false !== strpos( (string) $result['reply'], 'اورژانس' ), 'F01: the chat itself stops on the report\'s sentence, with the AI switched off' );
foreach ( array( 'تنگی نفس ندارم', 'آیا نفس کشیدن بعد از ورزش سخت می‌شود؟', 'نفس کشیدن غیرممکن نیست' ) as $calm ) {
	$stub->flags = array();
	check( null === $pharma_module->route_adr_intent( null, $calm, 'general', $stub ) && empty( $stub->flags['emergency'] ), 'F01: a denial or a teaching question is not intercepted: ' . $calm );
}

// N05: a value of the wrong kind in the settings section is refused whole, naming the setting; nothing is applied.
$bundle_of = function ( $settings ) {
	return wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'settings' => $settings ) );
};
$welcome_before = SSC_Settings::get( 'welcome_text', '' );
foreach ( array(
	'welcome_text'   => array( 'malformed' ),
	'ai_max_tokens'  => 'plenty',
	'form_fields'    => 'not a list',
	'position'       => array( 'x' => 'y' ),
	'ai_temperature' => null,
	'sound_enabled'  => array( 'yes' ),
) as $key => $bad ) {
	$parsed = SSC_Transfer::parse( $bundle_of( array( 'position' => 'left', $key => $bad ) ) );
	check( is_wp_error( $parsed ) && 'ssc_transfer_shape' === $parsed->get_error_code() && false !== strpos( $parsed->get_error_message(), '“' . $key . '”' ), 'N05: ' . $key . ' with a value of the wrong kind is refused, naming the setting' );
}
check( $welcome_before === SSC_Settings::get( 'welcome_text', '' ), 'N05: a refused file changes nothing' );
$parsed = SSC_Transfer::parse( wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'products' => array( array( 'name' => 'a', 'summary' => 's' ) ), 'settings' => array( 'welcome_text' => array( 'x' ) ) ) ) );
check( is_wp_error( $parsed ), 'N05: one bad setting refuses the whole file, even next to good sections' );
check( is_wp_error( SSC_Transfer::parse( $bundle_of( 'a string' ) ) ) && ! is_wp_error( SSC_Transfer::parse( wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'settings' => null ) ) ) ), 'N05: a settings section that is not an object is refused; a null one is the same as none' );
check( ! is_wp_error( SSC_Transfer::parse( $bundle_of( array( 'welcome_text' => 'Hello', 'ai_max_tokens' => '800', 'ai_temperature' => 0.4, 'sound_enabled' => 'yes', 'sitesync_types' => array( 'page', 'post' ), 'not_a_real_setting' => array( 'ignored' ), 'trusted_proxies' => array( 'ignored' ) ) ) ) ), 'N05: right kinds pass (numbers may be text); unknown and site-bound settings are ignored as before' );
$full = SSC_Transfer::build( array_keys( SSC_Transfer::sections() ), 'correct horse battery' );
check( ! is_wp_error( $full ) && ! is_wp_error( SSC_Transfer::parse( SSC_Transfer::encode( $full ) ) ), 'N05: a real export of this site is still accepted' );

// N06: the Connection panel does not claim that keys never leave the server.
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-connection' ), function () {
	( new SSC_Admin_Connection() )->render();
} );
check( false === strpos( $html, 'never leave the server' ) && false !== strpos( $html, 'never shown in the browser' ) && false !== strpos( $html, 'sent only to the provider you choose' ), 'N06: the panel says keys are stored encrypted, not shown in the browser, and sent only to the chosen provider' );

// Put everything back.
if ( null === $audit2_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $audit2_before['modules'], false );
}
if ( null === $audit2_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $audit2_before['settings'] );
}
SSC_Settings::reset_cache();
