<?php
/**
 * Admin date formatting with Solar Hijri (Jalali) support.
 *
 * Persian-locale sites read dates in the Solar Hijri calendar. When a
 * Jalali plugin (WP-Parsidate, jdate helpers) is present it already
 * converts WordPress dates, so this only steps in when nothing else does.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date helper.
 */
class SSC_Date {

	/**
	 * Should dates be shown in the Solar Hijri calendar?
	 *
	 * @return bool
	 */
	public static function use_jalali() {
		// Visitor-facing text follows the widget language when it overrides the site's.
		$locale = class_exists( 'SSC_I18n' ) && SSC_I18n::active_locale() ? SSC_I18n::active_locale() : (string) determine_locale();
		$jalali = 0 === strpos( $locale, 'fa' ) && ! function_exists( 'parsidate' ) && ! function_exists( 'jdate' );
		/**
		 * Force (true) or disable (false) Solar Hijri dates in the plugin admin.
		 *
		 * @param bool $jalali Default decision.
		 */
		return (bool) apply_filters( 'ssc_use_jalali', $jalali );
	}

	/**
	 * Format a stored site-time MySQL datetime for display.
	 *
	 * @param string $mysql     'Y-m-d H:i:s' (site time).
	 * @param bool   $with_time Include the time.
	 * @return string
	 */
	public static function display( $mysql, $with_time = true ) {
		$mysql = (string) $mysql;
		if ( '' === $mysql || 0 === strpos( $mysql, '0000' ) ) {
			return '';
		}
		if ( ! self::use_jalali() ) {
			$format = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );
			return (string) mysql2date( $format, $mysql );
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $mysql, $m ) ) {
			return $mysql;
		}
		list( $jy, $jm, $jd ) = self::to_jalali( (int) $m[1], (int) $m[2], (int) $m[3] );
		$out                  = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		if ( $with_time && isset( $m[4] ) ) {
			$out .= ' – ' . $m[4] . ':' . $m[5];
		}
		return self::persian_digits( $out );
	}

	/**
	 * Gregorian -> Solar Hijri (PURE - unit tested).
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return int[] Jalali year, month, day.
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			--$days;
			$jy   += intdiv( $days, 365 );
			$days %= 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * Western -> Persian digits.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function persian_digits( $text ) {
		return strtr(
			(string) $text,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}
}
