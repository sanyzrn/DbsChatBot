<?php
/**
 * مدیریت تنظیمات و گزینه‌های افزونه.
 *
 * @package NafasChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس تنظیمات.
 */
class Nafas_Chatbot_Settings {

	/**
	 * کلید گزینه در دیتابیس.
	 */
	const OPTION_KEY = 'nafas_chatbot_settings';

	/**
	 * کش تنظیمات.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * مقادیر پیش‌فرض تنظیمات.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// عمومی.
			'enabled'            => 'yes',
			// حالت کسب‌وکار: general (عمومی) | pharma (داروسازی — فرم استاندارد عوارض دارویی فعال می‌شود).
			'business_mode'      => 'general',
			'company_name'       => '',
			'company_id'         => 'company',
			'welcome_title'      => 'سلام! 👋',
			'welcome_text'       => 'به پشتیبانی آنلاین ما خوش آمدید.<br>چطور می‌تونم کمکتون کنم؟',
			'header_title'       => 'دستیار هوشمند',
			'disclaimer'         => 'هوش مصنوعی ممکن است اشتباه کند.',
			'support_phone'      => '', // شماره تماس پشتیبانی برای دکمهٔ «تماس با ما» داخل پاسخ‌ها.

			// نمایش گزینه‌های منو (پیش‌فرض عمومی: سوال از ما + درخواست مشاوره).
			'show_company'       => 'yes',
			'show_products'      => 'no',
			'show_adr'           => 'no',
			'show_consult'       => 'yes',

			// متن دکمه‌های منو.
			'company_btn_title'  => 'سوال از ما',
			'company_btn_desc'   => 'دربارهٔ ما، خدمات و اطلاعات تماس',
			'products_btn_title' => 'سوال در مورد محصولات',
			'products_btn_desc'  => 'اطلاعات محصولات و خدمات',
			'adr_btn_title'      => 'ثبت عوارض',
			'consult_btn_title'  => 'درخواست مشاوره',

			// محصولات (هر محصول می‌تواند لینک بروشور/تصویر داشته باشد). پیش‌فرض خالی — کاربر خودش اضافه می‌کند.
			'products'           => array(),

			// پاسخ‌های پیشنهادی (Quick Replies) در گفتگوی محصول.
			'quick_replies_enabled' => 'yes',
			'quick_replies'         => array(
				array( 'label' => 'قیمت', 'question' => 'قیمت این محصول چقدر است؟' ),
				array( 'label' => 'نحوه استفاده', 'question' => 'نحوه استفاده از این محصول چگونه است؟' ),
				array( 'label' => 'گارانتی', 'question' => 'شرایط گارانتی یا پشتیبانی این محصول چیست؟' ),
			),

			// ظاهر.
			'position'           => 'right', // right | left.
			'primary_color'      => '#b61615',
			'primary_hover'      => '#991211',
			'theme_mode'         => 'light', // light | dark | auto.

			// فونت و استایل پنجرهٔ چت (مبتنی بر متغیرهای CSS).
			'font_family'        => 'vazirmatn', // vazirmatn | system | inter | roboto | custom.
			'font_name'          => '',   // نام خانوادهٔ فونت سفارشی (وقتی font_family = custom).
			'font_url'           => '',   // آدرس شیوه‌نامهٔ فونت سفارشی (@font-face).
			'font_size'          => 14,   // اندازهٔ پایهٔ متن پیام‌ها (px).
			'window_width'       => 384,  // عرض پنجرهٔ چت (px).
			'window_radius'      => 24,   // گردی گوشه‌های پنجره (px).
			'bubble_radius'      => 16,   // گردی گوشه‌های حباب پیام (px).
			'user_bubble_color'  => '',   // رنگ حباب کاربر (خالی = رنگ اصلی).
			'bot_bubble_color'   => '',   // رنگ حباب ربات (خالی = پس‌زمینهٔ کارت).

			// آیکون شناور (سفارشی‌سازی).
			'button_size'        => 60, // قطر دکمه بر حسب پیکسل.
			'icon_size'          => 28, // اندازه آیکون بر حسب پیکسل.
			'button_radius'      => 50, // گردی گوشه‌های دکمه (درصد؛ ۵۰ = دایره کامل).
			'button_icon_url'    => '', // تصویر اختصاصی آیکون (اختیاری).

			// هوش مصنوعی.
			'ai_provider'        => 'fallback', // fallback | gemini | openai | claude | custom | webhook.
			'gemini_api_key'     => '',
			'gemini_model'       => 'gemini-2.0-flash',

			// OpenAI.
			'openai_api_key'     => '',
			'openai_model'       => 'gpt-4o-mini',

			// Anthropic Claude.
			'claude_api_key'     => '',
			'claude_model'       => 'claude-opus-4-8',

			// Custom (سازگار با OpenAI — هر شرکتی با لینک اختصاصی).
			'custom_api_key'     => '',
			'custom_endpoint'    => '',
			'custom_model'       => '',

			'ai_webhook_url'     => '',
			'ai_webhook_secret'  => '',
			'ai_system_prompt'   => 'شما دستیار هوشمند پشتیبانی این کسب‌وکار هستید. به سوالات کاربران به زبان فارسی، دقیق، کوتاه و محترمانه پاسخ دهید. اگر پاسخ سوالی را نمی‌دانید، کاربر را به ثبت «درخواست مشاوره» یا تماس با پشتیبانی راهنمایی کنید.',
			'ai_fallback_msg'    => 'سپاس از پیام شما. اکنون امکان پاسخ‌گویی خودکار به این سوال وجود ندارد. برای دریافت اطلاعات دقیق‌تر می‌توانید با پشتیبانی تماس بگیرید یا از بخش «درخواست مشاوره» استفاده کنید.',
			'ai_rate_limit'      => 100, // درخواست در روز برای هر IP.
			'rate_limit_mode'    => 'ip', // ip | session | both | off — روش محدودسازی استفاده.
			'session_rate_limit' => 50,  // درخواست در روز برای هر نشست (مرورگر).
			'ai_history_limit'   => 8,   // حداکثر پیام‌های تاریخچه ارسالی به مدل (حافظه مکالمه).
			'ai_temperature'     => '0.4', // میزان خلاقیت (۰ = دقیق، ۱ = خلاق). برای Claude اعمال نمی‌شود.
			'ai_max_tokens'      => 800,   // حداکثر طول پاسخ.
			'ai_strict_knowledge' => 'no', // فقط بر اساس پایگاه دانش پاسخ بده.

			// بانک سوال/جواب آفلاین و تاریخچه گفتگو.
			'qa_mode'            => 'ai_first', // ai_first | bank_first | bank_only.
			'qa_bank'            => array(),    // آرایه‌ای از { product, question, keywords, answer }.
			'chatlog_enabled'    => 'yes',      // ذخیره گفتگوها برای افزودن به بانک.
			'chatlog_retention_days' => 90,     // پاک‌سازی خودکار تاریخچه قدیمی‌تر از این تعداد روز (۰ = بدون پاک‌سازی).
			'submissions_retention_days' => 0,  // پاک‌سازی خودکار درخواست‌های قدیمی (۰ = نگهداری همیشگی).
			'ai_cache_enabled'   => 'yes',      // کش پاسخ هوش مصنوعی برای سوال‌های بدون تاریخچه.

			// تجربه کاربری پیشرفته.
			'feedback_enabled'   => 'yes',      // دکمه‌های بازخورد 👍/👎.
			'typewriter_enabled' => 'yes',      // افکت تایپ تدریجی پاسخ.

			// قابلیت‌های نسخه ۲.۵.
			'suggestions_enabled'  => 'yes',    // چیپس‌های پیگیری هوشمند پس از هر پاسخ.
			'autocomplete_enabled' => 'yes',    // تکمیل خودکار سوال از بانک هنگام تایپ.
			'voice_enabled'        => 'yes',    // ورودی/خروجی صوتی (Web Speech API، سمت کلاینت).
			'csat_enabled'         => 'yes',    // نظرسنجی رضایت پایان گفتگو (CSAT).
			'handoff_enabled'      => 'yes',    // واگذاری به کارشناس انسانی هنگام بی‌پاسخ ماندن.
			'handoff_text'         => 'به نظر می‌رسد بهتر است این موضوع را با یک کارشناس انسانی در میان بگذارید. مایل هستید درخواست مشاوره ثبت کنید؟',
			'consent_enabled'      => 'no',     // الزام موافقت با حریم خصوصی در فرم‌ها.
			'consent_text'         => 'با ثبت این فرم، با ذخیره و پردازش اطلاعات تماس خود جهت پیگیری موافقت می‌کنم.',
			'consent_link'         => '',       // لینک سیاست حریم خصوصی (اختیاری).

			// پیام دعوت هوشمند.
			'proactive_enabled'  => 'yes',
			'proactive_delay'    => 12,         // ثانیه.
			'proactive_text'     => 'سوالی دارید؟ همین‌جا بپرسید 👋',

			// ساعات کاری / وضعیت آنلاین.
			'office_enabled'     => 'no',
			'office_start'       => 8,          // ساعت شروع (۰-۲۳).
			'office_end'         => 16,         // ساعت پایان.
			'office_days'        => array( 6, 0, 1, 2, 3 ), // شنبه=6 ... چهارشنبه=3 (شمارهٔ روز هفته PHP: یکشنبه=0).
			'offline_text'       => 'خارج از ساعت کاری',
			'online_text'        => 'آنلاین',

			// دانش محصولات (per-product knowledge base).
			'product_knowledge'  => array(),

			// پایگاه دانش هیبریدی (KB Engine سبک — نسخهٔ ۲.۶).
			'kb_enabled'         => 'yes', // تزریق تکه‌های مرتبط پایگاه دانش به پرامپت هوش مصنوعی.
			'kb_max_chunks'      => 3,      // حداکثر تعداد تکه‌های تزریق‌شده در هر پاسخ.

			// اعلان‌ها (بات بله / تلگرام).
			'notify_enabled'     => 'no',
			'notify_platform'    => 'bale', // bale | telegram.
			'notify_token'       => '',
			'notify_chat_id'     => '',

			// اعلان ایمیل.
			'email_enabled'      => 'no',
			'email_to'           => '',
		);
	}

	/**
	 * دریافت تمام تنظیمات (ادغام با پیش‌فرض‌ها).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved        = get_option( self::OPTION_KEY, array() );
		$saved        = is_array( $saved ) ? $saved : array();
		self::$cache  = wp_parse_args( $saved, self::defaults() );
		return self::$cache;
	}

	/**
	 * دریافت یک گزینه.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار پیش‌فرض.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( isset( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * ذخیره تنظیمات.
	 *
	 * @param array $settings تنظیمات.
	 */
	public static function update( $settings ) {
		$merged = wp_parse_args( $settings, self::all() );
		// autoload = no: گزینه ممکن است با product_knowledge بزرگ شود؛ روی هر بارگذاری صفحه لازم نیست.
		update_option( self::OPTION_KEY, $merged, false );
		self::$cache = null;
	}

