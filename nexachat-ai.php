<?php
/**
 * Plugin Name:       NexaChatAI
 * Plugin URI:        https://saeedzarrini.ir/en/projects/nexachat
 * Description:       Professional AI assistant for WordPress. Setup wizard, multi-provider AI engines, business knowledge base, modular architecture. Optional voice, analytics, lead collection and pharmaceutical (pharmacovigilance) extension. Persian/RTL-first with LTR support.
 * Version:           1.1.2
 * Author:            DbsStudio
 * Author URI:        https://saeedzarrini.ir/en/projects/nexachat
 * Text Domain:       nexachat-ai
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * NexaChatAI — three layers in one plugin.
 *   Layer A - Core Engine   : chat, identity, knowledge, providers, appearance, security.
 *   Layer B - Optional      : modular capabilities, disabled by default (voice, analytics, ...).
 *   Layer C - Industry      : independent extensions (pharmaceutical ADR reporting).
 *
 * @package NexaChatAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access blocked.
}

/*
 * Hand-over from the previous package folder ("smart-support-chatbot").
 *
 * The plugin was renamed so its slug and text domain match the brand.
 * WordPress sees the renamed folder as a different plugin, and loading both
 * would declare every class twice (fatal). When the old copy is active, it
 * serves this one request, is removed from the active list, and this copy
 * takes over from the next request. Settings and tables are shared (same
 * option and table names), so nothing is lost.
 */
if ( ! function_exists( 'nexachatai_legacy_basename' ) ) {
	/**
	 * Basename of the pre-rename package.
	 *
	 * @return string
	 */
	function nexachatai_legacy_basename() {
		return 'smart-support-chatbot/smart-support-chatbot.php';
	}
}
$nexachatai_network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
if ( defined( 'SSC_CHATBOT_FILE' ) || in_array( nexachatai_legacy_basename(), (array) get_option( 'active_plugins', array() ), true ) || isset( $nexachatai_network[ nexachatai_legacy_basename() ] ) ) {
	$nexachatai_active = (array) get_option( 'active_plugins', array() );
	if ( in_array( nexachatai_legacy_basename(), $nexachatai_active, true ) ) {
		update_option( 'active_plugins', array_values( array_diff( $nexachatai_active, array( nexachatai_legacy_basename() ) ) ) );
	}
	if ( isset( $nexachatai_network[ nexachatai_legacy_basename() ] ) ) {
		unset( $nexachatai_network[ nexachatai_legacy_basename() ] );
		update_site_option( 'active_sitewide_plugins', $nexachatai_network );
	}
	update_option( 'nexachatai_replaced_legacy', 1, false );
	// Deleting the old copy runs ITS uninstall.php against the shared data:
	// never let that wipe what this copy now uses.
	update_option( 'ssc_chatbot_delete_on_uninstall', 'no', false );
	unset( $nexachatai_active, $nexachatai_network );
	return;
}
unset( $nexachatai_network );

// Functions are declared in a separate file: PHP binds top-level functions
// at compile time, so declaring them here would collide with the old copy
// before the guard above could run.
define( 'SSC_CHATBOT_VERSION', '1.1.2' );
define( 'NEXACHATAI_MAIN_FILE', __FILE__ );
require_once __DIR__ . '/includes/bootstrap.php';
