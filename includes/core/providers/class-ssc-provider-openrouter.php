<?php
/**
 * OpenRouter provider adapter (OpenAI-compatible aggregator).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenRouter adapter.
 */
class SSC_Provider_Openrouter extends SSC_Provider_OpenAI_Compat {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'openrouter';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'OpenRouter';
	}

	/**
	 * API base.
	 *
	 * @return string
	 */
	protected function api_base() {
		return 'https://openrouter.ai/api/v1';
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function default_model() {
		return 'openai/gpt-4o-mini';
	}

	/**
	 * Suggested models. OpenRouter catalog moves fast: this is only a
	 * starting point and manual entry is always available.
	 *
	 * @return string[]
	 */
	public function models() {
		$models = array(
			'openai/gpt-4o-mini'                => 'OpenAI GPT-4o mini',
			'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
			'deepseek/deepseek-chat'            => 'DeepSeek Chat',
		);
		/** This filter is documented in class-ssc-provider-openai.php. */
		return apply_filters( 'ssc_models_openrouter', $models );
	}

	/**
	 * OpenRouter benefits from an attribution header pair.
	 *
	 * @param string $api_key  Key.
	 * @param string $model    Model.
	 * @param string $system   System.
	 * @param array  $messages Messages.
	 * @param array  $opts     Options.
	 * @return array
	 */
	public function request_parts( $api_key, $model, $system, $messages, $opts = array() ) {
		$parts                            = parent::request_parts( $api_key, $model, $system, $messages, $opts );
		$parts['headers']['HTTP-Referer'] = home_url( '/' );
		$parts['headers']['X-Title']      = get_bloginfo( 'name' );
		return $parts;
	}
}
