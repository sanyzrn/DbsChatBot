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
		if ( ! empty( $opts['web_search'] ) ) {
			// OpenRouter's web plugin works with any model it routes to.
			$parts['body']['plugins'] = array(
				array(
					'id'          => 'web',
					'max_results' => 3,
				),
			);
		}
		return $parts;
	}

	/**
	 * OpenRouter's web plugin.
	 *
	 * @return bool
	 */
	public function supports_web_search() {
		return true;
	}

	/**
	 * URL citations attached to the message (PURE).
	 *
	 * @param array $data Decoded response.
	 * @return array[]
	 */
	public function extract_sources( $data ) {
		$sources = array();
		$notes   = isset( $data['choices'][0]['message']['annotations'] ) ? $data['choices'][0]['message']['annotations'] : array();
		foreach ( (array) $notes as $note ) {
			if ( isset( $note['type'], $note['url_citation']['url'] ) && 'url_citation' === $note['type'] ) {
				$sources[] = array(
					'title' => isset( $note['url_citation']['title'] ) ? $note['url_citation']['title'] : '',
					'url'   => $note['url_citation']['url'],
				);
			}
		}
		return self::clean_sources( $sources );
	}
}
