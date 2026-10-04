<?php
/**
 * The consultation request: name, phone, an optional email and a short
 * description; the chat offers the form when a visitor clearly asks for a
 * consultation. Required from tests/integration.php (check() and a disposable site).
 */

$consult_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
);
global $wpdb;
$leads_module = new SSC_Module_Leads();
update_option( SSC_Modules::OPTION, array( 'leads' ) );
SSC_Settings::update( array( 'consult_email' => 'optional', 'consent_enabled' => 'no', 'form_fields' => array() ) );
$valid = array( 'type' => 'consult', 'name' => 'Sara Ahmadi', 'phone' => '09123456789', 'description' => 'I would like advice about a long-term plan.' );
$last  = function () {
	return last_case();
};

// Name, phone and description are enough: the email is optional.
$res = $leads_module->handle_submission( $valid, '203.0.113.60' );
check( ! is_wp_error( $res ) && ! empty( $res['ok'] ) && 'consult' === $last()['type'] && '' === SSC_Module_Leads::email_of( $last() ), 'Consultation: a request without an email is accepted' );
$res = $leads_module->handle_submission( array_merge( $valid, array( 'email' => 'Sara@Example.com ' ) ), '203.0.113.60' );
$row = $last();
check( ! is_wp_error( $res ) && 'Sara@Example.com' === SSC_Module_Leads::email_of( $row ), 'Consultation: a valid email is stored with the request' );
$count_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SSC_Schema::table_name() );
$res          = $leads_module->handle_submission( array_merge( $valid, array( 'email' => 'not-an-email' ) ), '203.0.113.60' );
check( is_wp_error( $res ) && 400 === $res->get_error_data()['status'] && false !== strpos( $res->get_error_message(), 'valid email' ), 'Consultation: a wrong email is refused with a clear message' );
$res = $leads_module->handle_submission( array_merge( $valid, array( 'name' => '', 'email' => 'a@b.ir' ) ), '203.0.113.60' );
check( is_wp_error( $res ), 'Consultation: the name is still required' );
$res = $leads_module->handle_submission( array_merge( $valid, array( 'phone' => '12', 'email' => 'a@b.ir' ) ), '203.0.113.60' );
check( is_wp_error( $res ), 'Consultation: a missing phone is refused even with an email' );
check( $count_before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SSC_Schema::table_name() ), 'Consultation: refused requests are not stored' );
$res = $leads_module->handle_submission( array_merge( $valid, array( 'email' => array( 'a@b.ir' ) ) ), '203.0.113.60' );
check( ! is_wp_error( $res ) && '' === SSC_Module_Leads::email_of( $last() ), 'Consultation: a list in the email field is ignored, not a fatal error' );

// The site owner can switch the email field off.
SSC_Settings::update( array( 'consult_email' => 'off' ) );
check( ! SSC_Module_Leads::email_enabled(), 'Consultation: the email field can be switched off' );
$res = $leads_module->handle_submission( array_merge( $valid, array( 'email' => 'sara@example.com' ) ), '203.0.113.60' );
check( ! is_wp_error( $res ) && '' === SSC_Module_Leads::email_of( $last() ), 'Consultation: with the field off, an email sent anyway is not stored' );
check( 'off' === SSC_Settings::sanitize_value( 'consult_email', 'off' ) && 'optional' === SSC_Settings::sanitize_value( 'consult_email', 'junk' ) && 'optional' === SSC_Settings::sanitize_value( 'consult_email', array( 'x' ) ), 'Consultation: the email setting accepts only its two values' );
$config = ( new SSC_Frontend() )->build_config();
check( empty( $config['consultEmail'] ), 'Consultation: the widget is told not to show the email field when it is off' );
SSC_Settings::update( array( 'consult_email' => 'optional' ) );
$config = ( new SSC_Frontend() )->build_config();
check( ! empty( $config['consultEmail'] ) && ! empty( $config['features']['leads'] ) && ! empty( $config['i18n']['formEmail'] ), 'Consultation: the widget shows the email field when it is on' );

