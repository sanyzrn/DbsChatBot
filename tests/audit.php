<?php
/**
 * Regression tests for the findings of the external audit of 1.5.0 (F01-F16).
 * Each block states the acceptance criterion of its finding.
 * Required from tests/integration.php (check() and a disposable site).
 */

$audit_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
);

// F01: an urgent message is answered with emergency guidance at once. No side-effect word, no model, no form.
$pharma_module = new SSC_Module_Pharma();
$stub          = (object) array( 'flags' => array() );
foreach ( array( 'بعد از دارو عوارض شدید و تنگی نفس دارم', 'نمی‌توانم نفس بکشم', 'I cannot breathe' ) as $urgent ) {
	$stub->flags = array();
	$reply       = $pharma_module->route_adr_intent( null, $urgent, 'general', $stub );
	check( is_string( $reply ) && false !== strpos( $reply, 'medical emergency' ) && ! empty( $stub->flags['emergency'] ), 'F01: emergency guidance for: ' . $urgent );
}
$stub->flags = array();
check( null === $pharma_module->route_adr_intent( null, 'what is the dose of this medicine?', 'general', $stub ) && empty( $stub->flags ), 'F01: an ordinary question is not intercepted' );
check( null === $pharma_module->route_adr_intent( null, 'Can this medicine cause chest pain?', 'general', $stub ) && empty( $stub->flags['emergency'] ), 'F01: a general question about a symptom is not an emergency' );
check( null === $pharma_module->route_adr_intent( null, 'تنگی نفس ندارم', 'general', $stub ), 'F01: a negated sentence is not an emergency' );
$stub->flags = array();
$reply       = $pharma_module->route_adr_intent( null, 'after the side effect I cannot breathe', 'general', $stub );
check( false !== strpos( $reply, 'medical emergency' ) && false !== strpos( $reply, 'structured safety report' ) && ! empty( $stub->flags['adr_offer'] ), 'F01: with a side-effect word the emergency notice comes first and the report offer follows' );
SSC_Settings::update( array( 'ai_provider' => 'none' ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.77', '' );
$result = $engine->chat( 'نمی‌توانم نفس بکشم', 'general', array() );
check( false !== strpos( (string) $result['reply'], 'اورژانس' ) && ! empty( $result['flags']['emergency'] ), 'F01: the chat answers an emergency with the AI switched off' );

// F02: a forwarded address is believed only from a listed proxy; the chat engine and the screen use that rule.
$server_before = $_SERVER;
$_SERVER['REMOTE_ADDR']          = '203.0.113.44';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
SSC_Settings::update( array( 'trusted_proxy_header' => 'HTTP_X_FORWARDED_FOR', 'trusted_proxies' => '' ) );
check( '203.0.113.44' === ( new SSC_Chat_Engine() )->client_ip(), 'F02: with the header chosen but no proxy listed, the connecting address is used' );
add_filter( 'ssc_ip_header', $filter_header = function () { return 'HTTP_X_FORWARDED_FOR'; } );
check( '203.0.113.44' === ( new SSC_Chat_Engine() )->client_ip(), 'F02: the ssc_ip_header filter alone no longer makes a header believable' );
remove_filter( 'ssc_ip_header', $filter_header );
check( '203.0.113.0/24' === SSC_Settings::sanitize_value( 'trusted_proxies', "203.0.113.0/24\nnot-a-range\n" ) && '' === SSC_Settings::sanitize_value( 'trusted_proxies', array( 'x' ) ), 'F02: only valid addresses and blocks are kept when settings are saved' );
SSC_Settings::update( array( 'trusted_proxies' => SSC_Settings::sanitize_value( 'trusted_proxies', "203.0.113.0/24\nnot-a-range\n" ) ) );
check( '198.51.100.99' === ( new SSC_Chat_Engine() )->client_ip(), 'F02: a listed proxy is believed by the chat engine' );
$_SERVER['REMOTE_ADDR'] = '192.0.2.50';
check( '192.0.2.50' === ( new SSC_Chat_Engine() )->client_ip(), 'F02: a client that is not a listed proxy gets its own address even with the header sent' );
$_SERVER = $server_before;
check( in_array( 'trusted_proxies', SSC_Transfer::site_bound_settings(), true ) && in_array( 'trusted_proxy_header', SSC_Transfer::site_bound_settings(), true ), 'F02: the proxy setup belongs to the site and is not exported' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-settings' ), function () {
	( new SSC_Admin_Settings() )->render();
} );
check( false !== strpos( $html, 'name="trusted_proxies"' ) && false !== strpos( $html, 'data-ssc-fill="trusted_proxies"' ), 'F02: Settings has the trusted proxies list with the Cloudflare preset' );
SSC_Settings::update( array( 'trusted_proxy_header' => '', 'trusted_proxies' => '' ) );

// F15: switching the FAQ module off stops the answer bank everywhere; the data stays and returns when it is switched on.
$faq_before = SSC_Schema::qa_all();
SSC_Schema::qa_import( array( array( 'question' => 'F15 audit shipping question', 'keywords' => 'shipping', 'answer' => 'F15 bank answer', 'product_id' => 'general' ) ), true );
SSC_Settings::update( array( 'qa_mode' => 'bank_only', 'ai_provider' => 'none' ) );
update_option( SSC_Modules::OPTION, array( 'faq' ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.81', '' );
$result = $engine->chat( 'F15 audit shipping question', 'general', array() );
check( 'bank' === $result['source'] && 'F15 bank answer' === $result['reply'], 'F15: with the FAQ module on, the bank answers' );
check( array() !== SSC_Knowledge::related_questions( 'general', 'F15 audit shipping' ), 'F15: with the FAQ module on, the bank gives suggestions' );
update_option( SSC_Modules::OPTION, array() );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.82', '' );
$result = $engine->chat( 'F15 audit shipping question', 'general', array() );
check( 'bank' !== $result['source'] && 'F15 bank answer' !== $result['reply'], 'F15: with the FAQ module off, the bank no longer answers (bank-only mode)' );
SSC_Settings::update( array( 'qa_mode' => 'bank_first' ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.83', '' );
$result = $engine->chat( 'F15 audit shipping question', 'general', array() );
check( 'bank' !== $result['source'], 'F15: with the FAQ module off, bank-first mode does not use the bank either' );
check( array() === SSC_Knowledge::related_questions( 'general', 'F15 audit shipping' ) && null === SSC_Knowledge::bank_answer( 'general', 'F15 audit shipping question' ), 'F15: with the FAQ module off there are no suggestions and no bank answer from any path' );
check( 1 === SSC_Schema::qa_count(), 'F15: the saved answers are kept while the module is off' );
update_option( SSC_Modules::OPTION, array( 'faq' ) );
check( null !== SSC_Knowledge::bank_answer( 'general', 'F15 audit shipping question' ), 'F15: switching the module back on brings the answers back' );
SSC_Schema::qa_import( array(), true );
foreach ( $faq_before as $row ) {
	SSC_Schema::qa_insert( $row );
}

// F04: the bank never gives a stored answer to the opposite question.
SSC_Schema::qa_import(
	array(
		array( 'question' => 'مصرف دارو در بارداری ممنوع است؟', 'keywords' => 'بارداری', 'answer' => 'PREG-PROHIBITED', 'product_id' => 'general' ),
		array( 'question' => 'این دارو مجاز است؟', 'keywords' => 'مجاز', 'answer' => 'ALLOWED', 'product_id' => 'general' ),
		array( 'question' => 'آیا دارو را قطع کنم؟', 'keywords' => 'قطع', 'answer' => 'STOP-IT', 'product_id' => 'general' ),
		array( 'question' => 'این محصول موجود هست؟', 'keywords' => 'موجود', 'answer' => 'IN-STOCK', 'product_id' => 'general' ),
	),
	true
);
update_option( SSC_Modules::OPTION, array( 'faq' ) );
$ask = function ( $q ) {
	$row = SSC_Knowledge::bank_answer( 'general', $q );
	return $row ? $row['answer'] : null;
};
check( 'PREG-PROHIBITED' === $ask( 'مصرف دارو در بارداری ممنوع است؟' ) && 'PREG-PROHIBITED' === $ask( 'آیا مصرف دارو در بارداری ممنوع است' ), 'F04: the same question still gets its answer' );
check( null === $ask( 'مصرف دارو در بارداری ممنوع نیست؟' ), 'F04: "is it not prohibited in pregnancy?" does not get the answer for "is it prohibited?"' );
check( 'ALLOWED' === $ask( 'این دارو مجاز است؟' ) && null === $ask( 'این دارو غیرمجاز است؟' ) && null === $ask( 'این دارو غیر مجاز است؟' ), 'F04: allowed / not allowed are told apart' );
check( 'IN-STOCK' === $ask( 'این محصول موجود هست؟' ) && null === $ask( 'این محصول موجود نیست؟' ), 'F04: is / is not are told apart' );
check( 'STOP-IT' === $ask( 'آیا دارو را قطع کنم؟' ) && null === $ask( 'آیا دارو را قطع نکنم؟' ) && null === $ask( 'آیا دارو را ادامه دهم؟' ), 'F04: stop / do not stop / continue are told apart' );
SSC_Settings::update( array( 'qa_mode' => 'bank_only', 'ai_provider' => 'none' ) );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.84', '' );
$result = $engine->chat( 'مصرف دارو در بارداری ممنوع نیست؟', 'general', array() );
check( 'PREG-PROHIBITED' !== $result['reply'], 'F04: the chat does not answer the reversed question from the bank' );
SSC_Schema::qa_import( array(), true );

// F09: past the 800-row window the rows that contain the question's words are still found, wherever they sit.
$faq_before = SSC_Schema::qa_all();
$rows       = array();
$rows[]     = array( 'question' => 'zephyrion oldest row', 'keywords' => '', 'answer' => 'ANSWER-FIRST', 'product_id' => 'general' );
for ( $i = 1; $i <= 900; $i++ ) {
	$rows[] = array( 'question' => 'filler topic number ' . $i . ' generic words', 'keywords' => '', 'answer' => 'filler ' . $i, 'product_id' => 'general' );
	if ( 450 === $i ) {
		$rows[] = array( 'question' => 'quixoticon middle row', 'keywords' => '', 'answer' => 'ANSWER-MIDDLE', 'product_id' => 'general' );
	}
}
$rows[] = array( 'question' => 'marvellium newest row', 'keywords' => '', 'answer' => 'ANSWER-LAST', 'product_id' => 'general' );
SSC_Schema::qa_import( $rows, true );
update_option( SSC_Modules::OPTION, array( 'faq' ) );
$started = microtime( true );
$memory  = memory_get_peak_usage( true );
check( 'ANSWER-FIRST' === ( SSC_Knowledge::bank_answer( 'general', 'zephyrion oldest row' )['answer'] ?? '' ), 'F09: a bank answer outside the newest 800 rows is found (oldest)' );
check( 'ANSWER-MIDDLE' === ( SSC_Knowledge::bank_answer( 'general', 'quixoticon middle row' )['answer'] ?? '' ), 'F09: a bank answer in the middle of a large bank is found' );
check( 'ANSWER-LAST' === ( SSC_Knowledge::bank_answer( 'general', 'marvellium newest row' )['answer'] ?? '' ), 'F09: the newest bank answer is found' );
check( array() !== SSC_Knowledge::related_questions( 'general', 'zephyrion oldest' ), 'F09: suggestions also reach the old rows' );
check( count( SSC_Schema::qa_candidates( 'general', array( 'zzzznomatch' ) ) ) === SSC_Schema::CANDIDATE_LIMIT, 'F09: with no word in common the window is used as before' );
check( microtime( true ) - $started < 10 && memory_get_peak_usage( true ) - $memory < 64 * 1024 * 1024, 'F09: finding them in a large bank stays cheap in time and memory' );
// The same for the knowledge base.
SSC_Schema::kb_delete_document( 'doc-f09' );
$chunks = array( 'zephyrion chunk at the very start' );
for ( $i = 1; $i <= 900; $i++ ) {
	$chunks[] = 'filler paragraph number ' . $i . ' about nothing in particular';
	if ( 450 === $i ) {
		$chunks[] = 'quixoticon chunk in the middle';
	}
}
$chunks[] = 'marvellium chunk at the very end';
SSC_Schema::kb_insert_chunks( 'doc-f09', 'F09 audit document', $chunks );
foreach ( array( 'zephyrion', 'quixoticon', 'marvellium' ) as $word ) {
	$found = SSC_Knowledge::retrieve_chunks( 'general', 'tell me about ' . $word, 3 );
	check( $found && false !== strpos( $found[0]['chunk'], $word ), 'F09: the knowledge chunk containing "' . $word . '" is retrieved from a base larger than the window' );
}
SSC_Schema::kb_delete_document( 'doc-f09' );
SSC_Schema::qa_import( array(), true );
foreach ( $faq_before as $row ) {
	SSC_Schema::qa_insert( $row );
}
$page = SSC_Schema::qa_page( 1, 30 );
check( $page['total'] === SSC_Schema::qa_count() && count( $page['rows'] ) <= 30, 'F09: the FAQ list pages over the whole bank, not a window' );

// F11: a list, an object or null where text belongs is refused with the place named, never a fatal error and never a partial save.
$products_ok = SSC_Settings::sanitize_value( 'products', array( array( 'name' => 'Malformed audit entry', 'summary' => array( 'unexpected' ), 'brochure' => array( 'x' ), 'attributes' => array( 'k' => array( 'deep' ) ) ), array( 'name' => array( 'bad' ) ), array( 'name' => 'Fine', 'summary' => 'ok' ) ) );
check( 2 === count( $products_ok ) && '' === $products_ok[0]['summary'] && '' === $products_ok[0]['brochure'] && 'ok' === $products_ok[1]['summary'], 'F11: malformed product fields become empty instead of a fatal error, and a nameless row is skipped' );
$business_ok = SSC_Settings::sanitize_business( array( 'org_name' => array( 'x' ), 'description' => array( 'd' ), 'tone' => array( 'friendly' ), 'phone' => '021' ) );
check( '' === $business_ok['org_name'] && '' === $business_ok['description'] && 'professional' === $business_ok['tone'] && '021' === $business_ok['phone'], 'F11: malformed business fields become empty instead of a fatal error' );
$items_ok = SSC_Settings::sanitize_value( 'knowledge_items', array( array( 'title' => array( 't' ), 'content' => array( 'c' ) ), array( 'title' => 'Hours', 'content' => array( 'c' ), 'type' => array( 'faq' ) ) ) );
check( 1 === count( $items_ok ) && 'Hours' === $items_ok[0]['title'] && 'general' === $items_ok[0]['type'], 'F11: malformed knowledge entries become empty instead of a fatal error' );
$shape = new ReflectionMethod( 'SSC_Admin_Knowledge', 'malformed_post' );
$shape->setAccessible( true );
$_POST = array( 'business' => array( 'org_name' => 'Atlas', 'phone' => array( '1' ) ) );
check( 'business::phone' === $shape->invoke( null ), 'F11: a list in a business field is named' );
$_POST = array( 'ki' => array( array( 'title' => 'ok', 'content' => 'ok' ), array( 'title' => 'x', 'content' => array( 'c' ) ) ) );
check( 'ki:1:content' === $shape->invoke( null ), 'F11: the knowledge entry and field are named' );
$_POST = array( 'products' => array( array( 'name' => 'a' ), array( 'name' => 'b', 'summary' => array( 'x' ) ) ) );
check( 'products:1:summary' === $shape->invoke( null ), 'F11: the product row and field are named' );
$_POST = array( 'products' => array( array( 'name' => 'a', 'attributes' => array( 'k' => 'v' ) ) ), 'product_attributes' => array( array( 'k', 'v', array( 'x' => array( 'y' ) ) ) ) );
check( 'product_attributes:0:value' === $shape->invoke( null ), 'F11: a malformed specification is named' );
$_POST = array( 'business' => array( 'org_name' => 'Atlas', 'locations' => array( array( 'type' => 'hq', 'address' => 'x' ) ) ), 'ki' => array( array( 'title' => 'a', 'content' => 'b' ) ), 'products' => array( array( 'name' => 'a', 'summary' => 's' ) ), 'product_attributes' => array( array( 'k', 'v' ) ) );
check( '' === $shape->invoke( null ), 'F11: a well-formed form passes' );
$_POST = array();
$file = wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'products' => array( array( 'name' => 'a', 'summary' => 's' ), array( 'name' => 'b', 'summary' => array( 'x' ) ) ) ) );
$parsed = SSC_Transfer::parse( $file );
check( is_wp_error( $parsed ) && 'ssc_transfer_shape' === $parsed->get_error_code() && false !== strpos( $parsed->get_error_message(), 'products, row 2' ) && false !== strpos( $parsed->get_error_message(), 'summary' ), 'F11: an import file with a malformed row is refused whole, naming the section, row and field' );
$parsed = SSC_Transfer::parse( wp_json_encode( array( 'format' => SSC_Transfer::FORMAT, 'format_version' => SSC_Transfer::VERSION, 'products' => array( array( 'name' => 'a', 'attributes' => array( 'k' => 'v' ) ) ) ) ) );
check( ! is_wp_error( $parsed ), 'F11: a well-formed import file is accepted' );

// F12: the patient's age in Persian digits is accepted; impossible values and lists are refused.
SSC_Settings::update( array( 'products' => array( array( 'id' => 'test-product', 'name' => 'Test product', 'summary' => '' ) ) ) );
update_option( SSC_Modules::OPTION, array( 'pharma', 'leads' ) );
$adr = new SSC_Module_Pharma();
$valid_adr = array( 'name' => 'Audit reporter', 'phone' => '09123456789', 'description' => 'Audit narrative.', 'product' => 'test-product', 'consent' => '1', 'severity' => 'mild' );
foreach ( array( '۲۵', '٢٥', '25', '25.0', '۲۵٫۰' ) as $age ) {
	$res = $adr->handle_submission( array_merge( $valid_adr, array( 'patient_age' => $age ) ) );
	$row = last_case();
	$ex  = json_decode( $row['extra_fields'], true );
	check( ! is_wp_error( $res ) && 25.0 === (float) $ex['patient_age'], 'F12: the age ' . $age . ' is stored as 25' . ( is_wp_error( $res ) ? ' (' . $res->get_error_message() . ')' : '' ) );
}
foreach ( array( '-1', '131', '۱۳۱', 'abc', '1e999', array( '25' ) ) as $age ) {
	$res = $adr->handle_submission( array_merge( $valid_adr, array( 'patient_age' => $age ) ) );
	check( is_wp_error( $res ) && 400 === $res->get_error_data()['status'], 'F12: the age ' . ( is_array( $age ) ? 'list' : $age ) . ' is refused' );
}
$res = $adr->handle_submission( $valid_adr );
check( ! is_wp_error( $res ), 'F12: the age stays optional' );

// F10: what the panel shows is what the assistant gets.
$entries = array(
	array( 'id' => 'f10a', 'type' => 'general', 'title' => 'F10 first', 'content' => 'F10-FIRST ' . str_repeat( 'a', 2500 ) ),
	array( 'id' => 'f10b', 'type' => 'general', 'title' => 'F10 second', 'content' => 'F10-SECOND ' . str_repeat( 'b', 2500 ) ),
	array( 'id' => 'f10c', 'type' => 'general', 'title' => 'F10 third', 'content' => 'F10-THIRD tail entry' ),
);
SSC_Settings::update( array( 'knowledge_items' => $entries ) );
$context = SSC_Knowledge::business_context( '' );
check( false !== strpos( $context, 'F10-FIRST' ) && false !== strpos( $context, 'F10-SECOND' ) && false === strpos( $context, 'F10-THIRD' ), 'F10: the prompt keeps the entries that fit (the second one cut) and leaves out the one after the limit' );
$plan = SSC_Knowledge::plan_knowledge( SSC_Settings::get( 'knowledge_items', array() ) );
check( array( 'full', 'cut', 'out' ) === array_column( $plan['rows'], 'state' ), 'F10: the plan marks the same entries' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-knowledge' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
check( false !== strpos( $html, 'data-ssc-kbudget' ) && false !== strpos( $html, '4,000' ) && false !== strpos( $html, 'is-over' ), 'F10: the Knowledge screen shows the used budget and the warning' );
check( 1 === preg_match_all( '/data-ssc-kstate[^>]*>Partly used</', $html ) && 1 === preg_match_all( '/data-ssc-kstate[^>]*>Not used</', $html ), 'F10: the entry that is cut and the entry that is left out carry their badge' );
SSC_Settings::update( array( 'knowledge_items' => array( $entries[2] ) ) );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-knowledge' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
check( false === strpos( $html, 'ssc-kbudget is-over' ) && false !== strpos( $html, 'data-ssc-kbudget-warn hidden' ), 'F10: nothing is flagged while everything fits' );
SSC_Settings::update( array( 'knowledge_items' => array() ) );

// F06: two tabs opened from the same page: the second save must not undo what the first one saved.
$kn_post = function ( $fields ) {
	$_POST    = array_merge( array( '_wpnonce' => wp_create_nonce( 'ssc_knowledge' ), 'ssc_knowledge_save' => '1' ), $fields );
	$_REQUEST = $_POST;
	$where    = '';
	$stop     = function ( $location ) use ( &$where ) {
		$where = $location;
		throw new RuntimeException( 'redirect' );
	};
	add_filter( 'wp_redirect', $stop );
	try {
		( new ReflectionClass( 'SSC_Admin_Knowledge' ) )->newInstanceWithoutConstructor()->handle_actions();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	remove_filter( 'wp_redirect', $stop );
	$_POST    = array();
	$_REQUEST = array();
	return $where;
};
$kn_resolve = function ( $choice ) {
	$_POST    = array( '_wpnonce' => wp_create_nonce( 'ssc_knowledge_resolve' ), 'ssc_knowledge_resolve' => $choice );
	$_REQUEST = $_POST;
	$where    = '';
	$stop     = function ( $location ) use ( &$where ) {
		$where = $location;
		throw new RuntimeException( 'redirect' );
	};
	add_filter( 'wp_redirect', $stop );
	try {
		( new ReflectionClass( 'SSC_Admin_Knowledge' ) )->newInstanceWithoutConstructor()->handle_actions();
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	remove_filter( 'wp_redirect', $stop );
	$_POST    = array();
	$_REQUEST = array();
	return $where;
};
$kn_business = function ( $over = array() ) {
	$b = SSC_Settings::get( 'business', array() );
	$f = array();
	foreach ( array( 'org_name', 'brand_name', 'category', 'industry', 'phone', 'email', 'support_phone', 'support_email', 'hours', 'assistant_name', 'assistant_role', 'tone', 'language', 'description', 'products', 'differentiators', 'url' ) as $key ) {
		$f[ $key ] = isset( $b[ $key ] ) ? $b[ $key ] : '';
	}
	return array_merge( $f, $over );
};
SSC_Settings::update( array(
	'business'        => SSC_Settings::sanitize_business( array( 'org_name' => 'Atlas', 'phone' => '021-111', 'email' => 'a@example.com', 'tone' => 'friendly' ) ),
	'knowledge_items' => array( array( 'id' => 'f06k1', 'type' => 'policy', 'title' => 'Returns', 'content' => '7 days' ) ),
	'products'        => array( array( 'id' => 'f06p1', 'name' => 'Alpha', 'summary' => 'first', 'brochure' => '', 'image' => '', 'attributes' => array() ) ),
) );
delete_transient( SSC_Admin_Knowledge::conflict_key() );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-knowledge' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
preg_match( '/name="ssc_base" value="([^"]*)"/', $html, $m_base );
$tab_base = isset( $m_base[1] ) ? html_entity_decode( $m_base[1] ) : '';
check( null !== SSC_Merge::parse_snapshot( $tab_base ) && false !== strpos( $html, 'name="ki[0][id]" value="f06k1"' ), 'F06: the form carries what it was built from and the entry ids' );
$ki_row  = array( 'id' => 'f06k1', 'type' => 'policy', 'title' => 'Returns', 'content' => '7 days' );
$prod_a  = array( 'id' => 'f06p1', 'name' => 'Alpha', 'summary' => 'first' );
// Tab one adds a product.
$where = $kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business(), 'ki' => array( $ki_row ), 'products' => array( $prod_a, array( 'id' => '', 'name' => 'Beta', 'summary' => 'second' ) ) ) );
check( false !== strpos( $where, 'saved=1' ) && 2 === count( SSC_Settings::get( 'products', array() ) ), 'F06: the first tab saves its new product' );
// Tab two (older snapshot) only changes the phone.
$where = $kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business( array( 'phone' => '021-222' ) ), 'ki' => array( $ki_row ), 'products' => array( $prod_a ) ) );
$saved = SSC_Settings::get( 'products', array() );
check( false !== strpos( $where, 'saved=1' ) && '021-222' === SSC_Settings::get( 'business', array() )['phone'] && 2 === count( $saved ) && 'Beta' === $saved[1]['name'], 'F06: the second tab saves its phone change and the first tab\'s product is still there' );
// Tab three (the same old snapshot) changes the phone too: a clash, nothing saved, the person decides.
$where = $kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business( array( 'phone' => '021-333', 'email' => 'c@example.com' ) ), 'ki' => array( $ki_row ), 'products' => array( $prod_a ) ) );
check( false !== strpos( $where, 'conflict=1' ) && false === strpos( $where, 'saved=1' ) && '021-222' === SSC_Settings::get( 'business', array() )['phone'] && 'a@example.com' === SSC_Settings::get( 'business', array() )['email'], 'F06: a clash saves nothing and says so' );
$stash = get_transient( SSC_Admin_Knowledge::conflict_key() );
check( is_array( $stash ) && array( 'phone' ) === $stash['conflicts']['business'], 'F06: the clashing field is remembered, not the whole form lost' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-knowledge', 'conflict' => '1' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
check( false !== strpos( $html, 'ssc-kconflict' ) && false !== strpos( $html, 'Contact phone' ) && false !== strpos( $html, 'value="mine"' ) && false !== strpos( $html, 'value="latest"' ) && false !== strpos( $html, 'value="discard"' ), 'F06: the screen names the clashing field and offers the three choices' );
$kn_resolve( 'latest' );
$business = SSC_Settings::get( 'business', array() );
check( '021-222' === $business['phone'] && 'c@example.com' === $business['email'] && false === get_transient( SSC_Admin_Knowledge::conflict_key() ), 'F06: "keep the latest" keeps the stored phone but still saves what did not clash' );
$where = $kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business( array( 'phone' => '021-444' ) ), 'ki' => array( $ki_row ), 'products' => array( $prod_a ) ) );
check( false !== strpos( $where, 'conflict=1' ), 'F06: a clash is reported again for a new stale save' );
$kn_resolve( 'mine' );
check( '021-444' === SSC_Settings::get( 'business', array() )['phone'] && 2 === count( SSC_Settings::get( 'products', array() ) ), 'F06: "use my version" saves it and still keeps the other tab\'s product' );
$where = $kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business( array( 'phone' => '021-555' ) ), 'ki' => array( $ki_row ), 'products' => array( $prod_a ) ) );
$kn_resolve( 'discard' );
check( '021-444' === SSC_Settings::get( 'business', array() )['phone'] && false === get_transient( SSC_Admin_Knowledge::conflict_key() ), 'F06: "discard my changes" saves nothing' );
// An entry deleted in one tab is not brought back by a stale tab that did not touch it.
$kn_post( array( 'ssc_base' => wp_slash( $tab_base ), 'business' => $kn_business(), 'ki' => array(), 'products' => array( $prod_a, array( 'id' => 'f06p2', 'name' => 'Beta', 'summary' => 'second' ) ) ) );
check( array() === SSC_Settings::get( 'knowledge_items', array() ), 'F06: removing an entry works from a form that was current for it' );
SSC_Settings::update( array( 'knowledge_items' => array(), 'business' => SSC_Settings::sanitize_business( array() ), 'products' => array() ) );

