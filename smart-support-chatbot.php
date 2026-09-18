<?php
/**
 * Plugin Name:       NexaChatAI
 * Plugin URI:        https://saeedzarrini.ir/en/projects/nexachat
 * Description:       Professional AI assistant for WordPress. Setup wizard, multi-provider AI engines, business knowledge base, modular architecture. Optional voice, analytics, lead collection and pharmaceutical (pharmacovigilance) extension. Persian/RTL-first with LTR support.
 * Version:           0.6.1-beta
 * Author:            DbsStudio
 * Author URI:        https://saeedzarrini.ir/en/projects/nexachat
 * Text Domain:       smart-support-chatbot
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * NexaChatAI (beta) — three layers in one plugin.
 *   Layer A - Core Engine   : chat, identity, knowledge, providers, appearance, security.
 *   Layer B - Optional      : modular capabilities, disabled by default (voice, analytics, ...).
 *   Layer C - Industry      : independent extensions (pharmaceutical ADR reporting).
 *
 * @package NexaChatAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access blocked.
}

define( 'SSC_CHATBOT_VERSION', '0.6.1-beta' );
define( 'SSC_CHATBOT_FILE', __FILE__ );
define( 'SSC_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSC_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'SSC_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'NEXACHATAI_NAME', 'NexaChatAI' );

/*
 * Autoloader: maps SSC_* class names to class-ssc-*.php files across the
 * three architectural layers. No external dependencies.
 */
require_once SSC_CHATBOT_DIR . 'includes/class-ssc-autoloader.php';
SSC_Autoloader::register();

/**
 * Activation: install schema, seed defaults, arm the setup wizard.
 *
 * On brand-new installations the public chatbot stays INACTIVE until the
 * administrator completes the Setup Wizard and explicitly publishes it.
 * Upgrades from 4.x preserve all data and never disable a live chatbot.
 */
function ssc_chatbot_activate() {
	SSC_Schema::install();
	SSC_Settings::seed_defaults();
	SSC_Setup::on_activation();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ssc_chatbot_activate' );

/**
 * Deactivation: only unschedule recurring work. Data is never touched.
 */
function ssc_chatbot_deactivate() {
	SSC_Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ssc_chatbot_deactivate' );

/**
 * Boot the plugin after all core files of WordPress are available.
 */
function ssc_chatbot_boot() {
	// One-time data upgrades (settings remap, table migrations) before first read.
	SSC_Schema::maybe_upgrade();
	SSC_Plugin::instance();
}
add_action( 'plugins_loaded', 'ssc_chatbot_boot', 5 );

/**
 * Translations.
 */
function ssc_chatbot_load_textdomain() {
	load_plugin_textdomain( 'smart-support-chatbot', false, dirname( SSC_CHATBOT_BASENAME ) . '/languages' );
}
add_action( 'init', 'ssc_chatbot_load_textdomain' );

/**
 * Back-compat shim for 4.x integrations that called SSC_Chatbot().
 *
 * @deprecated 0.5.1 Use SSC_Plugin::instance().
 * @return SSC_Plugin
 */
function SSC_Chatbot() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals, WordPress.NamingConventions.ValidFunctionName
	return SSC_Plugin::instance();
}

