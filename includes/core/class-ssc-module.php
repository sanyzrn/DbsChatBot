<?php
/**
 * Base class for optional modules (Layer B) and industry extensions (Layer C).
 *
 * Module lifecycle contract:
 * - Disabled by default on NEW installations.
 * - When inactive: no admin UI entries, no frontend assets, no background
 *   jobs, no endpoints, no processing. Enforcement is SERVER-SIDE via
 *   SSC_Modules::is_active() checks inside every entry point.
 * - When deactivated: ALL data is preserved (only the feature flag flips).
 * - Each module declares dependencies and whether it needs configuration.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract module.
 */
abstract class SSC_Module {

	/**
	 * Module id (unique key).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Display title.
	 *
	 * @return string
	 */
	abstract public function title();

	/**
	 * One-line description.
	 *
	 * @return string
	 */
	abstract public function description();

	/**
	 * Primary user benefit (shown on the marketplace card).
	 *
	 * @return string
	 */
	abstract public function benefit();

	/**
	 * Category: engagement | communication | analytics | knowledge | business | industry.
	 *
	 * @return string
	 */
	abstract public function category();

	/**
	 * Inline SVG icon (20x20 viewBox, uses currentColor).
	 *
	 * @return string
	 */
	abstract public function icon();

	/**
	 * Module ids that must be active for this module to work.
	 *
	 * @return string[]
	 */
	public function dependencies() {
		return array();
	}

	/**
	 * Does the module need configuration before it is useful?
	 *
	 * @return bool
	 */
	public function needs_config() {
		return false;
	}

	/**
	 * Is the module fully configured right now?
	 *
	 * @return bool
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Is this an industry extension (Layer C)?
	 *
	 * @return bool
	 */
	public function is_industry() {
		return 'industry' === $this->category();
	}

	/**
	 * Register runtime hooks. Called ONLY when the module is active.
	 */
	public function register() {}

	/**
	 * Register admin-specific hooks. Called ONLY when active + is_admin().
	 */
	public function register_admin() {}

	/**
	 * Module-scoped settings contributed to the settings page (key => schema).
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * On-activation hook (module just switched on).
	 */
	public function on_activate() {}

	/**
	 * On-deactivation hook (module switched off). NEVER delete data here.
	 */
	public function on_deactivate() {}
}
