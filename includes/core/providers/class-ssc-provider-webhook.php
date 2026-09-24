<?php
/**
 * Webhook provider adapter: fully external answer engine, HMAC-signed both
 * ways (fail-closed when a secret is configured).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook adapter.
 */
class SSC_Provider_Webhook extends SSC_Provider {

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function id() {
		return 'webhook';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Custom webhook', 'smart-support-chatbot' );
	}

	/**
	 * No model concept.
	 *
	 * @return string
	 */
	public function default_model() {
		return '';
	}

	/**
	 * No models.
	 *
	 * @return string[]
	 */
	public function models() {
		return array();
	}

	/**
	 * A key is not used; the secret is optional but recommended.
	 *
	 * @return bool
	 */
	public function needs_key() {
		return false;
	}

	/**
	 * HTTPS only when a secret is configured (signature must not be sniffable).
	 *
	 * @return bool
	 */
	public function needs_https() {
		return '' !== trim( SSC_Settings::get_secret( 'ai_webhook_secret' ) );
	}

	/**
	 * Build request parts (PURE).
	 *
	 * @param string $secret   HMAC secret (unused for key).
	 * @param string $model    Unused.
	 * @param string $system   System prompt (forwarded for context).
	 * @param array  $messages Conversation (history + current).
	 * @param array  $opts     endpoint, product, product_name.
	 * @return array
	 */
	public function request_parts( $secret, $model, $system, $messages, $opts = array() ) {
		$current = end( $messages );
		$payload = array(
			'message'      => is_array( $current ) ? ( isset( $current['content'] ) ? $current['content'] : '' ) : '',
			'history'      => array_slice( (array) $messages, 0, -1 ),
			'system'       => (string) $system,
			'product'      => isset( $opts['product'] ) ? $opts['product'] : '',
			'product_name' => isset( $opts['product_name'] ) ? $opts['product_name'] : '',
		);
		$body    = wp_json_encode( $payload );

		$headers = array();
		if ( '' !== (string) $secret ) {
			$headers['X-Chatbot-Signature'] = 'sha256=' . hash_hmac( 'sha256', (string) $body, (string) $secret );
		}

		return array(
			'url'         => isset( $opts['endpoint'] ) ? (string) $opts['endpoint'] : '',
			'headers'     => $headers,
			'body'        => $body,
			'needs_https' => '' !== (string) $secret,
		);
	}

	/**
	 * Extract text (PURE).
	 *
	 * @param array $data Payload.
	 * @return string
	 */
	public function extract_text( $data ) {
		if ( isset( $data['reply'] ) && is_string( $data['reply'] ) ) {
			return trim( $data['reply'] );
		}
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return trim( $data['message'] );
		}
		if ( isset( $data['text'] ) && is_string( $data['text'] ) ) {
			return trim( $data['text'] );
		}
		return '';
	}

	/**
	 * Webhook request: signs, posts, verifies the RESPONSE signature.
	 *
	 * @param string $api_key  Unused.
	 * @param string $model    Unused.
	 * @param string $system   System prompt.
	 * @param array  $messages Messages.
	 * @param array  $opts     endpoint, product, product_name.
	 * @return array
	 */
	public function generate_with( $api_key, $model, $system, $messages, $opts = array() ) {
		$secret = SSC_Settings::get_secret( 'ai_webhook_secret' );
		$opts   = wp_parse_args( $opts, array( 'endpoint' => SSC_Settings::get( 'ai_webhook_url', '' ) ) );

		if ( '' === trim( (string) $opts['endpoint'] ) ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'endpoint',
					'message' => __( 'No webhook URL configured.', 'smart-support-chatbot' ),
				),
			);
		}

		$parts = $this->request_parts( $secret, '', $system, $messages, $opts );

		if ( ! SSC_HTTP::is_safe_url( $parts['url'], $parts['needs_https'] ) ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'endpoint',
					'message' => __( 'The webhook URL is not allowed (public address; HTTPS required when a secret is configured).', 'smart-support-chatbot' ),
				),
			);
		}

		// Manual post (response signature validation needs the raw body).
		$headers                 = $parts['headers'];
		$headers['Content-Type'] = 'application/json';
		$response                = wp_safe_remote_post(
			$parts['url'],
			array(
				'timeout' => (int) apply_filters( 'ssc_http_timeout', 60 ),
				'headers' => $headers,
				'body'    => (string) $parts['body'],
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg  = $response->get_error_message();
			$code = ( false !== stripos( $msg, 'timed out' ) ) ? 'timeout' : 'network';
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => $code,
					'message' => $msg,
				),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $status ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => SSC_HTTP::map_status( $status, '' ),
					'message' => 'HTTP ' . $status,
				),
			);
		}

		// Fail-closed response signature check.
		if ( '' !== $secret ) {
			$resp_sig = wp_remote_retrieve_header( $response, 'x-ssc-signature' );
			$expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
			if ( ! $resp_sig || ! hash_equals( $expected, (string) $resp_sig ) ) {
				return array(
					'ok'    => false,
					'text'  => '',
					'error' => array(
						'code'    => 'auth',
						'message' => __( 'The webhook response signature is missing or invalid.', 'smart-support-chatbot' ),
					),
				);
			}
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'malformed',
					'message' => __( 'The webhook did not return JSON.', 'smart-support-chatbot' ),
				),
			);
		}

		$text = trim( $this->extract_text( $data ) );
		if ( '' === $text ) {
			return array(
				'ok'    => false,
				'text'  => '',
				'error' => array(
					'code'    => 'malformed',
					'message' => __( 'The webhook JSON has no "reply", "message" or "text" field.', 'smart-support-chatbot' ),
				),
			);
		}
		return array(
			'ok'    => true,
			'text'  => $text,
			'error' => null,
		);
	}

	/**
	 * Webhook has no saved key; its "endpoint" is ai_webhook_url.
	 *
	 * @return array
	 */
	public function saved_credentials() {
		return array(
			'api_key'  => '',
			'model'    => '',
			'endpoint' => (string) SSC_Settings::get( 'ai_webhook_url', '' ),
		);
	}

	/**
	 * Test with overrides.
	 *
	 * @param array $overrides Explicit credentials.
	 * @return array
	 */
	public function test_connection( $overrides = array() ) {
		$saved    = $this->saved_credentials();
		$endpoint = isset( $overrides['endpoint'] ) ? (string) $overrides['endpoint'] : $saved['endpoint'];
		$result   = $this->generate_with(
			'',
			'',
			'You are a connection test. Reply with exactly: OK',
			array(
				array(
					'role'    => 'user',
					'content' => 'Reply with exactly: OK',
				),
			),
			array(
				'endpoint'   => $endpoint,
				'max_tokens' => 32,
			)
		);
		if ( ! $result['ok'] ) {
			$result['error']['friendly'] = SSC_HTTP::friendly_error( $result['error']['code'] );
		}
		return $result;
	}
}
