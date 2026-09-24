<?php
/**
 * OpenAI-compatible base adapter (OpenAI, OpenRouter, custom endpoints).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI-compatible provider base.
 */
abstract class SSC_Provider_OpenAI_Compat extends SSC_Provider {

	/**
	 * API base URL (WITHOUT trailing slash).
	 *
	 * @return string
	 */
	abstract protected function api_base();

	/**
	 * Build request parts (PURE).
	 *
	 * @param string $api_key  Key.
	 * @param string $model    Model.
	 * @param string $system   System prompt.
	 * @param array  $messages Messages.
	 * @param array  $opts     temperature, max_tokens, endpoint.
	 * @return array
	 */
	public function request_parts( $api_key, $model, $system, $messages, $opts = array() ) {
		$endpoint = ! empty( $opts['endpoint'] ) ? $opts['endpoint'] : $this->api_base() . '/chat/completions';

		$msgs = array();
		if ( '' !== (string) $system ) {
			$msgs[] = array(
				'role'    => 'system',
				'content' => (string) $system,
			);
		}
		foreach ( (array) $messages as $m ) {
			$msgs[] = array(
				'role'    => ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'assistant' : 'user',
				'content' => isset( $m['content'] ) ? (string) $m['content'] : '',
			);
		}

		$headers = array();
		if ( '' !== (string) $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$body = array(
			'model'       => (string) $model,
			'messages'    => $msgs,
			'max_tokens'  => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.4,
		);

		return array(
			'url'         => $endpoint,
			'headers'     => $headers,
			'body'        => $body,
			'needs_https' => true,
		);
	}

	/**
	 * Extract text (PURE).
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public function extract_text( $data ) {
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = $data['choices'][0]['message']['content'];
			if ( is_string( $content ) ) {
				return trim( $content );
			}
			// Some compatible providers return content part arrays.
			if ( is_array( $content ) ) {
				$text = '';
				foreach ( $content as $part ) {
					if ( is_array( $part ) && isset( $part['text'] ) ) {
						$text .= $part['text'];
					} elseif ( is_string( $part ) ) {
						$text .= $part;
					}
				}
				return trim( $text );
			}
		}
		return '';
	}
}
