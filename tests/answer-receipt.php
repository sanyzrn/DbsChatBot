<?php
/** Optional provenance: privacy, cache compatibility, isolation and no AI overhead. */
if ( ! defined( 'SSC_TEST_SITE' ) || ! SSC_TEST_SITE ) { exit; }
$ar_settings = SSC_Settings::all();
$ar_modules = get_option( SSC_Modules::OPTION );
update_option( SSC_Modules::OPTION, array() );
check( ! SSC_Modules::is_active( 'answer_receipt' ) && SSC_Modules::get( 'answer_receipt' ) instanceof SSC_Module, 'Receipt: optional module exists but is off by default' );
$basic = array( 'reply' => 'text' );
check( $basic === SSC_Answer_Receipt::transport( $basic, array( 'receipt' => array( 'version' => 1 ) ) ), 'Receipt: disabled transport adds no field' );
update_option( SSC_Modules::OPTION, array( 'answer_receipt' ) );
SSC_Settings::update( array( 'ai_provider' => 'none', 'qa_mode' => 'bank_only', 'show_sources' => 'yes', 'chatlog_enabled' => 'no' ) );
$ar_bank_filter = function () { return 'A saved integration answer.'; };
add_filter( 'ssc_pre_reply', $ar_bank_filter );
$ar_engine = new SSC_Chat_Engine();
$ar_first = $ar_engine->chat( 'Receipt first' );
check( 'filter' === $ar_first['receipt']['kind'] && null === $ar_first['receipt']['reviewed_at'] && ! SSC_Modules::is_active( 'history' ), 'Receipt: integration identity is honest and works without History' );
remove_filter( 'ssc_pre_reply', $ar_bank_filter );
$ar_missing = $ar_engine->chat( 'Receipt question without any matching knowledge 98764321' );
check( ! isset( $ar_missing['receipt'] ), 'Receipt: unanswered messages do not receive a trust label' );

$ar_post = wp_insert_post( array( 'post_title' => 'Public receipt page', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'Receipt reference.' ) );
// Pretty URLs allow provenance to reject all token-bearing query strings.
$ar_permalink = get_option( 'permalink_structure' );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
$ar_url = get_permalink( $ar_post );
$ar_refs = array( array( 'title' => 'PRIVATE IMPORT TITLE', 'url' => $ar_url, 'type' => 'context' ) );
$ar_snapshot = SSC_Answer_Receipt::snapshot( $ar_refs, array() );
check( 1 === count( $ar_snapshot['sources'] ) && 'Public receipt page' === $ar_snapshot['sources'][0]['title'], 'Receipt: only public post title is exposed, never imported private metadata' );
$ar_out = SSC_Answer_Receipt::decorate( array( 'ok' => true, 'reply' => 'Answer' ), 'cache', $ar_snapshot );
check( 'cache' === $ar_out['receipt']['kind'] && $ar_snapshot['generated_at'] === $ar_out['receipt']['generated_at'], 'Receipt: cache retains original generation time and provenance' );
wp_update_post( array( 'ID' => $ar_post, 'post_status' => 'private' ) );
$ar_hidden = SSC_Answer_Receipt::decorate( array( 'ok' => true, 'reply' => 'Answer' ), 'cache', $ar_snapshot );
check( array() === $ar_hidden['sources'] && array() === $ar_hidden['receipt']['sources'], 'Receipt: a public source made private is hidden even from an existing cache receipt' );
wp_update_post( array( 'ID' => $ar_post, 'post_status' => 'publish' ) );
update_post_meta( $ar_post, '_yoast_wpseo_meta-robots-noindex', '1' );
check( array() === SSC_Answer_Receipt::public_sources( $ar_refs ), 'Receipt: explicitly noindex sources are hidden' );
delete_post_meta( $ar_post, '_yoast_wpseo_meta-robots-noindex' );
check( array() === SSC_Answer_Receipt::public_sources( array( array( 'url' => $ar_url . '?token=secret', 'title' => 'secret' ), array( 'url' => 'javascript:alert(1)', 'title' => 'bad' ), array( 'url' => 'https://example.invalid/private.pdf', 'title' => 'private upload' ), array( 'url' => home_url( '/nonexistent-receipt-page-987634/' ), 'title' => 'missing' ) ) ), 'Receipt: credentials, unknown imports, unsafe links and unresolved local URLs are suppressed' );
SSC_Settings::update( array( 'show_sources' => 'no' ) );
$ar_hidden = SSC_Answer_Receipt::decorate( array( 'ok' => true, 'reply' => 'Answer' ), 'ai', $ar_snapshot );
check( array() === $ar_hidden['sources'] && array() === $ar_hidden['receipt']['sources'], 'Receipt: show_sources=no suppresses titles and URLs' );
check( ! isset( SSC_Answer_Receipt::decorate( array( 'ok' => true, 'reply' => '' ), 'live', null )['receipt'] ), 'Receipt: empty live responses create no extra bubble' );

