<?php
/**
 * Main plugin controller (singleton).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class.
 */
final class SSC_Plugin {

	/**
	 * Instance.
	 *
	 * @var SSC_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Frontend.
	 *
	 * @var SSC_Frontend
	 */
	public $frontend;

	/**
	 * Chat engine.
	 *
	 * @var SSC_Chat_Engine
	 */
	public $engine;

	/**
	 * REST.
	 *
	 * @var SSC_REST
	 */
	public $rest;

	/**
	 * AJAX bridge.
	 *
	 * @var SSC_Ajax
	 */
	public $ajax;

	/**
	 * Admin controller.
	 *
	 * @var SSC_Admin|null
	 */
	public $admin;

	/**
	 * Instance accessor.
	 *
	 * @return SSC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->engine   = new SSC_Chat_Engine();
		$this->frontend = new SSC_Frontend();
		$this->rest     = new SSC_REST( $this->engine );
		$this->ajax     = new SSC_Ajax( $this->engine );

		// Maintenance schedule (kept regardless of module states; purge honours them).
		SSC_Cron::init();
		SSC_Cron::schedule();

		// Optional modules boot ONLY their registered hooks when active.
		SSC_Modules::boot_active();

		if ( is_admin() ) {
			$this->admin = new SSC_Admin();
			add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
		}

		add_filter( 'plugin_action_links_' . SSC_CHATBOT_BASENAME, array( $this, 'action_links' ) );

		// Placement integrations.
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );

		// One-time wizard redirect after activation (never blocks anything).
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
	}

	/**
	 * Settings + wizard links on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = SSC_Setup::is_complete() ? admin_url( 'admin.php?page=ssc-dashboard' ) : SSC_Setup::wizard_url();
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Setup', 'smart-support-chatbot' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=ssc-settings' ) ) . '">' . esc_html__( 'Settings', 'smart-support-chatbot' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Activation redirect into the wizard (once, new installs only).
	 */
	public function maybe_redirect_to_wizard() {
		if ( wp_doing_ajax() || wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Never redirect while already on the wizard (avoids loops).
		if ( isset( $_GET['page'] ) && 'ssc-wizard' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( SSC_Setup::needs_wizard_redirect() ) {
			SSC_Setup::mark_wizard_redirected();
			wp_safe_redirect( SSC_Setup::wizard_url() );
			exit;
		}
	}

	/**
	 * Suggested privacy policy content.
	 */
	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p>' . esc_html__( 'This site uses an AI chat assistant. Messages you type are processed by this website and sent to the AI provider selected by the site owner (an external service) to generate replies. If you submit a contact form, your name, phone number and message are stored so we can follow up.', 'smart-support-chatbot' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Server conversation history is optional and follows the configured retention policy. Outside pharmaceutical mode, the browser may keep a short-lived conversation and the server may cache answers. In pharmaceutical mode these two caches are disabled; submitted safety reports are still stored for review. Deleting a browser conversation does not delete server records. Contact the site owner about access or deletion requests.', 'smart-support-chatbot' ) . '</p>';
		wp_add_privacy_policy_content( get_bloginfo( 'name' ), wp_kses_post( $content ) );
	}

	/**
	 * Elementor category.
	 *
	 * @param object $elements_manager Manager.
	 */
	public function register_elementor_category( $elements_manager ) {
		$elements_manager->add_category(
			'ssc_chatbot',
			array(
				'title' => __( 'NexaChatAI', 'smart-support-chatbot' ),
				'icon'  => 'eicon-chat',
			)
		);
	}

	/**
	 * Elementor widget.
	 *
	 * @param object $widgets_manager Manager.
	 */
	public function register_elementor_widget( $widgets_manager ) {
		require_once SSC_CHATBOT_DIR . 'widgets/class-ssc-elementor-widget.php';
		$widgets_manager->register( new SSC_Elementor_Widget() );
	}
}
