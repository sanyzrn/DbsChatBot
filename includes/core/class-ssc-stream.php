<?php
/**
 * Streaming chat transport (Server-Sent Events).
 *
 * Streams OpenAI-compatible tokens to the browser as they arrive so the
 * visitor can read partial output before the full reply is ready.
 * Falls back inside the common engine when the provider or server cannot stream.
 *
 * Security: same gates as /chat (is_live, rate limit). No secrets leave the
 * server; the stream only carries assistant deltas + a final envelope.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streaming class.
 */
class SSC_Stream {

	/**
	 * Can the current provider stream?
	 *
	 * @param SSC_Provider|null $provider Provider.
	 * @return bool
	 */
	public static function provider_supports( $provider ) {
		if ( null === $provider ) {
			return false;
		}
		if ( 'yes' !== SSC_Settings::get( 'streaming_enabled', 'yes' ) ) {
			return false;
		}
		// Only OpenAI-compatible chat/completions adapters stream today.
		return $provider instanceof SSC_Provider_OpenAI_Compat;
	}

	/**
	 * Stream a chat reply over SSE and terminate the request.
	 *
	 * Never returns — always exits after the final event (or an error event).
	 *
	 * @param SSC_Chat_Engine $engine   Chat engine.
	 * @param string          $message  User message.
	 * @param string          $product  Product id.
	 * @param array           $history  History.
	 */
	public static function serve( $engine, $message, $product, $history ) {
		self::send_headers();
		self::flush_all();
		$result = $engine->chat( $message, $product, $history, array( __CLASS__, 'emit_delta' ) );
		if ( empty( $result['ok'] ) ) { self::emit_error( 'ssc_chat_failed' ); }
		self::emit_done( $result );
	}

	/** Generate through the common engine; null requests a normal HTTP fallback. */
	public static function generate( $provider, $system, $messages, $opts, $on_delta ) {
		$creds = $provider->saved_credentials();
		$opts = wp_parse_args( $opts, array(
			'temperature' => (float) SSC_Settings::get( 'ai_temperature', 0.4 ),
			'max_tokens' => (int) SSC_Settings::get( 'ai_max_tokens', 800 ),
		) );
		$parts = $provider->request_parts( $creds['api_key'], $creds['model'] ?: $provider->default_model(), $system, $messages, $opts );
		$full = '';
		$status = self::curl_stream( $parts['url'], $parts['headers'], $parts['body'], function ( $delta ) use ( &$full, $on_delta ) {
			$full .= $delta;
			call_user_func( $on_delta, $delta );
		} );
		if ( connection_aborted() ) {
			return array( 'ok' => false, 'text' => '', 'error' => array( 'code' => 'network', 'message' => 'Client disconnected.' ) );
		}

		/*
		 * A stream that reached its terminal marker carries a COMPLETE answer.
		 * Accept it even when cURL reports a late transport error while closing
		 * the connection: falling back there would bill the provider a second
		 * time for a reply the visitor has already read.
		 */
		$complete = $status['got'] && $status['finished'] && ! $status['failed'] && '' !== trim( $full );
		if ( $complete ) {
			return array( 'ok' => true, 'text' => $full, 'error' => null );
		}

		// Nothing usable arrived: let the caller retry over plain HTTP.
		return null;
	}

	/**
	 * Emit SSE headers and disable buffering.
	 */
	protected static function send_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Connection: keep-alive' );
		// nginx / FastCGI: never buffer this response.
		header( 'X-Accel-Buffering: no' );
		ignore_user_abort( true );
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'output_buffering', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Drop every output buffer so chunks reach the client immediately.
	 */
	protected static function flush_all() {
		while ( ob_get_level() > 0 ) {
			if ( ! @ob_end_flush() ) { break; } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			// Not used: we need the connection open. Just flush.
		}
		self::flush();
	}

