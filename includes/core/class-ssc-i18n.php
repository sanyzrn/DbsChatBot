<?php
/**
 * Visitor-facing language, independent of the WordPress site language.
 *
 * A Persian chat widget on an English WordPress (or the reverse) is common:
 * the site owner picks the widget language in Appearance. Only this plugin's
 * own strings are affected, and only where visitors see them (site pages,
 * the widget's REST/AJAX calls, and the admin preview of the widget).
 *
 * Why gettext filters instead of load_textdomain(): since WordPress 6.5 the
 * translation controller looks strings up under the CURRENT locale, so a
 * fa_IR file loaded on an en_US site is never consulted. Filtering our own
 * domain with our own MO file works the same on every supported version.
 *
 * @package NexaChatAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_I18n
 */
class SSC_I18n {

	const DOMAIN = 'nexachat-ai';

	/**
	 * Locales the widget can be switched to (bundled translations + source).
	 *
	 * @var string[]
	 */
	const LOCALES = array( 'fa_IR', 'en_US' );

	/**
	 * Active override locale (null = WordPress decides).
	 *
	 * @var string|null
	 */
	protected static $active = null;

	/**
	 * Loaded catalogs per locale (false = no file, strings stay in English).
	 *
	 * @var array
	 */
	protected static $catalogs = array();

	/**
	 * Wire the visitor-facing contexts.
	 */
	public static function init() {
		// Front-end pages (the 'wp' action never runs for admin, REST or AJAX).
		add_action( 'wp', array( __CLASS__, 'use_widget_locale' ) );
		// The widget's REST calls (chat replies, form results, error messages).
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_pre_dispatch' ), 10, 3 );
		// The widget's admin-ajax fallback.
		add_action( 'admin_init', array( __CLASS__, 'maybe_ajax' ), 1 );
	}

	/**
	 * Locale the widget speaks.
	 *
	 * "auto": the assistant's answer language when it names Persian or
	 * English; else Persian when the widget is set right-to-left on a
	 * left-to-right site (an RTL widget with English labels is never wanted);
	 * else the site language.
	 *
	 * @return string
	 */
	public static function widget_locale() {
		$choice = (string) SSC_Settings::get( 'widget_language', 'auto' );
		if ( in_array( $choice, self::LOCALES, true ) ) {
			return $choice;
		}
		$business = SSC_Settings::business();
		$answer   = self::locale_from_language( isset( $business['language'] ) ? $business['language'] : '' );
		if ( '' !== $answer ) {
			return $answer;
		}
		$site = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( 'rtl' === SSC_Settings::get( 'direction', 'rtl' ) && ! self::is_rtl_locale( $site ) ) {
			return 'fa_IR';
		}
		return $site;
	}