// F08: a refusal (403) is not always the key; each kind of failure has its own code and wording.
$http_fixture = null;
$http_mock    = function () use ( &$http_fixture ) {
	return $http_fixture;
};
add_filter( 'pre_http_request', $http_mock, 30 );
$http_case = function ( $status, $body, $headers = array() ) use ( &$http_fixture ) {
	$http_fixture = array(
		'headers'  => $headers,
		'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
		'response' => array( 'code' => $status, 'message' => 'x' ),
		'cookies'  => array(),
		'filename' => null,
	);
	return SSC_HTTP::post_json( 'https://8.8.8.8/v1/chat/completions', array( 'Authorization' => 'Bearer sk-test' ), array( 'x' => 1 ) );
};
$r = $http_case( 401, array( 'error' => array( 'message' => 'Incorrect API key provided: sk-abc***xyz' ) ) );
check( 'auth' === $r['error']['code'], 'F08: 401 is a rejected key' );
$r = $http_case( 403, array( 'error' => array( 'message' => 'Access denied by security policy' ) ), array( 'x-request-id' => 'req_abc-123' ) );
check( 'policy' === $r['error']['code'] && 'req_abc-123' === $r['error']['request_id'], 'F08: 403 "access denied by security policy" is a policy block, with the provider\'s request id kept' );
$r = $http_case( 403, array( 'error' => array( 'message' => 'You do not have permission to access the model gpt-x' ) ) );
check( 'permission' === $r['error']['code'], 'F08: 403 "no permission for the model" is a permission problem' );
$r = $http_case( 403, array( 'error' => array( 'message' => 'Invalid API key' ) ) );
check( 'auth' === $r['error']['code'], 'F08: 403 that says the key is invalid is still a key problem' );
$r = $http_case( 403, array( 'error' => 'Forbidden' ) );
check( 'forbidden' === $r['error']['code'], 'F08: a bare 403 is "refused, reason not given", not a wrong key' );
$r = $http_case( 403, array( 'error' => array( 'message' => 'Quota exceeded for this project' ) ) );
check( 'credits' === $r['error']['code'], 'F08: 403 about quota is a credits problem' );
$r = $http_case( 429, array( 'error' => array( 'message' => 'Too many requests' ) ) );
check( 'rate_limit' === $r['error']['code'], 'F08: 429 is rate limiting' );
$r = $http_case( 429, array( 'error' => array( 'message' => 'You exceeded your current quota (insufficient_quota)' ) ) );
check( 'credits' === $r['error']['code'], 'F08: 429 insufficient quota is a credits problem' );
$http_fixture = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received' );
$r            = SSC_HTTP::post_json( 'https://8.8.8.8/v1/chat/completions', array(), array( 'x' => 1 ) );
check( 'timeout' === $r['error']['code'], 'F08: a timeout is a timeout' );
$wording = array();
foreach ( array( 'auth', 'permission', 'policy', 'forbidden', 'credits', 'rate_limit', 'timeout', 'network' ) as $code ) {
	$wording[ $code ] = SSC_HTTP::friendly_error( $code );
}
check( count( array_unique( $wording ) ) === count( $wording ), 'F08: every kind of failure has its own wording' );
check( false === stripos( $wording['policy'], 'key was rejected' ) && false === stripos( $wording['permission'], 'key was rejected' ) && false === stripos( $wording['forbidden'], 'key was rejected' ), 'F08: a 403 is never described as a rejected key' );
check( 0 === count( array_diff( array( 'permission', 'policy', 'forbidden' ), SSC_HTTP::error_codes() ) ), 'F08: the new codes are part of the canonical list' );