	/**
	 * Low-level flush.
	 */
	protected static function flush() {
		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	/**
	 * Write one SSE event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Payload.
	 */
	protected static function emit( $event, $data ) {
		// SSE field names are a fixed vocabulary; anything else could inject
		// extra frames into the event stream.
		$event = preg_replace( '/[^a-z_]/', '', (string) $event );
		echo 'event: ' . esc_html( $event ) . "\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		self::flush();
	}

	/**
	 * Emit a text delta.
	 *
	 * @param string $delta Text chunk.
	 */
	public static function emit_delta( $delta ) {
		self::emit( 'delta', array( 'text' => (string) $delta ) );
	}

	/**
	 * Emit the terminal envelope and stop.
	 *
	 * @param array $result Chat envelope.
	 */
	protected static function emit_done( $result ) {
		self::emit( 'done', array(
			'reply'     => isset( $result['reply'] ) ? $result['reply'] : '',
			'source'    => isset( $result['source'] ) ? $result['source'] : 'ai',
			'handoff'   => ! empty( $result['handoff'] ),
			'log_id'    => isset( $result['log_id'] ) ? (int) $result['log_id'] : 0,
			'log_token' => isset( $result['log_token'] ) ? (string) $result['log_token'] : '',
			'flags'     => isset( $result['flags'] ) ? $result['flags'] : (object) array(),
		) );
		exit;
	}

	/**
	 * Emit an error event and stop.
	 *
	 * @param string $code Error code.
	 */
	protected static function emit_error( $code ) {
		self::send_headers();
		self::flush_all();
		self::emit( 'error', array( 'code' => $code ) );
		exit;
	}

	/**
	 * Stream an OpenAI-compatible chat completion via cURL.
	 *
	 * @param string   $url      Endpoint.
	 * @param array    $headers  Headers.
	 * @param array    $body     JSON body (stream forced true).
	 * @param callable $on_delta function(string $text).
	 * @return array{got:bool,finished:bool,failed:bool,errno:int} Stream outcome.
	 */
	protected static function curl_stream( $url, $headers, $body, $on_delta ) {
		$aborted = array( 'got' => false, 'finished' => false, 'failed' => true, 'errno' => 0 );

		if ( defined( 'WP_PROXY_HOST' ) || ! function_exists( 'curl_init' ) || ! SSC_HTTP::is_safe_url( $url, true ) ) {
			return $aborted;
		}

		// Pin the validated address: cURL must not perform a second DNS lookup.
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$ip = gethostbyname( $host );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return $aborted; }
		$port = wp_parse_url( $url, PHP_URL_PORT ) ?: 443;
		$body['stream'] = true;
		$json = wp_json_encode( $body );
		if ( false === $json ) {
			return $aborted;
		}

		$header_lines = array( 'Content-Type: application/json' );
		foreach ( (array) $headers as $k => $v ) {
			$header_lines[] = $k . ': ' . $v;
		}

		$got = false;
		$finished = false;
		$failed = false;
		$bytes = 0;
		$buf = '';
		$ch  = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RESOLVE        => array( $host . ':' . $port . ':' . $ip ),
				CURLOPT_PROXY          => '',
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json,
				CURLOPT_HTTPHEADER     => $header_lines,
				CURLOPT_TIMEOUT        => 45,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_WRITEFUNCTION  => function ( $ch, $chunk ) use ( &$buf, &$got, &$finished, &$failed, &$bytes, $on_delta ) {
					$bytes += strlen( $chunk );
					if ( $bytes > 2097152 || connection_aborted() || 200 !== (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ) ) { return 0; }
					$buf .= $chunk;
					while ( false !== ( $pos = strpos( $buf, "\n" ) ) ) {
						$line = substr( $buf, 0, $pos );
						$buf  = substr( $buf, $pos + 1 );
						$line = trim( $line );
						if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
							continue;
						}
						$payload = trim( substr( $line, 5 ) );
						if ( '[DONE]' === $payload ) {
							$finished = true;
							continue;
						}
						$decoded = json_decode( $payload, true );
						if ( ! is_array( $decoded ) ) {
							continue;
						}
						if ( isset( $decoded['error'] ) ) { $failed = true; }
						if ( ! empty( $decoded['choices'][0]['finish_reason'] ) ) { $finished = true; }
						$delta = isset( $decoded['choices'][0]['delta']['content'] ) ? $decoded['choices'][0]['delta']['content'] : '';
						if ( is_string( $delta ) && '' !== $delta ) {
							$got = true;
							call_user_func( $on_delta, $delta );
						}
					}
					return strlen( $chunk );
				},
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			)
		);
		curl_exec( $ch );
		$errno = curl_errno( $ch );
		curl_close( $ch );
		return array(
			'got'      => $got,
			'finished' => $finished,
			'failed'   => $failed,
			'errno'    => $errno,
		);
	}
}
