<?php
/**
 * Plugin Name:       Vazho
 * Plugin URI:        https://dbsstudio.ir/vazho
 * Description:       Professional AI assistant for WordPress. Setup wizard, multi-provider AI engines, business knowledge base, modular architecture. Optional voice, analytics, lead collection and pharmaceutical (pharmacovigilance) extension. Persian/RTL-first with LTR support.
 * Version:           1.7.9
 * Author:            DbsStudio
 * Author URI:        https://dbsstudio.ir
 * Text Domain:       vazho
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * Vazho — three layers in one plugin.
 *   Layer A - Core Engine   : chat, identity, knowledge, providers, appearance, security.
 *   Layer B - Optional      : modular capabilities, disabled by default (voice, analytics, ...).
 *   Layer C - Industry      : independent extensions (pharmaceutical ADR reporting).
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access blocked.
}

define( 'SSC_CHATBOT_VERSION', '1.7.9' );
define( 'VAZHO_MAIN_FILE', __FILE__ );

// RTL-Theme licence file. It exists only in the ZIP sold on RTL-Theme (tools/build-release.py --rtl-license);
// the public/GitHub ZIP never contains it, and the plugin works the same without it.
// The file is encoded with ionCube: where the ionCube Loader is missing it prints an error and ends the request,
// and it was encoded for PHP 8.1 and 8.2 only. So it is loaded only where the Loader is active on those versions;
// everywhere else it is left alone instead of stopping the site.
$vazho_rtl_license = __DIR__ . DIRECTORY_SEPARATOR . 'RTL_License_c5f56181a1bd41bb.php';
if ( PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80300 && extension_loaded( 'ionCube Loader' ) && is_file( $vazho_rtl_license ) ) {
	require_once $vazho_rtl_license;
}
unset( $vazho_rtl_license );

// WooCommerce: orders are read only through WooCommerce's own order API (wc_get_order, the WC_Order getters,
// order notes and the checkout hooks), never from the posts tables, so the plugin works with high-performance
// order storage (HPOS). Declaring it keeps WooCommerce from listing the plugin as "compatibility unknown".
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VAZHO_MAIN_FILE, true );
		}
	}
);

require_once __DIR__ . '/includes/bootstrap.php';
