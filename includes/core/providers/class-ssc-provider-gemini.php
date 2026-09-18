<?php
/**
 * Google Gemini provider adapter.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gemini adapter.
 */
class SSC_Provider_Gemini extends SSC_Provider {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'gemini';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Google Gemini';
	}

	/**
	 * Default model.
	 *
	 * @return string
	 */
	public function default_model() {
		return 'gemini-2.5-flash';
	}

	/**
	 * Suggested models.
	 *
	 * @return string[]
	 */
	public function models() {
		$models = array(
			'gemini-2.5-flash'    => 'Gemini 2.5 Flash',
			'gemini-2.5-pro'      => 'Gemini 2.5 Pro',
		);
		/** This filter is documented in class-ssc-provider-openai.php. */
		return apply_filters( 'ssc_models_gemini', $models );
	}

	/**
	 * Build request parts (PURE). Key travels in a header, never the query
	 * string, so it cannot leak into server/proxy/CDN access logs.
	 *
	 * @param string $api_key  Key.
	 * @param string $model    Model.
	 * @param string $system   System prompt.
	 * @param array  $messages Messages.
	 * @param array  $opts     temperature, max_tokens.
	 * @return array
	 */
	public function request_parts( $api_key, $model, $system, $messages, $opts = array() ) {
		$contents = array();
		foreach ( (array) $messages as $m ) {
			$contents[] = array(
				'role'  => ( isset( $m['role'] ) && 'assistant' === $m['role'] ) ? 'model' : 'user',
				'parts' => array( array( 'text' => isset( $m['content'] ) ? (string) $m['content'] : '' ) ),
			);
		}

		$body = array(
			'contents' => $contents,
			'generationConfig' => array(
				'temperature'   => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.4,
				'maxOutputTokens' => isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800,
			),
		);
		if ( '' !== (string) $system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => (string) $system ) ) );
		}

		return array(
			'url'         => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( (string) $model ) . ':generateContent',
			'headers'     => array( 'x-goog-api-key' => (string) $api_key ),
			'body'        => $body,
			'needs_https' => true,
		);
	}

	/**
	 * Extract text (PURE). Walks all candidate parts, not just the first.
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public function extract_text( $data ) {
		if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
			$text = '';
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= $part['text'];
				}
			}
			return trim( $text );
		}
		return '';
	}

	/**
	 * Gemini embeds errors as {error:{code,message}} with 200 sometimes.
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public function embedded_error( $data ) {
		if ( isset( $data['candidates'][0]['finishReason'] ) && 'SAFETY' === $data['candidates'][0]['finishReason'] ) {
			return __( 'The model blocked the test prompt for safety reasons.', 'smart-support-chatbot' );
		}
		return parent::embedded_error( $data );
	}
}
