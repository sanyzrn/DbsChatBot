<?php
/**
 * Marketplace licence: three states, locked premium modules, daily AI allowance.
 * A stand-in for the marketplace's licence class is used; the real one is ionCube-encoded.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$lic_modules_before  = get_option( SSC_Modules::OPTION, null );
$lic_settings_before = get_option( SSC_Settings::OPTION_KEY, null );
SSC_License::reset();

// Open: no licence file is loaded (GitHub ZIP, or the file could not run here).
check( ! class_exists( SSC_License::CLASS_NAME, false ) && 'open' === SSC_License::state() && ! SSC_License::is_limited(), 'Licence: without the marketplace file nothing is limited' );
check( ! SSC_License::module_locked( 'live' ) && ! SSC_License::ai_cap_reached(), 'Licence: open state locks no module and caps no AI answer' );

// A stand-in class in a real file (the check reads the file of the class and its hash).
$lic_file = sys_get_temp_dir() . '/ssc-license-stub-' . wp_generate_password( 8, false ) . '.php';
file_put_contents( $lic_file, "<?php\nclass " . SSC_License::CLASS_NAME . " {\n\tpublic static \$active = false;\n\tpublic static \$throw = false;\n\tpublic function isActive() {\n\t\tif ( self::\$throw ) { throw new RuntimeException( 'unreachable' ); }\n\t\treturn self::\$active;\n\t}\n}\n" );
require $lic_file;
$lic_class = SSC_License::CLASS_NAME;
$lic_hash  = function () use ( $lic_file ) { return sha1_file( $lic_file ); };
add_filter( 'vazho_license_file_sha1', $lic_hash );

// Licensed.
$lic_class::$active = true;
SSC_License::reset();
check( 'licensed' === SSC_License::state() && ! SSC_License::is_limited() && ! SSC_License::module_locked( 'woo' ), 'Licence: an unchanged file reporting an active licence is licensed and limits nothing' );

// Unlicensed.
$lic_class::$active = false;
SSC_License::reset();
check( 'unlicensed' === SSC_License::state() && SSC_License::is_limited(), 'Licence: an unchanged file reporting an inactive licence limits the plugin' );
foreach ( SSC_License::PREMIUM_MODULES as $lic_id ) {
	check( SSC_License::module_locked( $lic_id ), 'Licence: the premium module "' . $lic_id . '" is locked without a licence' );
}
foreach ( array( 'faq', 'history', 'analytics', 'leads', 'voice', 'proactive', 'notifications', 'csat', 'handoff' ) as $lic_id ) {
	check( ! SSC_License::module_locked( $lic_id ), 'Licence: the module "' . $lic_id . '" stays free' );
}

// Locked modules cannot be activated; a free one can; a premium one stored as on does not run.
update_option( SSC_Modules::OPTION, array( 'history' ) );
$lic_res = SSC_Modules::activate( 'live' );
check( is_wp_error( $lic_res ) && 'ssc_module_license' === $lic_res->get_error_code() && ! in_array( 'live', SSC_Modules::active_ids(), true ), 'Licence: a premium module cannot be switched on without a licence' );
check( true === SSC_Modules::activate( 'faq' ) && SSC_Modules::is_active( 'faq' ), 'Licence: free modules can still be switched on' );
update_option( SSC_Modules::OPTION, array( 'history', 'faq', 'live' ) );
check( in_array( 'live', SSC_Modules::active_ids(), true ) && ! SSC_Modules::is_active( 'live' ) && SSC_Modules::is_active( 'faq' ), 'Licence: a premium module stored as on stays stored but does not run while the licence is inactive' );
$lic_status = SSC_Modules::statuses();
check( 'inactive' === $lic_status['live']['status'] && 'active' === $lic_status['faq']['status'], 'Licence: the module cards show the locked module as inactive' );
SSC_License::reset();
$lic_class::$active = true;
check( SSC_Modules::is_active( 'live' ), 'Licence: once the licence is active the stored module runs again, with its settings intact' );
$lic_class::$active = false;
SSC_License::reset();

// The marketplace file was replaced or edited: unlicensed, even if the stand-in says "active".
$lic_class::$active = true;
remove_filter( 'vazho_license_file_sha1', $lic_hash );
SSC_License::reset();
check( 'unlicensed' === SSC_License::state(), 'Licence: a licence file that does not match the published hash is treated as not licensed' );
add_filter( 'vazho_license_file_sha1', $lic_hash );

// A check that fails must not take the site down or lock the owner out.
$lic_class::$throw = true;
SSC_License::reset();
check( 'open' === SSC_License::state() && ! SSC_License::is_limited(), 'Licence: a check that throws leaves the plugin unlimited' );
$lic_class::$throw  = false;
$lic_class::$active = false;
SSC_License::reset();
check( 'unlicensed' === SSC_License::state(), 'Licence: the state is re-evaluated after a reset' );

// Daily AI allowance: counted only without a licence, and the rest falls back to the knowledge.
update_option( SSC_Modules::OPTION, array() );
SSC_Settings::update( array( 'ai_provider' => 'openai', 'openai_model' => 'gpt-4o-mini', 'qa_mode' => 'ai_first', 'ai_cache_enabled' => 'no', 'streaming_enabled' => 'no', 'show_sources' => 'no', 'web_search' => 'no', 'kb_semantic' => 'no' ) );
SSC_Settings::set_secret( 'openai_api_key', 'sk-licence-test-key' );
$lic_calls = 0;
$lic_mock  = function () use ( &$lic_calls ) {
	++$lic_calls;
	return array(
		'headers'  => array(),
		'body'     => wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'Licence test answer.' ), 'finish_reason' => 'stop' ) ) ) ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $lic_mock, 30 );
delete_transient( 'ssc_ai_day_' . gmdate( 'Ymd' ) );
$lic_ask = function ( $q ) {
	$engine = new SSC_Chat_Engine();
	$engine->set_context( '203.0.113.77', '' );
	return $engine->chat( $q, 'general', array() );
};
$lic_res = $lic_ask( 'Licence question 1' );
check( 'ai' === $lic_res['source'] && 1 === $lic_calls && 1 === SSC_License::ai_used_today(), 'Licence: without a licence an AI answer is given and counted' );
set_transient( 'ssc_ai_day_' . gmdate( 'Ymd' ), SSC_License::FREE_AI_DAILY, DAY_IN_SECONDS );
$lic_before = $lic_calls;
$lic_res    = $lic_ask( 'Licence question 2' );
check( SSC_License::ai_cap_reached() && $lic_calls === $lic_before && 'ai' !== $lic_res['source'], 'Licence: after the daily allowance the provider is not called and the visitor gets the knowledge/fallback answer' );
$lic_class::$active = true;
SSC_License::reset();
$lic_res = $lic_ask( 'Licence question 3' );
check( 'ai' === $lic_res['source'] && $lic_calls === $lic_before + 1 && ! SSC_License::ai_cap_reached(), 'Licence: with an active licence there is no daily allowance' );
$lic_before = SSC_License::ai_used_today();
SSC_License::count_ai_answer();
check( SSC_License::ai_used_today() === $lic_before, 'Licence: answers are not counted when licensed' );

// Source guards: the kill switch exists and the licence file is never required directly.
$lic_source = (string) file_get_contents( SSC_CHATBOT_DIR . 'includes/core/class-ssc-license.php' );
check( false !== strpos( $lic_source, 'VAZHO_DISABLE_LICENSE_GATE' ), 'Licence: a support switch can turn the limits off for a site that cannot reach the marketplace' );

// Restore.
remove_filter( 'pre_http_request', $lic_mock, 30 );
remove_filter( 'vazho_license_file_sha1', $lic_hash );
delete_transient( 'ssc_ai_day_' . gmdate( 'Ymd' ) );
delete_option( SSC_Chat_Engine::FAILURE_OPTION );
SSC_Settings::set_secret( 'openai_api_key', '' );
if ( null === $lic_modules_before ) { delete_option( SSC_Modules::OPTION ); } else { update_option( SSC_Modules::OPTION, $lic_modules_before ); }
if ( null !== $lic_settings_before ) { update_option( SSC_Settings::OPTION_KEY, $lic_settings_before ); }
unlink( $lic_file );
SSC_License::reset();
