<?php
/**
 * REST API افزونه.
 *
 * چرا REST به‌جای admin-ajax:
 *  - سبک‌تر و سریع‌تر (admin-ajax کل بار ادمین را بالا می‌آورد).
 *  - قابل تست و قابل مصرف توسط کلاینت‌های دیگر (اپ موبایل، یکپارچه‌سازی).
 *  - مسیر طبیعی برای پاسخ‌های استریم.
 *
 * منطق واقعی در SSC_Chatbot_Ajax به‌صورت متدهای سرویس پیاده شده و اینجا فقط
 * پوستهٔ REST است؛ بنابراین AJAX قدیمی و REST دقیقاً یک رفتار دارند.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس REST.
 */
class SSC_Chatbot_REST {

	/**
	 * فضای‌نام REST.
	 */
	const NS = 'ssc/v1';

	/**
	 * نمونهٔ سرویس (همان کلاس AJAX که متدهای سرویس را دارد).
	 *
	 * @var SSC_Chatbot_Ajax
	 */
	protected $service;

	/**
	 * سازنده.
	 *
	 * @param SSC_Chatbot_Ajax $service سرویس.
	 */
	public function __construct( $service ) {
		$this->service = $service;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * بررسی دسترسی: nonce استاندارد REST وردپرس.
	 * نقطه‌های عمومی‌اند (مهمان هم می‌تواند چت کند) اما nonce از CSRF جلوگیری می‌کند.
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return true|WP_Error
	 */
	public function check_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'ssc_bad_nonce',
				__( 'نشست شما منقضی شده است. لطفاً صفحه را تازه‌سازی کنید.', 'smart-support-chatbot' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * ثبت مسیرها.
	 */
	public function register_routes() {
		$public = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'check_nonce' ),
		);

		register_rest_route(
			self::NS,
			'/chat',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'chat' ),
					'args'     => array(
						'message' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'history' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/feedback',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'feedback' ),
					'args'     => array(
						'log_id'    => array(
							'required' => true,
							'type'     => 'integer',
						),
						'rating'    => array(
							'required' => true,
							'type'     => 'integer',
						),
						'log_token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/suggest',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'suggest' ),
					'args'     => array(
						'term'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'product' => array(
							'type'              => 'string',
							'default'           => 'general',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/csat',
			array_merge(
				$public,
				array(
					'callback' => array( $this, 'csat' ),
					'args'     => array(
						'score' => array(
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 5,
						),
					),
				)
			)
		);

		register_rest_route(
			self::NS,
			'/submit',
			array_merge(
				$public,
				array( 'callback' => array( $this, 'submit' ) )
			)
		);
	}

	/**
	 * تبدیل خروجی سرویس به پاسخ REST.
	 * شکل پاسخ عمداً با AJAX یکسان نگه داشته شده ({success, data}) تا کلاینت مشترک بماند.
	 *
	 * @param array|WP_Error $res نتیجهٔ سرویس.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function respond( $res ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $res,
			),
			200
		);
	}

	/**
	 * شناسهٔ نشست کلاینت را به سرویس می‌دهد (برای محدودسازی نرخ per-session).
	 *
	 * @param WP_REST_Request $request درخواست.
	 */
	protected function bridge_cid( $request ) {
		$cid = $request->get_param( 'cid' );
		if ( $cid ) {
			$this->service->set_client_id( sanitize_text_field( $cid ) );
		}
	}

	/**
	 * POST /chat
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat( $request ) {
		$this->bridge_cid( $request );
		return $this->respond(
			$this->service->chat(
				array(
					'message' => $request->get_param( 'message' ),
					'product' => $request->get_param( 'product' ),
					'history' => $request->get_param( 'history' ),
				)
			)
		);
	}

	/**
	 * POST /feedback
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public function feedback( $request ) {
		return $this->respond(
			$this->service->feedback(
				array(
					'log_id'    => $request->get_param( 'log_id' ),
					'rating'    => $request->get_param( 'rating' ),
					'log_token' => $request->get_param( 'log_token' ),
				)
			)
		);
	}

	/**
	 * POST /suggest
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public function suggest( $request ) {
		$this->bridge_cid( $request );
		return $this->respond(
			$this->service->suggest(
				array(
					'term'    => $request->get_param( 'term' ),
					'product' => $request->get_param( 'product' ),
				)
			)
		);
	}

	/**
	 * POST /csat
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public function csat( $request ) {
		$this->bridge_cid( $request );
		return $this->respond( $this->service->csat( array( 'score' => $request->get_param( 'score' ) ) ) );
	}

	/**
	 * POST /submit
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( $request ) {
		$this->bridge_cid( $request );
		$keys = array(
			'type',
			'name',
			'phone',
			'description',
			'product',
			'severity',
			'outcome',
			'batch_number',
			'concomitant_drugs',
			'reporter_type',
			'nfx_hp',
			'extra',
		);
		// نبود این مقدار نباید تلهٔ زمانیِ ضداسپم را فعال کند (مثل مسیر AJAX).
		$elapsed = $request->get_param( 'nfx_elapsed' );
		$args    = array( 'nfx_elapsed' => ( null === $elapsed || '' === $elapsed ) ? 99999 : (int) $elapsed );
		foreach ( $keys as $k ) {
			$args[ $k ] = (string) $request->get_param( $k );
		}
		return $this->respond( $this->service->submit( $args ) );
	}
}
