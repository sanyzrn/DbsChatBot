<?php
/**
 * Regression tests for the findings of the third external review (1.7.1, 2026-10-06).
 * Each block states the acceptance criterion of its finding.
 * Required from tests/integration.php (check() and a disposable site).
 */

global $wpdb;
$audit3_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
);
wp_set_current_user( 1 );

// F04: a stored answer is never given for another unit, another sign or another number; the exact question still gets it.
update_option( SSC_Modules::OPTION, array( 'faq' ) );
SSC_Settings::update( array( 'qa_mode' => 'bank_only', 'ai_provider' => 'none' ) );
SSC_Schema::qa_import(
	array(
		array( 'question' => 'آیا بسته ۱۰ گیگابایت شامل پشتیبانی است؟', 'keywords' => 'بسته پشتیبانی', 'answer' => 'PLAN-10-GB', 'product_id' => 'general' ),
		array( 'question' => 'آیا در دمای ۲۰ درجه نگهداری می‌شود؟', 'keywords' => 'دما نگهداری', 'answer' => 'STORE-PLUS-20', 'product_id' => 'general' ),
		array( 'question' => 'Is the 5 millimeter cable available?', 'keywords' => 'cable available', 'answer' => 'CABLE-5-MM', 'product_id' => 'general' ),
	),
	true
);
$ask = function ( $q ) {
	$row = SSC_Knowledge::bank_answer( 'general', $q );
	return $row ? $row['answer'] : null;
};
check( 'PLAN-10-GB' === $ask( 'آیا بسته ۱۰ گیگابایت شامل پشتیبانی است؟' ) && 'PLAN-10-GB' === $ask( 'آیا بسته 10 گیگ شامل پشتیبانی است' ), 'F04: the same question, spelled differently, gets its answer' );
check( null === $ask( 'آیا بسته ۱۰ گیگابیت شامل پشتیبانی است؟' ), 'F04: 10 gigabit does not get the answer written for 10 gigabyte' );
check( 'STORE-PLUS-20' === $ask( 'آیا در دمای +۲۰ درجه نگهداری می‌شود؟' ) && null === $ask( 'آیا در دمای -۲۰ درجه نگهداری می‌شود؟' ) && null === $ask( 'آیا در دمای منفی ۲۰ درجه نگهداری می‌شود؟' ), 'F04: minus 20 degrees does not get the answer written for 20 degrees' );
check( 'CABLE-5-MM' === $ask( 'Is the 5 mm cable available?' ) && null === $ask( 'Is the 5 milligram cable available?' ), 'F04: millimeter and milligram are told apart' );
SSC_Schema::qa_import( array(), true );

// N07: an import file whose documents hold a list, an object or nothing is refused whole, naming the place; nothing is written.
$doc_file = function ( $docs ) {
	return wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'documents' => $docs ) );
};
$good_doc = array( 'doc_id' => 'n07-doc', 'title' => 'N07 doc', 'chunks' => array( 'First part of the text.', 'Second part.' ), 'product_id' => 'general', 'url' => '' );
foreach ( array(
	'chunks'     => array_merge( $good_doc, array( 'chunks' => array( array( 'bad' ) ) ) ),
	'title'      => array_merge( $good_doc, array( 'title' => array( 'x' ) ) ),
	'doc_id'     => array_merge( $good_doc, array( 'doc_id' => array( 'x' ) ) ),
	'url'        => array_merge( $good_doc, array( 'url' => array( 'x' ) ) ),
	'product_id' => array_merge( $good_doc, array( 'product_id' => (object) array( 'a' => 1 ) ) ),
) as $field => $bad_doc ) {
	$parsed = SSC_Transfer::parse( $doc_file( array( $good_doc, $bad_doc ) ) );
	check( is_wp_error( $parsed ) && 'ssc_transfer_shape' === $parsed->get_error_code() && false !== strpos( $parsed->get_error_message(), 'documents, row 2' ) && false !== strpos( $parsed->get_error_message(), '“' . $field . '”' ), 'N07: a document with a bad ' . $field . ' is refused, naming the section, row and field' );
}
$no_chunks = $good_doc;
unset( $no_chunks['chunks'] );
$no_id = $good_doc;
unset( $no_id['doc_id'] );
check( is_wp_error( SSC_Transfer::parse( $doc_file( array( $no_chunks ) ) ) ) && is_wp_error( SSC_Transfer::parse( $doc_file( array( $no_id ) ) ) ) && is_wp_error( SSC_Transfer::parse( $doc_file( array( 'text' ) ) ) ), 'N07: a document without text, without an id, or that is not an object is refused' );
check( ! is_wp_error( SSC_Transfer::parse( $doc_file( array( $good_doc ) ) ) ), 'N07: a well-formed document is accepted' );

