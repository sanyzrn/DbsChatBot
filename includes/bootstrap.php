<?php
/**
 * Plugin bootstrap (loaded by nexachat-ai.php once it is safe to declare
 * functions and classes; see the hand-over note in the main file).
 *
 * @package NexaChatAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSC_CHATBOT_FILE', NEXACHATAI_MAIN_FILE );
define( 'SSC_CHATBOT_DIR', plugin_dir_path( NEXACHATAI_MAIN_FILE ) );
define( 'SSC_CHATBOT_URL', plugin_dir_url( NEXACHATAI_MAIN_FILE ) );
define( 'SSC_CHATBOT_BASENAME', plugin_basename( NEXACHATAI_MAIN_FILE ) );
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
register_activation_hook( NEXACHATAI_MAIN_FILE, 'ssc_chatbot_activate' );

/**
 * Deactivation: only unschedule recurring work. Data is never touched.
 */
function ssc_chatbot_deactivate() {
	SSC_Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( NEXACHATAI_MAIN_FILE, 'ssc_chatbot_deactivate' );

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
 * One-time notice after taking over from the pre-rename folder.
 */
function nexachatai_legacy_notice() {
	if ( ! get_option( 'nexachatai_replaced_legacy' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_option( 'nexachatai_replaced_legacy' );
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'NexaChatAI moved to a new plugin folder (nexachat-ai). The old copy (smart-support-chatbot) was deactivated automatically; all settings and data were kept. The "remove data on uninstall" policy was switched off so deleting the old copy cannot erase shared data; you can now delete it from the Plugins screen.', 'nexachat-ai' ) . '</p></div>';
}
add_action( 'admin_notices', 'nexachatai_legacy_notice' );

/**
 * Translations.
 */
function ssc_chatbot_load_textdomain() {
	load_plugin_textdomain( 'nexachat-ai', false, dirname( SSC_CHATBOT_BASENAME ) . '/languages' );
}
add_action( 'init', 'ssc_chatbot_load_textdomain' );

// Visitor-facing strings follow the widget language setting, not the site language.
SSC_I18n::init();

/**
 * Back-compat shim for 4.x integrations that called SSC_Chatbot().
 *
 * @deprecated 0.5.1 Use SSC_Plugin::instance().
 * @return SSC_Plugin
 */
function SSC_Chatbot() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals, WordPress.NamingConventions.ValidFunctionName
	return SSC_Plugin::instance();
}
