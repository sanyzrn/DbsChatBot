<?php
/**
 * WooCommerce sales assistant + SMS module. The SMS request formats and the
 * keyword/intent logic run everywhere; catalogue, order and coupon checks run
 * only when WooCommerce is loaded on the test site.
 * Required from tests/integration.php (check() and a disposable site).
 */

global $wpdb;
SSC_I18n::restore(); // An earlier REST request in this process may have switched to the widget language.
$modules_before  = get_option( SSC_Modules::OPTION );
$settings_before = get_option( SSC_Settings::OPTION_KEY );

// Keywords and intent.
check( array( 'ویتامین', 'd3' ) === SSC_Module_Woo::keywords( 'قیمت ويتامين D3 چقدر است؟' ), 'Product keywords drop stop words and unify Arabic letters' );
check( SSC_Module_Woo::is_order_question( 'سفارشم کجاست؟' ) && SSC_Module_Woo::is_order_question( 'Where is my order?' ) && ! SSC_Module_Woo::is_order_question( 'قیمت امگا ۳' ), 'Order questions are recognised' );

// SMS: numbers and every provider's request format (mocked).
check( '09121234567' === SSC_Module_Sms::normalize( '+98 912 123 4567' ) && '09121234567' === SSC_Module_Sms::normalize( '۰۹۱۲۱۲۳۴۵۶۷' ) && '' === SSC_Module_Sms::normalize( '12' ), 'Mobile numbers are normalised to 09…' );
SSC_Modules::activate( 'sms' );
SSC_Settings::set_secret( 'sms_api_key', 'KEY123' );
remove_all_filters( 'ssc_sms_pre_send' ); // A site's own SMS short-circuit must not hide the real request format.
$sms_request = null;
$sms_reply   = '';
$mock_sms    = function ( $pre, $args, $url ) use ( &$sms_request, &$sms_reply ) {
	if ( ! preg_match( '#^https://(api\.kavenegar\.com|rest\.payamak-panel\.com|api2\.ippanel\.com|api\.sms\.ir)/#', $url ) ) {
		return $pre;
	}
	$sms_request = array( 'url' => $url, 'args' => $args );
	return array( 'headers' => array(), 'body' => $sms_reply, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $mock_sms, 30, 3 );
$cases = array(
	'kavenegar'   => array( '{"return":{"status":200,"message":"ok"},"entries":[]}', '{"return":{"status":418,"message":"اعتبار کافی نیست"}}', 'https://api.kavenegar.com/v1/KEY123/sms/send.json' ),
	'melipayamak' => array( '{"Value":"1","RetStatus":1,"StrRetStatus":"Ok"}', '{"Value":"11","RetStatus":0,"StrRetStatus":"InvalidData"}', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS' ),
	'ippanel'     => array( '{"status":"OK","code":200,"data":{"message_id":1}}', '{"status":"ERROR","code":422,"error_message":"sender invalid"}', 'https://api2.ippanel.com/api/v1/sms/send/webservice/single' ),
	'smsir'       => array( '{"status":1,"message":"موفق","data":{"packId":"x"}}', '{"status":0,"message":"خط نامعتبر"}', 'https://api.sms.ir/v1/send/bulk' ),
);
foreach ( $cases as $provider => $case ) {
	SSC_Settings::update( array( 'sms_provider' => $provider, 'sms_sender' => '30001234', 'sms_username' => 'user1' ) );
	$sms_reply = $case[0];
	$ok        = SSC_Module_Sms::send( '+989121234567', 'سلام' );
	$sent_body = is_array( $sms_request['args']['body'] ) ? $sms_request['args']['body'] : json_decode( $sms_request['args']['body'], true );
	$to_ok     = in_array( '09121234567', array_map( 'strval', array_merge( (array) ( $sent_body['receptor'] ?? array() ), (array) ( $sent_body['to'] ?? array() ), (array) ( $sent_body['recipient'] ?? array() ), (array) ( $sent_body['mobiles'] ?? array() ) ) ), true );
	check( true === $ok && $case[2] === $sms_request['url'] && $to_ok, "SMS via {$provider}: correct endpoint and recipient" );
	$sms_reply = $case[1];
	$error     = SSC_Module_Sms::send( '09121234567', 'سلام' );
	check( is_wp_error( $error ) && 'ssc_sms_rejected' === $error->get_error_code(), "SMS via {$provider}: a refusal is reported with the panel's reason" );
}
check( 'KEY123' === $sms_request['args']['headers']['X-API-KEY'], 'SMS.ir sends the key in its header' );
remove_filter( 'pre_http_request', $mock_sms, 30 );

if ( class_exists( 'WooCommerce' ) ) {
	SSC_Modules::activate( 'woocommerce' );
	$woo = SSC_Modules::get( 'woocommerce' );
	$make = function ( $name, $price, $stock ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name );
		$p->set_regular_price( (string) $price );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $stock );
		$p->set_status( 'publish' );
		return $p->save();
	};
	$tag   = 'zq' . wp_rand( 1000, 9999 ); // Unique word so only our products match.
	$in    = $make( "کرم مرطوب کننده {$tag} صورت", 240000, 3 );
	$out   = $make( "کرم ضد آفتاب {$tag}", 310000, 0 );
	$found = array_map(
		function ( $p ) {
			return $p->get_id();
		},
		SSC_Module_Woo::find_products( "کرم {$tag} دارید؟" )
	);
	check( in_array( $in, $found, true ) && in_array( $out, $found, true ), 'Catalogue search finds products from a Persian question' );
	$context = SSC_Module_Woo::prompt_context( '', "کرم {$tag} دارید؟" );
	check( false !== strpos( $context, '240,000' ) && false !== strpos( $context, 'Only 3 left' ) && false !== strpos( $context, 'Out of stock' ), 'The model gets live price and stock' );
	$envelope = SSC_Module_Woo::envelope( array( 'cards' => array(), 'actions' => array() ), 'ai', "کرم {$tag} دارید؟" );
	$cards    = array_column( $envelope['cards'], 'addable', 'id' );
	check( true === $cards[ $in ] && false === $cards[ $out ], 'Cards offer "Add to cart" only for in-stock simple products' );
	$order_env = SSC_Module_Woo::envelope( array( 'cards' => array(), 'actions' => array() ), 'ai', 'سفارشم کجاست؟' );
	check( array( 'track_order' ) === $order_env['actions'] && ! $order_env['cards'], 'Order questions get the tracking form instead of cards' );

	// Order tracking: owner by phone or email; one answer for missing and mismatched.
	$order = wc_create_order();
	$order->add_product( wc_get_product( $in ), 1 );
	$order->set_billing_phone( '09121112233' );
	$order->set_billing_email( 'buyer@example.test' );
	$order->save();
	$order->update_meta_data( '_tracking_code', 'TRK-777' );
	$order->save();
	check( SSC_Module_Woo::owns_order( $order, '+98 912 111 2233' ) && SSC_Module_Woo::owns_order( $order, 'BUYER@example.test' ) && ! SSC_Module_Woo::owns_order( $order, '09120000000' ) && ! SSC_Module_Woo::owns_order( $order, '' ), 'Order ownership: billing phone or email' );
	$ask = function ( $number, $contact ) {
		$r = new WP_REST_Request( 'POST', '/ssc/v1/woo/order' );
		$r->set_param( 'order', $number );
		$r->set_param( 'contact', $contact );
		return SSC_Module_Woo::rest_order( $r );
	};
	delete_transient( 'ssc_woo_order_' . md5( ( new SSC_Chat_Engine() )->client_ip() ) );
	$good    = $ask( (string) $order->get_id(), '09121112233' );
	$wrong   = $ask( (string) $order->get_id(), '09120000000' );
	$missing = $ask( '99999999', '09121112233' );
	check( ! is_wp_error( $good ) && 'TRK-777' === $good->get_data()['tracking'] && 1 === $good->get_data()['items'][0]['qty'], 'Order tracking returns status, items and tracking code' );
	check( is_wp_error( $wrong ) && is_wp_error( $missing ) && $wrong->get_error_message() === $missing->get_error_message(), 'Wrong details and unknown orders get the same answer' );

	// Smart coupon: created once per visitor, single-use, daily cap.
	SSC_Settings::update( array( 'woo_coupon_enabled' => 'yes', 'woo_coupon_amount' => 12, 'woo_coupon_type' => 'percent', 'woo_coupon_daily' => 5, 'woo_coupon_min' => 0 ) );
	delete_transient( 'ssc_woo_cpn_' . md5( ( new SSC_Chat_Engine() )->client_ip() ) );
	delete_option( 'ssc_woo_cpn_day_' . gmdate( 'Ymd' ) );
	$first  = SSC_Module_Woo::rest_coupon( new WP_REST_Request( 'POST', '/ssc/v1/woo/coupon' ) )->get_data();
	$again  = SSC_Module_Woo::rest_coupon( new WP_REST_Request( 'POST', '/ssc/v1/woo/coupon' ) )->get_data();
	$coupon = new WC_Coupon( $first['code'] );
	check( $first['code'] === $again['code'] && 1 === (int) $coupon->get_usage_limit() && 12.0 === (float) $coupon->get_amount() && $coupon->get_date_expires(), 'One single-use, expiring coupon per visitor' );
	delete_transient( 'ssc_woo_cpn_' . md5( ( new SSC_Chat_Engine() )->client_ip() ) );
	update_option( 'ssc_woo_cpn_day_' . gmdate( 'Ymd' ), 5 );
	check( is_wp_error( SSC_Module_Woo::rest_coupon( new WP_REST_Request( 'POST', '/ssc/v1/woo/coupon' ) ) ), 'The daily coupon cap is enforced' );
	delete_option( 'ssc_woo_cpn_day_' . gmdate( 'Ymd' ) );
	wp_delete_post( $coupon->get_id(), true );

	// Cart reminder: stored, sent once after the delay, cancelled by an order.
	$carts = SSC_Schema::live_table( 'carts' );
	SSC_Settings::update( array( 'woo_abandoned_enabled' => 'yes', 'woo_abandoned_hours' => 1, 'woo_abandoned_text' => 'سبد {site}: {items}' ) );
	$sms_sent = array();
	$capture  = function ( $pre, $to, $text ) use ( &$sms_sent ) {
		$sms_sent[] = array( $to, $text );
		return true;
	};
	add_filter( 'ssc_sms_pre_send', $capture, 10, 3 );
	SSC_Module_Woo::save_reminder( '09127778899', 'sess-test', array( 'کرم × 1' ), '240,000' );
	$wpdb->query( $wpdb->prepare( "UPDATE {$carts} SET created_at = %s WHERE phone = '09127778899'", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 2 * HOUR_IN_SECONDS ) ) );
	SSC_Module_Woo::send_reminders();
	SSC_Module_Woo::send_reminders();
	check( 1 === count( $sms_sent ) && '09127778899' === $sms_sent[0][0] && false !== strpos( $sms_sent[0][1], 'کرم × 1' ), 'A due cart reminder is sent once, with the cart items' );
	SSC_Module_Woo::save_reminder( '09126665544', '', array( 'x' ), '1' );
	$buyer = wc_create_order();
	$buyer->set_billing_phone( '0912 666 5544' );
	$buyer->save();
	SSC_Module_Woo::order_placed( $buyer );
	check( 'ordered' === $wpdb->get_var( "SELECT status FROM {$carts} WHERE phone = '09126665544'" ), 'Placing an order cancels the pending reminder' );
	remove_filter( 'ssc_sms_pre_send', $capture, 10 );
	$wpdb->query( "DELETE FROM {$carts} WHERE phone IN ('09127778899','09126665544')" );
	foreach ( array( $order, $buyer ) as $o ) {
		$o->delete( true );
	}
	wp_delete_post( $in, true );
	wp_delete_post( $out, true );
} else {
	echo "SKIP: WooCommerce is not installed on the test site (catalogue, order and coupon checks)\n";
}

SSC_Settings::set_secret( 'sms_api_key', '' );
update_option( SSC_Modules::OPTION, $modules_before, false );
update_option( SSC_Settings::OPTION_KEY, $settings_before );
