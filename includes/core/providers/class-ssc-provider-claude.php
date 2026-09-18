<?php
/**
 * Anthropic Claude provider adapter.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claude adapter.
 */
class SSC_Provider_Claude extends SSC_Provider {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'claude';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Anthropic Claude';
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function default_model() {
		return 'claude-sonnet-4-5';
	}

	/**
	 * Suggested models.
	 *
	 * @return string[]
	 */
	public function models() {
		$models = array(
			'claude-sonnet-4-5' => 'Claude Sonnet 4.5',
			'claude-haiku-4-5'  => 'Claude Haiku 4.5 (fast)',
			'claude-opus-4-6'   => 'Claude Opus 4.6',
		);
		/** This filter is documented in class-ssc-provider-openai.php. */
		return apply_filters( 'ssc_models_claude', $models );
	}

	/**
	 * Build request parts (PURE). Claude 4.x rejects the temperature
	 * parameter, so it is only sent for 3.x model ids.
	 *
	 * @param string $api_key  Key.
	 * @param string $model    Model.
	 * @param string $system   System prompt (native top-level field).
	 * @param array  $messages Messages.
	 * @param array  $opts     temperature, max_tokens.
	 * @return array
	 */
	public function request_parts( $api_key, $model, $system, $messages, $opts = array() ) {
		$msgs = array();
		foreach ( (array) $messages as $m ) {
			$msgs[] = array(
				'role'    => ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'assistant' : 'user',
				'content' => isset( $m['content'] ) ? (string) $m['content'] : '',
			);
		}

		$body = array(
			'model'      => (string) $model,
			'max_tokens' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			'messages'   => $msgs,
		);
		if ( '' !== (string) $system ) {
			$body['system'] = (string) $system;
		}
		if ( 0 === strpos( (string) $model, 'claude-3' ) && isset( $opts['temperature'] ) ) {
			$body['temperature'] = (float) $opts['temperature'];
		}

		return array(
			'url'         => 'https://api.anthropic.com/v1/messages',
			'headers'     => array(
				'x-api-key'         => (string) $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'        => $body,
			'needs_https' => true,
		);
	}

	/**
	 * Extract text (PURE): concatenates all text blocks.
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public function extract_text( $data ) {
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$text = '';
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
			return trim( $text );
		}
		return '';
	}
}
