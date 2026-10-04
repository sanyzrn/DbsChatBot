<?php
/**
 * A short health check of the privacy and protection settings, for the
 * ring at the top of the Settings screen. Pure logic (no WordPress calls):
 * the caller passes in the settings and what it knows about the server.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings health.
 */
class SSC_Settings_Health {

	/**
	 * Run the checks.
	 *
	 * @param array $s   Settings (as SSC_Settings::all()).
	 * @param array $env {
	 *     @type bool $https        The site uses HTTPS.
	 *     @type bool $crypto       Keys can be encrypted (OpenSSL and AUTH_KEY).
	 *     @type bool $behind_proxy A proxy header (e.g. Cloudflare) reached this request.
	 *     @type bool $forms        A module that collects contact data is on (requests or safety reports).
	 *     @type bool $history      Conversation history is on.
	 * }
	 * @return array {
	 *     @type int     $percent Share of the applicable checks that pass.
	 *     @type array[] $checks  id, panel, ok, applicable.
	 *     @type array[] $steps   The failing checks, most useful first (at most three).
	 * }
	 */
	public static function compute( $s, $env ) {
		$get = function ( $key, $default = '' ) use ( $s ) {
			return isset( $s[ $key ] ) ? $s[ $key ] : $default;
		};
		$flag = function ( $key ) use ( $env ) {
			return ! empty( $env[ $key ] );
		};

		$checks = array(
			array( 'ip', 'privacy', 'full' !== $get( 'ip_storage', 'anonymize' ), true ),
			array( 'policy', 'privacy', '' !== trim( (string) $get( 'consent_link' ) ), true ),
			array( 'consent', 'privacy', 'yes' === $get( 'consent_enabled', 'no' ), $flag( 'forms' ) ),
			array( 'retention', 'privacy', (int) $get( 'chatlog_retention_days', 30 ) > 0, $flag( 'history' ) ),
			array( 'ratelimit', 'protection', 'off' !== $get( 'rate_limit_mode', 'ip' ), true ),
			array( 'proxy', 'protection', '' !== (string) $get( 'trusted_proxy_header' ), $flag( 'behind_proxy' ) ),
			array( 'https', 'protection', $flag( 'https' ), true ),
			array( 'crypto', 'protection', $flag( 'crypto' ), true ),
		);

		$rows       = array();
		$applicable = 0;
		$passed     = 0;
		$steps      = array();
		foreach ( $checks as $check ) {
			$rows[] = array(
				'id'         => $check[0],
				'panel'      => $check[1],
				'ok'         => (bool) $check[2],
				'applicable' => (bool) $check[3],
			);
			if ( ! $check[3] ) {
				continue;
			}
			++$applicable;
			if ( $check[2] ) {
				++$passed;
			} elseif ( count( $steps ) < 3 ) {
				$steps[] = array(
					'id'    => $check[0],
					'panel' => $check[1],
				);
			}
		}
		return array(
			'percent' => $applicable > 0 ? (int) round( 100 * $passed / $applicable ) : 100,
			'checks'  => $rows,
			'steps'   => $steps,
		);
	}
}
