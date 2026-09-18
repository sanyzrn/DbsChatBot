<?php
/**
 * Dashboard page: publication status, connection health, knowledge readiness,
 * actionable warnings.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard page.
 */
class SSC_Admin_Dashboard {

	/**
	 * Handle quick actions (publish / unpublish / rerun wizard).
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		if ( isset( $_GET['ssc_dash_action'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_dash' ) ) {
			$action = sanitize_key( wp_unslash( $_GET['ssc_dash_action'] ) );
			if ( 'publish' === $action ) {
				SSC_Settings::update( array( 'enabled' => 'yes' ) );
				SSC_Setup::publish();
			} elseif ( 'unpublish' === $action ) {
				SSC_Settings::update( array( 'enabled' => 'no' ) );
				SSC_Setup::unpublish();
			}
			wp_safe_redirect( remove_query_arg( array( 'ssc_dash_action', '_wpnonce' ) ) );
			exit;
		}
	}

	/**
	 * Render.
	 */
	public function render() {
		$state     = SSC_Setup::state();
		$live      = SSC_Setup::is_live();
		$complete  = SSC_Setup::is_complete();
		$readiness = SSC_Setup::readiness();
		$business  = SSC_Settings::business();
		$provider  = SSC_Providers::current();
		$conn_ok   = SSC_Setup::connection_is_verified();
		$modules   = SSC_Modules::statuses();
		$notify_pending = class_exists( 'SSC_Module_Notifications' ) ? SSC_Module_Notifications::pending_count() : 0;

		// Quick stats (only meaningful ones).
		$chats_14 = SSC_Schema::stats_sum( 'chat' );
		$requests = 0;
		foreach ( SSC_Schema::counts() as $type => $statuses ) {
			foreach ( $statuses as $status => $n ) {
				if ( 'archived' !== $status ) {
					$requests += (int) $n;
				}
			}
		}
		$unanswered_count = count( SSC_Schema::unanswered_questions( 14, 100 ) );

		require SSC_CHATBOT_DIR . 'includes/admin/views/page-dashboard.php';
	}
}