$doc_count = function ( $id ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SSC_Schema::kb_table_name() . ' WHERE doc_id = %s', $id ) );
};
$doc_text  = function ( $id ) use ( $wpdb ) {
	return implode( ' | ', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT chunk FROM ' . SSC_Schema::kb_table_name() . ' WHERE doc_id = %s ORDER BY id ASC', $id ) ) );
};
SSC_Schema::kb_delete_document( 'n07-doc' );
SSC_Schema::kb_delete_document( 'n07-old' );
$warnings = array();
set_error_handler( function ( $no, $msg ) use ( &$warnings ) {
	$warnings[] = $msg;
	return true;
} );
$apply = new ReflectionMethod( 'SSC_Transfer', 'apply_documents' );
$apply->setAccessible( true );
$report = $apply->invoke( null, array( $good_doc ), false );
check( 'ok' === $report['status'] && 1 === $report['count'] && 2 === $doc_count( 'n07-doc' ) && 'First part of the text. | Second part.' === $doc_text( 'n07-doc' ), 'N07: a valid document is imported with its own split' );
$changed = array_merge( $good_doc, array( 'chunks' => array( 'Replaced text.' ) ) );
$report  = $apply->invoke( null, array( $changed ), false );
check( 'ok' === $report['status'] && 1 === $doc_count( 'n07-doc' ) && 'Replaced text.' === $doc_text( 'n07-doc' ), 'N07: importing again replaces the document whole' );
// Replace mode: what the file does not name goes, but only after what it names is in place.
SSC_Schema::kb_insert_document( 'n07-old', 'Old', 'An older document that the file does not name.' );
$report = $apply->invoke( null, array( $good_doc ), true );
check( 'ok' === $report['status'] && 0 === $doc_count( 'n07-old' ) && 2 === $doc_count( 'n07-doc' ), 'N07: replace mode removes the document the file does not name' );
// A failure while a document is put in place: the previous version stays, and nothing else is removed in replace mode.
SSC_Schema::kb_insert_document( 'n07-old', 'Old', 'An older document that the file does not name.' );
$boom = function () {
	throw new RuntimeException( 'simulated failure in the middle' );
};
add_action( 'ssc_kb_replace_built', $boom );
$report = $apply->invoke( null, array( array_merge( $good_doc, array( 'chunks' => array( 'Never stored.' ) ) ) ), true );
remove_action( 'ssc_kb_replace_built', $boom );
check( 'warn' === $report['status'] && 0 === $report['count'] && 2 === $doc_count( 'n07-doc' ) && false === strpos( $doc_text( 'n07-doc' ), 'Never stored' ) && $doc_count( 'n07-old' ) > 0, 'N07: a failed insert keeps the previous document, is not reported as a success, and in replace mode removes nothing' );
// Rows that parse() would refuse never become "Array" text, whatever reaches the importer.
$report = $apply->invoke( null, array( array( 'doc_id' => 'n07-doc2', 'title' => array( 'x' ), 'chunks' => array( array( 'bad' ), 'good chunk' ) ) ), false );
check( 'ok' === $report['status'] && 'good chunk' === $doc_text( 'n07-doc2' ) && array() === $warnings, 'N07: a list can never be stored as the text "Array", and no PHP warning is raised' );
restore_error_handler();
SSC_Schema::kb_delete_document( 'n07-doc' );
SSC_Schema::kb_delete_document( 'n07-doc2' );
SSC_Schema::kb_delete_document( 'n07-old' );

// N08: values of the wrong kind one level down are refused too, with the exact place.
$settings_file = function ( $settings ) {
	return wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'settings' => $settings ) );
};
foreach ( array(
	'form_fields[0].label'        => array( 'form_fields' => array( array( 'key' => 'audit', 'label' => array( 'bad' ), 'type' => 'text' ) ) ),
	'form_fields[1].key'          => array( 'form_fields' => array( array( 'key' => 'a', 'label' => 'A', 'type' => 'text' ), array( 'key' => array( 'k' ), 'label' => 'B', 'type' => 'text' ) ) ),
	'form_fields[0].options[0]'   => array( 'form_fields' => array( array( 'key' => 'a', 'label' => 'A', 'type' => 'select', 'options' => array( array( 'x' ) ) ) ) ),
	'form_fields[0].required'     => array( 'form_fields' => array( array( 'key' => 'a', 'label' => 'A', 'type' => 'text', 'required' => array( 1 ) ) ) ),
	'form_fields[0]'              => array( 'form_fields' => array( 'a row that is text' ) ),
	'sitesync_types[0]'           => array( 'sitesync_types' => array( array( 'page' ) ) ),
	'adr_form.intro'              => array( 'adr_form' => array( 'intro' => array( 'x' ) ) ),
	'adr_form.custom[0].label'    => array( 'adr_form' => array( 'custom' => array( array( 'label' => array( 'x' ), 'type' => 'text' ) ) ) ),
	'adr_form.fields.age.label'   => array( 'adr_form' => array( 'fields' => array( 'age' => array( 'on' => true, 'label' => array( 'x' ) ) ) ) ),
) as $place => $settings ) {
	$parsed = SSC_Transfer::parse( $settings_file( $settings ) );
	check( is_wp_error( $parsed ) && 'ssc_transfer_shape' === $parsed->get_error_code() && false !== strpos( $parsed->get_error_message(), '“' . $place . '”' ), 'N08: ' . $place . ' is refused, naming the place' );
}
$persian_form = array(
	array( 'key' => 'team', 'label' => 'تعداد اعضا', 'type' => 'number', 'required' => true, 'options' => array(), 'placeholder' => '۱۰' ),
	array( 'key' => 'plan', 'label' => 'نوع بسته', 'type' => 'select', 'required' => false, 'options' => array( 'پایه', 'حرفه‌ای' ), 'placeholder' => '' ),
	array( 'key' => 'mode', 'label' => 'Mode', 'type' => 'radio', 'required' => '1', 'options' => "a\nb", 'placeholder' => '' ),
);
check( ! is_wp_error( SSC_Transfer::parse( $settings_file( array( 'form_fields' => $persian_form, 'sitesync_types' => array( 'page', 'post' ), 'adr_form' => array( 'preset' => 'custom', 'collapse_optional' => 'yes', 'intro' => 'x', 'fields' => array( 'age' => array( 'on' => true, 'req' => false, 'label' => 'سن' ) ), 'custom' => array( array( 'label' => 'Q', 'type' => 'select', 'options' => array( 'a', 'b' ) ) ) ) ) ) ) ), 'N08: valid forms with options and Persian digits pass' );
$clean = SSC_Settings::sanitize_value( 'form_fields', $persian_form );
check( 'team' === $clean[0]['key'] && '۱۰' === $clean[0]['placeholder'] && array( 'پایه', 'حرفه‌ای' ) === $clean[1]['options'] && 'plan' === $clean[1]['key'], 'N08: a valid form keeps its own keys, Persian text and options when applied' );
check( is_wp_error( SSC_Transfer::parse( $settings_file( array( 'welcome_text' => array( 'x' ) ) ) ) ), 'N08: the top-level check of the earlier review still refuses a list in a text setting' );

