<?php
/**
 * Class autoloader for the SSC_* namespace.
 *
 * Maps class SSC_Foo_Bar to class-ssc-foo-bar.php inside one of the known
 * layer directories. Keeps the plugin free of require_once chains and makes
 * the module system lightweight.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class.
 */
final class SSC_Autoloader {

	/**
	 * Search roots, in order.
	 *
	 * @var string[]
	 */
	private static $roots = array(
		'includes/core/',
		'includes/core/providers/',
		'includes/modules/',
		'includes/modules/pharma/',
		'includes/admin/',
		'includes/',
	);

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Attempt to load a SSC_* class.
	 *
	 * @param string $class Class name.
	 * @return bool True when the file was loaded.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'SSC_' ) ) {
			return false;
		}
		$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		foreach ( self::$roots as $root ) {
			$path = SSC_CHATBOT_DIR . $root . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				return true;
			}
		}
		return false;
	}
}