	/**
	 * Map a free-text language name ("fa", "Persian", "فارسی", "en-US"…) to a locale.
	 *
	 * @param string $language Language as typed by the admin.
	 * @return string Locale, or '' when unknown.
	 */
	public static function locale_from_language( $language ) {
		$language = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $language ) ) : strtolower( trim( (string) $language ) );
		if ( '' === $language ) {
			return '';
		}
		if ( preg_match( '/^fa(?:[_-]|$)|farsi|persian|فارسی|پارسی/u', $language ) ) {
			return 'fa_IR';
		}
		if ( preg_match( '/^en(?:[_-]|$)|english|انگلیسی/u', $language ) ) {
			return 'en_US';
		}
		return '';
	}

	/**
	 * Whether a locale is written right to left.
	 *
	 * @param string $locale Locale.
	 * @return bool
	 */
	public static function is_rtl_locale( $locale ) {
		return in_array( substr( strtolower( (string) $locale ), 0, 2 ), array( 'fa', 'ar', 'he', 'ur', 'ps', 'ug', 'yi', 'ku', 'sd', 'dv' ), true );
	}

	/**
	 * Serve this plugin's strings in the widget language (no-op when it
	 * already matches WordPress's locale).
	 *
	 * @return bool Whether an override is active.
	 */
	public static function use_widget_locale() {
		$locale = self::widget_locale();
		$site   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( $locale === $site ) {
			self::restore();
			return false;
		}
		if ( self::$active === $locale ) {
			return true;
		}
		self::restore();
		self::$active = $locale;
		add_filter( 'gettext_' . self::DOMAIN, array( __CLASS__, 'gettext' ), 10, 2 );
		add_filter( 'gettext_with_context_' . self::DOMAIN, array( __CLASS__, 'gettext_with_context' ), 10, 3 );
		add_filter( 'ngettext_' . self::DOMAIN, array( __CLASS__, 'ngettext' ), 10, 4 );
		add_filter( 'ngettext_with_context_' . self::DOMAIN, array( __CLASS__, 'ngettext_with_context' ), 10, 5 );
		return true;
	}

	/**
	 * Back to WordPress's own locale for this plugin's strings.
	 */
	public static function restore() {
		if ( null === self::$active ) {
			return;
		}
		self::$active = null;
		remove_filter( 'gettext_' . self::DOMAIN, array( __CLASS__, 'gettext' ), 10 );
		remove_filter( 'gettext_with_context_' . self::DOMAIN, array( __CLASS__, 'gettext_with_context' ), 10 );
		remove_filter( 'ngettext_' . self::DOMAIN, array( __CLASS__, 'ngettext' ), 10 );
		remove_filter( 'ngettext_with_context_' . self::DOMAIN, array( __CLASS__, 'ngettext_with_context' ), 10 );
	}

	/**
	 * Active override locale, or null.
	 *
	 * @return string|null
	 */
	public static function active_locale() {
		return self::$active;
	}

	/**
	 * Visitor routes only; the admin tools (connection and identity tests)
	 * keep the admin's language.
	 *
	 * @param mixed           $result  Short-circuit value (passed through).
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function rest_pre_dispatch( $result, $server, $request ) {
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( 0 === strpos( $route, '/ssc/v1/' ) && ! in_array( $route, array( '/ssc/v1/test-connection', '/ssc/v1/test-identity' ), true ) ) {
			self::use_widget_locale();
		}
		return $result;
	}

	/**
	 * The widget's admin-ajax actions.
	 */
	public static function maybe_ajax() {
		if ( ! wp_doing_ajax() || empty( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; each handler verifies its nonce.
			return;
		}
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( 0 === strpos( $action, 'ssc_chatbot_' ) && 'ssc_chatbot_test_ai' !== $action ) {
			self::use_widget_locale();
		}
	}

	/**
	 * Catalog for the active locale.
	 *
	 * @return MO|null
	 */
	protected static function catalog() {
		$locale = self::$active;
		if ( null === $locale ) {
			return null;
		}
		if ( ! array_key_exists( $locale, self::$catalogs ) ) {
			self::$catalogs[ $locale ] = false;
			$files                     = array(
				WP_LANG_DIR . '/plugins/' . self::DOMAIN . '-' . $locale . '.mo',
				SSC_CHATBOT_DIR . 'languages/' . self::DOMAIN . '-' . $locale . '.mo',
			);
			foreach ( $files as $file ) {
				if ( is_readable( $file ) && class_exists( 'MO' ) ) {
					$mo = new MO();
					if ( $mo->import_from_file( $file ) ) {
						self::$catalogs[ $locale ] = $mo;
						break;
					}
				}
			}
		}
		return self::$catalogs[ $locale ] ? self::$catalogs[ $locale ] : null;
	}

	/**
	 * Filter: gettext.
	 *
	 * @param string $translation WordPress's translation (site locale).
	 * @param string $text        Source text.
	 * @return string
	 */
	public static function gettext( $translation, $text ) {
		$mo = self::catalog();
		return $mo ? $mo->translate( $text ) : $text;
	}

	/**
	 * Filter: gettext with context.
	 *
	 * @param string $translation WordPress's translation.
	 * @param string $text        Source text.
	 * @param string $context     Context.
	 * @return string
	 */
	public static function gettext_with_context( $translation, $text, $context ) {
		$mo = self::catalog();
		return $mo ? $mo->translate( $text, $context ) : $text;
	}

	/**
	 * Filter: ngettext.
	 *
	 * @param string $translation WordPress's translation.
	 * @param string $single      Singular source.
	 * @param string $plural      Plural source.
	 * @param int    $number      Count.
	 * @return string
	 */
	public static function ngettext( $translation, $single, $plural, $number ) {
		$mo = self::catalog();
		return $mo ? $mo->translate_plural( $single, $plural, $number ) : ( 1 === (int) $number ? $single : $plural );
	}

	/**
	 * Filter: ngettext with context.
	 *
	 * @param string $translation WordPress's translation.
	 * @param string $single      Singular source.
	 * @param string $plural      Plural source.
	 * @param int    $number      Count.
	 * @param string $context     Context.
	 * @return string
	 */
	public static function ngettext_with_context( $translation, $single, $plural, $number, $context ) {
		$mo = self::catalog();
		return $mo ? $mo->translate_plural( $single, $plural, $number, $context ) : ( 1 === (int) $number ? $single : $plural );
	}
}
