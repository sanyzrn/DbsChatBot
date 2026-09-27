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
			'gpt-4o-mini'  => 'GPT-4o mini (fast, cheap)',
			'gpt-4o'       => 'GPT-4o',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4.1'      => 'GPT-4.1',
			'o4-mini'      => 'o4-mini (reasoning)',
		);
		/**
		 * Maintain the OpenAI model list without waiting for plugin releases.
		 *
		 * @param array $models id => label.
		 */
		return apply_filters( 'ssc_models_openai', $models );
	}

	/**
	 * Web search runs through the Responses API's built-in tool.
	 *
	 * @return bool
	 */
	public function supports_web_search() {
		return true;
	}

	/**
	 * Chat Completions normally; the Responses API when web search is on
	 * (PURE).
	 *
	 * @param string $api_key  Key.
	 * @param string $model    Model.
	 * @param string $system   System prompt.
	 * @param array  $messages Messages.
	 * @param array  $opts     Options.
	 * @return array
	 */
	public function request_parts( $api_key, $model, $system, $messages, $opts = array() ) {
		if ( empty( $opts['web_search'] ) ) {
			return parent::request_parts( $api_key, $model, $system, $messages, $opts );
		}
		$input = array();
		foreach ( (array) $messages as $m ) {
			$input[] = array(
				'role'    => ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'assistant' : 'user',
				'content' => isset( $m['content'] ) ? (string) $m['content'] : '',
			);
		}
		$tool = array( 'type' => 'web_search' );
		if ( ! empty( $opts['search_domains'] ) ) {
			$tool['filters'] = array( 'allowed_domains' => array_values( (array) $opts['search_domains'] ) );
		}
		$body = array(
			'model'             => (string) $model,
			'input'             => $input,
			'tools'             => array( apply_filters( 'ssc_openai_web_search_tool', $tool ) ),
			'max_output_tokens' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
		);
		if ( '' !== (string) $system ) {
			$body['instructions'] = (string) $system;
		}
		return array(
			'url'         => $this->api_base() . '/responses',
			'headers'     => array( 'Authorization' => 'Bearer ' . $api_key ),
			'body'        => $body,
			'needs_https' => true,
		);
	}

	/**
	 * Text from either API shape (PURE).
	 *
	 * @param array $data Decoded response.
	 * @return string
	 */
	public function extract_text( $data ) {
		if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
			$text = '';
			foreach ( $data['output'] as $item ) {
				foreach ( isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : array() as $part ) {
					if ( isset( $part['type'], $part['text'] ) && 'output_text' === $part['type'] ) {
						$text .= $part['text'];
					}
				}
			}
			return trim( $text );
		}
		return parent::extract_text( $data );
	}

	/**
	 * URL citations from a Responses API answer (PURE).
	 *
	 * @param array $data Decoded response.
	 * @return array[]
	 */
	public function extract_sources( $data ) {
		$sources = array();
		foreach ( isset( $data['output'] ) && is_array( $data['output'] ) ? $data['output'] : array() as $item ) {
			foreach ( isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : array() as $part ) {
				foreach ( isset( $part['annotations'] ) && is_array( $part['annotations'] ) ? $part['annotations'] : array() as $note ) {
					if ( isset( $note['type'], $note['url'] ) && 'url_citation' === $note['type'] ) {
						$sources[] = array(
							'title' => isset( $note['title'] ) ? $note['title'] : '',
							'url'   => $note['url'],
						);
					}
				}
			}
		}
		return self::clean_sources( $sources );
	}
}
