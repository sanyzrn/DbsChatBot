<?php
/**
 * کلاس اصلی افزونه (Singleton).
 *
 * @package NafasChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس هسته.
 */
final class Nafas_Chatbot {

	/**
	 * نمونه یکتا.
	 *
	 * @var Nafas_Chatbot|null
	 */
	protected static $instance = null;

	/**
	 * نمونه Frontend.
	 *
	 * @var Nafas_Chatbot_Frontend
	 */
	public $frontend;

	/**
	 * نمونه AJAX.
	 *
	 * @var Nafas_Chatbot_Ajax
	 */
	public $ajax;

	/**
	 * نمونه Admin.
	 *
	 * @var Nafas_Chatbot_Admin
	 */
	public $admin;

	/**
	 * دریافت نمونه یکتا.
	 *
	 * @return Nafas_Chatbot
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده.
	 */
	private function __construct() {
		$this->frontend = new Nafas_Chatbot_Frontend();
		$this->ajax     = new Nafas_Chatbot_Ajax();

		if ( is_admin() ) {
			$this->admin = new Nafas_Chatbot_Admin();
			// مهاجرت ساختار دیتابیس در صورت نیاز (افزودن ستون/جدول جدید + مهاجرت آمار).
			add_action( 'admin_init', array( 'Nafas_Chatbot_DB', 'maybe_upgrade' ) );
			// متن پیشنهادی سیاست حریم خصوصی وردپرس.
			add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		}

		// لینک تنظیمات در صفحه افزونه‌ها.
		add_filter( 'plugin_action_links_' . NAFAS_CHATBOT_BASENAME, array( $this, 'action_links' ) );

		// المنتور.
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );

		// زمان‌بندی پاک‌سازی خودکار تاریخچه گفتگو.
		if ( ! wp_next_scheduled( 'nafas_chatbot_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'nafas_chatbot_daily_cleanup' );
		}
		add_action( 'nafas_chatbot_daily_cleanup', array( $this, 'run_daily_cleanup' ) );
	}

	/**
	 * اجرای پاک‌سازی روزانه (حذف تاریخچه قدیمی).
	 */
	public function run_daily_cleanup() {
		$days = (int) Nafas_Chatbot_Settings::get( 'chatlog_retention_days', 90 );
		Nafas_Chatbot_DB::purge_old_chatlog( $days );
		// نگهداری/کمینه‌سازی دادهٔ درخواست‌ها.
		$sub_days = (int) Nafas_Chatbot_Settings::get( 'submissions_retention_days', 0 );
		Nafas_Chatbot_DB::purge_old_submissions( $sub_days );
	}

	/**
	 * افزودن متن پیشنهادی به راهنمای سیاست حریم خصوصی وردپرس.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = wp_kses_post(
			'<p>' . __( 'این سایت از یک «دستیار هوشمند گفتگو» برای پاسخ‌گویی، ثبت درخواست و مشاوره استفاده می‌کند. هنگام ارسال فرم، نام، شماره تماس و شرح واردشده به‌همراه نشانی IP ذخیره می‌شود. متن گفتگوها نیز ممکن است برای بهبود کیفیت پاسخ‌ها نگهداری شود. مدت نگهداری از پنل مدیریت قابل‌تنظیم است و داده‌های قدیمی به‌صورت خودکار حذف می‌شوند.', 'nafas-chatbot' ) . '</p>'
		);
		wp_add_privacy_policy_content( get_bloginfo( 'name' ), $content );
	}

	/**
	 * افزودن لینک تنظیمات.
	 *
	 * @param array $links لینک‌ها.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=nafas-chatbot-settings' ) ) . '">' . esc_html__( 'تنظیمات', 'nafas-chatbot' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * ثبت دسته‌بندی ویجت در المنتور.
	 *
	 * @param object $elements_manager مدیر المان‌ها.
	 */
	public function register_elementor_category( $elements_manager ) {
		$elements_manager->add_category(
			'nafas_chatbot',
			array(
				'title' => esc_html__( 'دستیار هوشمند گفتگو', 'nafas-chatbot' ),
				'icon'  => 'fa fa-comments',
			)
		);
	}

	/**
	 * ثبت ویجت المنتور.
	 *
	 * @param object $widgets_manager مدیر ویجت‌ها.
	 */
	public function register_elementor_widget( $widgets_manager ) {
		require_once NAFAS_CHATBOT_DIR . 'widgets/class-nafas-chatbot-widget.php';
		$widgets_manager->register( new Nafas_Chatbot_Elementor_Widget() );
	}
}
