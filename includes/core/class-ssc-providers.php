<?php
/**
 * Provider registry.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Providers registry.
 */
class SSC_Providers {

	/**
	 * All registered provider instances (id => object).
	 *
	 * @var array
	 */
	protected static $instances = null;

	/**
	 * Register providers.
	 *
	 * @return SSC_Provider[]
	 */
	public static function all() {
		if ( null === self::$instances ) {
			$providers = array(
				new SSC_Provider_Openai(),
				new SSC_Provider_Gemini(),
				new SSC_Provider_Claude(),
				new SSC_Provider_Openrouter(),
				new SSC_Provider_Custom(),
				new SSC_Provider_Webhook(),
			);
			/**
			 * Third parties can add adapters (must extend SSC_Provider).
			 *
			 * @param SSC_Provider[] $providers
			 */
			$filtered = apply_filters( 'ssc_providers', $providers );
			$map      = array();
			foreach ( $filtered as $p ) {
				if ( is_object( $p ) && $p instanceof SSC_Provider ) {
					$map[ $p->id() ] = $p;
				}
			}
			self::$instances = $map;
		}
		return self::$instances;
	}

	/**
	 * Provider labels for UI (id => label), plus the 'none' pseudo-provider.
	 *
	 * @return string[]
	 */
	public static function labels() {
		$out = array( 'none' => __( 'No AI engine (offline answers only)', 'smart-support-chatbot' ) );
		foreach ( self::all() as $id => $provider ) {
			$out[ $id ] = $provider->label();
		}
		return $out;
	}

	/**
	 * Fetch one provider instance.
	 *
	 * @param string $id Provider id.
	 * @return SSC_Provider|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Currently configured provider (null when 'none').
	 *
	 * @return SSC_Provider|null
	 */
	public static function current() {
		$id = (string) SSC_Settings::get( 'ai_provider', 'none' );
		return ( 'none' === $id ) ? null : self::get( $id );
	}
}