update_option( SSC_Modules::OPTION, array( 'answer_receipt', 'faq' ) );
// A bank-only response works without an AI provider and leaks no FAQ identifier.
$ar_qa = SSC_Schema::qa_insert( array( 'question' => 'Receipt bank exact question 73829', 'keywords' => 'receipt', 'answer' => 'Receipt bank exact answer.', 'product_id' => 'general' ) );
$ar_bank = $ar_engine->chat( 'Receipt bank exact question 73829' );
check( 'bank' === $ar_bank['source'] && 'bank' === $ar_bank['receipt']['kind'] && array() === $ar_bank['receipt']['sources'], 'Receipt: bank-only provenance works without a provider and has no private FAQ metadata' );
SSC_Schema::qa_delete( array( $ar_qa ) );

// Exercise the actual AI/cache engine with mocked service calls, no provider traffic.
SSC_Settings::update( array( 'ai_provider' => 'openai', 'openai_model' => 'gpt-4o-mini', 'qa_mode' => 'ai_first', 'ai_cache_enabled' => 'yes', 'streaming_enabled' => 'no', 'web_search' => 'no', 'kb_semantic' => 'no' ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-receipt-synthetic' );
$ar_calls = 0;
$ar_mock = function () use ( &$ar_calls ) {
    ++$ar_calls;
    return array( 'headers' => array(), 'body' => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'Receipt test answer.' ), 'finish_reason' => 'stop' ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $ar_mock, 40 );
SSC_Settings::flush_ai_cache();
$ar_engine = new SSC_Chat_Engine();
$ar_ai = $ar_engine->chat( 'Receipt unique cache question AR-01' );
$ar_cache = $ar_engine->chat( 'Receipt unique cache question AR-01' );
check( 'ai' === $ar_ai['source'] && 'cache' === $ar_cache['source'] && 1 === $ar_calls && $ar_ai['receipt']['generated_at'] === $ar_cache['receipt']['generated_at'], 'Receipt: real engine captures provenance once; cache needs no extra model call' );
update_option( SSC_Modules::OPTION, array() );
$ar_disabled = $ar_engine->chat( 'Receipt unique cache question AR-01' );
check( 'Receipt test answer.' === $ar_disabled['reply'] && 'cache' === $ar_disabled['source'] && ! isset( $ar_disabled['receipt'] ) && 1 === $ar_calls, 'Receipt: turning off the module can still read the new cache format without metadata output' );
SSC_Settings::flush_ai_cache();
$ar_legacy = $ar_engine->chat( 'Receipt legacy cache question AR-01' );
update_option( SSC_Modules::OPTION, array( 'answer_receipt' ) );
$ar_legacy = $ar_engine->chat( 'Receipt legacy cache question AR-01' );
check( 'cache' === $ar_legacy['source'] && 'unknown' === $ar_legacy['receipt']['provenance'] && null === $ar_legacy['receipt']['generated_at'], 'Receipt: legacy string cache is readable but never assigned fabricated provenance' );
$ar_second_engine = new SSC_Chat_Engine();
add_filter( 'ssc_pre_reply', $ar_bank_filter );
$ar_isolated = $ar_second_engine->chat( 'Receipt second engine' );
check( 'filter' === $ar_isolated['receipt']['kind'] && array() === $ar_isolated['receipt']['sources'] && null === $ar_isolated['receipt']['generated_at'], 'Receipt: another engine cannot inherit AI provenance' );
remove_filter( 'ssc_pre_reply', $ar_bank_filter );
remove_filter( 'pre_http_request', $ar_mock, 40 );
wp_delete_post( $ar_post, true );
update_option( 'permalink_structure', $ar_permalink );
flush_rewrite_rules();
SSC_Settings::set_secret( 'openai_api_key', '' );
SSC_Settings::update( $ar_settings );
update_option( SSC_Modules::OPTION, $ar_modules );