// N01: the last stand decides; a condition is not a request now; an unrelated negation does not cancel one.
foreach ( array(
	'I want a consultation, actually I do not want a consultation',
	'I want a consultation but never mind',
	'I want a consultation and I do not want a consultation',
	'اگر نیاز داشتم با من تماس بگیرید، فعلاً نمی‌خواهم',
	'می‌خواهم مشاوره بگیرم، نه ولش کن',
	'می‌خواهم مشاوره بگیرم ولی بی‌خیال',
	'می‌خواهم مشاوره بگیرم و مشاوره نمی‌خواهم',
	'call me if I need help later',
	'if I need a consultation I will let you know',
	'If I need help, call me',
	'اگر نیاز به مشاوره داشتم خبر می‌دهم',
	'در صورت نیاز با من تماس بگیرید',
	'I might want a consultation someday',
	'maybe I would like a consultation',
	'مشاوره نمی‌خوام',
	'نیاز به مشاوره ندارم',
	'I do not want a consultation',
	"don't call me",
	'تماس نگیرید',
	'مشکل خاصی ندارم ولی نمی‌خواهم مشاوره بگیرم',
	'هزینه مشاوره چقدر است؟',
	'مشکل خاصی ندارم',
) as $message ) {
	check( ! SSC_Module_Leads::wants_consultation( $message ), 'N01: not a request now: ' . $message );
}
foreach ( array(
	'مشکل خاصی ندارم ولی می‌خواهم مشاوره بگیرم',
	'I have no specific problem but I want a consultation',
	'مشاوره نمی‌خواستم ولی حالا می‌خواهم مشاوره بگیرم',
	'I do not want a consultation, I need a callback',
	'I want a consultation but I do not want to wait',
	'می‌خواهم مشاوره بگیرم',
	'I want a consultation',
	'Please call me back',
	'can you call me later today?',
	'When can I get a consultation? I want one',
	'اگر ممکنه با من تماس بگیرید',
	'If possible, call me',
) as $message ) {
	check( SSC_Module_Leads::wants_consultation( $message ), 'N01: a clear request is still recognised: ' . $message );
}

