<?php
/**
 * Marketplace licence state (RTL-Theme) and the soft limits that follow from it.
 *
 * The plugin is fully usable without any licence file (the GitHub ZIP never has
 * one). Only the copy sold on RTL-Theme ships the marketplace's licence file; when
 * that file is loaded and reports an inactive licence, a few premium capabilities
 * stay visible but locked and the AI is capped per day. Everything else keeps working.
 *
 * Three states:
 *   open        no licence file is loaded here (GitHub ZIP, or the file could not run:
 *               no ionCube Loader, other PHP version). Nothing is limited.
 *   licensed    the marketplace file is loaded, unchanged, and says the licence is active.
 *   unlicensed  the file is loaded and says the licence is not active (or it was changed).
 *               Premium modules are locked and AI answers are capped per day.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Licence state and limits.
 */
class SSC_License {

	/** Class defined by the marketplace licence file. */
	const CLASS_NAME = 'RTL_License_c5f56181a1bd41bb';

	/** SHA-1 of the unchanged licence file, as published by the marketplace. */
	const FILE_SHA1 = 'ae5fb4671f04882ce4c0846af81ab64d6d4b4f96';

	/** Marketplace page of the product. */
	const PRODUCT_URL = 'https://rtl-theme.com/?p=322159';

	/** AI answers per day without an active licence. */
	const FREE_AI_DAILY = 30;

	/** Modules that need an active licence (visible, but locked, without it). */
	const PREMIUM_MODULES = array( 'live', 'messenger', 'sms', 'woo', 'sitesync', 'pharma' );

	/** How long a licence verdict is reused, in seconds. */
	const CACHE_TTL = 900;

	/**
	 * Verdict for this request.
	 *
	 * @var string|null
	 */
	protected static $state = null;

	/**
	 * Current state: open | licensed | unlicensed.
	 *
	 * @return string
	 */
	public static function state() {
		if ( null === self::$state ) {
			self::$state = self::evaluate();
		}
		return self::$state;
	}

	/**
	 * Forget the verdict (tests, and after the licence changes).
	 */
	public static function reset() {
		self::$state = null;
		delete_transient( 'ssc_license_state' );
	}

	/**
	 * Is the licence missing or invalid, so that the limits apply?
	 *
	 * @return bool
	 */
	public static function is_limited() {
		return 'unlicensed' === self::state();
	}

	/**
	 * Is this module locked right now?
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public static function module_locked( $id ) {
		return self::is_limited() && in_array( $id, self::PREMIUM_MODULES, true );
	}

	/**
	 * Work out the state. Never throws: a failing check must not take the site down.
	 *
	 * @return string
	 */
	protected static function evaluate() {
		if ( defined( 'VAZHO_DISABLE_LICENSE_GATE' ) && VAZHO_DISABLE_LICENSE_GATE ) {
			return 'open'; // Support switch for a site that cannot reach the marketplace.
		}
		if ( ! class_exists( self::CLASS_NAME, false ) || ! method_exists( self::CLASS_NAME, 'isActive' ) ) {
			return 'open';
		}

		$cached = get_transient( 'ssc_license_state' );
		if ( in_array( $cached, array( 'licensed', 'unlicensed' ), true ) ) {
			return $cached;
		}

		$state = 'open';
		try {
			$reflection = new \ReflectionClass( self::CLASS_NAME );
			$file       = $reflection->getFileName();
			if ( ! is_string( $file ) || ! is_file( $file ) ) {
				return 'open'; // Cannot be checked; never punish the owner for that.
			}
			$hash = @sha1_file( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file is handled below.
			/**
			 * Expected SHA-1 of the licence file (changed only by tests).
			 *
			 * @param string $hash Published hash.
			 */
			$expected = (string) apply_filters( 'vazho_license_file_sha1', self::FILE_SHA1 );
			if ( ! is_string( $hash ) || ! hash_equals( $expected, $hash ) ) {
				$state = 'unlicensed'; // The marketplace file was replaced or edited.
			} else {
				$class = self::CLASS_NAME;
				$check = new $class();
				$state = ( true === $check->isActive() ) ? 'licensed' : 'unlicensed';
			}
		} catch ( \Throwable $e ) {
			return 'open';
		}

		set_transient( 'ssc_license_state', $state, self::CACHE_TTL );
		return $state;
	}

	/**
	 * Key of today's AI counter.
	 *
	 * @return string
	 */
	protected static function day_key() {
		return 'ssc_ai_day_' . gmdate( 'Ymd' );
	}

	/**
	 * AI answers produced today.
	 *
	 * @return int
	 */
	public static function ai_used_today() {
		return (int) get_transient( self::day_key() );
	}

	/**
	 * Has today's free allowance of AI answers been used up? Always false with a licence.
	 *
	 * @return bool
	 */
	public static function ai_cap_reached() {
		return self::is_limited() && self::ai_used_today() >= self::FREE_AI_DAILY;
	}

	/**
	 * Count one freshly generated AI answer (only while limited).
	 */
	public static function count_ai_answer() {
		if ( self::is_limited() ) {
			set_transient( self::day_key(), self::ai_used_today() + 1, 2 * DAY_IN_SECONDS );
		}
	}

	/**
	 * Admin sentence describing the limits, or '' when nothing is limited.
	 *
	 * @return string
	 */
	public static function notice() {
		if ( ! self::is_limited() ) {
			return '';
		}
		return sprintf(
			/* translators: 1: AI answers per day, 2: product page address. */
			__( 'The licence for this site is not active. You can use everything to try it, up to %1$d AI answers a day; live chat, the messenger bot, the WooCommerce assistant, the SMS gateway, learning from the website and the pharmaceutical extension need an active licence. Product page: %2$s', 'vazho' ),
			self::FREE_AI_DAILY,
			self::PRODUCT_URL
		);
	}
}

