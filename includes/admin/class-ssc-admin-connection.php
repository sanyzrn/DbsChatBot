<?php
/**
 * AI Connection page: provider configuration + mandatory verification.
 *
 * Connection tests run against the CURRENTLY ENTERED credentials (via the
 * REST test-connection endpoint) before anything needs to be saved.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection page.
 */
class SSC_Admin_Connection {

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
	 * Save connection settings (PRG).
	 */
	public function handle_actions() {
		if ( ! isset( $_POST['ssc_connection_save'] ) || ! check_admin_referer( 'ssc_connection' ) ) {
			return;
		}

		$provider = isset( $_POST['ai_provider'] ) ? sanitize_key( wp_unslash( $_POST['ai_provider'] ) ) : 'none';
		$labels   = SSC_Providers::labels();
		if ( ! isset( $labels[ $provider ] ) ) {
			$provider = 'none';
		}

		$patch = array(
			'ai_provider' => $provider,
			'qa_mode'     => isset( $_POST['qa_mode'] ) ? SSC_Settings::sanitize_value( 'qa_mode', wp_unslash( $_POST['qa_mode'] ) ) : 'ai_first',
			'ai_temperature' => isset( $_POST['ai_temperature'] ) ? SSC_Settings::sanitize_value( 'ai_temperature', wp_unslash( $_POST['ai_temperature'] ) ) : '0.4',
			'ai_max_tokens'  => isset( $_POST['ai_max_tokens'] ) ? (int) $_POST['ai_max_tokens'] : 800,
			'ai_history_limit' => isset( $_POST['ai_history_limit'] ) ? max( 0, min( 20, (int) $_POST['ai_history_limit'] ) ) : 8,
			'ai_strict_knowledge' => isset( $_POST['ai_strict_knowledge'] ) ? 'yes' : 'no',
			'ai_cache_enabled' => isset( $_POST['ai_cache_enabled'] ) ? 'yes' : 'no',
			'ai_system_prompt_extra' => isset( $_POST['ai_system_prompt_extra'] ) ? wp_kses_post( wp_unslash( $_POST['ai_system_prompt_extra'] ) ) : '',
			'ai_fallback_msg' => isset( $_POST['ai_fallback_msg'] ) ? wp_kses_post( wp_unslash( $_POST['ai_fallback_msg'] ) ) : '',
			'kb_max_chunks' => isset( $_POST['kb_max_chunks'] ) ? max( 1, min( 8, (int) $_POST['kb_max_chunks'] ) ) : 3,
		);
		foreach ( array( 'openai', 'gemini', 'claude', 'openrouter', 'custom' ) as $pid ) {
			if ( isset( $_POST[ $pid . '_model' ] ) ) {
				$patch[ $pid . '_model' ] = sanitize_text_field( wp_unslash( $_POST[ $pid . '_model' ] ) );
			}
		}
		if ( isset( $_POST['custom_endpoint'] ) ) {
			$patch['custom_endpoint'] = esc_url_raw( wp_unslash( $_POST['custom_endpoint'] ) );
		}
		if ( isset( $_POST['ai_webhook_url'] ) ) {
			$patch['ai_webhook_url'] = esc_url_raw( wp_unslash( $_POST['ai_webhook_url'] ) );
		}
		SSC_Settings::update( $patch );

		// Secrets: replace when typed, delete when the clear checkbox is used.
		$secrets = array( 'openai_api_key', 'gemini_api_key', 'claude_api_key', 'openrouter_api_key', 'custom_api_key', 'ai_webhook_secret' );
		$secret_error = '';
		foreach ( $secrets as $key ) {
			if ( isset( $_POST[ $key ] ) && '' !== trim( (string) wp_unslash( $_POST[ $key ] ) ) ) {
				if ( ! SSC_Settings::set_secret( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) ) {
					$secret_error = 'encrypt_failed';
				}
			} elseif ( isset( $_POST[ $key . '_clear' ] ) ) {
				SSC_Settings::set_secret( $key, '' );
			}
		}

		if ( '' !== $secret_error ) {
			self::prg( array( 'saved' => 1, 'secret_error' => 1 ) );
		}
		self::prg( array( 'saved' => 1 ) );
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-connection';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render.
	 */
	public function render() {
		$s            = SSC_Settings::all();
		$providers    = SSC_Providers::all();
		$labels       = SSC_Providers::labels();
		$verified     = SSC_Setup::connection_is_verified();
		$verify_state = SSC_Setup::state()['connection_verified'];
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-connection.php';
	}
}
