<?php
/**
 * WooCommerce sales assistant.
 *
 * - Product search: every question is matched against the live catalogue;
 *   matching products reach the model with their real price and stock, and
 *   the widget shows them as cards with an "Add to cart" button.
 * - Comparison / "which one should I buy?": answer rules for the model.
 * - Order tracking: order number + billing phone or email (or the logged-in
 *   owner), with status, items, the latest customer note and tracking code.
 * - Smart coupon: a single-use, time-limited coupon offered when a visitor
 *   hesitates or is about to leave, under a daily cap.
 * - Cart reminder by SMS: only for visitors who asked for it in the chat.
 *
 * Requires WooCommerce; every entry point checks for it.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Module_Woo
 */
class SSC_Module_Woo extends SSC_Module {

	const REMINDER_HOOK = 'ssc_woo_reminders';

	/**
	 * Products found for the current request (message => products).
	 *
	 * @var array
	 */
	protected static $found = array();

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'woocommerce';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'WooCommerce sales assistant', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Real prices and stock in answers, product cards with "Add to cart", order tracking, comparisons, a smart discount for hesitating visitors and SMS cart reminders.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Turns questions into orders and answers "where is my order?" without your team.', 'nexachat-ai' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'business';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
	}

	/**
	 * Needs WooCommerce.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured = WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return self::wc();
	}

	/**
	 * Is WooCommerce loaded?
	 *
	 * @return bool
	 */
	public static function wc() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Runtime hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( self::REMINDER_HOOK, array( __CLASS__, 'send_reminders' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		if ( ! self::wc() ) {
			return;
		}
		add_filter( 'ssc_prompt_context', array( __CLASS__, 'prompt_context' ), 10, 3 );
		add_filter( 'ssc_prompt_extra', array( __CLASS__, 'prompt_rules' ) );
		add_filter( 'ssc_chat_envelope', array( __CLASS__, 'envelope' ), 10, 4 );
		add_filter( 'ssc_frontend_config', array( __CLASS__, 'widget_config' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'order_placed' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'order_placed' ), 10, 1 );
	}

	/*
	 * --------------------------------------------------------------
	 * Product search.
	 * --------------------------------------------------------------
	 */

	/**
	 * Keywords of a question (stop words removed, Arabic letters unified).
	 *
	 * @param string $message Message.
	 * @return string[]
	 */
	public static function keywords( $message ) {
		// Arabic letter forms and the zero-width non-joiner written differently by keyboards.
		$text  = str_replace( array( 'ي', 'ك', 'ة', "\u{200c}" ), array( 'ی', 'ک', 'ه', ' ' ), mb_strtolower( (string) $message ) );
		$text  = strtr( $text, array_combine( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), range( 0, 9 ) ) );
		$words = preg_split( '/[^\p{L}\p{N}\-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$stop  = array_flip(
			array(
				'از',
				'به',
				'با',
				'در',
				'که',
				'را',
				'رو',
				'این',
				'آن',
				'اون',
				'و',
				'یا',
				'برای',
				'واسه',
				'چه',
				'چی',
				'چند',
				'چقدر',
				'کدام',
				'کدوم',
				'است',
				'هست',
				'هستش',
				'دارید',
				'دارین',
				'داری',
				'دارم',
				'میخوام',
				'میخواستم',
				'می',
				'خواهم',
				'قیمت',
				'خرید',
				'بخرم',
				'محصول',
				'لطفا',
				'لطفاً',
				'سلام',
				'من',
				'یک',
				'یه',
				'هم',
				'تا',
				'اگر',
				'آیا',
				'ایا',
				'موجود',
				'موجودی',
				'دارد',
				'داره',
				'بهتر',
				'بهترین',
				'کدومش',
				'شما',
				'مرسی',
				'ممنون',
				'the',
				'a',
				'an',
				'is',
				'are',
				'do',
				'does',
				'you',
				'have',
				'has',
				'price',
				'buy',
				'i',
				'want',
				'need',
				'what',
				'which',
				'how',
				'much',
				'for',
				'of',
				'to',
				'and',
				'or',
				'please',
				'hi',
				'hello',
				'in',
				'stock',
				'any',
				'can',
				'me',
				'my',
				'best',
				'better',
			)
		);
		$out   = array();
		foreach ( $words as $word ) {
			if ( mb_strlen( $word ) < 2 || isset( $stop[ $word ] ) ) {
				continue;
			}
			$out[ $word ] = true;
			if ( count( $out ) >= 6 ) {
				break;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Catalogue products matching a question, best first.
	 *
	 * @param string $message Message.
	 * @param int    $limit   Max products.
	 * @return WC_Product[]
	 */
	public static function find_products( $message, $limit = 4 ) {
		if ( ! self::wc() || 'yes' !== SSC_Settings::get( 'woo_product_search', 'yes' ) ) {
			return array();
		}
		$key = md5( $message . '|' . $limit );
		if ( isset( self::$found[ $key ] ) ) {
			return self::$found[ $key ];
		}
		$scores = array();
		foreach ( self::keywords( $message ) as $word ) {
			$query = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					's'                      => $word,
					'fields'                 => 'ids',
					'posts_per_page'         => 20,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $query->posts as $id ) {
				$scores[ $id ] = ( isset( $scores[ $id ] ) ? $scores[ $id ] : 0 ) + ( false !== mb_stripos( get_the_title( $id ), $word ) ? 3 : 1 );
			}
			if ( preg_match( '/[0-9a-z]/i', $word ) ) {
				$sku_id = wc_get_product_id_by_sku( $word );
				if ( $sku_id ) {
					$parent            = wp_get_post_parent_id( $sku_id );
					$sku_id            = $parent ? $parent : $sku_id;
					$scores[ $sku_id ] = ( isset( $scores[ $sku_id ] ) ? $scores[ $sku_id ] : 0 ) + 5;
				}
			}
		}
		arsort( $scores );
		$products = array();
		foreach ( array_keys( $scores ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$products[] = $product;
			if ( count( $products ) >= $limit ) {
				break;
			}
		}
		self::$found[ $key ] = $products;
		return $products;
	}

	/**
	 * Plain-text price ("185,000 تومان").
	 *
	 * @param string|float $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		if ( '' === (string) $amount ) {
			return '';
		}
		return trim( html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Stock wording for the model and the card.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function stock_text( $product ) {
		if ( ! $product->is_in_stock() ) {
			return __( 'Out of stock', 'nexachat-ai' );
		}
		if ( $product->is_on_backorder() ) {
			return __( 'Available on backorder', 'nexachat-ai' );
		}
		$qty = $product->get_stock_quantity();
		if ( $product->managing_stock() && null !== $qty && $qty <= 5 ) {
			/* translators: %d: units left. */
			return sprintf( _n( 'Only %d left', 'Only %d left', (int) $qty, 'nexachat-ai' ), (int) $qty );
		}
		return __( 'In stock', 'nexachat-ai' );
	}

	/**
	 * Live product data for the model.
	 *
	 * @param string $context    Existing context.
	 * @param string $message    Message.
	 * @param string $product_id Focused (plugin) product.
	 * @return string
	 */
	public static function prompt_context( $context, $message, $product_id = 'general' ) {
		$products = self::find_products( $message, (int) SSC_Settings::get( 'woo_cards', 4 ) );
		if ( ! $products ) {
			return $context;
		}
		$blocks = array();
		foreach ( $products as $product ) {
			$lines   = array();
			$lines[] = 'Price: ' . self::money( $product->get_price() ) . ( $product->is_on_sale() ? ' (regular ' . self::money( $product->get_regular_price() ) . ', on sale)' : '' );
			$lines[] = 'Stock: ' . self::stock_text( $product );
			if ( $product->get_sku() ) {
				$lines[] = 'SKU: ' . $product->get_sku();
			}
			foreach ( $product->get_attributes() as $attribute ) {
				if ( is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ) {
					$options = $attribute->is_taxonomy() ? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) ) : $attribute->get_options();
					$lines[] = wc_attribute_label( $attribute->get_name() ) . ': ' . implode( ', ', array_map( 'strval', (array) $options ) );
				}
			}
			$desc = trim( wp_strip_all_tags( (string) ( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ) ) );
			if ( '' !== $desc ) {
				$lines[] = 'About: ' . mb_substr( preg_replace( '/\s+/u', ' ', $desc ), 0, 400 );
			}
			$blocks[] = SSC_Prompt_Builder::fence( 'PRODUCT', $product->get_name(), implode( "\n", $lines ) );
		}
		return trim( $context . "\n\n" . implode( "\n\n", $blocks ) );
	}

	/**
	 * Sales rules for the model.
	 *
	 * @param string $extra Existing extra instructions.
	 * @return string
	 */
	public static function prompt_rules( $extra ) {
		if ( 'yes' !== SSC_Settings::get( 'woo_product_search', 'yes' ) ) {
			return $extra;
		}
		$rules = "\n\nSHOP RULES: You are also this online shop's sales assistant. PRODUCT blocks hold live catalogue data: "
			. 'quote prices, discounts and stock only from them and never invent products, prices or delivery times. '
			. 'If a product is out of stock, say so and suggest an in-stock alternative from the blocks. '
			. 'When the visitor is choosing between products, compare the relevant ones briefly (price, key attributes, stock) and recommend the one that fits their stated need; if the need is unclear, ask one short question first. '
			. 'Product cards with an "Add to cart" button appear under your answer, so do not paste product links. '
			. 'For questions about an existing order, ask for the order number: an order-tracking form is available.';
		return $extra . $rules;
	}

	/**
	 * Is the message about an existing order?
	 *
	 * @param string $message Message.
	 * @return bool
	 */
	public static function is_order_question( $message ) {
		$text = mb_strtolower( (string) $message );
		return (bool) preg_match( '/(سفارش|مرسوله|بسته|order|parcel|package)/u', $text )
			&& (bool) preg_match( '/(پیگیری|رهگیری|کجاست|کی می|نرسید|وضعیت|ارسال|track|where|status|arriv|shipped|deliver)/u', $text );
	}

	/**
	 * Add product cards and suggested actions to a reply.
	 *
	 * @param array           $out      Envelope.
	 * @param string          $source   Source.
	 * @param string          $question Visitor message.
	 * @param SSC_Chat_Engine $engine   Engine.
	 * @return array
	 */
	public static function envelope( $out, $source, $question = '', $engine = null ) {
		if ( 'live' === $source || '' === (string) $question ) {
			return $out;
		}
		if ( 'yes' === SSC_Settings::get( 'woo_order_tracking', 'yes' ) && self::is_order_question( $question ) ) {
			$out['actions'][] = 'track_order';
			return $out; // No product cards for an order question.
		}
		foreach ( self::find_products( $question, (int) SSC_Settings::get( 'woo_cards', 4 ) ) as $product ) {
			$out['cards'][] = self::card( $product );
		}
		return $out;
	}

	/**
	 * Card data for the widget.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function card( $product ) {
		$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		return array(
			'id'      => $product->get_id(),
			'name'    => $product->get_name(),
			'url'     => $product->get_permalink(),
			'image'   => $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'price'   => self::money( $product->get_price() ),
			'regular' => $product->is_on_sale() ? self::money( $product->get_regular_price() ) : '',
			'inStock' => $product->is_in_stock(),
			'stock'   => self::stock_text( $product ),
			// Simple products go straight to the cart; the rest need options on the product page.
			'addable' => $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock(),
		);
	}

	/*
	 * --------------------------------------------------------------
	 * Widget.
	 * --------------------------------------------------------------
	 */

	/**
	 * Widget configuration.
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function widget_config( $config ) {
		$coupon                            = 'yes' === SSC_Settings::get( 'woo_coupon_enabled', 'no' );
		$config['features']['woocommerce'] = true;
		$config['woo']                     = array(
			'tracking'   => 'yes' === SSC_Settings::get( 'woo_order_tracking', 'yes' ),
			'ajaxUrl'    => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
			'cartUrl'    => wc_get_cart_url(),
			'applyNonce' => $coupon ? wp_create_nonce( 'apply-coupon' ) : '',
			'coupon'     => $coupon ? array(
				'trigger' => (string) SSC_Settings::get( 'woo_coupon_trigger', 'both' ),
				'idle'    => (int) SSC_Settings::get( 'woo_coupon_idle', 40 ),
				'teaser'  => self::coupon_teaser(),
			) : null,
			'remind'     => 'yes' === SSC_Settings::get( 'woo_abandoned_enabled', 'no' ) && SSC_Modules::is_active( 'sms' ) && SSC_Module_Sms::ready(),
			'i18n'       => array(
				'addToCart'   => __( 'Add to cart', 'nexachat-ai' ),
				'added'       => __( 'Added ✓', 'nexachat-ai' ),
				'viewCart'    => __( 'View cart', 'nexachat-ai' ),
				'options'     => __( 'Choose options', 'nexachat-ai' ),
				'view'        => __( 'View', 'nexachat-ai' ),
				'trackOrder'  => __( 'Track my order', 'nexachat-ai' ),
				'orderNumber' => __( 'Order number', 'nexachat-ai' ),
				'contact'     => __( 'Phone or email used for the order', 'nexachat-ai' ),
				'check'       => __( 'Check', 'nexachat-ai' ),
				'status'      => __( 'Status', 'nexachat-ai' ),
				'date'        => __( 'Date', 'nexachat-ai' ),
				'total'       => __( 'Total', 'nexachat-ai' ),
				'items'       => __( 'Items', 'nexachat-ai' ),
				'note'        => __( 'Latest update', 'nexachat-ai' ),
				'tracking'    => __( 'Tracking code', 'nexachat-ai' ),
				'copy'        => __( 'Copy', 'nexachat-ai' ),
				'copied'      => __( 'Copied ✓', 'nexachat-ai' ),
				'getCoupon'   => __( 'Show my discount code', 'nexachat-ai' ),
				'apply'       => __( 'Apply to my cart', 'nexachat-ai' ),
				'applied'     => __( 'Applied to your cart ✓', 'nexachat-ai' ),
				'expires'     => __( 'Valid until', 'nexachat-ai' ),
				'remind'      => __( 'Remind me about my cart by SMS', 'nexachat-ai' ),
				'mobile'      => __( 'Mobile number', 'nexachat-ai' ),
				'remindOk'    => __( 'I agree to receive one SMS reminder about my cart.', 'nexachat-ai' ),
				'send'        => __( 'Send', 'nexachat-ai' ),
				'error'       => __( 'That did not work. Please try again.', 'nexachat-ai' ),
			),
		);
		return $config;
	}

	/**
	 * Coupon teaser line (the code itself is revealed only on click).
	 *
	 * @return string
	 */
	public static function coupon_teaser() {
		$text = trim( (string) SSC_Settings::get( 'woo_coupon_text', '' ) );
		if ( '' !== $text ) {
			return $text;
		}
		$amount = (int) SSC_Settings::get( 'woo_coupon_amount', 10 );
		return 'percent' === SSC_Settings::get( 'woo_coupon_type', 'percent' )
			/* translators: %d: percent. */
			? sprintf( __( 'Still deciding? Here is %d%% off your order today 🎁', 'nexachat-ai' ), $amount )
			/* translators: %s: amount. */
			: sprintf( __( 'Still deciding? Here is %s off your order today 🎁', 'nexachat-ai' ), self::money( $amount ) );
	}

	/*
	 * --------------------------------------------------------------
	 * REST.
	 * --------------------------------------------------------------
	 */

	/**
	 * Routes.
	 */
	public function routes() {
		foreach ( array( 'order', 'coupon', 'remind' ) as $route ) {
			register_rest_route(
				'ssc/v1',
				'/woo/' . $route,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'permission' ),
					'callback'            => array( __CLASS__, 'rest_' . $route ),
				)
			);
		}
	}

	/**
	 * Public routes: live assistant, WooCommerce present, small body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function permission( $request ) {
		if ( ! SSC_Setup::is_live() || ! self::wc() ) {
			return new WP_Error( 'ssc_not_available', __( 'This is not available right now.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		if ( strlen( $request->get_body() ) > 4096 ) {
			return new WP_Error( 'ssc_too_large', __( 'The request is too large.', 'nexachat-ai' ), array( 'status' => 413 ) );
		}
		return true;
	}

	/**
	 * Too many attempts from this address? (Counts failures only.)
	 *
	 * @param string $bucket Bucket.
	 * @param int    $max    Allowed failures per window.
	 * @param bool   $count  Record one failure.
	 * @return bool
	 */
	protected static function blocked( $bucket, $max, $count = false ) {
		$engine = new SSC_Chat_Engine();
		$key    = 'ssc_woo_' . $bucket . '_' . md5( $engine->client_ip() );
		$fails  = (int) get_transient( $key );
		if ( $count ) {
			set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
			return $fails + 1 > $max;
		}
		return $fails >= $max;
	}

	/**
	 * Order tracking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_order( $request ) {
		if ( 'yes' !== SSC_Settings::get( 'woo_order_tracking', 'yes' ) ) {
			return new WP_Error( 'ssc_not_available', __( 'This is not available right now.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		if ( self::blocked( 'order', 8 ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Too many attempts. Please try again in 15 minutes.', 'nexachat-ai' ), array( 'status' => 429 ) );
		}
		$number  = (int) preg_replace( '/\D/', '', SSC_Input::phone( (string) $request->get_param( 'order' ) ) );
		$contact = trim( SSC_Input::text( (string) $request->get_param( 'contact' ), 120 ) );
		$order   = $number ? wc_get_order( $number ) : false;
		if ( $order && ! is_a( $order, 'WC_Order' ) ) {
			$order = false; // Refunds and other order types are not trackable here.
		}
		if ( ! $order || ! self::owns_order( $order, $contact ) ) {
			self::blocked( 'order', 8, true );
			// One answer for "no such order" and "details do not match": no enumeration.
			return new WP_Error( 'ssc_order_not_found', __( 'No order matches these details. Check the order number and the phone or email you used.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::order_payload( $order ) );
	}

	/**
	 * Does the visitor own the order (logged-in owner, billing phone or email)?
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $contact Phone or email given.
	 * @return bool
	 */
	public static function owns_order( $order, $contact ) {
		if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
			return true;
		}
		$contact = trim( (string) $contact );
		if ( '' === $contact ) {
			return false;
		}
		if ( is_email( $contact ) ) {
			return '' !== $order->get_billing_email() && 0 === strcasecmp( $order->get_billing_email(), $contact );
		}
		$given  = substr( preg_replace( '/\D/', '', SSC_Input::phone( $contact ) ), -10 );
		$billed = substr( preg_replace( '/\D/', '', SSC_Input::phone( (string) $order->get_billing_phone() ) ), -10 );
		return strlen( $given ) >= 8 && hash_equals( $billed, $given );
	}

	/**
	 * What the customer may see about their order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_payload( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name' => $item->get_name(),
				'qty'  => (int) $item->get_quantity(),
			);
		}
		$note  = '';
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'type'     => 'customer',
				'limit'    => 1,
			)
		);
		if ( $notes ) {
			$note = wp_strip_all_tags( (string) $notes[0]->content );
		}
		$created = $order->get_date_created();
		return array(
			'number'   => $order->get_order_number(),
			'status'   => wc_get_order_status_name( $order->get_status() ),
			'date'     => $created ? SSC_Date::display( $created->date( 'Y-m-d H:i:s' ), false ) : '',
			'total'    => self::money( $order->get_total() ),
			'items'    => $items,
			'note'     => $note,
			'tracking' => self::tracking_code( $order ),
			'url'      => is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ? $order->get_view_order_url() : '',
		);
	}

	/**
	 * Shipment tracking code from common plugins' order meta.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function tracking_code( $order ) {
		/**
		 * Order meta keys that may hold a tracking code (first non-empty wins).
		 *
		 * @param string[] $keys Keys.
		 */
		$keys = apply_filters( 'ssc_woo_tracking_meta_keys', array( '_tracking_code', 'tracking_code', '_post_tracking_code', 'post_tracking_code', '_shipping_tracking_code', '_tracking_number', '_wc_shipment_tracking_items' ) );
		foreach ( $keys as $key ) {
			$value = $order->get_meta( $key );
			if ( is_array( $value ) ) {
				// WooCommerce Shipment Tracking: a list of shipments.
				$last  = end( $value );
				$value = is_array( $last ) && isset( $last['tracking_number'] ) ? $last['tracking_number'] : '';
			}
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		return '';
	}

	/**
	 * Smart coupon: created only when the visitor clicks the offer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_coupon( $request ) {
		if ( 'yes' !== SSC_Settings::get( 'woo_coupon_enabled', 'no' ) ) {
			return new WP_Error( 'ssc_not_available', __( 'This offer has ended.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		$engine  = new SSC_Chat_Engine();
		$visitor = 'ssc_woo_cpn_' . md5( $engine->client_ip() );
		$given   = get_transient( $visitor );
		if ( is_array( $given ) ) {
			return rest_ensure_response( $given ); // Same visitor, same code: no farming.
		}
		$day   = 'ssc_woo_cpn_day_' . gmdate( 'Ymd' );
		$count = (int) get_option( $day, 0 );
		if ( $count >= (int) SSC_Settings::get( 'woo_coupon_daily', 20 ) ) {
			return new WP_Error( 'ssc_coupon_cap', __( 'Today\'s offers have all been claimed. Please check back tomorrow.', 'nexachat-ai' ), array( 'status' => 409 ) );
		}
		$min = (int) SSC_Settings::get( 'woo_coupon_min', 0 );
		if ( $min > 0 ) {
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
			$subtotal = WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;
			if ( $subtotal < $min ) {
				/* translators: %s: minimum cart total. */
				return new WP_Error( 'ssc_coupon_min', sprintf( __( 'This offer is for carts of %s or more.', 'nexachat-ai' ), self::money( $min ) ), array( 'status' => 409 ) );
			}
		}
		$hours  = (int) SSC_Settings::get( 'woo_coupon_hours', 24 );
		$code   = 'NX' . strtoupper( wp_generate_password( 6, false, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' === SSC_Settings::get( 'woo_coupon_type', 'percent' ) ? 'fixed_cart' : 'percent' );
		$coupon->set_amount( (float) SSC_Settings::get( 'woo_coupon_amount', 10 ) );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( time() + $hours * HOUR_IN_SECONDS );
		if ( $min > 0 ) {
			$coupon->set_minimum_amount( $min );
		}
		$coupon->set_description( __( 'Smart offer from the NexaChatAI assistant', 'nexachat-ai' ) );
		$coupon->save();
		update_option( $day, $count + 1, false );
		$payload = array(
			'code'    => $code,
			'text'    => self::coupon_teaser(),
			'expires' => SSC_Date::display( wp_date( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ) ),
		);
		set_transient( $visitor, $payload, $hours * HOUR_IN_SECONDS );
		return rest_ensure_response( $payload );
	}

	/**
	 * Cart reminder request (explicit consent required).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_remind( $request ) {
		if ( 'yes' !== SSC_Settings::get( 'woo_abandoned_enabled', 'no' ) || ! SSC_Modules::is_active( 'sms' ) ) {
			return new WP_Error( 'ssc_not_available', __( 'This is not available right now.', 'nexachat-ai' ), array( 'status' => 404 ) );
		}
		if ( ! SSC_Input::consent( $request->get_param( 'consent' ) ) ) {
			return new WP_Error( 'ssc_consent', __( 'Please confirm that you want the reminder.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		if ( self::blocked( 'remind', 5 ) ) {
			return new WP_Error( 'ssc_rate_limited', __( 'Too many attempts. Please try again in 15 minutes.', 'nexachat-ai' ), array( 'status' => 429 ) );
		}
		$phone = SSC_Module_Sms::normalize( (string) $request->get_param( 'phone' ) );
		if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
			self::blocked( 'remind', 5, true );
			return new WP_Error( 'ssc_phone', __( 'Please enter a valid mobile number (09…).', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return new WP_Error( 'ssc_cart_empty', __( 'Your cart is empty, so there is nothing to remind you about.', 'nexachat-ai' ), array( 'status' => 400 ) );
		}
		$items = array();
		foreach ( $cart->get_cart() as $line ) {
			if ( isset( $line['data'] ) && is_object( $line['data'] ) ) {
				$items[] = $line['data']->get_name() . ' × ' . (int) $line['quantity'];
			}
		}
		self::save_reminder( $phone, WC()->session ? (string) WC()->session->get_customer_id() : '', $items, self::money( $cart->get_total( 'edit' ) ) );
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Done. If you do not finish your order, we will send you one reminder by SMS.', 'nexachat-ai' ),
			)
		);
	}

	/**
	 * Store (or refresh) the one pending reminder for a phone.
	 *
	 * @param string   $phone   Mobile.
	 * @param string   $session WooCommerce customer/session id.
	 * @param string[] $items   Cart lines.
	 * @param string   $total   Cart total.
	 */
	public static function save_reminder( $phone, $session, $items, $total ) {
		global $wpdb;
		$table = SSC_Schema::live_table( 'carts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- keyed read.
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE phone = %s AND status = 'pending'", $phone ) );
		$data     = array(
			'phone'       => $phone,
			'session_key' => substr( $session, 0, 64 ),
			'cart'        => wp_json_encode( array_slice( $items, 0, 20 ) ),
			'total'       => substr( $total, 0, 40 ),
			'status'      => 'pending',
			'created_at'  => current_time( 'mysql' ),
		);
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- update by key.
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insert.
			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * An order was placed: no reminder for that customer.
	 *
	 * @param int|WC_Order $order Order or id.
	 */
	public static function order_placed( $order ) {
		$order = is_object( $order ) ? $order : wc_get_order( $order );
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		global $wpdb;
		$table   = SSC_Schema::live_table( 'carts' );
		$phone   = SSC_Module_Sms::normalize( (string) $order->get_billing_phone() );
		$session = WC()->session ? (string) WC()->session->get_customer_id() : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- small keyed update.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'ordered' WHERE status = 'pending' AND ( ( phone <> '' AND phone = %s ) OR ( session_key <> '' AND session_key = %s ) )", $phone, $session ) );
	}

	/**
	 * Hourly job while cart reminders are on.
	 */
	public static function schedule() {
		$want = SSC_Modules::is_active( 'woocommerce' ) && 'yes' === SSC_Settings::get( 'woo_abandoned_enabled', 'no' );
		$next = wp_next_scheduled( self::REMINDER_HOOK );
		if ( $want && ! $next ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::REMINDER_HOOK );
		} elseif ( ! $want && $next ) {
			wp_clear_scheduled_hook( self::REMINDER_HOOK );
		}
	}

	/**
	 * Send due reminders (one per request, never twice a week to a number).
	 *
	 * @return int Reminders sent.
	 */
	public static function send_reminders() {
		if ( ! SSC_Modules::is_active( 'woocommerce' ) || 'yes' !== SSC_Settings::get( 'woo_abandoned_enabled', 'no' ) || ! SSC_Modules::is_active( 'sms' ) ) {
			return 0;
		}
		global $wpdb;
		$table = SSC_Schema::live_table( 'carts' );
		$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local-time columns.
		$due   = gmdate( 'Y-m-d H:i:s', $now - (int) SSC_Settings::get( 'woo_abandoned_hours', 2 ) * HOUR_IN_SECONDS );
		$week  = gmdate( 'Y-m-d H:i:s', $now - WEEK_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- bounded job.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND created_at < %s", $week ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' AND created_at <= %s ORDER BY id ASC LIMIT 50", $due ), ARRAY_A );
		$sent = 0;
		foreach ( (array) $rows as $row ) {
			$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE phone = %s AND status = 'sent' AND sent_at >= %s", $row['phone'], $week ) );
			if ( $recent ) {
				$wpdb->update( $table, array( 'status' => 'skipped' ), array( 'id' => (int) $row['id'] ) );
				continue;
			}
			$ok = SSC_Module_Sms::send( $row['phone'], self::reminder_text( $row ) );
			$wpdb->update(
				$table,
				array(
					'status'  => true === $ok ? 'sent' : 'failed',
					'sent_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row['id'] )
			);
			$sent += true === $ok ? 1 : 0;
		}
		// phpcs:enable
		return $sent;
	}

	/**
	 * Reminder text ({site}, {items}, {total}, {cart_url}).
	 *
	 * @param array $row Reminder row.
	 * @return string
	 */
	public static function reminder_text( $row ) {
		$items    = json_decode( (string) $row['cart'], true );
		$template = trim( (string) SSC_Settings::get( 'woo_abandoned_text', '' ) );
		if ( '' === $template ) {
			$template = SSC_I18n::in_widget_locale(
				function () {
					return __( 'Your cart at {site} is waiting: {items}. Finish your order here: {cart_url}', 'nexachat-ai' );
				}
			);
		}
		return strtr(
			$template,
			array(
				'{site}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{items}'    => implode( '، ', array_slice( is_array( $items ) ? $items : array(), 0, 3 ) ),
				'{total}'    => (string) $row['total'],
				'{cart_url}' => self::wc() ? wc_get_cart_url() : home_url( '/' ),
			)
		);
	}
}
