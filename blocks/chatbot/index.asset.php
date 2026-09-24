<?php
/**
 * Hand-declared script dependencies for the block editor script
 * (substitute for the @wordpress/scripts-generated asset file).
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
	// Defined by the plugin bootstrap; the fallback keeps this file harmless
	// if WordPress ever reads it before the constant exists.
	'version'      => defined( 'SSC_CHATBOT_VERSION' ) ? SSC_CHATBOT_VERSION : false,
);
