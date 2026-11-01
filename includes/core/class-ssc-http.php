<?php
/**
 * Hardened outbound HTTP helper with SSRF protection.
 *
 * Every provider/webhook request goes through this class:
 * - wp_safe_remote_post only,
 * - DNS-resolved private/link-local range blocking (defeats hostname tricks),
 * - HTTPS required whenever credentials travel,
 * - timeouts and response validation centralized.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP helper class.
 */
class SSC_HTTP {

	/**
	 * Canonical failure codes used across providers.
	 *
	 * @return string[]
	 */
	public static function error_codes() {
		return array( 'auth', 'permission', 'policy', 'forbidden', 'model', 'credits', 'rate_limit', 'region', 'network', 'timeout', 'endpoint', 'malformed' );
	}

	/**
	 * Validate an outbound URL (SSRF guard).
	 *
	 * Known limitation (DNS rebinding / TOCTOU): DNS is resolved once here
	 * to reject private/link-local targets. wp_safe_remote_post / wp_safe_remote_get
	 * perform their own resolution when the request is issued, so a hostname
	 * could theoretically rebind to a private address between this check and
	 * the actual request. WordPress core applies a second layer of protection
	 * (http_request_host_is_external / reject_unsafe_urls), so practical risk
	 * is low — documented here as a known residual limitation, not a bug.
	 *
	 * @param string $url          URL to validate.
	 * @param bool   $needs_https Require HTTPS (any credential-bearing endpoint).
	 * @return bool
	 */
	public static function is_safe_url( $url, $needs_https = false ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$host = strtolower( $host );

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ip = $host;
		} else {
			// Resolve DNS: hostnames pointing into private space are rejected.
			$ip = gethostbyname( $host );
			if ( $ip === $host ) {
				return false; // Unresolvable.
			}
		}
		$blocked = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, $blocked ) ) {
			return false;
		}
		if ( $needs_https && 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * POST JSON and return a normalized envelope.
	 *
	 * @param string $url     Destination.
	 * @param array  $headers Headers.
	 * @param mixed  $body    JSON-encodable body.
	 * @param array  $opts    timeout, needs_https, signature secret.
	 * @return array envelope: ok(bool), status(int), data(array|null), raw(string), error(array{code,message}|null).
	 */
	public static function post_json( $url, $headers, $body, $opts = array() ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'timeout'     => 60,
				'needs_https' => false,
			)
		);

		if ( ! self::is_safe_url( $url, $opts['needs_https'] ) ) {
			return self::fail( 'endpoint', __( 'The endpoint address is not allowed (it must be a valid public address; HTTPS is required when credentials are sent).', 'vazho' ) );
		}

		$payload = wp_json_encode( $body );
		if ( false === $payload ) {
			return self::fail( 'malformed', __( 'The request body could not be encoded.', 'vazho' ) );
		}

		$headers['Content-Type'] = 'application/json';
		$response                = wp_safe_remote_post(
			$url,
			array(
				'redirection'         => 0, // Never forward keys or signed payloads to a redirect target.
				'limit_response_size' => 2097152,
				'timeout'             => (int) $opts['timeout'],
				'headers'             => $headers,
				'body'                => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			$code    = 'network';
			if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' ) ) {
				$code = 'timeout';
			}
			if ( false !== stripos( $message, 'resolve' ) || false !== stripos( $message, 'name or service' ) ) {
				$code = 'network';
			}
			return self::fail( $code, $message );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$detail = '';
			if ( is_array( $data ) ) {
				if ( isset( $data['error']['message'] ) ) {
					$detail = (string) $data['error']['message'];
				} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
					$detail = (string) $data['error'];
				} elseif ( isset( $data['message'] ) ) {
					$detail = (string) $data['message'];
				}
			}
			if ( '' === $detail ) {
				$detail = mb_substr( wp_strip_all_tags( $raw ), 0, 300 );
			}
			return array(
				'ok'     => false,
				'status' => $status,
				'data'   => is_array( $data ) ? $data : null,
				'raw'    => $raw,
				'error'  => array(
					'code'       => self::map_status( $status, $detail ),
					'message'    => 'HTTP ' . $status . ( '' !== $detail ? ' — ' . $detail : '' ),
					// The provider's own request id: what its support asks for.
					'request_id' => self::request_id( $response ),
				),
			);
		}

		if ( ! is_array( $data ) ) {
			return self::fail( 'malformed', __( 'The provider response was not valid JSON.', 'vazho' ) );
		}

		return array(
			'ok'     => true,
			'status' => $status,
			'data'   => $data,
			'raw'    => $raw,
			'error'  => null,
		);
	}

	/**
	 * The request id a provider put in its response headers (for its support), cleaned.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string '' when there is none.
	 */
	public static function request_id( $response ) {
		foreach ( array( 'x-request-id', 'request-id', 'x-amzn-requestid', 'cf-ray' ) as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( is_string( $value ) && '' !== $value ) {
				return substr( preg_replace( '/[^A-Za-z0-9._:\-]/', '', $value ), 0, 80 );
			}
		}
		return '';
	}

	/**
	 * Does the text contain any of the phrases?
	 *
	 * @param string   $text    Lower-case text.
	 * @param string[] $phrases Phrases.
	 * @return bool
	 */
	protected static function mentions( $text, $phrases ) {
		foreach ( $phrases as $phrase ) {
			if ( false !== strpos( $text, $phrase ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Map an HTTP status + detail text to a canonical error code.
	 *
	 * 401 means the key was not accepted. 403 means the provider (or something in
	 * front of it) refused the request: that is NOT always the key, so it is told
	 * apart: a key the provider calls invalid, a policy or firewall block, missing
	 * permission for the model or action, or a refusal with no reason given.
	 *
	 * @param int    $status HTTP status.
	 * @param string $detail Provider detail text.
	 * @return string
	 */
	public static function map_status( $status, $detail = '' ) {
		$d = strtolower( $detail );
		if ( 401 === $status || 403 === $status ) {
			if ( self::mentions( $d, array( 'quota', 'credit', 'billing' ) ) ) {
				return 'credits';
			}
			if ( self::mentions( $d, array( 'region', 'country', 'location' ) ) ) {
				return 'region';
			}
			if ( 401 === $status || self::mentions( $d, array( 'invalid api key', 'invalid_api_key', 'incorrect api key', 'api key not valid', 'api_key_invalid', 'invalid key', 'invalid x-api-key', 'invalid_authentication', 'invalid authentication', 'api key expired', 'expired api key' ) ) ) {
				return 'auth';
			}
			if ( self::mentions( $d, array( 'policy', 'firewall', 'waf', 'blocked', 'access denied', 'denied by' ) ) ) {
				return 'policy';
			}
			if ( self::mentions( $d, array( 'permission', 'not allowed', 'does not have access', 'do not have access', 'no access', 'not authorized', 'unauthorized to' ) ) ) {
				return 'permission';
			}
			return 'forbidden';
		}
		if ( 429 === $status ) {
			if ( false !== strpos( $d, 'insufficient_quota' ) || false !== strpos( $d, 'billing' ) || false !== strpos( $d, 'credit' ) ) {
				return 'credits';
			}
			return 'rate_limit';
		}
		if ( 404 === $status || 400 === $status ) {
			if ( false !== strpos( $d, 'model' ) ) {
				return 'model';
			}
			return 'malformed';
		}
		if ( $status >= 500 ) {
			return 'network';
		}
		if ( false !== strpos( $d, 'insufficient' ) || false !== strpos( $d, 'quota' ) ) {
			return 'credits';
		}
		if ( false !== strpos( $d, 'model' ) && false !== strpos( $d, 'not found' ) ) {
			return 'model';
		}
		return 'network';
	}

	/**
	 * Human-readable, credential-free error messages.
	 *
	 * @param string $code Canonical code.
	 * @return string
	 */
	public static function friendly_error( $code ) {
		$map = array(
			'auth'       => __( 'The API key was rejected. Double-check the key and that it belongs to the selected provider.', 'vazho' ),
			'permission' => __( 'The provider refused the request: this key or project has no permission for the selected model or action (HTTP 403). The key itself may be fine. Check the project permissions and model access.', 'vazho' ),
			'policy'     => __( 'The request was blocked by a security policy at the provider or at a firewall in front of it (HTTP 403). This is not necessarily a wrong key. Check the account\'s access rules, IP restrictions and content policy, then test again.', 'vazho' ),
			'forbidden'  => __( 'The provider refused the request (HTTP 403) without saying why. That does not always mean the key is wrong. Check the account\'s access rules and the model, and quote the request id if you contact the provider.', 'vazho' ),
			'model'      => __( 'The selected model is not available for this account or does not exist. Pick another model or enter the model ID manually.', 'vazho' ),
			'credits'    => __( 'The account has insufficient credits or billing is not active. Check your provider account balance.', 'vazho' ),
			'rate_limit' => __( 'The provider is rate-limiting requests right now. Wait a moment and test again.', 'vazho' ),
			'region'     => __( 'The provider is not available from your server region or the account is region-restricted.', 'vazho' ),
			'network'    => __( 'The server could not reach the provider (network or provider outage). Try again later.', 'vazho' ),
			'timeout'    => __( 'The provider took too long to respond. Try a faster model or test again.', 'vazho' ),
			'endpoint'   => __( 'The endpoint address is invalid or not allowed.', 'vazho' ),
			'malformed'  => __( 'The provider returned an unexpected response format.', 'vazho' ),
			'length'     => __( 'The model used its whole answer budget before it wrote anything. Raise "Max answer length", or choose a model that thinks less before answering.', 'vazho' ),
			'filtered'   => __( 'The provider stopped the answer under its content policy.', 'vazho' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : __( 'Unknown connection error.', 'vazho' );
	}

	/**
	 * Failure envelope.
	 *
	 * @param string $code    Canonical code.
	 * @param string $message Raw message.
	 * @return array
	 */
	protected static function fail( $code, $message ) {
		return array(
			'ok'     => false,
			'status' => 0,
			'data'   => null,
			'raw'    => '',
			'error'  => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