// F07: a failure after a success is shown as the latest attempt, with its time; the success keeps its own time.
SSC_Settings::update( array( 'ai_provider' => 'openai', 'openai_model' => 'gpt-4o-mini' ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-f07-secret-key' );
$rest = new SSC_REST();
$run  = function () use ( $rest ) {
	$req = new WP_REST_Request( 'POST', '/ssc/v1/test-connection' );
	$req->set_body( wp_json_encode( array( 'provider' => 'openai' ) ) );
	$req->set_header( 'content-type', 'application/json' );
	return $rest->test_connection( $req )->get_data();
};
SSC_Setup::update_state( array( 'connection_verified' => array(), 'connection_attempt' => array() ) );
$http_fixture = array(
	'headers'  => array(),
	'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'OK' ), 'finish_reason' => 'stop' ) ) ) ),
	'response' => array( 'code' => 200, 'message' => 'OK' ),
	'cookies'  => array(),
	'filename' => null,
);
$out = $run();
check( ! empty( $out['ok'] ) && SSC_Setup::connection_is_verified() && 'ok' === SSC_Setup::connection_status()['state'], 'F07: a passing test verifies the connection' );
$verified_at = SSC_Setup::connection_status()['verified_at'];
check( $verified_at > 0, 'F07: the success carries a time' );
$http_fixture = array(
	'headers'  => array( 'x-request-id' => 'req_f07' ),
	'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Access denied by security policy. Key sk-f07-secret-key was used.' ) ) ),
	'response' => array( 'code' => 403, 'message' => 'Forbidden' ),
	'cookies'  => array(),
	'filename' => null,
);
$out    = $run();
$status = SSC_Setup::connection_status();
check( empty( $out['ok'] ) && 'policy' === $out['code'] && 'req_f07' === $out['request_id'], 'F07: the failed test reports its code and request id' );
check( 'failed_after_ok' === $status['state'] && ! $status['verified'] && ! SSC_Setup::connection_is_verified(), 'F07: after a newer failure the connection is no longer shown as verified' );
check( $status['verified_at'] === $verified_at && 'policy' === $status['attempt']['code'] && $status['attempt']['at'] >= $verified_at, 'F07: the last success keeps its time and the failure has its own' );
check( false === strpos( wp_json_encode( SSC_Setup::state() ), 'sk-f07-secret-key' ) && false === strpos( wp_json_encode( $out ), 'sk-f07-secret-key' ), 'F07: no key or provider text is stored or returned' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-connection' ), function () {
	( new SSC_Admin_Connection() )->render();
} );
check( false !== strpos( $html, 'The last connection test failed' ) && false !== strpos( $html, 'req_f07' ) && false !== strpos( $html, 'It last worked' ) && false === strpos( $html, 'sk-f07-secret-key' ), 'F07: the Connection screen shows the failure, when the connection last worked, and the request id' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-dashboard' ), function () {
	( new SSC_Admin_Dashboard() )->render();
} );
check( false !== strpos( $html, 'The last test failed' ), 'F07: the dashboard shows the failure instead of "verified"' );
$http_fixture = array(
	'headers'  => array(),
	'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'OK' ), 'finish_reason' => 'stop' ) ) ) ),
	'response' => array( 'code' => 200, 'message' => 'OK' ),
	'cookies'  => array(),
	'filename' => null,
);
$run();
check( 'ok' === SSC_Setup::connection_status()['state'] && SSC_Setup::connection_is_verified(), 'F07: a later passing test clears the failure' );
// A failed test of a key that is only typed in does not change the saved connection.
SSC_Setup::mark_failed( SSC_Setup::connection_fingerprint( 'openai', 'sk-other', 'gpt-4o-mini', '' ), 'auth' );
check( 'ok' === SSC_Setup::connection_status()['state'], 'F07: a failed test of other credentials does not change the saved connection' );
remove_filter( 'pre_http_request', $http_mock, 30 );
SSC_Setup::update_state( array( 'connection_verified' => array(), 'connection_attempt' => array() ) );
SSC_Settings::set_secret( 'openai_api_key', '' );

