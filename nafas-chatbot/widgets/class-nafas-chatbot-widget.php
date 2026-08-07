<?php
/**
 * ویجت المنتور برای چت‌بات.
 *
 * @package NafasChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

/**
 * کلاس ویجت المنتور.
 */
class Nafas_Chatbot_Elementor_Widget extends Widget_Base {

	/**
	 * نام ویجت.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'nafas_chatbot';
	}

	/**
	 * عنوان ویجت.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'دستیار هوشمند گفتگو', 'nafas-chatbot' );
	}

	/**
	 * آیکون ویجت.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-chat';
	}

	/**
	 * دسته‌بندی.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'nafas_chatbot', 'general' );
	}

	/**
	 * کلمات کلیدی جستجو.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'chat', 'chatbot', 'bot', 'ai', 'دستیار', 'پشتیبانی', 'مشاوره', 'گفتگو' );
	}

	/**
	 * این ویجت یک عنصر شناور است و در فوتر کانتینر تزریق می‌شود.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'nafas-chatbot' );
	}

	/**
	 * استایل‌های وابسته.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		// فونت به‌صورت شرطی و بر اساس انتخاب کاربر در enqueue_with_config() بارگذاری می‌شود.
		return array( 'nafas-chatbot' );
	}

	/**
	 * ثبت کنترل‌ها.
	 */
	protected function register_controls() {

		/* ---------- بخش: محتوا ---------- */
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'محتوا و متن‌ها', 'nafas-chatbot' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'header_title',
			array(
				'label'       => esc_html__( 'عنوان هدر', 'nafas-chatbot' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Nafas_Chatbot_Settings::get( 'header_title', 'دستیار هوشمند' ),
			)
		);

		$this->add_control(
			'company_name',
			array(
				'label'       => esc_html__( 'نام شرکت', 'nafas-chatbot' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Nafas_Chatbot_Settings::get( 'company_name', '' ),
			)
		);

		$this->add_control(
			'welcome_title',
			array(
				'label'       => esc_html__( 'عنوان خوش‌آمد', 'nafas-chatbot' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Nafas_Chatbot_Settings::get( 'welcome_title', 'سلام! 👋' ),
			)
		);

		$this->add_control(
			'welcome_text',
			array(
				'label'       => esc_html__( 'متن خوش‌آمد', 'nafas-chatbot' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => wp_strip_all_tags( Nafas_Chatbot_Settings::get( 'welcome_text', '' ) ),
			)
		);

		$this->add_control(
			'disclaimer',
			array(
				'label'       => esc_html__( 'متن سلب مسئولیت', 'nafas-chatbot' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => Nafas_Chatbot_Settings::get( 'disclaimer', '' ),
			)
		);

		$this->end_controls_section();

		/* ---------- بخش: گزینه‌های منو ---------- */
		$this->start_controls_section(
			'section_menu',
			array(
				'label' => esc_html__( 'گزینه‌های منو', 'nafas-chatbot' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$default_show = array(
			'company'  => Nafas_Chatbot_Settings::get( 'show_company', 'yes' ),
			'products' => Nafas_Chatbot_Settings::get( 'show_products', 'yes' ),
			'adr'      => Nafas_Chatbot_Settings::get( 'show_adr', 'yes' ),
			'consult'  => Nafas_Chatbot_Settings::get( 'show_consult', 'yes' ),
		);

		$this->add_control(
			'show_company',
			array(
				'label'        => esc_html__( 'نمایش «سوال درباره شرکت»', 'nafas-chatbot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $default_show['company'],
			)
		);
		$this->add_control(
			'show_products',
			array(
				'label'        => esc_html__( 'نمایش «سوال درباره محصولات»', 'nafas-chatbot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $default_show['products'],
			)
		);
		$this->add_control(
			'show_adr',
			array(
				'label'        => esc_html__( 'نمایش «ثبت عوارض»', 'nafas-chatbot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $default_show['adr'],
			)
		);
		$this->add_control(
			'show_consult',
			array(
				'label'        => esc_html__( 'نمایش «درخواست مشاوره»', 'nafas-chatbot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $default_show['consult'],
			)
		);

		$this->end_controls_section();

		/* ---------- بخش: محصولات ---------- */
		$this->start_controls_section(
			'section_products',
			array(
				'label' => esc_html__( 'محصولات', 'nafas-chatbot' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'override_products',
			array(
				'label'        => esc_html__( 'بازنویسی لیست محصولات', 'nafas-chatbot' ),
				'description'  => esc_html__( 'در صورت غیرفعال بودن، از لیست محصولات تنظیمات افزونه استفاده می‌شود.', 'nafas-chatbot' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'product_id',
			array(
				'label'   => esc_html__( 'شناسه (انگلیسی)', 'nafas-chatbot' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$repeater->add_control(
			'product_name',
			array(
				'label'   => esc_html__( 'نام نمایشی', 'nafas-chatbot' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'products_list',
			array(
				'label'       => esc_html__( 'محصولات', 'nafas-chatbot' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ product_name }}}',
				'condition'   => array( 'override_products' => 'yes' ),
				'default'     => array(),
			)
		);

		$this->end_controls_section();

		/* ---------- بخش: ظاهر (استایل) ---------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'ظاهر', 'nafas-chatbot' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'position',
			array(
				'label'   => esc_html__( 'موقعیت دکمه', 'nafas-chatbot' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left'  => array(
						'title' => esc_html__( 'پایین چپ', 'nafas-chatbot' ),
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'پایین راست', 'nafas-chatbot' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default' => Nafas_Chatbot_Settings::get( 'position', 'right' ),
			)
		);

		$this->add_control(
			'primary_color',
			array(
				'label'   => esc_html__( 'رنگ اصلی', 'nafas-chatbot' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
			)
		);

		$this->add_control(
			'primary_hover',
			array(
				'label'   => esc_html__( 'رنگ اصلی (هاور)', 'nafas-chatbot' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
			)
		);

		$this->add_control(
			'theme_mode',
			array(
				'label'   => esc_html__( 'حالت تم', 'nafas-chatbot' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					''      => esc_html__( 'پیش‌فرض افزونه', 'nafas-chatbot' ),
					'auto'  => esc_html__( 'خودکار', 'nafas-chatbot' ),
					'light' => esc_html__( 'روشن', 'nafas-chatbot' ),
					'dark'  => esc_html__( 'تیره', 'nafas-chatbot' ),
				),
				'default' => '',
			)
		);

		$this->add_control(
			'user_bubble_color',
			array(
				'label'   => esc_html__( 'رنگ حباب کاربر', 'nafas-chatbot' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
			)
		);

		$this->add_control(
			'bot_bubble_color',
			array(
				'label'   => esc_html__( 'رنگ حباب ربات', 'nafas-chatbot' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
			)
		);

		$this->add_control(
			'font_size',
			array(
				'label'       => esc_html__( 'اندازهٔ متن پیام (px)', 'nafas-chatbot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 10,
				'max'         => 24,
				'default'     => '',
				'placeholder' => (string) Nafas_Chatbot_Settings::get( 'font_size', 14 ),
			)
		);

		$this->add_control(
			'window_width',
			array(
				'label'       => esc_html__( 'عرض پنجره (px)', 'nafas-chatbot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 300,
				'max'         => 520,
				'default'     => '',
				'placeholder' => (string) Nafas_Chatbot_Settings::get( 'window_width', 384 ),
			)
		);

		$this->add_control(
			'window_radius',
			array(
				'label'       => esc_html__( 'گردی گوشه‌های پنجره (px)', 'nafas-chatbot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 40,
				'default'     => '',
				'placeholder' => (string) Nafas_Chatbot_Settings::get( 'window_radius', 24 ),
			)
		);

		$this->add_control(
			'bubble_radius',
			array(
				'label'       => esc_html__( 'گردی گوشه‌های حباب (px)', 'nafas-chatbot' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 30,
				'default'     => '',
				'placeholder' => (string) Nafas_Chatbot_Settings::get( 'bubble_radius', 16 ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * رندر ویجت در فرانت.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$overrides = array(
			'header_title'  => isset( $settings['header_title'] ) ? $settings['header_title'] : '',
			'company_name'  => isset( $settings['company_name'] ) ? $settings['company_name'] : '',
			'welcome_title' => isset( $settings['welcome_title'] ) ? $settings['welcome_title'] : '',
			'welcome_text'  => isset( $settings['welcome_text'] ) ? $settings['welcome_text'] : '',
			'disclaimer'    => isset( $settings['disclaimer'] ) ? $settings['disclaimer'] : '',
			'position'      => isset( $settings['position'] ) ? $settings['position'] : '',
			'primary_color' => isset( $settings['primary_color'] ) ? $settings['primary_color'] : '',
			'primary_hover' => isset( $settings['primary_hover'] ) ? $settings['primary_hover'] : '',
			'theme_mode'    => isset( $settings['theme_mode'] ) ? $settings['theme_mode'] : '',
			'show_company'  => ! empty( $settings['show_company'] ) ? 'yes' : 'no',
			'show_products' => ! empty( $settings['show_products'] ) ? 'yes' : 'no',
			'show_adr'      => ! empty( $settings['show_adr'] ) ? 'yes' : 'no',
			'show_consult'  => ! empty( $settings['show_consult'] ) ? 'yes' : 'no',
			// استایل پنجره (فقط در صورت مقداردهی، جایگزین تنظیم سراسری می‌شوند).
			'user_bubble_color' => isset( $settings['user_bubble_color'] ) ? $settings['user_bubble_color'] : '',
			'bot_bubble_color'  => isset( $settings['bot_bubble_color'] ) ? $settings['bot_bubble_color'] : '',
			'font_size'         => isset( $settings['font_size'] ) ? $settings['font_size'] : '',
			'window_width'      => isset( $settings['window_width'] ) ? $settings['window_width'] : '',
			'window_radius'     => isset( $settings['window_radius'] ) ? $settings['window_radius'] : '',
			'bubble_radius'     => isset( $settings['bubble_radius'] ) ? $settings['bubble_radius'] : '',
		);

		// محصولات سفارشی.
		if ( ! empty( $settings['override_products'] ) && ! empty( $settings['products_list'] ) ) {
			$products = array();
			foreach ( $settings['products_list'] as $item ) {
				if ( empty( $item['product_id'] ) ) {
					continue;
				}
				$products[] = array(
					'id'   => sanitize_key( $item['product_id'] ),
					'name' => $item['product_name'],
				);
			}
			if ( $products ) {
				$overrides['products'] = $products;
			}
		}

		echo Nafas_Chatbot()->frontend->render( $overrides ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن HTML کانتینر.
	}
}

/**
 * دسترسی کوتاه به نمونه افزونه.
 *
 * @return Nafas_Chatbot
 */
function Nafas_Chatbot() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals,WordPress.NamingConventions.ValidFunctionName
	return Nafas_Chatbot::instance();
}
