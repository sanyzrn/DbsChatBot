<?php
/**
 * Analytics module: conversation trends, top topics, unanswered questions
 * radar, CSAT summary. Insights only - respects every privacy switch.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics module.
 */
class SSC_Module_Analytics extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'analytics';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Analytics & Insights', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Conversation volume trends, most-discussed products, the unanswered-questions radar and satisfaction summary.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'See exactly what visitors ask and where your knowledge has gaps - not decorative charts.', 'smart-support-chatbot' );
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
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
	}

	/**
	 * Insights data (respects privacy: radar only reads chatlog rows that
	 * exist, i.e. only when the history module logged them).
	 *
	 * @return array
	 */
	public function insights() {
		$chats = SSC_Schema::stats_series( 14, 'chat' );
		$series = array();
		for ( $i = 13; $i >= 0; --$i ) {
			$day         = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS * $i );
			$series[ $day ] = 0;
		}
		foreach ( $chats as $row ) {
			if ( isset( $series[ $row['stat_date'] ] ) ) {
				$series[ $row['stat_date'] ] = (int) $row['cnt'];
			}
		}

		$top_products = array();
		foreach ( SSC_Schema::stats_top( 'product:%', 8 ) as $row ) {
			$pid  = substr( $row['metric'], strlen( 'product:' ) );
			$name = $pid;
			foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
				if ( isset( $p['id'] ) && $p['id'] === $pid ) {
					$name = $p['name'];
					break;
				}
			}
			$top_products[] = array(
				'name'  => $name,
				'count' => (int) $row['cnt'],
			);
		}

		$unanswered = array();
		if ( SSC_Modules::is_active( 'history' ) ) {
			$unanswered = SSC_Schema::unanswered_questions( 14, 20 );
		}

		$csat = array();
		if ( SSC_Modules::is_active( 'csat' ) ) {
			$csat = SSC_Schema::csat_summary();
		}

		return array(
			'series14'     => $series,
			'total14'      => array_sum( $series ),
			'top_products' => $top_products,
			'unanswered'   => $unanswered,
			'csat'         => $csat,
		);
	}

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 70 );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'analytics' ) ) {
			return;
		}
		add_submenu_page(
			'ssc-dashboard',
			__( 'Analytics', 'smart-support-chatbot' ),
			__( 'Analytics', 'smart-support-chatbot' ),
			'manage_options',
			'ssc-analytics',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		$data = $this->insights();
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-analytics.php';
	}
}