// F14: two requests of one conversation together keep every turn; a summary is made once.
SSC_Settings::update( array( 'ai_provider' => 'openai', 'ai_history_limit' => 4, 'memory_days' => 7 ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-f14-test' );
$sum_calls = 0;
$sum_mock  = function ( $pre, $args, $url ) use ( &$sum_calls ) {
	if ( false === strpos( $url, 'api.openai.com/v1/chat' ) ) {
		return $pre;
	}
	++$sum_calls;
	return array( 'headers' => array(), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'F14 summary of the early turns' ), 'finish_reason' => 'stop' ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $sum_mock, PHP_INT_MAX, 3 );
wp_set_current_user( 0 );
$conv = str_repeat( 'f1', 16 );
SSC_Conversation::forget( $conv );
SSC_Conversation::append( $conv, 'first question', 'first answer' );
// A second request writes between this request's read and its write.
$race = function ( $id, $attempt ) use ( &$race ) {
	if ( 0 === $attempt ) {
		remove_action( 'ssc_conversation_before_save', $race, 10 );
		SSC_Conversation::append( $id, 'concurrent question', 'concurrent answer' );
	}
};
add_action( 'ssc_conversation_before_save', $race, 10, 2 );
SSC_Conversation::append( $conv, 'second question', 'second answer' );
remove_action( 'ssc_conversation_before_save', $race, 10 );
$texts = array_column( SSC_Conversation::load( $conv ), 'content' );
check( 6 === count( $texts ) && in_array( 'concurrent question', $texts, true ) && in_array( 'second answer', $texts, true ) && array( 'first question', 'first answer' ) === array_slice( $texts, 0, 2 ), 'F14: when two requests write together, both exchanges are kept' );
check( 6 === (int) SSC_Conversation::row( $conv )['total'], 'F14: the running total matches the turns kept' );
// The very first turn of a new conversation, from two requests together.
$conv2 = str_repeat( 'f2', 16 );
SSC_Conversation::forget( $conv2 );
$race2 = function ( $id, $attempt ) use ( &$race2 ) {
	if ( 0 === $attempt ) {
		remove_action( 'ssc_conversation_before_save', $race2, 10 );
		SSC_Conversation::append( $id, 'parallel hello', 'parallel hi' );
	}
};
add_action( 'ssc_conversation_before_save', $race2, 10, 2 );
SSC_Conversation::append( $conv2, 'hello', 'hi' );
remove_action( 'ssc_conversation_before_save', $race2, 10 );
$texts2 = array_column( SSC_Conversation::load( $conv2 ), 'content' );
check( 4 === count( $texts2 ) && in_array( 'parallel hello', $texts2, true ) && in_array( 'hello', $texts2, true ), 'F14: two first turns of one new conversation are both kept' );
global $wpdb;
check( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SSC_Schema::live_table( 'memory' ) . ' WHERE conv_hash = %s', SSC_Conversation::hash( $conv2 ) ) ), 'F14: still one row for the conversation' );
// Many exchanges in a row: nothing lost.
$conv3 = str_repeat( 'f3', 16 );
SSC_Conversation::forget( $conv3 );
for ( $n = 1; $n <= 12; $n++ ) {
	SSC_Conversation::append( $conv3, 'q' . $n, 'a' . $n );
}
$texts3 = array_column( SSC_Conversation::load( $conv3 ), 'content' );
check( 24 === count( $texts3 ) && 'q1' === $texts3[0] && 'a12' === $texts3[23], 'F14: twelve exchanges in a row are all there, in order' );
// The summary: one run at a time, and a repeat does nothing.
check( SSC_Conversation::pending_count( $conv3 ) >= SSC_Conversation::REFRESH_AFTER, 'F14: the conversation is long enough to need a summary' );
$lease_name = 'summary-' . SSC_Conversation::hash( $conv3 );
$held       = SSC_Lease::acquire( $lease_name, 120 );
$sum_calls  = 0;
check( false !== $held && false === SSC_Conversation::refresh_summary( $conv3 ) && 0 === $sum_calls, 'F14: while another run holds the conversation, a second one does nothing and makes no model call' );
SSC_Lease::release( $lease_name, $held );
check( true === SSC_Conversation::refresh_summary( $conv3 ) && 1 === $sum_calls && 'F14 summary of the early turns' === SSC_Conversation::summary( $conv3 ), 'F14: the summary is made once' );
check( false === SSC_Conversation::refresh_summary( $conv3 ) && 1 === $sum_calls, 'F14: a repeated run finds nothing to do and makes no second model call' );
check( false !== SSC_Lease::acquire( $lease_name, 120 ), 'F14: the conversation is free again once the run ends' );
SSC_Lease::release( $lease_name, 'wrong-token' );
check( false === SSC_Lease::acquire( $lease_name, 120 ), 'F14: only the holder can release a lease' );
$wpdb->update( $wpdb->options, array( 'option_value' => ( time() - 5 ) . ':abandoned' ), array( 'option_name' => 'ssc_lease_' . substr( md5( $lease_name ), 0, 24 ) ) );
wp_cache_delete( 'ssc_lease_' . substr( md5( $lease_name ), 0, 24 ), 'options' );
$again = SSC_Lease::acquire( $lease_name, 120 );
check( false !== $again, 'F14: a lease left behind by a request that died expires on its own' );
SSC_Lease::release( $lease_name, $again );
remove_filter( 'pre_http_request', $sum_mock, PHP_INT_MAX );
foreach ( array( $conv, $conv2, $conv3 ) as $forget ) {
	SSC_Conversation::forget( $forget );
}
wp_set_current_user( 1 );
SSC_Settings::set_secret( 'openai_api_key', '' );

