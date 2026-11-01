<?php
/**
 * Regression tests for the confirmed points of the fourth review (1.7.2, an agent's report):
 * WooCommerce order-storage (HPOS) compatibility is declared, and a live chat is offered only when
 * both the working hours and an operator allow it.
 * Required from tests/integration.php (check() and a disposable site).
 */

$audit4_before = array(
	'modules'  => get_option( SSC_Modules::OPTION, null ),
	'settings' => get_option( SSC_Settings::OPTION_KEY, null ),
	'seen'     => get_user_meta( 1, 'ssc_live_seen', true ),
);

// HPOS: the plugin tells WooCommerce it works with high-performance order storage. A stand-in for WooCommerce's
// FeaturesUtil records the declaration (when real WooCommerce is loaded it makes the declaration itself).
if ( ! class_exists( 'WooCommerce' ) ) {
	if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		eval( 'namespace Automattic\WooCommerce\Utilities; class FeaturesUtil { public static $calls = array(); public static function declare_compatibility( $feature, $file, $positive = true ) { self::$calls[] = array( $feature, $file, $positive ); return true; } }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a test double for a class that only exists with WooCommerce.
	}
	do_action( 'before_woocommerce_init' );
	$declared = \Automattic\WooCommerce\Utilities\FeaturesUtil::$calls;
	check( array( array( 'custom_order_tables', VAZHO_MAIN_FILE, true ) ) === $declared, 'HPOS: the plugin declares compatibility with custom order tables, for its own main file' );
	check( 1 === preg_match( '/\*\s+Plugin Name:/', (string) file_get_contents( VAZHO_MAIN_FILE ) ), 'HPOS: the declared file is the plugin file WooCommerce maps to the plugin' );
} else {
	check( true, 'HPOS: real WooCommerce is loaded and receives the declaration itself (skipped)' );
}

// F-5 of that review: hours x operator. Nobody is offered a live chat outside the working hours, even with an inbox open.
update_option( SSC_Modules::OPTION, array( 'live' ) );
SSC_Messenger::unlink( 1 );
SSC_Settings::update( array( 'notify_chat_id' => '', 'business_timezone' => 'UTC' ) );
$today  = (int) gmdate( 'N' );
$other  = ( $today % 7 ) + 1;
$open   = array( 'business_hours_enabled' => 'yes', 'business_hours_days' => '1,2,3,4,5,6,7', 'business_hours_start' => '00:00', 'business_hours_end' => '00:00' );
$closed = array( 'business_hours_enabled' => 'yes', 'business_hours_days' => (string) $other, 'business_hours_start' => '00:00', 'business_hours_end' => '00:00' );
$no_schedule = array( 'business_hours_enabled' => 'no' );
$inbox_open  = function ( $on ) {
	if ( $on ) {
		update_user_meta( 1, 'ssc_live_seen', time() );
	} else {
		delete_user_meta( 1, 'ssc_live_seen' );
	}
	delete_user_meta( 1, 'ssc_live_available' );
};
foreach ( array(
	array( $no_schedule, true, true, 'no schedule, an inbox is open' ),
	array( $open, true, true, 'inside the working hours, an inbox is open' ),
	array( $closed, true, false, 'outside the working hours, an inbox is open' ),
	array( $open, false, false, 'inside the working hours, nobody has an inbox open' ),
	array( $no_schedule, false, false, 'no schedule, nobody has an inbox open' ),
) as $case ) {
	SSC_Settings::update( $case[0] );
	$inbox_open( $case[1] );
	check( $case[2] === SSC_Module_Live::anyone_available(), 'Live chat: ' . $case[3] . ' -> ' . ( $case[2] ? 'a person is offered' : 'nobody is offered' ) );
}
SSC_Settings::update( $closed );
$inbox_open( true );
check( ! SSC_Availability::is_online() && true === (bool) SSC_Module_Live::reachable_operators(), 'Live chat: the closed case really is closed while an operator is reachable (the case that matters)' );
$inbox_open( false );
check( false === SSC_Module_Live::anyone_available(), 'Live chat: outside the working hours and with nobody there, nobody is offered either' );

// Put everything back.
if ( '' === (string) $audit4_before['seen'] ) {
	delete_user_meta( 1, 'ssc_live_seen' );
} else {
	update_user_meta( 1, 'ssc_live_seen', $audit4_before['seen'] );
}
if ( null === $audit4_before['modules'] ) {
	delete_option( SSC_Modules::OPTION );
} else {
	update_option( SSC_Modules::OPTION, $audit4_before['modules'], false );
}
if ( null === $audit4_before['settings'] ) {
	delete_option( SSC_Settings::OPTION_KEY );
} else {
	update_option( SSC_Settings::OPTION_KEY, $audit4_before['settings'] );
}
SSC_Settings::reset_cache();