// N09: a Gemini SAFETY stop, streamed or not, is a content-policy stop and never a damaged response.
SSC_Settings::update( array( 'ai_provider' => 'gemini', 'gemini_model' => 'gemini-2.5-flash', 'qa_mode' => 'ai_first', 'ai_cache_enabled' => 'no', 'streaming_enabled' => 'no', 'web_search' => 'no' ) );
SSC_Settings::set_secret( 'gemini_api_key', 'AIza-n09-test-key' );
$n09_resp = null;
$n09_mock = function () use ( &$n09_resp ) {
	return $n09_resp;
};
add_filter( 'pre_http_request', $n09_mock, 31 );
$n09_set  = function ( $payload ) use ( &$n09_resp ) {
	$n09_resp = array( 'headers' => array(), 'body' => wp_json_encode( $payload ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
$gemini = new SSC_Provider_Gemini();
$n09_go = function () use ( $gemini ) {
	return $gemini->generate_with( 'AIza-n09-test-key', 'gemini-2.5-flash', 'system', array( array( 'role' => 'user', 'content' => 'hello' ) ) );
};
$n09_set( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'text the provider blocked' ) ) ), 'finishReason' => 'SAFETY' ) ) ) );
$res = $n09_go();
check( false === $res['ok'] && 'filtered' === $res['finish'] && 'filtered' === $res['error']['code'] && '' === $res['text'], 'N09: a SAFETY stop comes back as filtered, with no text' );
$n09_set( array( 'candidates' => array( array( 'finishReason' => 'SAFETY' ) ) ) );
$res = $n09_go();
check( 'filtered' === $res['finish'] && 'filtered' === $res['error']['code'], 'N09: a SAFETY stop without text is filtered too' );
$n09_set( array( 'promptFeedback' => array( 'blockReason' => 'SAFETY' ), 'candidates' => array() ) );
$res = $n09_go();
check( 'filtered' === $res['finish'] && 'filtered' === $res['error']['code'], 'N09: a request refused before any answer (promptFeedback) is filtered, not "unexpected response"' );
$n09_set( array( 'error' => array( 'code' => 400, 'message' => 'Invalid request' ) ) );
$res = $n09_go();
check( false === $res['ok'] && 'malformed' === $res['error']['code'], 'N09: a real provider error in a 200 response is still malformed' );
$n09_set( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'A fine answer.' ) ) ), 'finishReason' => 'STOP' ) ) ) );
$res = $n09_go();
check( true === $res['ok'] && 'stop' === $res['finish'] && 'A fine answer.' === $res['text'], 'N09: a normal answer is unchanged' );
// Through the engine: no continue offer, nothing cached, and the visitor gets the honest fallback rather than blocked text.
$n09_calls = 0;
add_filter( 'pre_http_request', function ( $pre ) use ( &$n09_calls, &$n09_resp ) {
	++$n09_calls;
	return $pre;
}, 5 );
$n09_set( array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'blocked words' ) ) ), 'finishReason' => 'SAFETY' ) ) ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.95', '' );
update_option( SSC_Modules::OPTION, array() );
$result = $engine->chat( 'N09 filtered question', 'general', array() );
check( 1 === $n09_calls && 'unanswered' === $result['source'] && false === strpos( (string) $result['reply'], 'blocked words' ) && ! isset( $result['flags']['incomplete'] ) && 'filtered' === ( SSC_Chat_Engine::recent_failures()['code'] ?? '' ), 'N09: the visitor gets the honest fallback, never the blocked text or a continue offer; the reason is recorded as filtered' );
delete_option( SSC_Chat_Engine::FAILURE_OPTION );
// Streamed: a SAFETY chunk ends the stream (so no second, billed request is made) and still counts as a failure.
$ev = $gemini->parse_stream_event( array( 'candidates' => array( array( 'finishReason' => 'SAFETY' ) ) ) );
check( $ev['done'] && $ev['error'] && 'filtered' === $ev['finish'], 'N09: a streamed SAFETY chunk ends the stream, as a failure, and says filtered' );
remove_filter( 'pre_http_request', $n09_mock, 31 );
SSC_Settings::set_secret( 'gemini_api_key', '' );

// Put everything back.
if ( null === $audit3_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $audit3_before['modules'], false );
}
if ( null === $audit3_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $audit3_before['settings'] );
}
SSC_Settings::reset_cache();