// F16: replacing a document is all or nothing; a failure on the way leaves the previous version complete and searchable.
global $wpdb;
$kb_table  = SSC_Schema::kb_table_name();
$f16_old   = str_repeat( 'Old version sentence about the returns policy and shipping times. ', 30 );
$f16_new   = str_repeat( 'New version sentence about the warranty terms and service centers. ', 30 );
$f16_count = function ( $doc ) use ( $wpdb, $kb_table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$kb_table} WHERE doc_id = %s", $doc ) );
};
$f16_text  = function ( $doc ) use ( $wpdb, $kb_table ) {
	return implode( ' ', (array) $wpdb->get_col( $wpdb->prepare( "SELECT chunk FROM {$kb_table} WHERE doc_id = %s ORDER BY id ASC", $doc ) ) );
};
$f16_pending = function () use ( $wpdb, $kb_table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$kb_table} WHERE product_id = %s", SSC_Schema::KB_PENDING ) );
};
SSC_Schema::kb_delete_document( 'f16-doc' );
$old_chunks = SSC_Schema::kb_insert_document( 'f16-doc', 'F16 doc', $f16_old );
check( $old_chunks > 0 && $old_chunks === $f16_count( 'f16-doc' ), 'F16: the old version is stored' );
// A crash after the new version is built and before it takes the place.
$boom = function () {
	throw new RuntimeException( 'simulated failure in the middle' );
};
add_action( 'ssc_kb_replace_built', $boom );
$res = SSC_Schema::kb_replace_document( 'f16-doc', 'F16 doc', $f16_new );
remove_action( 'ssc_kb_replace_built', $boom );
check( false === $res && $old_chunks === $f16_count( 'f16-doc' ) && false !== strpos( $f16_text( 'f16-doc' ), 'returns policy' ) && false === strpos( $f16_text( 'f16-doc' ), 'warranty terms' ), 'F16: a failure in the middle keeps the previous version complete' );
check( 0 === $f16_pending(), 'F16: the half-built version is cleaned up' );
check( null !== SSC_Knowledge::retrieve_chunks( 'general', 'returns policy shipping times' ) && array() !== SSC_Knowledge::retrieve_chunks( 'general', 'returns policy shipping times' ), 'F16: the previous version is still found by a question' );
// The step that puts the new version in place fails.
$break_update = function ( $query ) use ( $kb_table ) {
	return ( 0 === strpos( $query, 'UPDATE `' . $kb_table . '` SET `doc_id`' ) ) ? 'UPDATE `ssc_no_such_table_f16` SET `a` = 1' : $query;
};
$quiet = $wpdb->suppress_errors( true );
add_filter( 'query', $break_update );
$res = SSC_Schema::kb_replace_document( 'f16-doc', 'F16 doc', $f16_new );
remove_filter( 'query', $break_update );
$wpdb->suppress_errors( $quiet );
check( false === $res && $old_chunks === $f16_count( 'f16-doc' ) && false === strpos( $f16_text( 'f16-doc' ), 'warranty terms' ) && 0 === $f16_pending(), 'F16: a failure while putting the new version in place keeps the old one and leaves nothing behind' );
// While a replacement is being built, searches see only the old version, and lists show one document.
$peek = function () use ( &$peek_found, &$peek_docs ) {
	$peek_found = array_column( SSC_Knowledge::retrieve_chunks( 'general', 'warranty terms service centers', 5 ), 'chunk' );
	$peek_docs  = array_column( SSC_Schema::kb_documents(), 'doc_id' );
};
$peek_found = array();
$peek_docs  = array();
add_action( 'ssc_kb_replace_built', $peek );
$res = SSC_Schema::kb_replace_document( 'f16-doc', 'F16 doc', $f16_new );
remove_action( 'ssc_kb_replace_built', $peek );
check( false === strpos( implode( ' ', $peek_found ), 'warranty terms' ) && 1 === count( array_keys( $peek_docs, 'f16-doc', true ) ) && 0 === count( preg_grep( '/^tmp-/', $peek_docs ) ), 'F16: a half-built version is invisible to searches and to the document list' );
check( is_int( $res ) && $res > 0 && $res === $f16_count( 'f16-doc' ) && false !== strpos( $f16_text( 'f16-doc' ), 'warranty terms' ) && false === strpos( $f16_text( 'f16-doc' ), 'returns policy' ) && 0 === $f16_pending(), 'F16: a successful replace swaps the whole document and leaves nothing behind' );
$again = SSC_Schema::kb_replace_document( 'f16-doc', 'F16 doc', $f16_new );
check( $again === $f16_count( 'f16-doc' ), 'F16: running the same replace again makes no duplicate chunks' );
check( false === SSC_Schema::kb_replace_document( 'f16-doc', 'F16 doc', '' ) && $again === $f16_count( 'f16-doc' ), 'F16: an empty text never wipes the previous version' );
// Leftovers of a build that died are purged after an hour, and not before.
$wpdb->insert( $kb_table, array( 'doc_id' => 'tmp-f16left', 'product_id' => SSC_Schema::KB_PENDING, 'source_title' => 'x', 'source_url' => '', 'chunk' => 'left over', 'search_text' => 'left over', 'created_at' => current_time( 'mysql' ) ) );
check( 0 === SSC_Schema::kb_purge_pending() && 1 === $f16_count( 'tmp-f16left' ), 'F16: a build that is only just under way is not purged' );
$wpdb->update( $kb_table, array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 2 * HOUR_IN_SECONDS ) ), array( 'doc_id' => 'tmp-f16left' ) );
check( 1 === SSC_Schema::kb_purge_pending() && 0 === $f16_count( 'tmp-f16left' ), 'F16: leftovers of a build that died are purged' );
// Site sync: a page whose new version cannot be built keeps its previous version and is counted.
$sync_was_on = SSC_Modules::is_active( 'sitesync' );
SSC_Modules::activate( 'sitesync' );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'F16 sync page', 'post_content' => '<p>' . str_repeat( 'The first published text of this page talks about delivery. ', 12 ) . '</p>' ) );
$first = SSC_Module_Sitesync::index_post( $page );
check( $first > 0, 'F16: a page is indexed' );
$page_doc  = SSC_Module_Sitesync::doc_id( $page );
$before    = $f16_text( $page_doc );
wp_update_post( array( 'ID' => $page, 'post_content' => '<p>' . str_repeat( 'The second text of this page talks about invoices and payment. ', 12 ) . '</p>' ) );
add_action( 'ssc_kb_replace_built', $boom );
$res = SSC_Module_Sitesync::index_post( $page );
remove_action( 'ssc_kb_replace_built', $boom );
check( -1 === $res && $before === $f16_text( $page_doc ) && 0 === $f16_pending(), 'F16: a failed update of a synced page keeps its previous version' );
update_option( SSC_Module_Sitesync::STATE, array( 'running' => true, 'cursor' => (int) $page - 1, 'done' => 0, 'total' => 1, 'failed' => 0, 'seen' => array() ), false );
add_action( 'ssc_kb_replace_built', $boom );
$state = SSC_Module_Sitesync::run_batch( 0 );
remove_action( 'ssc_kb_replace_built', $boom );
check( $state['failed'] >= 1 && $before === $f16_text( $page_doc ), 'F16: failures are counted in the sync state' );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-knowledge' ), function () {
	( new SSC_Admin_Knowledge() )->render();
} );
check( false !== strpos( $html, 'could not be updated' ), 'F16: the sync panel says how many pages kept their previous version' );
update_option( SSC_Module_Sitesync::STATE, array( 'running' => true, 'cursor' => (int) $page - 1, 'done' => 0, 'total' => 1, 'failed' => 0, 'seen' => array() ), false );
SSC_Module_Sitesync::run_batch( 0 );
check( false !== strpos( $f16_text( $page_doc ), 'invoices and payment' ) && false === strpos( $f16_text( $page_doc ), 'first published text' ), 'F16: the next sync brings the page up to date, with no duplicate chunks' );
wp_delete_post( $page, true );
SSC_Schema::kb_delete_document( 'f16-doc' );
SSC_Schema::kb_delete_document( $page_doc );
update_option( SSC_Module_Sitesync::STATE, array(), false );
if ( ! $sync_was_on ) {
	SSC_Modules::deactivate( 'sitesync' );
}

