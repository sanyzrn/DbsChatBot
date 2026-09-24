<?php
/**
 * Conversation history module: chatlog admin management, feedback binding,
 * unanswered radar feed.
 *
 * Disabled by default: without this module nothing about conversations is
 * persisted (privacy-preserving default).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * History module.
 */
class SSC_Module_History extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'history';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Conversation History', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Stores conversations so you can review quality, capture answers into the FAQ bank and spot unanswered questions.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Improve answer quality with real conversation evidence, with configurable retention.', 'smart-support-chatbot' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'analytics';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>';
	}

	/**
	 * Needs config: logging must be switched on inside the module settings.
	 *
	 * @return bool
	 */
	public function needs_config() {
		return true;
	}

	/**
	 * Configured = logging enabled.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return 'yes' === SSC_Settings::get( 'chatlog_enabled', 'no' );
	}

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 50 );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'history' ) ) {
			return;
		}
		add_submenu_page(
			'ssc-dashboard',
			__( 'Conversations', 'smart-support-chatbot' ),
			__( 'Conversations', 'smart-support-chatbot' ),
			'manage_options',
			'ssc-conversations',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the conversations page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		$this->handle_row_actions();

		$filters = array(
			'source' => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'rating' => isset( $_GET['rating'] ) ? sanitize_key( wp_unslash( $_GET['rating'] ) ) : '',
			'page'   => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		$result  = SSC_Schema::get_chatlog( $filters );
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-conversations.php';
	}

	/**
	 * Row actions with nonce + PRG.
	 */
	protected function handle_row_actions() {
		if ( ! isset( $_GET['ssc_log_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['ssc_log_action'] ) );
		$id     = (int) $_GET['id'];
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_log_' . $id ) ) {
			return;
		}
		if ( 'delete' === $action ) {
			SSC_Schema::delete_chatlog( $id );
		} elseif ( 'tobank' === $action ) {
			// Promote an answered exchange into the FAQ bank.
			global $wpdb;
			$table = SSC_Schema::chatlog_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- single-row read.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT question, answer FROM {$table} WHERE id = %d", $id ), ARRAY_A );
			if ( $row ) {
				SSC_Schema::qa_insert(
					array(
						'question'   => $row['question'],
						'answer'     => $row['answer'],
						'product_id' => 'general',
					)
				);
			}
		}
		$args = array( 'page' => 'ssc-conversations' );
		foreach ( array( 'source', 'rating', 'paged' ) as $keep ) {
			// Nonce already verified above; sanitize before the value is read.
			$value = isset( $_GET[ $keep ] ) ? sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $value ) {
				$args[ $keep ] = rawurlencode( $value );
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
