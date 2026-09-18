<?php
/**
 * Modules page: the module marketplace / manager.
 *
 * A module manager, not a store: no prices, no plans, no purchase buttons.
 * Statuses: Active / Inactive / Setup Required.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modules page.
 */
class SSC_Admin_Modules {

	/**
	 * Constructor: activation toggles (PRG).
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_actions' ), 5 );
	}

	/**
	 * Activate / deactivate with nonce + meaningful feedback.
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['ssc_module'], $_GET['ssc_module_action'], $_GET['_wpnonce'] ) ) {
			return;
		}
		$module_id = sanitize_key( wp_unslash( $_GET['ssc_module'] ) );
		$action    = sanitize_key( wp_unslash( $_GET['ssc_module_action'] ) );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_module_' . $module_id ) ) {
			return;
		}

		if ( 'activate' === $action ) {
			$result = SSC_Modules::activate( $module_id );
			if ( is_wp_error( $result ) ) {
				self::prg( array( 'error' => rawurlencode( $result->get_error_message() ) ) );
			}
			self::prg( array( 'activated' => $module_id ) );
		} elseif ( 'deactivate' === $action ) {
			SSC_Modules::deactivate( $module_id );
			self::prg( array( 'deactivated' => $module_id ) );
		}
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-modules';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the marketplace.
	 */
	public function render() {
		$modules    = SSC_Modules::by_category();
		$categories = SSC_Modules::categories();
		$statuses   = SSC_Modules::statuses();
		$search     = isset( $_GET['ssc_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ssc_search'] ) ) : '';
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-modules.php';
	}
}