// F13: a small file that unpacks to a huge amount of data, too many objects or too much text is refused with a clear message before the memory is used.
$doc_limits = array();
$doc_filter = function ( $value, $name ) use ( &$doc_limits ) {
	return isset( $doc_limits[ $name ] ) ? $doc_limits[ $name ] : $value;
};
add_filter( 'ssc_doc_limit', $doc_filter, 10, 2 );
$tmp = sys_get_temp_dir();
$make_docx = function ( $text ) use ( $tmp ) {
	$path = tempnam( $tmp, 'ssc-docx' );
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::OVERWRITE );
	$zip->addFromString( 'word/document.xml', '<w:document><w:body><w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p></w:body></w:document>' );
	$zip->close();
	return $path;
};
$make_pdf = function ( $contents, $extra_objects = 0 ) {
	$pdf   = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
	$refs  = array();
	$n     = 10;
	$body  = '';
	foreach ( $contents as $content ) {
		$z      = gzcompress( $content );
		$refs[] = $n . ' 0 R';
		$body  .= $n . " 0 obj\n<< /Filter /FlateDecode /Length " . strlen( $z ) . " >>\nstream\n" . $z . "\nendstream\nendobj\n";
		$n++;
	}
	$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [" . implode( ' ', $refs ) . "] /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n" . $body;
	for ( $i = 0; $i < $extra_objects; $i++ ) {
		$pdf .= ( 500 + $i ) . " 0 obj\n<< /Note $i >>\nendobj\n";
	}
	return $pdf . "%%EOF\n";
};
$write_pdf = function ( $data ) use ( $tmp ) {
	$path = tempnam( $tmp, 'ssc-pdf' );
	file_put_contents( $path, $data );
	return $path;
};
$sentence = 'This sample document describes the opening hours and the return policy of the audit company. ';

