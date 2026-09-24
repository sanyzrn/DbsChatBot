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

	/**
	 * Resolve the model a submitted form means for one provider.
	 *
	 * The model dropdown carries a `__manual__` sentinel that reveals a free-text
	 * field named `{provider}_model_manual`. This reads the pair server-side so the
	 * typed model is honoured even when the browser never rewrote the select
	 * (no JavaScript, a blocked script, or a failed option injection).
	 *
	 * @param array  $source      Raw request data (already unslashed).
	 * @param string $provider_id Provider id, e.g. 'openai'.
	 * @return string|null Sanitized model id, or null when the form carried none.
	 */
	public static function model_from_request( $source, $provider_id ) {
		$select = isset( $source[ $provider_id . '_model' ] ) ? sanitize_text_field( (string) $source[ $provider_id . '_model' ] ) : null;
		$manual = isset( $source[ $provider_id . '_model_manual' ] ) ? sanitize_text_field( (string) $source[ $provider_id . '_model_manual' ] ) : '';
		$manual = trim( $manual );

		// The sentinel is a UI token, never a model id: the typed value replaces it.
		if ( '__manual__' === $select || ( null !== $select && '' === trim( $select ) ) ) {
			return $manual;
		}
		if ( null === $select ) {
			return '' !== $manual ? $manual : null;
		}
		return $select;
	}
}
