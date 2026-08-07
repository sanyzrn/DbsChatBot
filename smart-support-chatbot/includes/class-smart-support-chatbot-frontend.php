<?php
/**
 * بخش نمایش در سایت (Frontend).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس Frontend.
 */
class SSC_Chatbot_Frontend {

	/**
	 * آیا اسکریپت‌ها بارگذاری شده‌اند.
	 *
	 * @var bool
	 */
	protected $assets_done = false;

	/**
	 * آیا ویجت در صفحه رندر شده (برای جلوگیری از رندر دوباره ویجت شناور).
	 *
	 * @var bool
	 */
	protected $rendered = false;

	/**
	 * راه‌اندازی.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'ssc_chatbot', array( $this, 'shortcode' ) );

		// در صورت فعال بودن نمایش خودکار شناور.
		add_action( 'wp_footer', array( $this, 'maybe_render_floating' ) );
	}

	/**
	 * ثبت استایل و اسکریپت.
	 */
	public function register_assets() {
		wp_register_style(
			'smart-support-chatbot',
			SSC_CHATBOT_URL . 'assets/css/smart-support-chatbot.css',
			array(),
			SSC_CHATBOT_VERSION
		);

		// فونت‌های میزبانی‌شدهٔ محلی (بدون CDN خارجی).
		wp_register_style(
			'smart-support-chatbot-fonts',
			SSC_CHATBOT_URL . 'assets/css/fonts.css',
			array(),
			SSC_CHATBOT_VERSION
		);

		wp_register_script(
			'smart-support-chatbot',
			SSC_CHATBOT_URL . 'assets/js/smart-support-chatbot.js',
			array(),
			SSC_CHATBOT_VERSION,
			true
		);
	}

