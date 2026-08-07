<?php
/**
 * کلاس اصلی افزونه (Singleton).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس هسته.
 */
final class SSC_Chatbot {

	/**
	 * نمونه یکتا.
	 *
	 * @var SSC_Chatbot|null
	 */
	protected static $instance = null;

	/**
	 * نمونه Frontend.
	 *
	 * @var SSC_Chatbot_Frontend
	 */
	public $frontend;

	/**
	 * نمونه AJAX.
	 *
	 * @var SSC_Chatbot_Ajax
	 */
	public $ajax;

	/**
	 * نمونه Admin.
	 *
	 * @var SSC_Chatbot_Admin
	 */
	public $admin;

	/**
	 * دریافت نمونه یکتا.
	 *
	 * @return SSC_Chatbot
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
		$this->frontend = new SSC_Chatbot_Frontend();
		$this->ajax     = new SSC_Chatbot_Ajax();

		if ( is_admin() ) {
			$this->admin = new SSC_Chatbot_Admin();
			// مهاجرت ساختار دیتابیس در صورت نیاز (افزودن ستون/جدول جدید + مهاجرت آمار).
			add_action( 'admin_init', array( 'SSC_Chatbot_DB', 'maybe_upgrade' ) );
			// متن پیشنهادی سیاست حریم خصوصی وردپرس.
			add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		}

		// لینک تنظیمات در صفحه افزونه‌ها.
		add_filter( 'plugin_action_links_' . SSC_CHATBOT_BASENAME, array( $this, 'action_links' ) );

		// المنتور.
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );

		// زمان‌بندی پاک‌سازی خودکار تاریخچه گفتگو.
		if ( ! wp_next_scheduled( 'ssc_chatbot_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ssc_chatbot_daily_cleanup' );
		}
		add_action( 'ssc_chatbot_daily_cleanup', array( $this, 'run_daily_cleanup' ) );
	}

	/**
	 * اجرای پاک‌سازی روزانه (حذف تاریخچه قدیمی).
	 */
	public function run_daily_cleanup() {
		$days = (int) SSC_Chatbot_Settings::get( 'chatlog_retention_days', 90 );
		SSC_Chatbot_DB::purge_old_chatlog( $days );
		// نگهداری/کمینه‌سازی دادهٔ درخواست‌ها.
		$sub_days = (int) SSC_Chatbot_Settings::get( 'submissions_retention_days', 0 );
		SSC_Chatbot_DB::purge_old_submissions( $sub_days );
		// پاک‌سازی شمارنده‌های محدودیت نرخ قدیمی.
		SSC_Chatbot_DB::purge_rate_limits( 2 );
	}

	/**
	 * افزودن متن پیشنهادی به راهنمای سیاست حریم خصوصی وردپرس.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = wp_kses_post(
			'<p>' . __( 'این سایت از یک «دستیار هوشمند گفتگو» برای پاسخ‌گویی، ثبت درخواست و مشاوره استفاده می‌کند. هنگام ارسال فرم، نام، شماره تماس و شرح واردشده به‌همراه نشانی IP ذخیره می‌شود. متن گفتگوها نیز ممکن است برای بهبود کیفیت پاسخ‌ها نگهداری شود. مدت نگهداری از پنل مدیریت قابل‌تنظیم است و داده‌های قدیمی به‌صورت خودکار حذف می‌شوند.', 'smart-support-chatbot' ) . '</p>'
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
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=smart-support-chatbot-settings' ) ) . '">' . esc_html__( 'تنظیمات', 'smart-support-chatbot' ) . '</a>';
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
			'ssc_chatbot',
			array(
				'title' => esc_html__( 'دستیار هوشمند گفتگو', 'smart-support-chatbot' ),
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
		require_once SSC_CHATBOT_DIR . 'widgets/class-smart-support-chatbot-widget.php';
		$widgets_manager->register( new SSC_Chatbot_Elementor_Widget() );
	}
}
