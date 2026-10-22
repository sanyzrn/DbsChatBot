<?php
/**
 * Regression tests for the confirmed points of the two reviews of 1.7.3 (2026-10-21/22).
 * Each block names the finding it closes. Required from tests/integration.php (check() and a disposable site).
 */

global $wpdb;
$audit5_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
	'flag'     => get_option( 'ssc_settings_v5_applied', null ),
	'setup'    => get_option( 'ssc_chatbot_setup', null ),
);
wp_set_current_user( 1 );

// Elementor: the widget renders the assistant. SSC_Plugin is a class, so the guard must ask class_exists().
if ( ! class_exists( 'Elementor\Widget_Base' ) ) {
	eval( 'namespace Elementor; abstract class Widget_Base { public function get_settings_for_display() { return array( "position" => "left" ); } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a test double for a class that only exists with Elementor.
}
require_once SSC_CHATBOT_DIR . 'widgets/class-ssc-elementor-widget.php';
SSC_Settings::update( array( 'enabled' => 'yes', 'display_mode' => 'all', 'display_users' => 'all', 'display_devices' => 'all' ) );
SSC_Setup::update_state( array( 'steps' => array_fill_keys( SSC_Setup::steps(), true ), 'published' => true, 'completed_at' => time(), 'started_at' => time() ) );
$frontend = SSC_Plugin::instance()->frontend;
$flag     = new ReflectionProperty( $frontend, 'rendered' );
$flag->setAccessible( true );
$flag->setValue( $frontend, false );
$widget = new SSC_Elementor_Widget();
$render = new ReflectionMethod( $widget, 'render' );
$render->setAccessible( true );
ob_start();
$render->invoke( $widget );
$html = ob_get_clean();
check( false !== strpos( $html, 'id="ssc-chatbot-root"' ), 'Elementor: the widget puts the assistant on the page (it printed nothing before)' );
$flag->setValue( $frontend, false );

// CRIT-01: the 4.x settings move runs once, and never on settings that are already in the 5.x shape.
$custom                          = SSC_Settings::defaults();
$custom['business']['org_name']  = 'Atlas';
$custom['business']['phone']     = '021-123';
$custom['business']['hours']     = 'Sat-Wed 9-17';
$custom['ai_provider']           = 'openrouter';
$custom['ip_storage']            = 'none';
$custom['answer_scope']          = 'knowledge';
$custom['trusted_proxies']       = '203.0.113.0/24';
$custom['company_id']            = 'acme'; // a 4.x key that the first move leaves in place
delete_option( 'ssc_settings_v5_applied' );
update_option( SSC_Settings::OPTION_KEY, $custom );
SSC_Settings::reset_cache();
SSC_Schema::migrate_settings_v5();
SSC_Settings::reset_cache();
$after = get_option( SSC_Settings::OPTION_KEY );
check( 'openrouter' === $after['ai_provider'] && 'none' === $after['ip_storage'] && 'knowledge' === $after['answer_scope'] && '203.0.113.0/24' === $after['trusted_proxies'] && '021-123' === $after['business']['phone'] && 'Sat-Wed 9-17' === $after['business']['hours'], 'Migration: a customised 5.x site that still carries a 4.x key keeps every setting' );
check( ! empty( get_option( 'ssc_settings_v5_applied' ) ), 'Migration: the move is marked as done' );
$four = array( 'company_id' => 'acme', 'company_name' => 'Old Co', 'support_phone' => '021-9', 'ai_provider' => 'openai', 'ai_rate_limit' => 77, 'product_knowledge' => array( 'acme' => 'We sell things.' ) );
delete_option( 'ssc_settings_v5_applied' );
update_option( SSC_Settings::OPTION_KEY, $four );
SSC_Settings::reset_cache();
SSC_Schema::migrate_settings_v5();
$moved = get_option( SSC_Settings::OPTION_KEY );
check( 'Old Co' === $moved['business']['org_name'] && '021-9' === $moved['business']['support_phone'] && 'openai' === $moved['ai_provider'] && 77 === (int) $moved['chat_rate_limit'] && isset( $moved['business'] ), 'Migration: real 4.x settings are still moved to the 5.x shape, once' );
$moved['business']['phone'] = '021-555';
$moved['ai_provider']       = 'gemini';
update_option( SSC_Settings::OPTION_KEY, $moved );
SSC_Schema::migrate_settings_v5();
$again = get_option( SSC_Settings::OPTION_KEY );
check( '021-555' === $again['business']['phone'] && 'gemini' === $again['ai_provider'], 'Migration: what the owner changes after the move survives every later database upgrade' );

// CRIT-04: a replace-import file with nothing usable in it never empties the FAQ bank.
update_option( SSC_Modules::OPTION, array( 'faq' ) );
SSC_Schema::qa_import( array( array( 'question' => 'Audit five question one', 'answer' => 'Answer one', 'keywords' => 'one' ), array( 'question' => 'Audit five question two', 'answer' => 'Answer two', 'keywords' => 'two' ) ), true );
$faq    = new SSC_Module_FAQ();
$import = new ReflectionMethod( $faq, 'import_content' );
$import->setAccessible( true );
foreach ( array(
	'a header only'                  => "question,answer,keywords\n",
	'a single line'                  => 'question,answer',
	'rows without an answer'         => "question,answer\nWhat?,\nWhy?,  \n",
	'JSON with no question or answer' => '[{"title":"x"},{"q":"y"}]',
	'JSON with empty answers'        => '[{"question":"A","answer":""},{"question":"","answer":"B"}]',
	'an empty file'                  => '',
) as $label => $content ) {
	$res = $import->invoke( $faq, $content, true );
	check( 'empty' === $res['error'] && 0 === $res['inserted'] && 2 === SSC_Schema::qa_count(), 'FAQ import: ' . $label . ' in replace mode is refused and the bank is untouched' );
}
$res = $import->invoke( $faq, "question,answer,keywords\nNew question,New answer,new\n", true );
check( '' === $res['error'] && 1 === $res['inserted'] && 1 === SSC_Schema::qa_count(), 'FAQ import: a file with a usable row still replaces the bank' );
$res = $import->invoke( $faq, "question,answer\n", false );
check( '' === $res['error'] && 0 === $res['inserted'] && 1 === SSC_Schema::qa_count(), 'FAQ import: adding an empty file to the bank is harmless and leaves it as it is' );
SSC_Schema::qa_import( array(), true );

// B1: a visitor's address is never stored as a plain MD5.
$ip  = '203.0.113.9';
$key = SSC_Input::visitor_key( $ip );
check( 1 === preg_match( '/^[0-9a-f]{32}$/', $key ) && md5( $ip ) !== $key && SSC_Input::visitor_key( $ip ) === $key && SSC_Input::visitor_key( '203.0.113.10' ) !== $key, 'Privacy: the visitor key is a 32-character keyed hash, stable per address and different from MD5' );
$stats = SSC_Schema::stats_table_name();
$wpdb->query( "DELETE FROM {$stats} WHERE metric LIKE 'rl:audit5:%'" );
SSC_Schema::rate_limit( 'audit5', 'both', 100, 50, $ip, 'cid-audit5' );
$metrics = (array) $wpdb->get_col( "SELECT metric FROM {$stats} WHERE metric LIKE 'rl:audit5:%'" );
check( count( $metrics ) >= 3 && ! in_array( 'rl:audit5:ip:' . md5( $ip ), $metrics, true ) && ! in_array( 'rl:audit5:ipcap:' . md5( $ip ), $metrics, true ) && in_array( 'rl:audit5:ip:' . $key, $metrics, true ), 'Privacy: the rate limiter keeps no plain MD5 of the visitor address' );
$wpdb->query( "DELETE FROM {$stats} WHERE metric LIKE 'rl:audit5:%'" );
check( SSC_Schema::rate_limit( 'audit5', 'ip', 2, 2, $ip, '' ) && SSC_Schema::rate_limit( 'audit5', 'ip', 2, 2, $ip, '' ) && ! SSC_Schema::rate_limit( 'audit5', 'ip', 2, 2, $ip, '' ), 'Privacy: the rate limit itself still counts and blocks' );
$wpdb->query( "DELETE FROM {$stats} WHERE metric LIKE 'rl:audit5:%'" );

// B2: the uninstall removes what later versions write (read from the file: running it would erase the test site).
$uninstall = (string) file_get_contents( SSC_CHATBOT_DIR . 'uninstall.php' );
check( false !== strpos( $uninstall, "'ssc_ai_failures'" ) && false !== strpos( $uninstall, "'ssc_private_proxy_trust_applied'" ) && false !== strpos( $uninstall, "'ssc_settings_v5_applied'" ) && false !== strpos( $uninstall, 'ssc_woo_cpn_day_' ), 'Uninstall: the failure record, the migration marks and the old daily coupon counters are removed too' );
check( SSC_Chat_Engine::FAILURE_OPTION === 'ssc_ai_failures', 'Uninstall: the failure option it removes is the one the plugin writes' );

// B3: leftover daily coupon options of older versions are cleaned up.
update_option( 'ssc_woo_cpn_day_20250101', 3, false );
update_option( 'ssc_woo_cpn_day_20250102', 5, false );
SSC_Schema::purge_old( 0, 0 );
check( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ssc_woo_cpn_day_' ) . '%' ) ), 'Coupons: the old one-option-a-day counters are removed by the daily cleanup' );

// SEC-04 / MOD-09: the webhook and the SMS panels never follow a redirect and never read an unbounded answer.
$seen = array();
$spy  = function ( $pre, $args, $url ) use ( &$seen ) {
	$seen[] = array( $url, $args );
	return new WP_Error( 'audit5_stop', 'stopped by the test' );
};
add_filter( 'pre_http_request', $spy, 99, 3 );
SSC_Settings::update( array( 'ai_provider' => 'webhook', 'ai_webhook_url' => 'https://93.184.216.34/hook' ) );
$hook = new SSC_Provider_Webhook();
$hook->generate_with( '', '', 'system', array( array( 'role' => 'user', 'content' => 'hello' ) ) );
$last = end( $seen );
check( $last && false !== strpos( $last[0], '93.184.216.34' ) && 0 === $last[1]['redirection'] && 2097152 === $last[1]['limit_response_size'], 'Webhook: no redirect is followed with the signed body, and the answer is size-capped' );
update_option( SSC_Modules::OPTION, array( 'sms' ) );
SSC_Modules::boot_active();
foreach ( array( 'kavenegar', 'melipayamak', 'ippanel', 'smsir' ) as $panel ) {
	$seen = array();
	SSC_Settings::update( array( 'sms_provider' => $panel, 'sms_sender' => '3000', 'sms_username' => 'u' ) );
	SSC_Settings::set_secret( 'sms_api_key', 'audit5-sms-key' );
	SSC_Module_Sms::send( '09123456789', 'Audit five' );
	$last = end( $seen );
	check( $last && 0 === $last[1]['redirection'] && $last[1]['limit_response_size'] > 0, 'SMS (' . $panel . '): no redirect is followed and the answer is size-capped' );
}
SSC_Settings::set_secret( 'sms_api_key', '' );
remove_filter( 'pre_http_request', $spy, 99 );

// SEC-03: keys and tokens never pass through the general sanitizer as plain text.
check( null === SSC_Settings::sanitize_value( 'openai_api_key', 'sk-plain' ) && null === SSC_Settings::sanitize_value( 'sms_api_key', 'k' ) && null === SSC_Settings::sanitize_value( 'ai_webhook_secret', 's' ), 'Secrets: the general sanitizer refuses every key and token field' );
check( 'ltr' === SSC_Settings::sanitize_value( 'direction', 'ltr' ) && 'rtl' === SSC_Settings::sanitize_value( 'direction', 'bad' ) && 'hello' === SSC_Settings::sanitize_value( 'welcome_text', 'hello' ), 'Secrets: other settings are sanitized as before' );

// MOD-01: a reminder SMS to a typed number counts against the limit on success, not only on a typo (WooCommerce is not on the test site: read from the source).
$woo   = (string) file_get_contents( SSC_CHATBOT_DIR . 'includes/modules/class-ssc-module-woo.php' );
$start = strpos( $woo, 'public static function rest_remind' );
$end   = strpos( $woo, 'public static function save_reminder' );
$body  = substr( $woo, $start, $end - $start );
check( 2 === substr_count( $body, "self::blocked( 'remind', 5, true )" ), 'Reminders: both an invalid number and a successful request count against the limit' );

// B10: Cancel on the Plugins screen cancels. The first question deletes with data, the second deletes and keeps it, Cancel on both stops.
$admin = (string) file_get_contents( SSC_CHATBOT_DIR . 'includes/admin/class-ssc-admin.php' );
check( false !== strpos( $admin, 'if (!wipe && !window.confirm(KEEP)) { return; }' ) && false === strpos( $admin, 'Cancel = delete plugin, KEEP data' ), 'Uninstall prompt: Cancel on the second question stops the deletion' );

// Put everything back.
foreach ( array( 'modules' => SSC_Modules::OPTION, 'setup' => 'ssc_chatbot_setup', 'settings' => SSC_Settings::OPTION_KEY, 'flag' => 'ssc_settings_v5_applied' ) as $key => $option ) {
	if ( null === $audit5_before[ $key ] ) {
		delete_option( $option );
	} else {
		update_option( $option, $audit5_before[ $key ], false );
	}
}
SSC_Settings::reset_cache();
