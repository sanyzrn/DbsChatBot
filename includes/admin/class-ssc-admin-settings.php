<?php
/**
 * Settings page: privacy & retention, security/limits, module settings,
 * advanced engine options, data tools (export/uninstall safety).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page.
 */
class SSC_Admin_Settings {

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
	 * Save settings (PRG).
	 */
	public function handle_actions() {
		if ( isset( $_POST['ssc_settings_save'] ) && check_admin_referer( 'ssc_settings' ) ) {
			$patch = array();
			if ( isset( $_POST['pharma_answer_mode'] ) ) {
				$patch['pharma_answer_mode'] = SSC_Settings::sanitize_value( 'pharma_answer_mode', wp_unslash( $_POST['pharma_answer_mode'] ) );
			}

			// Privacy & consent.
			$patch['consent_enabled'] = isset( $_POST['consent_enabled'] ) ? 'yes' : 'no';
			$patch['consent_text']    = isset( $_POST['consent_text'] ) ? wp_kses_post( wp_unslash( $_POST['consent_text'] ) ) : '';
			$patch['consent_link']    = isset( $_POST['consent_link'] ) ? esc_url_raw( wp_unslash( $_POST['consent_link'] ) ) : '';
			$patch['chatlog_retention_days'] = isset( $_POST['chatlog_retention_days'] ) ? max( 0, min( 3650, (int) $_POST['chatlog_retention_days'] ) ) : 90;
			$patch['submissions_retention_days'] = isset( $_POST['submissions_retention_days'] ) ? max( 0, min( 3650, (int) $_POST['submissions_retention_days'] ) ) : 0;

			// Security & abuse protection.
			$patch['rate_limit_mode']    = isset( $_POST['rate_limit_mode'] ) ? SSC_Settings::sanitize_value( 'rate_limit_mode', wp_unslash( $_POST['rate_limit_mode'] ) ) : 'ip';
			$patch['chat_rate_limit']    = isset( $_POST['chat_rate_limit'] ) ? max( 0, (int) $_POST['chat_rate_limit'] ) : 100;
			$patch['submit_rate_limit']  = isset( $_POST['submit_rate_limit'] ) ? max( 0, (int) $_POST['submit_rate_limit'] ) : 20;
			$patch['session_rate_limit'] = isset( $_POST['session_rate_limit'] ) ? max( 0, (int) $_POST['session_rate_limit'] ) : 50;
			$patch['trusted_proxy_header'] = isset( $_POST['trusted_proxy_header'] ) ? SSC_Settings::sanitize_value( 'trusted_proxy_header', wp_unslash( $_POST['trusted_proxy_header'] ) ) : '';

			// Display targeting.
			$patch['display_mode']    = isset( $_POST['display_mode'] ) ? SSC_Settings::sanitize_value( 'display_mode', wp_unslash( $_POST['display_mode'] ) ) : 'all';
			$patch['display_paths']   = isset( $_POST['display_paths'] ) ? SSC_Settings::sanitize_value( 'display_paths', wp_unslash( $_POST['display_paths'] ) ) : '';
			$patch['display_devices'] = isset( $_POST['display_devices'] ) ? SSC_Settings::sanitize_value( 'display_devices', wp_unslash( $_POST['display_devices'] ) ) : 'all';
			$patch['display_users']   = isset( $_POST['display_users'] ) ? SSC_Settings::sanitize_value( 'display_users', wp_unslash( $_POST['display_users'] ) ) : 'all';

			// Business hours.
			$patch['business_hours_enabled'] = isset( $_POST['business_hours_enabled'] ) ? 'yes' : 'no';
			$patch['business_hours_start']   = isset( $_POST['business_hours_start'] ) ? SSC_Settings::sanitize_value( 'business_hours_start', wp_unslash( $_POST['business_hours_start'] ) ) : '09:00';
			$patch['business_hours_end']     = isset( $_POST['business_hours_end'] ) ? SSC_Settings::sanitize_value( 'business_hours_end', wp_unslash( $_POST['business_hours_end'] ) ) : '18:00';
			$patch['business_hours_days']    = isset( $_POST['business_hours_days'] ) ? SSC_Settings::sanitize_value( 'business_hours_days', wp_unslash( $_POST['business_hours_days'] ) ) : '1,2,3,4,5';
			$patch['business_timezone']      = isset( $_POST['business_timezone'] ) ? SSC_Settings::sanitize_value( 'business_timezone', wp_unslash( $_POST['business_timezone'] ) ) : '';
			$patch['offline_message']        = isset( $_POST['offline_message'] ) ? SSC_Settings::sanitize_value( 'offline_message', wp_unslash( $_POST['offline_message'] ) ) : '';

			// Widget behaviour.
			$patch['streaming_enabled'] = isset( $_POST['streaming_enabled'] ) ? 'yes' : 'no';
			$patch['sound_enabled']     = isset( $_POST['sound_enabled'] ) ? 'yes' : 'no';

			// Module settings (only relevant ones; each block only shows when
			// its module is active, so stray POST values are ignored safely).
			$patch['voice_input']   = isset( $_POST['voice_input'] ) ? 'yes' : 'no';
			$patch['voice_output']  = isset( $_POST['voice_output'] ) ? 'yes' : 'no';
			$patch['voice_language'] = isset( $_POST['voice_language'] ) ? sanitize_key( $_POST['voice_language'] ) : 'auto';
			$patch['chatlog_enabled'] = isset( $_POST['chatlog_enabled'] ) ? 'yes' : 'no';
			$patch['csat_enabled']  = isset( $_POST['csat_enabled'] ) ? 'yes' : 'no';
			$patch['handoff_text']  = isset( $_POST['handoff_text'] ) ? wp_kses_post( wp_unslash( $_POST['handoff_text'] ) ) : '';
			$patch['proactive_delay'] = isset( $_POST['proactive_delay'] ) ? max( 2, min( 120, (int) $_POST['proactive_delay'] ) ) : 12;
			$patch['proactive_text'] = isset( $_POST['proactive_text'] ) ? wp_kses_post( wp_unslash( $_POST['proactive_text'] ) ) : '';
			$patch['form_fields']   = isset( $_POST['form_fields'] ) ? (array) wp_unslash( $_POST['form_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Settings::sanitize_list.
			$patch['notify_platform'] = isset( $_POST['notify_platform'] ) ? SSC_Settings::sanitize_value( 'notify_platform', wp_unslash( $_POST['notify_platform'] ) ) : 'bale';
			$patch['notify_chat_id'] = isset( $_POST['notify_chat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_chat_id'] ) ) : '';
			$patch['notify_email_enabled'] = isset( $_POST['notify_email_enabled'] ) ? 'yes' : 'no';
			$patch['notify_email_to'] = isset( $_POST['notify_email_to'] ) ? sanitize_email( wp_unslash( $_POST['notify_email_to'] ) ) : '';

			// Secrets for notifications.
			$secret_failed = false;
			if ( SSC_Modules::is_active( 'notifications' ) && isset( $_POST['notify_token'] ) && '' !== trim( (string) wp_unslash( $_POST['notify_token'] ) ) ) {
				$secret_failed = ! SSC_Settings::set_secret( 'notify_token', sanitize_text_field( wp_unslash( $_POST['notify_token'] ) ) );
			} elseif ( SSC_Modules::is_active( 'notifications' ) && isset( $_POST['notify_token_clear'] ) ) {
				SSC_Settings::set_secret( 'notify_token', '' );
			}

			SSC_Settings::update( self::active_module_patch( $patch ) );
			if ( $secret_failed ) {
				self::prg( array( 'saved' => 1, 'secret_error' => 1 ) );
			}
			self::prg( array( 'saved' => 1 ) );
		}

		// Data tools: AI cache flush.
		if ( isset( $_POST['ssc_flush_cache'] ) && check_admin_referer( 'ssc_tools' ) ) {
			SSC_Settings::flush_ai_cache();
			self::prg( array( 'flushed' => 1 ) );
		}

		// Data tools: uninstall data policy (explicit opt-in for destructive uninstall).
		if ( isset( $_POST['ssc_delete_policy'] ) && check_admin_referer( 'ssc_tools' ) ) {
			$policy = isset( $_POST['delete_on_uninstall'] ) ? 'yes' : 'no';
			update_option( 'ssc_chatbot_delete_on_uninstall', $policy, false );
			self::prg( array( 'policy' => 1 ) );
		}
	}

	/** Hidden module controls must not erase their saved configuration. */
	public static function active_module_patch( $patch ) {
		$groups = array(
			'pharma' => array( 'pharma_answer_mode' ),
			'voice' => array( 'voice_input', 'voice_output', 'voice_language' ),
			'history' => array( 'chatlog_enabled' ),
			'csat' => array( 'csat_enabled' ),
			'handoff' => array( 'handoff_text' ),
			'proactive' => array( 'proactive_delay', 'proactive_text' ),
			'leads' => array( 'form_fields' ),
			'notifications' => array( 'notify_platform', 'notify_chat_id', 'notify_email_enabled', 'notify_email_to' ),
		);
		foreach ( $groups as $module => $keys ) {
			if ( ! SSC_Modules::is_active( $module ) ) {
				foreach ( $keys as $key ) {
					unset( $patch[ $key ] );
				}
			}
		}
		return $patch;
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-settings';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render.
	 */
	public function render() {
		$s        = SSC_Settings::all();
		$modules  = SSC_Modules::statuses();
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-settings.php';
	}
}
