<?php
/**
 * هندلرهای AJAX برای چت و ثبت فرم.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس AJAX.
 */
class SSC_Chatbot_Ajax {

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public function __construct() {
		// چت (برای کاربران لاگین‌شده و مهمان).
		add_action( 'wp_ajax_ssc_chatbot_chat', array( $this, 'handle_chat' ) );
		add_action( 'wp_ajax_nopriv_ssc_chatbot_chat', array( $this, 'handle_chat' ) );

		// ثبت فرم.
		add_action( 'wp_ajax_ssc_chatbot_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_ssc_chatbot_submit', array( $this, 'handle_submit' ) );

		// بازخورد پاسخ (👍/👎).
		add_action( 'wp_ajax_ssc_chatbot_feedback', array( $this, 'handle_feedback' ) );
		add_action( 'wp_ajax_nopriv_ssc_chatbot_feedback', array( $this, 'handle_feedback' ) );

		// تکمیل خودکار سوال از بانک (هنگام تایپ).
		add_action( 'wp_ajax_ssc_chatbot_suggest', array( $this, 'handle_suggest' ) );
		add_action( 'wp_ajax_nopriv_ssc_chatbot_suggest', array( $this, 'handle_suggest' ) );

		// نظرسنجی رضایت پایان گفتگو (CSAT).
		add_action( 'wp_ajax_ssc_chatbot_csat', array( $this, 'handle_csat' ) );
		add_action( 'wp_ajax_nopriv_ssc_chatbot_csat', array( $this, 'handle_csat' ) );

		// تست اتصال هوش مصنوعی (فقط مدیر).
		add_action( 'wp_ajax_ssc_chatbot_test_ai', array( $this, 'handle_test_ai' ) );
	}

	/**
	 * آخرین خطای فراخوانی API (برای تشخیص در تست اتصال).
	 *
	 * @var string
	 */
	protected $last_error = '';

	/**
	 * تست اتصال به موتور هوش مصنوعی پیکربندی‌شده و بازگرداندن نتیجه/خطای واقعی.
	 */
	public function handle_test_ai() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
		check_ajax_referer( 'ssc_chatbot_admin', 'nonce' );

		$provider = SSC_Chatbot_Settings::get( 'ai_provider', 'fallback' );
		if ( 'fallback' === $provider ) {
			wp_send_json_error( array( 'message' => 'موتور پاسخ‌گویی روی «پیام ثابت» تنظیم شده است. ابتدا یک موتور هوش مصنوعی را انتخاب و ذخیره کنید.' ) );
		}

		$this->last_error = '';
		$system           = $this->build_system_text( '', '' );
		$messages         = array(
			array(
				'role' => 'user',
				'content' => 'سلام، لطفاً در یک جمله کوتاه خودت را معرفی کن.',
			),
		);

		switch ( $provider ) {
			case 'gemini':
				$reply = $this->gemini_reply( $system, $messages );
				break;
			case 'openai':
				$reply = $this->openai_compatible_reply( 'https://api.openai.com/v1/chat/completions', SSC_Chatbot_Settings::get_secret( 'openai_api_key' ), SSC_Chatbot_Settings::get( 'openai_model', 'gpt-4o-mini' ), $system, $messages );
				break;
			case 'claude':
				$reply = $this->claude_reply( $system, $messages );
				break;
			case 'custom':
				$reply = $this->openai_compatible_reply( SSC_Chatbot_Settings::get( 'custom_endpoint', '' ), SSC_Chatbot_Settings::get_secret( 'custom_api_key' ), SSC_Chatbot_Settings::get( 'custom_model', '' ), $system, $messages );
				break;
			case 'webhook':
				$reply = $this->webhook_reply( 'سلام', 'test', '', array() );
				break;
			default:
				$reply = '';
		}

		if ( ! empty( $reply ) ) {
			wp_send_json_success(
				array(
					'message' => 'اتصال موفق بود ✅',
					'reply'   => $reply,
				)
			);
		}

