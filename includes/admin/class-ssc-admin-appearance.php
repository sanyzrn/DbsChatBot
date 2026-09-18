<?php
/**
 * Appearance page: essential controls + live preview; granular styling
 * moved into the Advanced section (progressive disclosure).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appearance page.
 */
class SSC_Admin_Appearance {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_actions' ), 5 );
	}

	/**
	 * Save appearance (PRG).
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['ssc_appearance_save'] ) || ! check_admin_referer( 'ssc_appearance' ) ) {
			return;
		}

		$patch = array();
		foreach ( array( 'theme_mode', 'position', 'direction', 'font_family', 'assistant_display_name', 'welcome_title', 'welcome_text', 'disclaimer', 'primary_color', 'user_bubble_color', 'bot_bubble_color', 'avatar_url', 'launcher_icon_url', 'font_name', 'font_url' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$patch[ $key ] = SSC_Settings::sanitize_value( $key, wp_unslash( $_POST[ $key ] ) );
			} elseif ( in_array( $key, array( 'user_bubble_color', 'bot_bubble_color' ), true ) ) {
				// Color pickers post empty when cleared.
				$patch[ $key ] = '';
			}
		}
		foreach ( array( 'launcher_size', 'font_size', 'window_width', 'window_radius', 'bubble_radius' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$patch[ $key ] = (int) $_POST[ $key ];
			}
		}
		SSC_Settings::update( $patch );
		self::prg( array( 'saved' => 1 ) );
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-appearance';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render.
	 */
	public function render() {
		$s          = SSC_Settings::all();
		$business   = SSC_Settings::business();
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-appearance.php';
	}
}
