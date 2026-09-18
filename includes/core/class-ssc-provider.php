<?php
/**
 * Abstract AI provider adapter.
 *
 * Each adapter implements pure request/parse primitives so payload shapes
 * are unit-testable, and inherits the real connection test that performs an
 * actual generation and validates the response structure (HTTP 200 alone is
 * NOT sufficient).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract provider.
 */
abstract class SSC_Provider {

	/**
	 * Provider id (matches settings ai_provider values).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Default model id.
	 *
	 * @return string
	 */
	abstract public function default_model();

	/**
	 * Suggested models (maintainable, filterable - never the only option).
	 *
	 * @return string[] id => label.
	 */
	abstract public function models();

	/**
	 * Build the HTTP request parts (PURE - unit tested).
	 *
	 * @param string $api_key  Decrypted key.
	 * @param string $model    Model id.
	 * @param string $system   System prompt.
	 * @param array  $messages user/assistant messages.
	 * @param array  $opts     temperature, max_tokens, endpoint override.
	 * @return array url, headers, body, needs_https.
	 */
	abstract public function request_parts( $api_key, $model, $system, $messages, $opts = array() );

	/**
	 * Extract the assistant text from a provider payload (PURE - unit tested).
	 *
	 * @param array $data Decoded JSON body.
	 * @return string Empty when the structure carries no text.
	 */
	abstract public function extract_text( $data );

	/**
	 * Credential-bearing? (drives HTTPS enforcement)
	 *
	 * @return bool
	 */
	public function needs_https() {
		return true;
	}

	/**
	 * Read the saved credentials for this provider.
	 *
	 * @return array api_key, model, endpoint.
	 */
	public function saved_credentials() {
		$id = $this->id();
		return array(
			'api_key'  => SSC_Settings::get_secret( $id . '_api_key' ),
			'model'    => (string) SSC_Settings::get( $id . '_model', '' ),
			'endpoint' => (string) SSC_Settings::get( $id . '_endpoint', '' ),
		);
	}

	/**
	 * Generate a reply using SAVED credentials (chat path).
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Conversation.
	 * @return array envelope: ok, text, error.
	 */
	public function generate( $system, $messages ) {
		$c = $this->saved_credentials();
		return $this->generate_with( $c['api_key'], $c['model'], $system, $messages, array( 'endpoint' => $c['endpoint'] ) );
	}

	/**
	 * Generate with EXPLICIT credentials (wizard tests unsaved input).
	 *
	 * @param string $api_key  API key.
	 * @param string $model    Model.
	 * @param string $system   System prompt.
	 * @param array  $messages Messages.
	 * @param array  $opts     endpoint, temperature, max_tokens, timeout.
	 * @return array ok, text, error{code,message}.
	 */
	public function generate_with( $api_key, $model, $system, $messages, $opts = array() ) {
		if ( '' === trim( (string) $model ) ) {
			return array( 'ok' => false, 'text' => '', 'error' => array( 'code' => 'model', 'message' => __( 'No model selected.', 'smart-support-chatbot' ) ) );
		}
		$opts = wp_parse_args(
			$opts,
			array(
				'endpoint'    => '',
				'temperature' => (float) SSC_Settings::get( 'ai_temperature', 0.4 ),
				'max_tokens'  => (int) SSC_Settings::get( 'ai_max_tokens', 800 ),
				'timeout'     => (int) apply_filters( 'ssc_http_timeout', 60 ),
			)
		);

		$parts    = $this->request_parts( $api_key, $model, $system, $messages, $opts );
		$response = SSC_HTTP::post_json(
			$parts['url'],
			$parts['headers'],
			$parts['body'],
			array(
				'timeout'     => $opts['timeout'],
				'needs_https' => $this->needs_https(),
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'error' => $response['error'] );
		}

		// Extra guard: some gateways return 200 with an embedded error object.
		$embedded = $this->embedded_error( $response['data'] );
		if ( '' !== $embedded ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'malformed',
					'message' => $embedded,
				),
			);
		}

		$text = trim( $this->extract_text( $response['data'] ) );
		if ( '' === $text ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'malformed',
					'message' => __( 'The provider replied 200 but no generated text was found in the response.', 'smart-support-chatbot' ),
				),
			);
		}
		return array( 'ok' => true, 'text' => $text, 'error' => null );
	}

	/**
	 * Detect provider-style errors embedded in 200 responses (PURE).
	 *
	 * @param array $data Payload.
	 * @return string '' when none.
	 */
	public function embedded_error( $data ) {
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			return (string) $data['error']['message'];
		}
		if ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
			return (string) $data['error'];
		}
		return '';
	}

	/**
	 * REAL connection test: performs an actual tiny generation and validates
	 * that a usable answer comes back. Never trusts saved state when
	 * $overrides are supplied (wizard tests what the admin just typed).
	 *
	 * @param array $overrides Optional explicit credentials.
	 * @return array ok, text, error{code,message,friendly}.
	 */
	public function test_connection( $overrides = array() ) {
		$saved  = $this->saved_credentials();
		$key    = isset( $overrides['api_key'] ) ? (string) $overrides['api_key'] : $saved['api_key'];
		$model  = isset( $overrides['model'] ) && '' !== trim( (string) $overrides['model'] ) ? (string) $overrides['model'] : $saved['model'];
		$endpoint = isset( $overrides['endpoint'] ) ? (string) $overrides['endpoint'] : $saved['endpoint'];

		if ( '' === trim( $key ) && $this->needs_key() ) {
			return array(
				'ok'     => false,
				'text'   => '',
				'error'  => array(
					'code'     => 'auth',
					'message'  => __( 'No API key provided.', 'smart-support-chatbot' ),
					'friendly' => SSC_HTTP::friendly_error( 'auth' ),
				),
			);
		}

		$result = $this->generate_with(
			$key,
			'' !== $model ? $model : $this->default_model(),
			'You are a connection test. Reply with exactly: OK',
			array( array( 'role' => 'user', 'content' => 'Reply with exactly: OK' ) ),
			array(
				'endpoint'    => $endpoint,
				'max_tokens'  => 32,
				'temperature' => 0,
				'timeout'     => 30,
			)
		);

		if ( ! $result['ok'] ) {
			$result['error']['friendly'] = SSC_HTTP::friendly_error( $result['error']['code'] );
			return $result;
		}
		return $result;
	}

	/**
	 * Does this provider need an API key?
	 *
	 * @return bool
	 */
	public function needs_key() {
		return true;
	}
}
