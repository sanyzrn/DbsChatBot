<?php
/**
 * Plugin Name:       NexaChatAI
 * Plugin URI:        https://dbsstudio.ir/nexachatai
 * Description:       Professional AI assistant for WordPress. Setup wizard, multi-provider AI engines, business knowledge base, modular architecture. Optional voice, analytics, lead collection and pharmaceutical (pharmacovigilance) extension. Persian/RTL-first with LTR support.
 * Version:           1.7.8
 * Author:            DbsStudio
 * Author URI:        https://dbsstudio.ir
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
define( 'SSC_CHATBOT_VERSION', '1.7.8' );
define( 'NEXACHATAI_MAIN_FILE', __FILE__ );

// RTL-Theme licence file. It exists only in the ZIP sold on RTL-Theme (tools/build-release.py --rtl-license);
// the public/GitHub ZIP never contains it, and the plugin works the same without it.
// The file is encoded with ionCube: where the ionCube Loader is missing it prints an error and ends the request,
// and it was encoded for PHP 8.1 and 8.2 only. So it is loaded only where the Loader is active on those versions;
// everywhere else it is left alone instead of stopping the site.
$nexachatai_rtl_license = __DIR__ . DIRECTORY_SEPARATOR . 'RTL_License_c5f56181a1bd41bb.php';
if ( PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80300 && extension_loaded( 'ionCube Loader' ) && is_file( $nexachatai_rtl_license ) ) {
	require_once $nexachatai_rtl_license;
}
unset( $nexachatai_rtl_license );

// WooCommerce: orders are read only through WooCommerce's own order API (wc_get_order, the WC_Order getters,
// order notes and the checkout hooks), never from the posts tables, so the plugin works with high-performance
// order storage (HPOS). Declaring it keeps WooCommerce from listing the plugin as "compatibility unknown".
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NEXACHATAI_MAIN_FILE, true );
		}
	}
);

require_once __DIR__ . '/includes/bootstrap.php';
