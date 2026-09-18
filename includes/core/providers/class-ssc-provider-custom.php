<?php
/**
 * Custom OpenAI-compatible endpoint adapter (Groq, DeepSeek, Ollama gateways,
 * Azure-style proxies, self-hosted relays...).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom adapter.
 */
class SSC_Provider_Custom extends SSC_Provider_OpenAI_Compat {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'custom';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'OpenAI-compatible endpoint', 'smart-support-chatbot' );
	}

	/**
	 * No fixed base: the admin supplies the full endpoint URL.
	 *
	 * @return string
	 */
	protected function api_base() {
		return '';
	}

	/**
	 * Default model is always manual.
	 *
	 * @return string
	 */
	public function default_model() {
		return '';
	}

	/**
	 * No suggested list - the model id is typed by the admin.
	 *
	 * @return string[]
	 */
	public function models() {
		return array();
	}

	/**
	 * A key is optional (local relays may not need one).
	 *
	 * @return bool
	 */
	public function needs_key() {
		return false;
	}

	/**
	 * Credentials include the endpoint URL.
	 *
	 * @return array
	 */
	public function saved_credentials() {
		return array(
			'api_key'  => SSC_Settings::get_secret( 'custom_api_key' ),
			'model'    => (string) SSC_Settings::get( 'custom_model', '' ),
			'endpoint' => (string) SSC_Settings::get( 'custom_endpoint', '' ),
		);
	}
}