$ok_docx = $make_docx( $sentence );
$res     = SSC_Doc_Extract::file( $ok_docx, 'docx' );
check( is_string( $res ) && false !== strpos( $res, 'return policy' ), 'F13: an ordinary Word file is read' );
$ok_pdf = $write_pdf( $make_pdf( array( 'BT /F1 12 Tf 72 700 Td (' . $sentence . ') Tj ET' ) ) );
$res    = SSC_Doc_Extract::file( $ok_pdf, 'pdf' );
check( is_string( $res ) && false !== strpos( $res, 'return policy' ), 'F13: an ordinary PDF is read' );

$doc_limits = array( 'stream' => 1000000 );
$bomb_docx  = $make_docx( str_repeat( 'A', 3000000 ) );
check( filesize( $bomb_docx ) < 20000, 'F13: the Word fixture is small on disk' );
$res = SSC_Doc_Extract::file( $bomb_docx, 'docx' );
check( is_wp_error( $res ) && 'ssc_doc_big' === $res->get_error_code(), 'F13: a Word file that unpacks past the limit is refused' );
$bomb_pdf = $write_pdf( $make_pdf( array( 'BT /F1 12 Tf (' . str_repeat( 'A', 3000000 ) . ') Tj ET' ) ) );
check( filesize( $bomb_pdf ) < 20000, 'F13: the PDF fixture is small on disk' );
$res = SSC_Doc_Extract::file( $bomb_pdf, 'pdf' );
check( is_wp_error( $res ) && 'ssc_doc_big' === $res->get_error_code(), 'F13: a PDF stream that unpacks past the limit is refused' );
$before = memory_get_peak_usage( true );
SSC_Doc_Extract::file( $bomb_pdf, 'pdf' );
check( memory_get_peak_usage( true ) - $before < 8000000, 'F13: refusing the stream does not allocate the whole payload' );