	/**
	 * تزریق داده‌های پیکربندی به جاوااسکریپت.
	 *
	 * @param array $overrides تنظیمات سفارشی از ویجت المنتور.
	 */
	public function enqueue_with_config( $overrides = array() ) {
		wp_enqueue_style( 'smart-support-chatbot' );
		wp_enqueue_script( 'smart-support-chatbot' );

		if ( $this->assets_done ) {
			return;
		}
		$this->assets_done = true;

		$s = SSC_Chatbot_Settings::all();

		// بارگذاری فونت انتخاب‌شده (فقط در صورت نیاز — از بارگذاری بی‌مورد جلوگیری می‌شود).
		$font_stack = $this->enqueue_font( $s );

		// ساخت دادهٔ محصولات برای کارت محصول (تصویر + خلاصهٔ کوتاه).
		$knowledge_map = (array) $s['product_knowledge'];
		$products_cfg  = array();
		foreach ( (array) $s['products'] as $p ) {
			if ( empty( $p['id'] ) ) {
				continue;
			}
			$pid     = $p['id'];
			$summary = isset( $p['summary'] ) ? trim( (string) $p['summary'] ) : '';
			// در نبود خلاصهٔ دستی، از ابتدای پایگاه دانش محصول استفاده می‌شود.
			if ( '' === $summary && ! empty( $knowledge_map[ $pid ] ) ) {
				$summary = wp_strip_all_tags( (string) $knowledge_map[ $pid ] );
				$summary = mb_substr( trim( $summary ), 0, 160 );
			}
			$products_cfg[] = array(
				'id'       => $pid,
				'name'     => isset( $p['name'] ) ? $p['name'] : $pid,
				'brochure' => isset( $p['brochure'] ) ? $p['brochure'] : '',
				'image'    => isset( $p['image'] ) ? $p['image'] : '',
				'summary'  => $summary,
			);
		}

		// ادغام تنظیمات سراسری با تنظیمات ویجت.
		$config = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'ssc_chatbot_nonce' ),
			'businessMode'   => isset( $s['business_mode'] ) ? $s['business_mode'] : 'general',
			'companyId'      => $s['company_id'],
			'companyName'    => '' !== trim( (string) $s['company_name'] ) ? $s['company_name'] : get_bloginfo( 'name' ),
			'headerTitle'    => $s['header_title'],
			'welcomeTitle'   => $s['welcome_title'],
			'welcomeText'    => $s['welcome_text'],
			'disclaimer'     => $s['disclaimer'],
			'show'           => array(
				'company'  => 'yes' === $s['show_company'],
				'products' => 'yes' === $s['show_products'],
				'adr'      => 'yes' === $s['show_adr'],
				'consult'  => 'yes' === $s['show_consult'],
			),
			'labels'         => array(
				'companyTitle'  => $s['company_btn_title'],
				'companyDesc'   => $s['company_btn_desc'],
				'productsTitle' => $s['products_btn_title'],
				'productsDesc'  => $s['products_btn_desc'],
				'adrTitle'      => $s['adr_btn_title'],
				'consultTitle'  => $s['consult_btn_title'],
			),
			'products'       => array_values( $products_cfg ),
			'supportPhone'   => $s['support_phone'],
			'position'       => $s['position'],
			'primaryColor'   => $s['primary_color'],
			'primaryHover'   => $s['primary_hover'],
			'themeMode'      => $s['theme_mode'],
			'buttonSize'     => $s['button_size'],
			'iconSize'       => $s['icon_size'],
			'buttonRadius'   => $s['button_radius'],
			'buttonIconUrl'  => $s['button_icon_url'],
			// فونت و استایل پنجره (مبتنی بر متغیرهای CSS).
			'fontStack'      => $font_stack,
			'fontSize'       => (int) $s['font_size'],
			'windowWidth'    => (int) $s['window_width'],
			'windowRadius'   => (int) $s['window_radius'],
			'bubbleRadius'   => (int) $s['bubble_radius'],
			'userBubble'     => $s['user_bubble_color'],
			'botBubble'      => $s['bot_bubble_color'],
			'quickRepliesEnabled' => ( 'yes' === $s['quick_replies_enabled'] ),
			'quickReplies'   => array_values( (array) $s['quick_replies'] ),
			'brochureLabel'  => 'مشاهده بروشور',
			'adrOptions'     => SSC_Chatbot_Settings::adr_options(),
			'feedbackEnabled'   => ( 'yes' === $s['feedback_enabled'] ),
			'typewriter'        => ( 'yes' === $s['typewriter_enabled'] ),
			'proactiveEnabled'  => ( 'yes' === $s['proactive_enabled'] ),
			'proactiveDelay'    => (int) $s['proactive_delay'],
			'proactiveText'     => $s['proactive_text'],
			'online'            => SSC_Chatbot_Settings::is_online(),
			'statusText'        => SSC_Chatbot_Settings::is_online() ? $s['online_text'] : $s['offline_text'],