	/**
	 * تنظیم مقادیر پیش‌فرض هنگام فعال‌سازی.
	 */
	public static function set_defaults() {
		$existing = get_option( self::OPTION_KEY, false );
		if ( false === $existing ) {
			add_option( self::OPTION_KEY, self::defaults(), '', 'no' );
		}
	}

	/**
	 * فیلدهای حساس که رمزنگاری می‌شوند.
	 *
	 * @return array
	 */
	public static function secret_fields() {
		return array( 'gemini_api_key', 'openai_api_key', 'claude_api_key', 'custom_api_key', 'notify_token', 'ai_webhook_secret' );
	}

	/**
	 * رمزنگاری یک مقدار (AES-256-CBC با کلید AUTH_KEY وردپرس).
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	public static function encrypt( $value ) {
		$value = (string) $value;
		if ( '' === $value || ! function_exists( 'openssl_encrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return $value;
		}
		$key       = hash( 'sha256', AUTH_KEY, true );
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $encrypted ) {
			return $value;
		}
		return 'enc::v1::' . base64_encode( $iv . $encrypted );
	}

	/**
	 * رمزگشایی یک مقدار (با پشتیبانی از مقادیر قدیمی plaintext).
	 *
	 * @param string $value مقدار ذخیره‌شده.
	 * @return string
	 */
	public static function decrypt( $value ) {
		$value = (string) $value;
		if ( 0 !== strpos( $value, 'enc::v1::' ) ) {
			return $value; // مقدار قدیمی plaintext.
		}
		if ( ! function_exists( 'openssl_decrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $value, 9 ) );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$key       = hash( 'sha256', AUTH_KEY, true );
		$iv        = substr( $raw, 0, 16 );
		$encrypted = substr( $raw, 16 );
		$dec       = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return ( false === $dec ) ? '' : $dec;
	}

	/**
	 * دریافت یک مقدار حساس به‌صورت رمزگشایی‌شده.
	 *
	 * @param string $key کلید.
	 * @return string
	 */
	public static function get_secret( $key ) {
		return self::decrypt( (string) self::get( $key, '' ) );
	}

	/**
	 * آیا یک مقدار حساس ذخیره شده است؟ (برای نمایش وضعیت در پنل بدون افشای مقدار)
	 *
	 * @param string $key کلید.
	 * @return bool
	 */
	public static function has_secret( $key ) {
		return '' !== trim( self::get_secret( $key ) );
	}

	/**
	 * دریافت لیست محصولات به صورت آرایه id => name.
	 *
	 * @return array
	 */
	public static function products_map() {
		$map = array();
		foreach ( (array) self::get( 'products', array() ) as $p ) {
			if ( ! empty( $p['id'] ) ) {
				$map[ $p['id'] ] = isset( $p['name'] ) ? $p['name'] : $p['id'];
			}
		}
		return $map;
	}

	/**
	 * آیا اکنون در ساعات کاری (آنلاین) هستیم؟
	 *
	 * @return bool
	 */
	public static function is_online() {
		if ( 'yes' !== self::get( 'office_enabled', 'no' ) ) {
			return true;
		}
		$now   = (int) current_time( 'G' );        // ساعت ۰-۲۳.
		$day   = (int) current_time( 'w' );        // روز هفته ۰=یکشنبه.
		$days  = (array) self::get( 'office_days', array( 6, 0, 1, 2, 3 ) );
		$start = (int) self::get( 'office_start', 8 );
		$end   = (int) self::get( 'office_end', 16 );
		if ( ! in_array( $day, array_map( 'intval', $days ), true ) ) {
			return false;
		}
		return ( $now >= $start && $now < $end );
	}

	/**
	 * گزینه‌های استاندارد فرم گزارش عوارض دارویی (فارماکوویژیلانس).
	 * قابل سفارشی‌سازی از طریق فیلتر.
	 *
	 * @return array
	 */
	public static function adr_options() {
		return apply_filters(
			'nafas_chatbot_adr_options',
			array(
				'severity'      => array( 'خفیف', 'متوسط', 'شدید', 'تهدیدکننده حیات' ),
				'outcome'       => array(
					'بهبود کامل یافت',
					'در حال بهبود',
					'بهبود نیافت',
					'عارضه ماندگار/ناتوانی',
					'منجر به بستری شد',
					'فوت',
					'نامشخص',
				),
				'reporter_type' => array( 'بیمار/مصرف‌کننده', 'پزشک', 'داروساز', 'پرستار', 'سایر کادر درمان' ),
			)
		);
	}
}