// The inbox, the CSV and the notification show the email.
$leads_module->handle_submission( array_merge( $valid, array( 'name' => 'Email shown', 'email' => 'shown@example.com' ) ), '203.0.113.60' );
$notify_row = $last();
$result     = $leads_module->query( array( 'per_page' => 5 ) );
ob_start();
$filters = array( 'type' => '', 'status' => '', 'search' => '', 'page' => 1 );
$_GET    = array( 'page' => 'ssc-requests' );
wp_set_current_user( 1 );
require SSC_CHATBOT_DIR . 'includes/admin/views/page-requests.php';
$html = ob_get_clean();
$_GET = array();
check( false !== strpos( $html, 'mailto:shown@example.com' ) && false !== strpos( $html, 'shown@example.com' ), 'Consultation: the Requests inbox shows the email' );
$notify = new ReflectionMethod( 'SSC_Module_Notifications', 'build_text' );
$notify->setAccessible( true );
$text = $notify->invoke( new SSC_Module_Notifications(), $notify_row );
check( false !== strpos( $text, 'Email: shown@example.com' ), 'Consultation: the notification text carries the email' );

// The chat offers the form when the visitor clearly asks for a consultation.
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.61', '' );
SSC_Settings::update( array( 'ai_provider' => 'none', 'qa_mode' => 'bank_only' ) );
$offer = $engine->chat( 'می‌خوام مشاوره بگیرم', 'general', array() );
check( 'filter' === $offer['source'] && ! empty( $offer['flags']['consult_offer'] ) && '' !== $offer['reply'], 'Consultation: "I want a consultation" gets the offer and a flag for the widget' );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.62', '' );
$offer = $engine->chat( 'Can someone call me?', 'general', array() );
check( ! empty( $offer['flags']['consult_offer'] ), 'Consultation: an English request for a call back gets the offer too' );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.63', '' );
$plain = $engine->chat( 'هزینه مشاوره چقدر است؟', 'general', array() );
check( empty( $plain['flags']['consult_offer'] ) && 'filter' !== $plain['source'], 'Consultation: a question about the price of a consultation is not hijacked' );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.64', '' );
$plain = $engine->chat( 'what are your opening hours?', 'general', array() );
check( empty( $plain['flags']['consult_offer'] ), 'Consultation: an ordinary question is not offered a form' );
// With the module off there is no offer and no form.
update_option( SSC_Modules::OPTION, array() );
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.65', '' );
$off = $engine->chat( 'I want a consultation', 'general', array() );
check( empty( $off['flags']['consult_offer'] ), 'Consultation: with the module off the chat does not offer the form' );
$res = $leads_module->handle_submission( $valid, '203.0.113.60' );
check( is_wp_error( $res ), 'Consultation: with the module off a submission is refused' );
// An emergency in pharmaceutical mode is never answered with a consultation offer.
update_option( SSC_Modules::OPTION, array( 'leads', 'pharma' ) );
SSC_Modules::boot_active();
$engine = new SSC_Chat_Engine();
$engine->set_context( '203.0.113.66', '' );
$urgent = $engine->chat( 'I cannot breathe and I want a consultation', 'general', array() );
check( ! empty( $urgent['flags']['emergency'] ) && empty( $urgent['flags']['consult_offer'] ), 'Consultation: an emergency gets the emergency guidance, not the form offer' );

// The settings screen offers the choice.
update_option( SSC_Modules::OPTION, array( 'leads' ) );
list( $html, ) = ssc_test_screen( array( 'page' => 'ssc-settings' ), function () {
	( new SSC_Admin_Settings() )->render();
} );
check( false !== strpos( $html, 'name="consult_email"' ) && false !== strpos( $html, 'Do not ask for an email address' ), 'Consultation: Settings has the email field choice' );

// Put everything back.
if ( null === $consult_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $consult_before['modules'], false );
}
if ( null === $consult_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $consult_before['settings'] );
}
SSC_Settings::reset_cache();