		$err = $this->last_error ? $this->last_error : 'پاسخی از سرویس دریافت نشد (ممکن است کلید، نام مدل یا آدرس نادرست باشد، یا دسترسی سرور به این سرویس مسدود باشد).';
		wp_send_json_error( array( 'message' => $err ) );
	}

	/**
	 * دریافت IP کاربر.
	 *
	 * @return string
	 */
	protected function get_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * نام هدر حاوی IP واقعی پشت پروکسی/CDN مورد اعتماد (مثلاً 'HTTP_CF_CONNECTING_IP' یا 'HTTP_X_FORWARDED_FOR').
		 * به‌صورت پیش‌فرض خالی است (فقط REMOTE_ADDR) تا از جعل IP جلوگیری شود؛ فقط وقتی پشت پروکسی مطمئن هستید فعال کنید.
		 *
		 * @param string $header نام کلید در $_SERVER.
		 */
		$header = (string) apply_filters( 'ssc_chatbot_ip_header', '' );
		if ( '' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			$first     = trim( explode( ',', $forwarded )[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				$ip = $first;
			}
		}
		return $ip;
	}

	/**
	 * شناسهٔ نشست کلاینت (برای محدودسازی نرخ per-session).
	 * در مسیر AJAX از $_POST['cid'] خوانده می‌شود و در مسیر REST با set_client_id تزریق می‌گردد.
	 *
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * تعیین شناسهٔ نشست کلاینت (مسیر REST).
	 *
	 * @param string $cid شناسه.
	 */
	public function set_client_id( $cid ) {
		$this->client_id = (string) $cid;
	}

	/**
	 * اعتبارسنجی شناسهٔ محصول در برابر فهرست واقعی محصولات.
	 * هر مقدار ناشناخته به 'general' نگاشت می‌شود تا مهاجم نتواند جدول آمار را
	 * با متریک‌های دلخواه و بی‌نهایت پر کند (و داشبورد را آلوده کند).
	 *
	 * @param string $product_id ورودی خام.
	 * @return string شناسهٔ معتبر.
	 */
	protected function sanitize_product_id( $product_id ) {
		$product_id = (string) $product_id;
		if ( '' === $product_id || 'general' === $product_id ) {
			return 'general';
		}
		if ( (string) SSC_Chatbot_Settings::get( 'company_id', 'company' ) === $product_id ) {
			return $product_id;
		}
		$map = SSC_Chatbot_Settings::products_map();
		return isset( $map[ $product_id ] ) ? $product_id : 'general';
	}

	/**
	 * ساخت توکن امضاشده برای یک ردیف تاریخچه.
	 * بدون این توکن، هر بازدیدکننده می‌تواند روی هر log_id (که عددی ترتیبی و
	 * قابل‌شمارش است) رأی بدهد و آمار بازخورد را مسموم کند.
	 *
	 * @param int $log_id شناسهٔ ردیف.
	 * @return string
	 */
	protected function log_token( $log_id ) {
		return hash_hmac( 'sha256', 'ssc_log_' . (int) $log_id, wp_salt( 'nonce' ) );
	}

	/**
	 * کنترل محدودیت تعداد درخواست روزانه.
	 * روش محدودسازی قابل‌انتخاب است: بر اساس IP، نشست (per-session)، هر دو، یا خاموش.
	 *
	 * @param string $bucket نام سطل (chat یا submit).
	 * @return bool true اگر مجاز باشد.
	 */
	protected function check_rate_limit( $bucket ) {
		$mode = SSC_Chatbot_Settings::get( 'rate_limit_mode', 'ip' );
		if ( 'off' === $mode ) {
			return true;
		}
		$ip     = $this->get_ip();
		$checks = array();

		// سقف‌های پیش‌فرض هر سطل. chat/submit از تنظیمات کاربر می‌آیند؛
		// csat و suggest ماهیت متفاوتی دارند و سقف اختصاصی می‌گیرند.
		$ip_limit   = (int) SSC_Chatbot_Settings::get( 'ai_rate_limit', 100 );
		$sess_limit = (int) SSC_Chatbot_Settings::get( 'session_rate_limit', 50 );
		if ( 'csat' === $bucket ) {
			// نظرسنجی رضایت: چند رأی در روز کافی است.
			$ip_limit   = (int) apply_filters( 'ssc_chatbot_csat_ip_limit', 20 );
			$sess_limit = (int) apply_filters( 'ssc_chatbot_csat_session_limit', 3 );
		} elseif ( 'suggest' === $bucket ) {
			// تکمیل خودکار حین تایپ: پرتکرار است، پس سقف سخاوتمند اما محدود.
			$ip_limit   = (int) apply_filters( 'ssc_chatbot_suggest_ip_limit', 1000 );
			$sess_limit = (int) apply_filters( 'ssc_chatbot_suggest_session_limit', 600 );
		}

		if ( 'ip' === $mode || 'both' === $mode ) {
			$checks[] = array(
				'metric' => 'rl:' . $bucket . ':ip:' . md5( $ip ),
				'limit'  => $ip_limit,
			);
		}
		if ( 'session' === $mode || 'both' === $mode ) {
			$cid = $this->client_id;
			if ( '' === $cid ) {
				$cid = isset( $_POST['cid'] ) ? sanitize_text_field( wp_unslash( $_POST['cid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- nonce در هندلر بررسی شده.
			}
			if ( '' === $cid ) {
				$cid = $ip; // در نبود شناسهٔ نشست، به IP برمی‌گردیم.
			}
			$checks[] = array(
				'metric' => 'rl:' . $bucket . ':sess:' . md5( $cid ),
				'limit'  => $sess_limit,
			);
			// سقف پشتیبان IP در حالت «نشست»: شناسهٔ نشست سمت کلاینت ساخته و جعل‌پذیر است؛
			// یک سقف بالاتر روی IP از تخلیهٔ بودجهٔ توکن API با cidهای تصادفی جلوگیری می‌کند.
			if ( 'session' === $mode ) {
				$ceiling  = (int) apply_filters( 'ssc_chatbot_session_ip_ceiling', max( 1, $sess_limit ) * 10 );
				$checks[] = array(
					'metric' => 'rl:' . $bucket . ':ipcap:' . md5( $ip ),
					'limit'  => $ceiling,
				);
			}
		}

		// افزایش اتمیک (race-safe) و بررسی سقف.
		foreach ( $checks as $c ) {
			if ( $c['limit'] <= 0 ) {
				continue; // ۰ = نامحدود.
			}
			if ( SSC_Chatbot_DB::rl_hit( $c['metric'] ) > $c['limit'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * ارسال نتیجهٔ یک سرویس به‌صورت پاسخ AJAX.
	 * سرویس‌ها آرایه یا WP_Error برمی‌گردانند تا هم AJAX و هم REST از یک منطق استفاده کنند.
	 *
	 * @param array|WP_Error $res نتیجه.
	 */
	protected function send_json( $res ) {
		if ( is_wp_error( $res ) ) {
			$data   = $res->get_error_data();
			$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 400;
			wp_send_json_error( array( 'message' => $res->get_error_message() ), $status );
		}
		wp_send_json_success( $res );
	}

	/**
	 * هندلر AJAX چت (پوستهٔ نازک روی سرویس).
	 */
	public function handle_chat() {
		check_ajax_referer( 'ssc_chatbot_nonce', 'nonce' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
		$this->send_json(
			$this->chat(
				array(
					'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
					'product' => isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : 'general',
					'history' => isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- در parse_history پاکسازی می‌شود.
				)
			)
		);
		// phpcs:enable
	}

	/**
	 * سرویس چت — منطق مشترک AJAX و REST.
	 *
	 * @param array $args آرگومان‌ها (message, product, history).
	 * @return array|WP_Error
	 */
	public function chat( $args ) {
		if ( ! $this->check_rate_limit( 'chat' ) ) {
			return new WP_Error(
				'ssc_rate_limited',
				__( 'محدودیت روزانه درخواست پر شده. لطفاً فردا مجدداً تلاش کنید.', 'smart-support-chatbot' ),
				array( 'status' => 429 )
			);
		}

		$message    = isset( $args['message'] ) ? (string) $args['message'] : '';
		$product_id = $this->sanitize_product_id( isset( $args['product'] ) ? $args['product'] : 'general' );
		$history    = $this->parse_history( isset( $args['history'] ) ? $args['history'] : '' );

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'ssc_empty_message', __( 'لطفاً سوال خود را بپرسید.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}

		// سقف طول پیام ورودی برای جلوگیری از مصرف بی‌رویه توکن.
		$max_len = (int) apply_filters( 'ssc_chatbot_max_message_length', 2000 );
		if ( mb_strlen( $message ) > $max_len ) {
			return new WP_Error(
				'ssc_message_too_long',
				sprintf(
					/* translators: %d: حداکثر تعداد کاراکتر مجاز. */
					__( 'پیام شما بیش از حد طولانی است (حداکثر %d کاراکتر).', 'smart-support-chatbot' ),
					$max_len
				),
				array( 'status' => 400 )
			);
		}

		$reply = $this->generate_ai_reply( $message, $product_id, $history );

		// ثبت گفتگو در آمار داشبورد.
		SSC_Chatbot_DB::record_chat( $product_id );

		// ثبت در تاریخچه گفتگو (پاسخ‌های AI/بانک + سوال‌های بی‌پاسخ).
		$log_id = 0;
		if ( 'yes' === SSC_Chatbot_Settings::get( 'chatlog_enabled', 'yes' )
			&& in_array( $this->last_source, array( 'ai', 'bank', 'unanswered' ), true )
			&& 0 !== mb_strpos( (string) $reply, '⚠️' ) ) {
			$log_id = (int) SSC_Chatbot_DB::log_chat_entry(
				array(
					'product'  => $product_id,
					'question' => $message,
					'answer'   => $reply,
					'source'   => $this->last_source,
					'ip'       => $this->get_ip(),
				)
			);
		}

		$resp = array( 'reply' => $reply );
		// شناسهٔ لاگ فقط برای پاسخ‌های واقعی (برای بازخورد 👍/👎) + توکن امضاشدهٔ مالکیت.
		if ( $log_id && in_array( $this->last_source, array( 'ai', 'bank' ), true ) ) {
			$resp['log_id']    = $log_id;
			$resp['log_token'] = $this->log_token( $log_id );
		}

		// چیپس‌های پیگیری هوشمند (سوالات مرتبط از بانک) پس از پاسخ‌های واقعی.
		if ( in_array( $this->last_source, array( 'ai', 'bank', 'cache' ), true )
			&& 'yes' === SSC_Chatbot_Settings::get( 'suggestions_enabled', 'yes' )
			&& 0 !== mb_strpos( (string) $reply, '⚠️' ) ) {
			$suggestions = $this->related_questions( $product_id, $message );
			if ( ! empty( $suggestions ) ) {
				$resp['suggestions'] = $suggestions;
			}
		}

		// پیشنهاد واگذاری به کارشناس انسانی هنگام بی‌پاسخ ماندن.
		if ( 'unanswered' === $this->last_source && 'yes' === SSC_Chatbot_Settings::get( 'handoff_enabled', 'yes' ) ) {
			$resp['handoff'] = true;
		}

		return $resp;
	}

	/**
	 * هندلر AJAX بازخورد (پوستهٔ نازک روی سرویس).
	 */
	public function handle_feedback() {
		check_ajax_referer( 'ssc_chatbot_nonce', 'nonce' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
		$this->send_json(
			$this->feedback(
				array(
					'log_id'    => isset( $_POST['log_id'] ) ? (int) $_POST['log_id'] : 0,
					'rating'    => isset( $_POST['rating'] ) ? (int) $_POST['rating'] : 0,
					'log_token' => isset( $_POST['log_token'] ) ? sanitize_text_field( wp_unslash( $_POST['log_token'] ) ) : '',
				)
			)
		);
		// phpcs:enable
	}

	/**
	 * سرویس بازخورد پاسخ (👍/👎).
	 *
	 * @param array $args آرگومان‌ها (log_id, rating, log_token).
	 * @return array|WP_Error
	 */
	public function feedback( $args ) {
		$id     = isset( $args['log_id'] ) ? (int) $args['log_id'] : 0;
		$rating = isset( $args['rating'] ) ? (int) $args['rating'] : 0;
		$token  = isset( $args['log_token'] ) ? (string) $args['log_token'] : '';

		// فقط کسی که خودِ این پاسخ را دریافت کرده می‌تواند رأی بدهد
		// (شناسه‌های ترتیبی قابل‌شمارش‌اند؛ بدون این بررسی آمار بازخورد جعل‌پذیر است).
		if ( $id <= 0 || 0 === $rating || ! hash_equals( $this->log_token( $id ), $token ) ) {
			return new WP_Error( 'ssc_invalid_feedback', __( 'درخواست نامعتبر است.', 'smart-support-chatbot' ), array( 'status' => 403 ) );
		}

		// set_chatlog_rating فقط ردیف‌های بدون رأی را به‌روزرسانی می‌کند (رأی مجدد نادیده گرفته می‌شود).
		SSC_Chatbot_DB::set_chatlog_rating( $id, $rating );
		return array( 'ok' => true );
	}

	/**
	 * پاکسازی و آماده‌سازی تاریخچه مکالمه دریافتی از کلاینت (حافظه مکالمه سبک).
	 *
	 * @param string $raw رشته JSON تاریخچه.
	 * @return array آرایه‌ای از { role, content } با نقش‌های user/assistant.
	 */
	protected function parse_history( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		$decoded = json_decode( is_string( $raw ) ? $raw : wp_json_encode( $raw ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$limit = (int) SSC_Chatbot_Settings::get( 'ai_history_limit', 8 );
		$limit = max( 0, min( 20, $limit ) );
		if ( 0 === $limit ) {
			return array();
		}

		$out = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['role'], $item['content'] ) ) {
				continue;
			}
			$role = ( 'assistant' === $item['role'] ) ? 'assistant' : 'user';
			$text = sanitize_textarea_field( (string) $item['content'] );
			if ( '' === trim( $text ) ) {
				continue;
			}
			// محدودسازی طول هر پیام برای کنترل مصرف توکن.
			if ( mb_strlen( $text ) > 1500 ) {
				$text = mb_substr( $text, 0, 1500 );
			}
			$out[] = array(
				'role' => $role,
				'content' => $text,
			);
		}

		// فقط آخرین N پیام را نگه می‌داریم.
		if ( count( $out ) > $limit ) {
			$out = array_slice( $out, -$limit );
		}
		return $out;
	}

	/**
	 * ساخت متن سیستمی (دستورالعمل + زمینه محصول + دانش).
	 *
	 * @param string $product_name نام محصول/شرکت فعال.
	 * @param string $knowledge    پایگاه دانش محصول.
	 * @return string
	 */
	protected function build_system_text( $product_name, $knowledge ) {
		$system = (string) SSC_Chatbot_Settings::get( 'ai_system_prompt', '' );
		if ( $product_name ) {
			$system .= "\n\nموضوع جاری گفتگو: «" . $product_name . '». ' .
				'تمام سوالات کاربر مربوط به همین موضوع است، حتی اگر نام آن را دوباره ذکر نکند.';
		}
		if ( $knowledge ) {
			$system .= "\n\nاطلاعات مرجع برای پاسخ‌گویی:\n" . $knowledge;
		}
		// حالت سخت‌گیرانه: فقط از پایگاه دانش پاسخ بده.
		if ( 'yes' === SSC_Chatbot_Settings::get( 'ai_strict_knowledge', 'no' ) ) {
			$system .= "\n\n[قانون مهم]: فقط و فقط بر اساس «اطلاعات مرجع» بالا پاسخ بده. " .
				'اگر پاسخ سوال در اطلاعات مرجع موجود نیست، صریحاً بگو که اطلاعات کافی در این مورد نداری و ' .
				'کاربر را به تماس با شرکت یا بخش «درخواست مشاوره» ارجاع بده. از دانش عمومی خودت استفاده نکن و چیزی از خودت نساز.';
		}
		return $system;
	}

	/**
	 * دریافت میزان خلاقیت (temperature) به‌صورت عدد.
	 *
	 * @return float
	 */
	protected function get_temperature() {
		$t = (float) SSC_Chatbot_Settings::get( 'ai_temperature', 0.4 );
		return max( 0, min( 1, $t ) );
	}

	/**
	 * دریافت حداکثر طول پاسخ.
	 *
	 * @return int
	 */
	protected function get_max_tokens() {
		$m = (int) SSC_Chatbot_Settings::get( 'ai_max_tokens', 800 );
		return max( 100, min( 4000, $m ) );
	}

	/**
	 * منبع آخرین پاسخ (ai | bank | fallback | filter) برای ثبت در تاریخچه.
	 *
	 * @var string
	 */
	public $last_source = 'fallback';

	/**
	 * تولید پاسخ بر اساس جریان: اول AI، سپس بانک سوال/جواب آفلاین، سپس پیام پیش‌فرض.
	 * (ترتیب با تنظیم qa_mode قابل تغییر است.)
	 *
	 * @param string $message    پیام کاربر.
	 * @param string $product_id شناسه محصول.
	 * @param array  $history    تاریخچه مکالمه.
	 * @return string
	 */
	protected function generate_ai_reply( $message, $product_id, $history = array() ) {
		$this->last_error  = '';
		$this->last_source = 'fallback';

		$provider = SSC_Chatbot_Settings::get( 'ai_provider', 'fallback' );
		$qa_mode  = SSC_Chatbot_Settings::get( 'qa_mode', 'ai_first' );

		// نام محصول.
		$products_map = SSC_Chatbot_Settings::products_map();
		$company_id   = SSC_Chatbot_Settings::get( 'company_id', 'company' );
		if ( $company_id === $product_id ) {
			$product_name = SSC_Chatbot_Settings::get( 'company_name', '' );
		} else {
			$product_name = isset( $products_map[ $product_id ] ) ? $products_map[ $product_id ] : $product_id;
		}

		// دانش محصول.
		$knowledge_all = (array) SSC_Chatbot_Settings::get( 'product_knowledge', array() );
		$knowledge     = isset( $knowledge_all[ $product_id ] ) ? $knowledge_all[ $product_id ] : '';

		/**
		 * فیلتر برای جایگزینی کامل منطق پاسخ‌گویی.
		 */
		$pre = apply_filters( 'ssc_chatbot_pre_reply', null, $message, $product_id, $product_name );
		if ( null !== $pre ) {
			$this->last_source = 'filter';
			return (string) $pre;
		}

		$use_ai = ( 'fallback' !== $provider && 'bank_only' !== $qa_mode );

		// حالت «اول بانک».
		if ( 'bank_first' === $qa_mode || 'bank_only' === $qa_mode ) {
			$bank = $this->bank_reply( $product_id, $message );
			if ( '' !== $bank ) {
				$this->last_source = 'bank';
				return $bank;
			}
		}

		// تلاش با هوش مصنوعی.
		if ( $use_ai ) {
			// بازیابی هیبریدی از پایگاه دانش و افزودن به دانش مرجع (RAG سبک).
			$kb = $this->kb_retrieve( $product_id, $message );
			if ( '' !== $kb ) {
				$knowledge = trim( $knowledge . "\n\n— از پایگاه دانش —\n" . $kb );
			}
			$system = $this->build_system_text( $product_name, $knowledge );

			// کش پاسخ برای سوال‌های بدون تاریخچه (پاسخ فوری به سوال‌های تکراری + کاهش هزینه).
			$cache_enabled = ( 'yes' === SSC_Chatbot_Settings::get( 'ai_cache_enabled', 'yes' ) ) && empty( $history );
			$cache_key     = '';
			if ( $cache_enabled ) {
				$cache_key = 'ssc_ai_' . md5( $provider . '|' . $product_id . '|' . mb_strtolower( trim( $message ) ) . '|' . md5( $system ) );
				$cached    = get_transient( $cache_key );
				if ( false !== $cached && '' !== $cached ) {
					$this->last_source = 'cache';
					return (string) $cached;
				}
			}

			$messages = is_array( $history ) ? $history : array();
			// حذف پیام‌های assistant ابتدایی (برخی APIها باید با نقش user شروع شوند).
			while ( ! empty( $messages ) && isset( $messages[0]['role'] ) && 'assistant' === $messages[0]['role'] ) {
				array_shift( $messages );
			}
			$messages[] = array(
				'role' => 'user',
				'content' => $message,
			);

			$reply = $this->dispatch_ai( $provider, $message, $product_id, $product_name, $system, $messages, $history );
			if ( ! empty( $reply ) ) {
				$this->last_source = 'ai';
				if ( $cache_enabled && $cache_key ) {
					$ttl = (int) apply_filters( 'ssc_chatbot_ai_cache_ttl', 6 * HOUR_IN_SECONDS );
					set_transient( $cache_key, $reply, $ttl );
				}
				return $reply;
			}
			// ثبت خطای AI در لاگ برای عیب‌یابی.
			if ( $this->last_error ) {
				error_log( '[SSC Chatbot] AI (' . $provider . ') failed: ' . $this->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		// حالت «اول AI»: اکنون سراغ بانک می‌رویم (در bank_first قبلاً امتحان شده).
		if ( 'bank_first' !== $qa_mode ) {
			$bank = $this->bank_reply( $product_id, $message );
			if ( '' !== $bank ) {
				$this->last_source = 'bank';
				return $bank;
			}
		}

		// نمایش خطای واقعی AI فقط برای مدیران (وقتی بانک هم پاسخی نداشت).
		if ( $use_ai && $this->last_error && current_user_can( 'manage_options' ) ) {
			return '⚠️ [پیام فقط برای مدیر] خطای موتور هوش مصنوعی: ' . $this->last_error;
		}

		// هیچ منبعی پاسخ نداد → ثبت به‌عنوان «سوال بی‌پاسخ» برای تقویت بانک.
		$this->last_source = 'unanswered';
		return (string) SSC_Chatbot_Settings::get( 'ai_fallback_msg', '' );
	}

	/**
	 * فراخوانی ارائه‌دهنده هوش مصنوعی انتخابی.
	 *
	 * @param string $provider     ارائه‌دهنده.
	 * @param string $message      پیام جاری.
	 * @param string $product_id   شناسه محصول.
	 * @param string $product_name نام محصول.
	 * @param string $system       متن سیستمی.
	 * @param array  $messages     پیام‌ها (تاریخچه + جاری).
	 * @param array  $history      تاریخچه خام (برای webhook).
	 * @return string
	 */
	protected function dispatch_ai( $provider, $message, $product_id, $product_name, $system, $messages, $history ) {
		switch ( $provider ) {
			case 'gemini':
				return $this->gemini_reply( $system, $messages );
			case 'openai':
				return $this->openai_compatible_reply(
					'https://api.openai.com/v1/chat/completions',
					SSC_Chatbot_Settings::get_secret( 'openai_api_key' ),
					SSC_Chatbot_Settings::get( 'openai_model', 'gpt-4o-mini' ),
					$system,
					$messages
				);
			case 'claude':
				return $this->claude_reply( $system, $messages );
			case 'custom':
				return $this->openai_compatible_reply(
					SSC_Chatbot_Settings::get( 'custom_endpoint', '' ),
					SSC_Chatbot_Settings::get_secret( 'custom_api_key' ),
					SSC_Chatbot_Settings::get( 'custom_model', '' ),
					$system,
					$messages
				);
			case 'webhook':
				return $this->webhook_reply( $message, $product_id, $product_name, $history );
		}
		return '';
	}

	/**
	 * نرمال‌سازی متن فارسی برای تطبیق (یکسان‌سازی ی/ک، حذف اعراب و علائم).
	 *
	 * @param string $text متن.
	 * @return string
	 */
	protected function normalize_fa( $text ) {
		return self::normalize( $text );
	}

	/**
	 * نرمال‌سازی متن فارسی (نسخهٔ ایستا — قابل‌استفاده در ذخیره‌سازی پایگاه دانش).
	 *
	 * @param string $text متن.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = (string) $text;
		// یکسان‌سازی حروف عربی/فارسی.
		$text = str_replace( array( 'ي', 'ك', 'ۀ', 'ة', 'أ', 'إ', 'آ', 'ؤ', 'ئ' ), array( 'ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'و', 'ی' ), $text );
		// حذف اعراب و کشیده.
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text );
		// تبدیل علائم و ارقام به فاصله.
		$text = preg_replace( '/[\x{200C}\x{200F}\x{200E}]/u', ' ', $text ); // نیم‌فاصله و علائم جهت.
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( mb_strtolower( $text ) );
	}

	/**
	 * توکن‌سازی با حذف کلمات پرتکرار کم‌اهمیت.
	 *
	 * @param string $text متن نرمال‌شده.
	 * @return array
	 */
	protected function tokenize_fa( $text ) {
		$stop   = array( 'و', 'در', 'به', 'از', 'که', 'را', 'با', 'این', 'آن', 'است', 'هست', 'برای', 'یا', 'تا', 'هم', 'چه', 'چی', 'چیست', 'چطور', 'چگونه', 'ایا', 'آیا', 'می', 'شود', 'کنم', 'کنید', 'کرد', 'های', 'ها', 'یک', 'من', 'شما', 'لطفا', 'لطفاً', 'بگو', 'بگویید', 'دارد', 'دارم', 'مورد', 'درباره', 'راجع', 'باید', 'ایا', 'وقتی', 'کدام', 'چند' );
		$tokens = array_filter(
			explode( ' ', $text ),
			function ( $t ) use ( $stop ) {
				return '' !== $t && mb_strlen( $t ) > 1 && ! in_array( $t, $stop, true );
			}
		);
		return array_values( array_unique( $tokens ) );
	}

	/**
	 * گروه‌های مترادف فارسی برای بهبود تطبیق.
	 *
	 * @return array
	 */
	protected function synonym_groups() {
		return apply_filters(
			'ssc_chatbot_synonyms',
			array(
				array( 'عوارض', 'عارضه', 'عوارضی', 'مضر', 'ضرر' ),
				array( 'دارو', 'قرص', 'دوا', 'محصول', 'دارویی' ),
				array( 'مصرف', 'استفاده', 'خوردن', 'نحوه‌مصرف' ),
				array( 'دوز', 'مقدار', 'میزان', 'تعداد' ),
				array( 'تداخل', 'تداخلات', 'تاثیر', 'اثر' ),
				array( 'بارداری', 'حاملگی', 'باردار' ),
				array( 'بروشور', 'دفترچه', 'راهنما' ),
				array( 'قیمت', 'هزینه', 'تومان' ),
				array( 'نگهداری', 'انبار', 'یخچال' ),
			)
		);
	}

	/**
	 * گسترش توکن‌ها با مترادف‌ها (هر توکن به نمایندهٔ گروهش نگاشت می‌شود).
	 *
	 * @param array $tokens توکن‌ها.
	 * @return array
	 */
	protected function expand_synonyms( $tokens ) {
		$groups = $this->synonym_groups();
		$out    = array();
		foreach ( $tokens as $tok ) {
			$out[] = $tok;
			foreach ( $groups as $g ) {
				if ( in_array( $tok, $g, true ) ) {
					$out[] = 'syn_' . $g[0]; // نمایندهٔ گروه.
					break;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * ساخت رشتهٔ جستجوی FULLTEXT (توکن‌های نرمال‌شده + کلمات هم‌گروهِ مترادف) برای پیش‌فیلتر دیتابیس.
	 *
	 * @param string $text متن کاربر.
	 * @return string
	 */
	protected function fulltext_against( $text ) {
		$tokens = $this->tokenize_fa( $this->normalize_fa( $text ) );
		if ( empty( $tokens ) ) {
			return '';
		}
		$groups = $this->synonym_groups();
		$words  = $tokens;
		foreach ( $tokens as $tok ) {
			foreach ( $groups as $g ) {
				if ( in_array( $tok, $g, true ) ) {
					$words = array_merge( $words, $g );
					break;
				}
			}
		}
		// حذف کاراکترهای عملگر boolean و توکن‌های خیلی کوتاه.
		$words = array_filter(
			array_map(
				function ( $w ) {
					return preg_replace( '/[+\-><()~*"@]/u', '', (string) $w );
				},
				$words
			),
			function ( $w ) {
				return mb_strlen( $w ) >= 2;
			}
		);
		return implode( ' ', array_values( array_unique( $words ) ) );
	}

	/**
	 * یافتن پاسخ از بانک سوال/جواب آفلاین (از جدول مستقل + تطبیق فارسی با مترادف).
	 *
	 * @param string $product_id شناسه محصول.
	 * @param string $message    پیام کاربر.
	 * @return string پاسخ یا رشته خالی.
	 */
	protected function bank_reply( $product_id, $message ) {
		$rows = SSC_Chatbot_DB::qa_candidates( $product_id, $this->fulltext_against( $message ) );
		if ( empty( $rows ) ) {
			return '';
		}

		$user_tokens = $this->expand_synonyms( $this->tokenize_fa( $this->normalize_fa( $message ) ) );
		if ( empty( $user_tokens ) ) {
			return '';
		}

		$best_answer = '';
		$best_id     = 0;
		$best_score  = 0;

		foreach ( $rows as $entry ) {
			if ( empty( $entry['answer'] ) ) {
				continue;
			}
			$kw         = isset( $entry['keywords'] ) ? str_replace( array( '|', '،', ',' ), ' ', $entry['keywords'] ) : '';
			$ref        = $this->normalize_fa( ( isset( $entry['question'] ) ? $entry['question'] : '' ) . ' ' . $kw );
			$ref_tokens = $this->expand_synonyms( $this->tokenize_fa( $ref ) );
			if ( empty( $ref_tokens ) ) {
				continue;
			}

			$common = array_intersect( $user_tokens, $ref_tokens );
			$nc     = count( $common );
			if ( 0 === $nc ) {
				continue;
			}
			// امتیاز ترکیبی: پوشش سوال کاربر + پوشش مرجع (برای سوال‌های کوتاه دقیق‌تر).
			$score = ( $nc / count( $user_tokens ) ) * 0.7 + ( $nc / count( $ref_tokens ) ) * 0.3;
			// تطبیق مستقیم کلیدواژه امتیاز اضافه می‌گیرد.
			$kw_tokens = $this->expand_synonyms( $this->tokenize_fa( $this->normalize_fa( $kw ) ) );
			if ( $kw_tokens && array_intersect( $user_tokens, $kw_tokens ) ) {
				$score += 0.2;
			}

			if ( $score > $best_score ) {
				$best_score  = $score;
				$best_answer = $entry['answer'];
				$best_id     = (int) $entry['id'];
			}
		}

		$threshold = (float) apply_filters( 'ssc_chatbot_bank_threshold', 0.32 );
		if ( $best_score >= $threshold ) {
			if ( $best_id ) {
				SSC_Chatbot_DB::qa_increment_usage( $best_id );
			}
			return (string) $best_answer;
		}
		return '';
	}

	/**
	 * بازیابی هیبریدی از پایگاه دانش: یافتن مرتبط‌ترین تکه‌ها برای تزریق به پرامپت AI.
	 * (تطبیق لغوی + مترادف، کاملاً آفلاین — بدون نیاز به embeddings.)
	 *
	 * @param string $product_id شناسه محصول.
	 * @param string $message    پیام کاربر.
	 * @return string دانش بازیابی‌شده (یا رشته خالی).
	 */
	protected function kb_retrieve( $product_id, $message ) {
		if ( 'yes' !== SSC_Chatbot_Settings::get( 'kb_enabled', 'yes' ) ) {
			return '';
		}
		$rows = SSC_Chatbot_DB::kb_candidates( $product_id, $this->fulltext_against( $message ) );
		if ( empty( $rows ) ) {
			return '';
		}
		$tokens = $this->expand_synonyms( $this->tokenize_fa( $this->normalize_fa( $message ) ) );
		if ( empty( $tokens ) ) {
			return '';
		}

		$scored = array();
		foreach ( $rows as $r ) {
			$st = ! empty( $r['search_text'] ) ? $r['search_text'] : $this->normalize_fa( $r['chunk'] );
			$rt = $this->expand_synonyms( $this->tokenize_fa( $st ) );
			if ( empty( $rt ) ) {
				continue;
			}
			$common = count( array_intersect( $tokens, $rt ) );
			if ( 0 === $common ) {
				continue;
			}
			// امتیاز: پوشش توکن‌های کاربر (مهم‌تر) + چگالی تطبیق در تکه.
			$score    = ( $common / max( 1, count( $tokens ) ) ) * 0.7 + ( $common / max( 1, count( $rt ) ) ) * 0.3;
			$scored[] = array(
				'chunk' => (string) $r['chunk'],
				'title' => (string) $r['source_title'],
				'score' => $score,
			);
		}
		if ( empty( $scored ) ) {
			return '';
		}
		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);

		$threshold = (float) apply_filters( 'ssc_chatbot_kb_threshold', 0.08 );
		$max       = (int) SSC_Chatbot_Settings::get( 'kb_max_chunks', 3 );
		$max       = max( 1, min( 8, $max ) );
		$out       = '';
		$used      = 0;
		foreach ( $scored as $item ) {
			if ( $item['score'] < $threshold ) {
				break;
			}
			$out .= '• از «' . $item['title'] . "»:\n" . $item['chunk'] . "\n\n";
			++$used;
			if ( $used >= $max ) {
				break;
			}
		}
		return trim( $out );
	}

	/**
	 * سوالات مرتبط از بانک برای چیپس‌های پیگیری هوشمند (پس از پاسخ).
	 *
	 * @param string $product_id شناسه محصول.
	 * @param string $message    پیام جاری کاربر (برای حذف سوال تکراری).
	 * @return array فهرست متن سوال‌ها (حداکثر ۳).
	 */
	protected function related_questions( $product_id, $message ) {
		$rows = SSC_Chatbot_DB::qa_candidates( $product_id, $this->fulltext_against( $message ) );
		if ( empty( $rows ) ) {
			return array();
		}
		$asked  = $this->normalize_fa( $message );
		$tokens = $this->expand_synonyms( $this->tokenize_fa( $asked ) );
		$scored = array();
		foreach ( $rows as $entry ) {
			$q = isset( $entry['question'] ) ? trim( (string) $entry['question'] ) : '';
			if ( '' === $q ) {
				continue;
			}
			$norm = $this->normalize_fa( $q );
			// حذف سوال تقریباً یکسان با سوال فعلی.
			if ( $norm === $asked ) {
				continue;
			}
			$ref_tokens = $this->expand_synonyms( $this->tokenize_fa( $norm ) );
			if ( empty( $ref_tokens ) ) {
				continue;
			}
			// امتیاز: ارتباط با موضوع (اشتراک توکن) + اولویت محصول جاری + کاربردِ بالا.
			$common  = count( array_intersect( $tokens, $ref_tokens ) );
			$overlap = $tokens ? ( $common / max( 1, count( $tokens ) ) ) : 0;
			$score   = $overlap;
			if ( isset( $entry['product_id'] ) && $product_id === $entry['product_id'] ) {
				$score += 0.15;
			}
			$score   += min( 0.2, ( (int) ( isset( $entry['usage_count'] ) ? $entry['usage_count'] : 0 ) ) * 0.02 );
			$scored[] = array(
				'q' => $q,
				'score' => $score,
			);
		}
		if ( empty( $scored ) ) {
			return array();
		}
		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);
		$out  = array();
		$seen = array();
		foreach ( $scored as $item ) {
			$q = $item['q'];
			if ( isset( $seen[ $q ] ) ) {
				continue;
			}
			$seen[ $q ] = true;
			$out[]      = ( mb_strlen( $q ) > 90 ) ? ( mb_substr( $q, 0, 88 ) . '…' ) : $q;
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * هندلر تکمیل خودکار: پیشنهاد سوال‌های بانک هنگام تایپ (مستقل از AI، فوری).
	 */
	public function handle_suggest() {
		check_ajax_referer( 'ssc_chatbot_nonce', 'nonce' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
		$this->send_json(
			$this->suggest(
				array(
					'term'    => isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '',
					'product' => isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : 'general',
				)
			)
		);
		// phpcs:enable
	}

	/**
	 * سرویس تکمیل خودکار.
	 *
	 * @param array $args آرگومان‌ها (term, product).
	 * @return array
	 */
	public function suggest( $args ) {
		$empty = array( 'items' => array() );

		if ( 'yes' !== SSC_Chatbot_Settings::get( 'autocomplete_enabled', 'yes' ) ) {
			return $empty;
		}
		// هر keystroke یک واکشی و امتیازدهی کامل روی دیتابیس است؛ بدون سقف، اهرم ارزانِ DoS.
		if ( ! $this->check_rate_limit( 'suggest' ) ) {
			return $empty;
		}
		$term       = isset( $args['term'] ) ? (string) $args['term'] : '';
		$product_id = $this->sanitize_product_id( isset( $args['product'] ) ? $args['product'] : 'general' );
		if ( mb_strlen( trim( $term ) ) < 2 ) {
			return $empty;
		}

		$rows = SSC_Chatbot_DB::qa_candidates( $product_id, $this->fulltext_against( $term ) );
		if ( empty( $rows ) ) {
			return $empty;
		}

		$norm_term = $this->normalize_fa( $term );
		$tokens    = $this->expand_synonyms( $this->tokenize_fa( $norm_term ) );
		$scored    = array();
		foreach ( $rows as $entry ) {
			$q = isset( $entry['question'] ) ? trim( (string) $entry['question'] ) : '';
			if ( '' === $q ) {
				continue;
			}
			$norm_q = $this->normalize_fa( $q );
			$score  = 0;
			// تطبیق رشته‌ای (شامل‌بودن) امتیاز بالا.
			if ( false !== mb_strpos( $norm_q, $norm_term ) ) {
				$score += 1.0;
			}
			// اشتراک توکن/مترادف.
			$ref_tokens = $this->expand_synonyms( $this->tokenize_fa( $norm_q ) );
			if ( $tokens && $ref_tokens ) {
				$score += count( array_intersect( $tokens, $ref_tokens ) ) * 0.3;
			}
			if ( $score > 0 ) {
				$scored[] = array(
					'q' => $q,
					'score' => $score,
				);
			}
		}
		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);
		$items = array();
		$seen  = array();
		foreach ( $scored as $item ) {
			if ( isset( $seen[ $item['q'] ] ) ) {
				continue;
			}
			$seen[ $item['q'] ] = true;
			$items[]            = $item['q'];
			if ( count( $items ) >= 6 ) {
				break;
			}
		}
		return array( 'items' => $items );
	}

	/**
	 * هندلر AJAX نظرسنجی رضایت (پوستهٔ نازک روی سرویس).
	 */
	public function handle_csat() {
		check_ajax_referer( 'ssc_chatbot_nonce', 'nonce' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
		$this->send_json( $this->csat( array( 'score' => isset( $_POST['score'] ) ? (int) $_POST['score'] : 0 ) ) );
	}

	/**
	 * سرویس ثبت امتیاز رضایت پایان گفتگو (CSAT).
	 *
	 * @param array $args آرگومان‌ها (score).
	 * @return array|WP_Error
	 */
	public function csat( $args ) {
		// بدون محدودسازی، میانگین رضایت با رأی انبوه کاملاً جعل‌پذیر است.
		if ( ! $this->check_rate_limit( 'csat' ) ) {
			return new WP_Error( 'ssc_csat_limited', __( 'امتیاز شما قبلاً ثبت شده است.', 'smart-support-chatbot' ), array( 'status' => 429 ) );
		}
		$score = isset( $args['score'] ) ? (int) $args['score'] : 0;
		if ( $score >= 1 && $score <= 5 ) {
			SSC_Chatbot_DB::record_csat( $score );
		}
		return array( 'ok' => true );
	}

	/**
	 * فراخوانی Google Gemini (با تاریخچه).
	 *
	 * @param string $system   متن سیستمی.
	 * @param array  $messages پیام‌ها.
	 * @return string
	 */
	protected function gemini_reply( $system, $messages ) {
		$api_key = SSC_Chatbot_Settings::get_secret( 'gemini_api_key' );
		if ( empty( $api_key ) ) {
			return '';
		}
		$model = SSC_Chatbot_Settings::get( 'gemini_model', 'gemini-2.0-flash' );

		// تبدیل پیام‌ها به فرمت Gemini (نقش‌ها: user / model).
		$contents = array();
		foreach ( $messages as $m ) {
			$contents[] = array(
				'role'  => ( 'assistant' === $m['role'] ) ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			);
		}

		// کلید در هدر ارسال می‌شود، نه در query string — تا در لاگ سرور/پروکسی/CDN ثبت نشود.
		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
			rawurlencode( $model )
		);

		$body = array(
			'contents'          => $contents,
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'generationConfig'  => array(
				'temperature'     => $this->get_temperature(),
				'maxOutputTokens' => $this->get_max_tokens(),
			),
		);

		$data = $this->remote_json(
			$endpoint,
			array(
				'Content-Type'     => 'application/json',
				'x-goog-api-key'   => $api_key,
			),
			$body
		);
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
		}
		return '';
	}

	/**
	 * فراخوانی Anthropic Claude (Messages API، با تاریخچه).
	 *
	 * @param string $system   متن سیستمی.
	 * @param array  $messages پیام‌ها.
	 * @return string
	 */
	protected function claude_reply( $system, $messages ) {
		$api_key = SSC_Chatbot_Settings::get_secret( 'claude_api_key' );
		if ( empty( $api_key ) ) {
			return '';
		}
		$model = SSC_Chatbot_Settings::get( 'claude_model', 'claude-opus-4-8' );

		$msgs = array();
		foreach ( $messages as $m ) {
			$msgs[] = array(
				'role'    => ( 'assistant' === $m['role'] ) ? 'assistant' : 'user',
				'content' => $m['content'],
			);
		}

		// نکته: مدل‌های Claude 4.x پارامتر temperature را نمی‌پذیرند، پس ارسال نمی‌شود.
		$body = array(
			'model'      => $model,
			'max_tokens' => $this->get_max_tokens(),
			'system'     => $system,
			'messages'   => $msgs,
		);

		$data = $this->remote_json(
			'https://api.anthropic.com/v1/messages',
			array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			$body
		);

		// استخراج متن از بلوک‌های پاسخ.
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$text = '';
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
			return trim( $text );
		}
		return '';
	}

	/**
	 * فراخوانی APIهای سازگار با OpenAI (OpenAI و Custom).
	 *
	 * @param string $endpoint آدرس کامل endpoint.
	 * @param string $api_key  کلید API.
	 * @param string $model    نام مدل.
	 * @param string $system   متن سیستمی.
	 * @param array  $messages پیام‌ها.
	 * @return string
	 */
	protected function openai_compatible_reply( $endpoint, $api_key, $model, $system, $messages ) {
		if ( empty( $endpoint ) || empty( $model ) ) {
			return '';
		}

		$msgs = array();
		if ( $system ) {
			$msgs[] = array(
				'role' => 'system',
				'content' => $system,
			);
		}
		foreach ( $messages as $m ) {
			$msgs[] = array(
				'role'    => ( 'assistant' === $m['role'] ) ? 'assistant' : 'user',
				'content' => $m['content'],
			);
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$body = array(
			'model'       => $model,
			'messages'    => $msgs,
			'max_tokens'  => $this->get_max_tokens(),
			'temperature' => $this->get_temperature(),
		);

		$data = $this->remote_json( $endpoint, $headers, $body );
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return trim( $data['choices'][0]['message']['content'] );
		}
		return '';
	}

	/**
	 * ارسال درخواست POST JSON و دریافت پاسخ JSON.
	 *
	 * @param string $url     آدرس.
	 * @param array  $headers هدرها.
	 * @param array  $body    بدنه.
	 * @return array|null
	 */
	protected function remote_json( $url, $headers, $body ) {
		/**
		 * مهلت پاسخ‌گویی API (ثانیه). مدل‌های رایگان گاهی کند هستند؛ مقدار بالاتر از خطای timeout جلوگیری می‌کند.
		 *
		 * @param int $timeout مهلت بر حسب ثانیه.
		 */
		$timeout = (int) apply_filters( 'ssc_chatbot_http_timeout', 60 );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->last_error = 'خطای اتصال: ' . $response->get_error_message();
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			// استخراج پیام خطای سرویس برای تشخیص بهتر.
			$detail = '';
			$json   = json_decode( $raw, true );
			if ( isset( $json['error']['message'] ) ) {
				$detail = $json['error']['message'];
			} elseif ( isset( $json['error'] ) && is_string( $json['error'] ) ) {
				$detail = $json['error'];
			} elseif ( isset( $json['message'] ) ) {
				$detail = $json['message'];
			} else {
				$detail = mb_substr( wp_strip_all_tags( (string) $raw ), 0, 300 );
			}
			$this->last_error = 'کد خطای HTTP ' . $code . ( $detail ? ' — ' . $detail : '' );
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$this->last_error = 'پاسخ نامعتبر (JSON قابل پردازش نبود).';
			return null;
		}
		return $data;
	}

	/**
	 * فراخوانی Webhook سفارشی (با تاریخچه).
	 *
	 * @param string $message      پیام.
	 * @param string $product_id   شناسه محصول.
	 * @param string $product_name نام محصول.
	 * @param array  $history      تاریخچه مکالمه.
	 * @return string
	 */
	protected function webhook_reply( $message, $product_id, $product_name, $history = array() ) {
		$url = SSC_Chatbot_Settings::get( 'ai_webhook_url', '' );
		if ( empty( $url ) ) {
			return '';
		}

		$payload = wp_json_encode(
			array(
				'message'      => $message,
				'product'      => $product_id,
				'product_name' => $product_name,
				'history'      => $history,
			)
		);

		$headers = array( 'Content-Type' => 'application/json' );
		$secret  = SSC_Chatbot_Settings::get_secret( 'ai_webhook_secret' );
		if ( $secret ) {
			$headers['X-Chatbot-Signature'] = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => (int) apply_filters( 'ssc_chatbot_http_timeout', 60 ),
				'headers' => $headers,
				'body'    => $payload,
			)
		);
		if ( is_wp_error( $response ) ) {
			$this->last_error = 'خطای اتصال Webhook: ' . $response->get_error_message();
			return '';
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->last_error = 'Webhook کد ' . wp_remote_retrieve_response_code( $response ) . ' برگرداند.';
			return '';
		}
		$body = wp_remote_retrieve_body( $response );

		// اعتبارسنجی امضای پاسخ (در صورت تنظیم secret و وجود هدر امضا).
		if ( $secret ) {
			$resp_sig = wp_remote_retrieve_header( $response, 'x-ssc-signature' );
			if ( $resp_sig ) {
				$expected = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
				if ( ! hash_equals( $expected, $resp_sig ) ) {
					$this->last_error = 'امضای پاسخ Webhook نامعتبر است.';
					return '';
				}
			}
		}

		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			if ( isset( $data['reply'] ) ) {
				return (string) $data['reply'];
			}
			if ( isset( $data['message'] ) ) {
				return (string) $data['message'];
			}
		}
		return '';
	}

	/**
	 * هندلر AJAX ثبت فرم (پوستهٔ نازک روی سرویس).
	 */
	public function handle_submit() {
		check_ajax_referer( 'ssc_chatbot_nonce', 'nonce' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
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
		$args = array( 'nfx_elapsed' => isset( $_POST['nfx_elapsed'] ) ? (int) $_POST['nfx_elapsed'] : 99999 );
		foreach ( $keys as $k ) {
			$args[ $k ] = isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- در سرویس پاکسازی می‌شود.
		}
		$this->send_json( $this->submit( $args ) );
		// phpcs:enable
	}

	/**
	 * سرویس ثبت فرم درخواست/عوارض.
	 *
	 * @param array $args آرگومان‌های خام (در همین متد پاکسازی می‌شوند).
	 * @return array|WP_Error
	 */
	public function submit( $args ) {
		$get = function ( $key, $textarea = false ) use ( $args ) {
			$v = isset( $args[ $key ] ) ? (string) $args[ $key ] : '';
			return $textarea ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		};

		// ضد‌اسپم آفلاین (Honeypot + تله‌زمان) — بدون نیاز به سرویس خارجی (مناسب شرایط تحریم).
		$honeypot = trim( isset( $args['nfx_hp'] ) ? (string) $args['nfx_hp'] : '' );
		$elapsed  = isset( $args['nfx_elapsed'] ) ? (int) $args['nfx_elapsed'] : 99999;
		$min_ms   = (int) apply_filters( 'ssc_chatbot_min_form_time', 1500 );
		if ( '' !== $honeypot || $elapsed < $min_ms ) {
			// پاسخ موفقیت تقلبی تا ربات متوجه فیلتر نشود (بدون ذخیره).
			return array( 'message' => __( 'دریافت شد.', 'smart-support-chatbot' ) );
		}

		if ( ! $this->check_rate_limit( 'submit' ) ) {
			return new WP_Error(
				'ssc_rate_limited',
				__( 'محدودیت روزانه درخواست پر شده. لطفاً فردا مجدداً تلاش کنید.', 'smart-support-chatbot' ),
				array( 'status' => 429 )
			);
		}

		$type        = '' !== $get( 'type' ) ? $get( 'type' ) : __( 'نامشخص', 'smart-support-chatbot' );
		$name        = $get( 'name' );
		$phone       = $get( 'phone' );
		$description = $get( 'description', true );
		$product     = $get( 'product' );

		// فیلدهای استاندارد گزارش عوارض دارویی (ADR).
		$severity          = $get( 'severity' );
		$outcome           = $get( 'outcome' );
		$batch_number      = $get( 'batch_number' );
		$concomitant_drugs = $get( 'concomitant_drugs', true );
		$reporter_type     = $get( 'reporter_type' );

		// اعتبارسنجی پایه.
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) {
			return new WP_Error( 'ssc_invalid_name', __( 'نام نامعتبر است.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}
		/**
		 * الگوی اعتبارسنجی شماره تماس. پیش‌فرض: پذیرش شمارهٔ بین‌المللی (E.164) و موبایل ایران.
		 * برای محدودسازی به کشور خاص، این فیلتر را بازنویسی کنید.
		 *
		 * @param string $regex الگوی regex.
		 */
		$phone_regex = (string) apply_filters( 'ssc_chatbot_phone_regex', '/^(\+?\d[\d\s\-]{6,18}\d)$/' );
		if ( ! preg_match( $phone_regex, $phone ) ) {
			return new WP_Error( 'ssc_invalid_phone', __( 'شماره تماس نامعتبر است.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $description ) < 10 || mb_strlen( $description ) > 1000 ) {
			return new WP_Error( 'ssc_invalid_description', __( 'طول توضیحات نامعتبر است.', 'smart-support-chatbot' ), array( 'status' => 400 ) );
		}

		$is_adr = ( false !== mb_strpos( $type, 'عوارض' ) );

		// اعتبارسنجی گزینه‌های ADR در برابر مقادیر مجاز.
		if ( $is_adr ) {
			$opts = SSC_Chatbot_Settings::adr_options();
			if ( $severity && ! in_array( $severity, $opts['severity'], true ) ) {
				$severity = '';
			}
			if ( $outcome && ! in_array( $outcome, $opts['outcome'], true ) ) {
				$outcome = '';
			}
			if ( $reporter_type && ! in_array( $reporter_type, $opts['reporter_type'], true ) ) {
				$reporter_type = '';
			}
		}

		// فیلدهای سفارشی فرم‌ساز پویا (تعریف‌شده در تنظیمات).
		$extra = $this->validate_extra_fields( isset( $args['extra'] ) ? (string) $args['extra'] : '' );
		if ( is_wp_error( $extra ) ) {
			return $extra;
		}

		$row = array(
			'type'              => $type,
			'name'              => $name,
			'phone'             => $phone,
			'description'       => $description,
			'product'           => $product ? $product : null,
			'severity'          => $is_adr && $severity ? $severity : null,
			'outcome'           => $is_adr && $outcome ? $outcome : null,
			'batch_number'      => $is_adr && $batch_number ? $batch_number : null,
			'concomitant_drugs' => $is_adr && $concomitant_drugs ? $concomitant_drugs : null,
			'reporter_type'     => $is_adr && $reporter_type ? $reporter_type : null,
			'extra_fields'      => $extra ? wp_json_encode( $extra ) : null,
			'ip'                => $this->get_ip(),
		);

		$id = SSC_Chatbot_DB::insert( $row );
		if ( ! $id ) {
			return new WP_Error(
				'ssc_save_failed',
				__( 'خطا در ذخیره‌سازی اطلاعات. لطفاً مجدداً تلاش کنید.', 'smart-support-chatbot' ),
				array( 'status' => 500 )
			);
		}

		// اعلان‌ها.
		$this->maybe_send_messenger_notification( $row );
		$this->maybe_send_email_notification( $row );

		/**
		 * اکشن پس از ثبت موفق درخواست.
		 */
		do_action( 'ssc_chatbot_after_submit', $id, $row );

		return array( 'message' => __( 'اطلاعات با موفقیت ثبت و ارسال شد.', 'smart-support-chatbot' ) );
	}

	/**
	 * اعتبارسنجی و پاکسازی پاسخ‌های فیلدهای سفارشی فرم‌ساز پویا در برابر تعریف تنظیمات.
	 *
	 * @param string $raw_json رشتهٔ JSON دریافتی از کلاینت ({ key: value, ... }).
	 * @return array|WP_Error نگاشت key => مقدار پاکسازی‌شده.
	 */
	protected function validate_extra_fields( $raw_json ) {
		$fields = SSC_Chatbot_Settings::form_fields();
		if ( empty( $fields ) ) {
			return array();
		}
		$raw = array();
		if ( '' !== trim( $raw_json ) ) {
			$decoded = json_decode( $raw_json, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}

		$out = array();
		foreach ( $fields as $f ) {
			$key   = $f['key'];
			$value = isset( $raw[ $key ] ) ? $raw[ $key ] : '';

			if ( 'checkbox' === $f['type'] ) {
				$checked = ! empty( $value ) && 'false' !== $value && '0' !== (string) $value;
				if ( $f['required'] && ! $checked ) {
					/* translators: %s: برچسب فیلد. */
					return new WP_Error( 'ssc_field_required', sprintf( __( 'تکمیل فیلد «%s» الزامی است.', 'smart-support-chatbot' ), $f['label'] ), array( 'status' => 400 ) );
				}
				if ( $checked ) {
					$out[ $key ] = __( 'بله', 'smart-support-chatbot' );
				}
				continue;
			}

			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			$value = ( 'textarea' === $f['type'] ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

			if ( in_array( $f['type'], array( 'select', 'radio' ), true ) && '' !== $value && ! in_array( $value, $f['options'], true ) ) {
				$value = ''; // مقدار خارج از گزینه‌های مجاز، نادیده گرفته می‌شود.
			}

			if ( '' === $value ) {
				if ( $f['required'] ) {
					/* translators: %s: برچسب فیلد. */
					return new WP_Error( 'ssc_field_required', sprintf( __( 'تکمیل فیلد «%s» الزامی است.', 'smart-support-chatbot' ), $f['label'] ), array( 'status' => 400 ) );
				}
				continue;
			}

			if ( 'email' === $f['type'] && ! is_email( $value ) ) {
				/* translators: %s: برچسب فیلد. */
				return new WP_Error( 'ssc_field_invalid', sprintf( __( 'مقدار فیلد «%s» معتبر نیست.', 'smart-support-chatbot' ), $f['label'] ), array( 'status' => 400 ) );
			}

			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * ساخت متن پیام اعلان.
	 *
	 * @param array $row داده‌ها.
	 * @return string
	 */
	protected function is_serious_adr( $row ) {
		$serious = apply_filters( 'ssc_chatbot_serious_severities', array( 'شدید', 'تهدیدکننده حیات', 'منجر به بستری شد', 'فوت' ) );
		return ! empty( $row['severity'] ) && in_array( $row['severity'], $serious, true );
	}

	/**
	 * ساخت متن اعلان (ایمیل/وبهوک) برای یک درخواست ثبت‌شده.
	 *
	 * @param array $row ردیف درخواست.
	 * @return string
	 */
	protected function build_notification_text( $row ) {
		$msg = '';
		if ( $this->is_serious_adr( $row ) ) {
			$msg .= "🚨🚨🚨 هشدار فوری — گزارش عارضهٔ جدی 🚨🚨🚨\n\n";
		}
		/* translators: %s: نام سایت. */
		$msg .= '📥 ' . sprintf( __( 'دریافت درخواست جدید از %s', 'smart-support-chatbot' ), get_bloginfo( 'name' ) ) . "\n\n";
		$msg .= '📋 نوع فرم: ' . $row['type'] . "\n";
		$msg .= '👤 نام کاربر: ' . $row['name'] . "\n";
		$msg .= '📞 شماره تماس: ' . $row['phone'] . "\n";
		if ( ! empty( $row['product'] ) ) {
			$msg .= '💊 محصول مرتبط: ' . $row['product'] . "\n";
		}

		// بخش استاندارد گزارش عوارض دارویی.
		$has_adr = ! empty( $row['severity'] ) || ! empty( $row['outcome'] ) || ! empty( $row['batch_number'] ) || ! empty( $row['concomitant_drugs'] ) || ! empty( $row['reporter_type'] );
		if ( $has_adr ) {
			$msg .= "\n— — — گزارش استاندارد عارضه — — —\n";
			if ( ! empty( $row['reporter_type'] ) ) {
				$msg .= '🧑‍⚕️ نوع گزارش‌دهنده: ' . $row['reporter_type'] . "\n";
			}
			if ( ! empty( $row['severity'] ) ) {
				$msg .= '⚠️ شدت عارضه: ' . $row['severity'] . "\n";
			}
			if ( ! empty( $row['outcome'] ) ) {
				$msg .= '🏁 پیامد: ' . $row['outcome'] . "\n";
			}
			if ( ! empty( $row['batch_number'] ) ) {
				$msg .= '🔢 شماره سری ساخت (Batch): ' . $row['batch_number'] . "\n";
			}
			if ( ! empty( $row['concomitant_drugs'] ) ) {
				$msg .= '💊 داروهای مصرفی همزمان: ' . $row['concomitant_drugs'] . "\n";
			}
		}

		// فیلدهای سفارشی فرم‌ساز پویا.
		if ( ! empty( $row['extra_fields'] ) ) {
			$extra = json_decode( (string) $row['extra_fields'], true );
			if ( is_array( $extra ) && $extra ) {
				$labels = wp_list_pluck( SSC_Chatbot_Settings::form_fields(), 'label', 'key' );
				$msg   .= "\n— — — فیلدهای سفارشی — — —\n";
				foreach ( $extra as $key => $value ) {
					$label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
					$msg  .= '▫️ ' . $label . ': ' . $value . "\n";
				}
			}
		}

		$msg .= "\n📝 شرح:\n" . $row['description'] . "\n\n";
		$msg .= '⏰ زمان ثبت: ' . current_time( 'H:i - Y/m/d' );
		return $msg;
	}

	/**
	 * ارسال اعلان به پیام‌رسان (بله یا تلگرام).
	 *
	 * @param array $row داده‌ها.
	 */
	protected function maybe_send_messenger_notification( $row ) {
		if ( 'yes' !== SSC_Chatbot_Settings::get( 'notify_enabled', 'no' ) ) {
			return;
		}
		$token   = SSC_Chatbot_Settings::get_secret( 'notify_token' );
		$chat_id = SSC_Chatbot_Settings::get( 'notify_chat_id', '' );
		if ( empty( $token ) || empty( $chat_id ) ) {
			return;
		}
		$platform = SSC_Chatbot_Settings::get( 'notify_platform', 'bale' );
		$base     = 'telegram' === $platform ? 'https://api.telegram.org/bot' : 'https://tapi.bale.ai/bot';
		$url      = $base . $token . '/sendMessage';

		wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'body'    => array(
					'chat_id' => $chat_id,
					'text'    => $this->build_notification_text( $row ),
				),
			)
		);
	}

	/**
	 * ارسال اعلان ایمیلی.
	 *
	 * @param array $row داده‌ها.
	 */
	protected function maybe_send_email_notification( $row ) {
		if ( 'yes' !== SSC_Chatbot_Settings::get( 'email_enabled', 'no' ) ) {
			return;
		}
		$to = SSC_Chatbot_Settings::get( 'email_to', '' );
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		$subject = 'درخواست جدید: ' . $row['type'];
		wp_mail( $to, $subject, $this->build_notification_text( $row ) );
	}
}
