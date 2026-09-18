<?php
/**
 * OpenAI provider adapter.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI adapter.
 */
class SSC_Provider_Openai extends SSC_Provider_OpenAI_Compat {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'openai';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'OpenAI';
	}

	/**
	 * API base.
	 *
	 * @return string
	 */
	protected function api_base() {
		return 'https://api.openai.com/v1';
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function default_model() {
		return 'gpt-4o-mini';
	}

	/**
	 * Suggested models - filterable so sites stay current without plugin updates.
	 *
	 * @return string[]
	 */
	public function models() {
		$models = array(
			'gpt-4o-mini' => 'GPT-4o mini (fast, cheap)',
			'gpt-4o'      => 'GPT-4o',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4.1'     => 'GPT-4.1',
			'o4-mini'     => 'o4-mini (reasoning)',
		);
		/**
		 * Maintain the OpenAI model list without waiting for plugin releases.
		 *
		 * @param array $models id => label.
		 */
		return apply_filters( 'ssc_models_openai', $models );
	}
}
