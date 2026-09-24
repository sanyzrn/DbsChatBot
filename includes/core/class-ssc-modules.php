<?php
/**
 * Module registry: feature flags, activation state, dependency resolution,
 * and the 4.x -> 5.0 module-state migration.
 *
 * Server-side enforcement pattern (used everywhere):
 *   if ( ! SSC_Modules::is_active( 'voice' ) ) { return error_or_noop; }
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modules registry.
 */
class SSC_Modules {

	const OPTION = 'ssc_chatbot_modules';

	/**
	 * Instantiated module objects.
	 *
	 * @var SSC_Module[]|null
	 */
	protected static $modules = null;

	/**
	 * All module definitions (id => SSC_Module).
	 *
	 * @return SSC_Module[]
	 */
	public static function all() {
		if ( null === self::$modules ) {
			$modules = array(
				new SSC_Module_Voice(),
				new SSC_Module_Leads(),
				new SSC_Module_History(),
				new SSC_Module_Faq(),
				new SSC_Module_Analytics(),
				new SSC_Module_Csat(),
				new SSC_Module_Handoff(),
				new SSC_Module_Proactive(),
				new SSC_Module_Notifications(),
				new SSC_Module_Pharma(),
			);
			/**
			 * Filter the module catalog.
			 *
			 * @param SSC_Module[] $modules
			 */
			$filtered = apply_filters( 'ssc_modules', $modules );
			$map      = array();
			foreach ( $filtered as $m ) {
				if ( is_object( $m ) && $m instanceof SSC_Module ) {
					$map[ $m->id() ] = $m;
				}
			}
			self::$modules = $map;
		}
		return self::$modules;
	}