$doc_limits = array( 'stream' => 1000000, 'total' => 1200000 );
$many_pdf   = $write_pdf( $make_pdf( array( 'BT /F1 12 Tf (' . str_repeat( 'B', 600000 ) . ') Tj ET', 'BT (' . str_repeat( 'C', 600000 ) . ') Tj ET', 'BT (' . str_repeat( 'D', 600000 ) . ') Tj ET' ) ) );
$res        = SSC_Doc_Extract::file( $many_pdf, 'pdf' );
check( is_wp_error( $res ) && 'ssc_doc_big' === $res->get_error_code(), 'F13: many streams are limited together, not only one by one' );

$doc_limits = array( 'objects' => 10 );
$obj_pdf    = $write_pdf( $make_pdf( array( 'BT /F1 12 Tf 72 700 Td (' . $sentence . ') Tj ET' ), 40 ) );
$res        = SSC_Doc_Extract::file( $obj_pdf, 'pdf' );
check( is_wp_error( $res ) && 'ssc_doc_big' === $res->get_error_code(), 'F13: a PDF with too many objects is refused' );

$doc_limits = array( 'text' => 50 );
$res        = SSC_Doc_Extract::file( $ok_docx, 'docx' );
check( is_wp_error( $res ) && 'ssc_doc_big' === $res->get_error_code() && false !== strpos( $res->get_error_message(), 'Nothing was added' ), 'F13: too much text is refused whole, not cut' );

$doc_limits = array();
$res        = SSC_Doc_Extract::file( $ok_docx, 'docx' );
check( is_string( $res ), 'F13: the limits reset between documents' );
remove_filter( 'ssc_doc_limit', $doc_filter, 10 );
foreach ( array( $ok_docx, $ok_pdf, $bomb_docx, $bomb_pdf, $many_pdf, $obj_pdf ) as $f ) {
	@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- temporary test files.
}

// Put everything back.
if ( null === $audit_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $audit_before['modules'], false );
}
if ( null === $audit_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $audit_before['settings'] );
}
SSC_Settings::reset_cache();