			// قابلیت‌های نسخه ۲.۵.
			'suggestionsEnabled'  => ( 'yes' === $s['suggestions_enabled'] ),
			'autocompleteEnabled' => ( 'yes' === $s['autocomplete_enabled'] ),
			'voiceEnabled'        => ( 'yes' === $s['voice_enabled'] ),
			'csatEnabled'         => ( 'yes' === $s['csat_enabled'] ),
			'handoffEnabled'      => ( 'yes' === $s['handoff_enabled'] ),
			'handoffText'         => $s['handoff_text'],
			'consentEnabled'      => ( 'yes' === $s['consent_enabled'] ),
			'consentText'         => $s['consent_text'],
			'consentLink'         => $s['consent_link'],
			'i18n'                => array(
				'handoffBtn'      => __( 'گفتگو با کارشناس انسانی', 'smart-support-chatbot' ),
				'csatTitle'       => __( 'گفتگوی ما چطور بود؟', 'smart-support-chatbot' ),
				'csatThanks'      => __( 'سپاس از امتیاز شما 🙏', 'smart-support-chatbot' ),
				'csatSkip'        => __( 'رد کردن', 'smart-support-chatbot' ),
				'mainMenu'        => __( 'منوی اصلی', 'smart-support-chatbot' ),
				'speak'           => __( 'شنیدن پاسخ', 'smart-support-chatbot' ),
				'speakStop'       => __( 'توقف صدا', 'smart-support-chatbot' ),
				'mic'             => __( 'گفتن با صدا', 'smart-support-chatbot' ),
				'micListening'    => __( 'در حال شنیدن…', 'smart-support-chatbot' ),
				'suggestionsHint' => __( 'سوالات مرتبط:', 'smart-support-chatbot' ),
				'consentRequired' => __( 'برای ادامه، موافقت با حریم خصوصی الزامی است.', 'smart-support-chatbot' ),
				'privacy'         => __( 'سیاست حریم خصوصی', 'smart-support-chatbot' ),
				'copy'            => __( 'کپی پاسخ', 'smart-support-chatbot' ),
				'copied'          => __( 'کپی شد ✓', 'smart-support-chatbot' ),
				'callUs'          => __( 'تماس با ما', 'smart-support-chatbot' ),
				'brochureBtn'     => __( 'بروشور', 'smart-support-chatbot' ),
				'urgentReport'    => __( 'گزارش فوری عارضه', 'smart-support-chatbot' ),
				'sent'            => __( 'ارسال شد', 'smart-support-chatbot' ),
			),
		);

		// اعمال overrides از ویجت.
		$config = $this->apply_overrides( $config, $overrides );

		wp_localize_script( 'smart-support-chatbot', 'SSCChatbotConfig', $config );
	}

	/**
	 * بارگذاری فونت انتخاب‌شده و بازگرداندن رشتهٔ font-family برای متغیر CSS.
	 * فقط فونت لازم بارگذاری می‌شود (system هیچ درخواست خارجی ندارد).
	 *
	 * @param array $s تنظیمات.
	 * @return string رشتهٔ font-family (خالی = پیش‌فرض شیوه‌نامه).
	 */
	protected function enqueue_font( $s ) {
		$family = isset( $s['font_family'] ) ? $s['font_family'] : 'vazirmatn';

		// پشتهٔ سیستمی به‌عنوان fallback همهٔ حالت‌ها (بدون هیچ درخواست شبکه).
		$system_stack = "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";

		// فونت سفارشی: آدرس را خود مدیر سایت آگاهانه وارد کرده است.
		if ( 'custom' === $family ) {
			$url  = isset( $s['font_url'] ) ? $s['font_url'] : '';
			$name = isset( $s['font_name'] ) ? trim( (string) $s['font_name'] ) : '';
			if ( $url ) {
				wp_enqueue_style( 'smart-support-chatbot-font-custom', $url, array(), SSC_CHATBOT_VERSION );
			}
			return $name ? "'" . $name . "', sans-serif" : $system_stack;
		}

		if ( 'system' === $family ) {
			return $system_stack;
		}

		// فونت‌های همراه افزونه — فقط از فایل محلی.
		$bundled = array(
			'vazirmatn' => array(
				'probe' => 'vazirmatn-400.woff2',
				'stack' => "'Vazirmatn', 'Vazir', Tahoma, sans-serif",
				// در نبود فایل، پشتهٔ امنِ فارسی بدون درخواست خارجی.
				'fallback' => "Tahoma, 'Segoe UI', sans-serif",
			),
			'inter'     => array(
				'probe'    => 'inter-400.woff2',
				'stack'    => "'Inter', system-ui, sans-serif",
				'fallback' => $system_stack,
			),
			'roboto'    => array(
				'probe'    => 'roboto-400.woff2',
				'stack'    => "'Roboto', system-ui, sans-serif",
				'fallback' => $system_stack,
			),
		);

		if ( ! isset( $bundled[ $family ] ) ) {
			return $system_stack;
		}

		// فایل فونت باید واقعاً همراه افزونه باشد؛ در غیر این صورت به فونت سیستمی برمی‌گردیم
		// و هیچ درخواستی به CDN خارجی ارسال نمی‌شود (پایداری + حریم خصوصی).
		if ( ! file_exists( SSC_CHATBOT_DIR . 'assets/fonts/' . $bundled[ $family ]['probe'] ) ) {
			return $bundled[ $family ]['fallback'];
		}

		wp_enqueue_style( 'smart-support-chatbot-fonts' );
		return $bundled[ $family ]['stack'];
	}

	/**
	 * اعمال تنظیمات سفارشی ویجت روی پیکربندی.
	 *
	 * @param array $config    پیکربندی پایه.
	 * @param array $overrides تنظیمات ویجت.
	 * @return array
	 */
	protected function apply_overrides( $config, $overrides ) {
		if ( empty( $overrides ) || ! is_array( $overrides ) ) {
			return $config;
		}
		$map = array(
			'header_title'  => 'headerTitle',
			'welcome_title' => 'welcomeTitle',
			'welcome_text'  => 'welcomeText',
			'company_name'  => 'companyName',
			'position'      => 'position',
			'primary_color' => 'primaryColor',
			'primary_hover' => 'primaryHover',
			'theme_mode'    => 'themeMode',
			'disclaimer'    => 'disclaimer',
			// استایل پنجره (override اختیاری المنتور).
			'font_size'         => 'fontSize',
			'window_width'      => 'windowWidth',
			'window_radius'     => 'windowRadius',
			'bubble_radius'     => 'bubbleRadius',
			'user_bubble_color' => 'userBubble',
			'bot_bubble_color'  => 'botBubble',
		);
		foreach ( $map as $from => $to ) {
			if ( isset( $overrides[ $from ] ) && '' !== $overrides[ $from ] ) {
				$config[ $to ] = $overrides[ $from ];
			}
		}
		// نمایش گزینه‌ها.
		foreach ( array( 'company', 'products', 'adr', 'consult' ) as $opt ) {
			$key = 'show_' . $opt;
			if ( isset( $overrides[ $key ] ) && '' !== $overrides[ $key ] ) {
				$config['show'][ $opt ] = ( 'yes' === $overrides[ $key ] || '1' === (string) $overrides[ $key ] );
			}
		}
		// محصولات سفارشی.
		if ( ! empty( $overrides['products'] ) && is_array( $overrides['products'] ) ) {
			$config['products'] = array_values( $overrides['products'] );
		}
		return $config;
	}

	/**
	 * خروجی HTML کانتینر چت‌بات.
	 *
	 * @param array $overrides تنظیمات ویجت.
	 * @return string
	 */
	public function render( $overrides = array() ) {
		if ( $this->rendered ) {
			return '';
		}
		$this->rendered = true;
		$this->enqueue_with_config( $overrides );

		return '<div id="smart-support-chatbot-root" class="nfx-root" dir="rtl" aria-live="polite"></div>';
	}

	/**
	 * شورت‌کد.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'position' => '',
			),
			$atts,
			'ssc_chatbot'
		);
		return $this->render( array_filter( $atts ) );
	}

	/**
	 * نمایش خودکار دکمه شناور اگر در تنظیمات فعال باشد.
	 */
	public function maybe_render_floating() {
		if ( 'yes' !== SSC_Chatbot_Settings::get( 'enabled', 'yes' ) ) {
			return;
		}
		// اگر قبلاً توسط ویجت یا شورت‌کد رندر شده، دوباره رندر نکن.
		if ( $this->rendered ) {
			return;
		}
		echo wp_kses_post( $this->render() );
	}

	/**
	 * بررسی اینکه آیا قبلاً رندر شده.
	 *
	 * @return bool
	 */
	public function is_rendered() {
		return $this->rendered;
	}
}