	/**
	 * Active module ids (stored state).
	 *
	 * @return string[]
	 */
	public static function active_ids() {
		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) ) {
			// First run after 5.0 upgrade: derive state from 4.x toggles so a
			// configured chatbot never loses functionality on upgrade.
			$saved = self::migrate_v4_state();
			update_option( self::OPTION, $saved, false );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- no random here, array filter.
		return array_values( array_filter( $saved, 'is_string' ) );
	}

	/**
	 * Fetch one module definition.
	 *
	 * @param string $id Module id.
	 * @return SSC_Module|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Is a module active? THE server-side feature gate.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public static function is_active( $id ) {
		return in_array( $id, self::active_ids(), true );
	}

	/**
	 * Activate a module (with dependency check + on_activate hook).
	 *
	 * @param string $id Module id.
	 * @return bool|WP_Error True on success; false when unknown; WP_Error on missing dependency.
	 */
	public static function activate( $id ) {
		$modules = self::all();
		if ( ! isset( $modules[ $id ] ) ) {
			return false;
		}
		if ( self::is_active( $id ) ) {
			return true;
		}

		// Dependencies must be active (auto-offered, not silently enabled).
		$missing = array();
		foreach ( $modules[ $id ]->dependencies() as $dep ) {
			if ( ! self::is_active( $dep ) ) {
				$missing[] = isset( $modules[ $dep ] ) ? $modules[ $dep ]->title() : $dep;
			}
		}
		if ( $missing ) {
			return new WP_Error(
				'ssc_module_dep',
				sprintf(
					/* translators: %s: comma-separated list of required module names. */
					__( 'This module needs: %s', 'smart-support-chatbot' ),
					implode( ', ', $missing )
				)
			);
		}

		$active   = self::active_ids();
		$active[] = $id;
		update_option( self::OPTION, $active, false );
		$modules[ $id ]->on_activate();
		return true;
	}

	/**
	 * Deactivate a module (data preserved, dependents warned by UI).
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public static function deactivate( $id ) {
		if ( ! self::is_active( $id ) ) {
			return true;
		}
		$active = array_values( array_diff( self::active_ids(), array( $id ) ) );
		update_option( self::OPTION, $active, false );
		$modules = self::all();
		if ( isset( $modules[ $id ] ) ) {
			$modules[ $id ]->on_deactivate();
		}
		return true;
	}

	/**
	 * Boot all ACTIVE modules (runtime hooks). Inactive modules never load
	 * their hooks, scripts, endpoints or jobs.
	 */
	public static function boot_active() {
		foreach ( self::all() as $id => $module ) {
			if ( self::is_active( $id ) ) {
				$module->register();
				if ( is_admin() ) {
					$module->register_admin();
				}
			}
		}
	}

	/**
	 * Derive the 5.0 module state from legacy 4.x toggles (upgrade path).
	 * New installations get an EMPTY list (all modules off).
	 *
	 * @return string[]
	 */
	public static function migrate_v4_state() {
		$settings = get_option( SSC_Settings::OPTION_KEY, array() );
		$legacy   = is_array( $settings ) && ( isset( $settings['company_id'] ) || isset( $settings['show_company'] ) );
		if ( ! $legacy ) {
			return array(); // Fresh install: everything optional stays off.
		}

		$active = array();
		$yes    = function ( $key ) use ( $settings ) {
			return isset( $settings[ $key ] ) && 'yes' === $settings[ $key ];
		};

		if ( $yes( 'voice_enabled' ) ) {
			$active[] = 'voice';
		}
		if ( $yes( 'show_consult' ) || ! empty( $settings['form_fields'] ) ) {
			$active[] = 'leads';
		}
		if ( $yes( 'chatlog_enabled' ) ) {
			$active[] = 'history';
		}
		if ( $yes( 'csat_enabled' ) ) {
			$active[] = 'csat';
		}
		if ( $yes( 'handoff_enabled' ) ) {
			$active[] = 'handoff';
		}
		if ( $yes( 'proactive_enabled' ) ) {
			$active[] = 'proactive';
		}
		if ( $yes( 'notify_enabled' ) || $yes( 'email_enabled' ) ) {
			$active[] = 'notifications';
		}
		if ( isset( $settings['business_mode'] ) && 'pharma' === $settings['business_mode'] && $yes( 'show_adr' ) ) {
			$active[] = 'pharma';
		}
		// FAQ module: active when a populated QA bank exists (valuable capability).
		if ( isset( $settings['qa_bank'] ) && is_array( $settings['qa_bank'] ) && count( $settings['qa_bank'] ) > 0 ) {
			$active[] = 'faq';
		}
		return array_values( array_unique( $active ) );
	}

	/**
	 * Category labels for the marketplace.
	 *
	 * @return string[]
	 */
	public static function categories() {
		return array(
			'engagement'    => __( 'Customer Engagement', 'smart-support-chatbot' ),
			'communication' => __( 'Communication', 'smart-support-chatbot' ),
			'analytics'     => __( 'Analytics', 'smart-support-chatbot' ),
			'knowledge'     => __( 'Knowledge & Intelligence', 'smart-support-chatbot' ),
			'business'      => __( 'Business Tools', 'smart-support-chatbot' ),
			'industry'      => __( 'Industry Extensions', 'smart-support-chatbot' ),
		);
	}

	/**
	 * Modules grouped by category (for the marketplace grid).
	 *
	 * @return SSC_Module[][]
	 */
	public static function by_category() {
		$out = array();
		foreach ( self::all() as $module ) {
			$out[ $module->category() ][] = $module;
		}
		return $out;
	}

	/**
	 * Status of every module for UI cards: active | inactive | setup.
	 *
	 * @return string[][] id => {status, configured}
	 */
	public static function statuses() {
		$out = array();
		foreach ( self::all() as $id => $module ) {
			$status = 'inactive';
			if ( self::is_active( $id ) ) {
				$status = $module->needs_config() && ! $module->is_configured() ? 'setup' : 'active';
			}
			$out[ $id ] = array(
				'status'     => $status,
				'configured' => $module->is_configured(),
			);
		}
		return $out;
	}
}
