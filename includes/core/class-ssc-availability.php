<?php
/**
 * Availability + display targeting.
 *
 * Server-side gate for: business hours (online/offline), device/user targeting,
 * and page include/exclude rules. The frontend mirrors the computed status;
 * it never decides alone.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Availability class.
 */
class SSC_Availability {

	/**
	 * Is the assistant currently within business hours?
	 *
	 * @return bool
	 */
	public static function is_online() {
		if ( 'yes' !== SSC_Settings::get( 'business_hours_enabled', 'no' ) ) {
			return true;
		}

		$tz_name = (string) SSC_Settings::get( 'business_timezone', '' );
		try {
			$tz = $tz_name ? new DateTimeZone( $tz_name ) : new DateTimeZone( self::wp_timezone_string() );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}

		$now    = new DateTime( 'now', $tz );
		return self::is_online_at( $now );
	}

	/** Evaluate a local time; after-midnight hours belong to the previous opening day. */
	public static function is_online_at( $now ) {
		$hour   = (int) $now->format( 'G' );
		$minute = (int) $now->format( 'i' );
		$dow    = (int) $now->format( 'N' ); // 1 = Mon … 7 = Sun.

		$days = self::allowed_days();

		$start = self::parse_hhmm( (string) SSC_Settings::get( 'business_hours_start', '09:00' ), 9 * 60 );
		$end   = self::parse_hhmm( (string) SSC_Settings::get( 'business_hours_end', '18:00' ), 18 * 60 );
		if ( $end === $start ) {
			return in_array( $dow, $days, true ); // 24h window.
		}

		$now_mins = $hour * 60 + $minute;
		if ( $start < $end ) {
			return in_array( $dow, $days, true ) && $now_mins >= $start && $now_mins < $end;
		}
		// Overnight window (e.g. 22:00–06:00).
		return ( in_array( $dow, $days, true ) && $now_mins >= $start )
			|| ( in_array( 1 === $dow ? 7 : $dow - 1, $days, true ) && $now_mins < $end );
	}

	/**
	 * Offline message for visitors (fallback when unset).
	 *
	 * @return string
	 */
	public static function offline_message() {
		$msg = trim( (string) SSC_Settings::get( 'offline_message', '' ) );
		if ( '' !== $msg ) {
			return $msg;
		}
		return __( 'We are currently away. Leave a message and we will get back to you during business hours.', 'smart-support-chatbot' );
	}

	/**
	 * Should the floating widget render on this request?
	 *
	 * Applies device, login state, and page path rules. Used by SSC_Frontend
	 * before enqueueing any assets — a filtered-out page costs zero CSS/JS.
	 *
	 * @return bool
	 */
	public static function should_render() {
		if ( ! SSC_Setup::is_live() ) {
			return false;
		}

		// Login-state targeting.
		$users = (string) SSC_Settings::get( 'display_users', 'all' );
		if ( 'guest' === $users && is_user_logged_in() ) {
			return false;
		}
		if ( 'logged_in' === $users && ! is_user_logged_in() ) {
			return false;
		}

		// Device targeting is refined client-side (server cannot know viewport
		// reliably); the config carries the rule so JS can hide the launcher.
		// Page rules are the server's job:
		$mode  = (string) SSC_Settings::get( 'display_mode', 'all' );
		$paths = self::display_paths();
		if ( 'all' === $mode || empty( $paths ) ) {
			return true;
		}

		$current = self::current_request_path();
		$match   = false;
		foreach ( $paths as $pattern ) {
			if ( self::path_matches( $current, $pattern ) ) {
				$match = true;
				break;
			}
		}

		return ( 'include' === $mode ) ? $match : ! $match;
	}

	/**
	 * Public status payload for the widget config.
	 *
	 * @return array
	 */
	public static function public_status() {
		$online = self::is_online();
		return array(
			'online'          => $online,
			'offlineMessage'  => $online ? '' : self::offline_message(),
			'device'          => (string) SSC_Settings::get( 'display_devices', 'all' ),
			'sound'           => 'yes' === SSC_Settings::get( 'sound_enabled', 'no' ),
			'streaming'       => 'yes' === SSC_Settings::get( 'streaming_enabled', 'yes' ),
		);
	}

	/**
	 * Allowed weekdays (1–7, Monday-first).
	 *
	 * @return int[]
	 */
	protected static function allowed_days() {
		$raw = (string) SSC_Settings::get( 'business_hours_days', '1,2,3,4,5' );
		$out = array();
		foreach ( preg_split( '/[\s,]+/', $raw ) as $d ) {
			$d = (int) $d;
			if ( $d >= 1 && $d <= 7 ) {
				$out[] = $d;
			}
		}
		return $out ? array_values( array_unique( $out ) ) : array( 1, 2, 3, 4, 5 );
	}

	/**
	 * Parse HH:MM into minutes since midnight.
	 *
	 * @param string $hhmm   Time string.
	 * @param int    $fallback Fallback minutes.
	 * @return int
	 */
	protected static function parse_hhmm( $hhmm, $fallback ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $hhmm ), $m ) ) {
			return $fallback;
		}
		if ( (int) $m[1] > 23 || (int) $m[2] > 59 ) { return $fallback; }
		return ( (int) $m[1] ) * 60 + ( (int) $m[2] );
	}

	/**
	 * WordPress timezone as a PHP timezone name (handles +05:30 offsets).
	 *
	 * @return string
	 */
	protected static function wp_timezone_string() {
		$tz = get_option( 'timezone_string', '' );
		if ( $tz ) {
			return $tz;
		}
		$offset = (float) get_option( 'gmt_offset', 0 );
		$sign   = $offset < 0 ? '-' : '+';
		$abs    = abs( $offset );
		$hours  = (int) $abs;
		$mins   = (int) round( ( $abs - $hours ) * 60 );
		return sprintf( '%s%02d:%02d', $sign, $hours, $mins );
	}

	/**
	 * Parsed include/exclude path list.
	 *
	 * @return string[]
	 */
	protected static function display_paths() {
		$raw = (string) SSC_Settings::get( 'display_paths', '' );
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Current request path (no query string, leading slash).
	 *
	 * @return string
	 */
	protected static function current_request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Glob-ish path match: `*` = any run, exact otherwise (case-insensitive).
	 *
	 * @param string $current Current path.
	 * @param string $pattern Pattern.
	 * @return bool
	 */
	protected static function path_matches( $current, $pattern ) {
		$pattern = trim( $pattern );
		if ( '' === $pattern ) {
			return false;
		}
		// Treat bare host-relative forms.
		if ( 0 !== strpos( $pattern, '/' ) && 0 !== strpos( $pattern, '*' ) ) {
			$pattern = '/' . $pattern;
		}
		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
		return (bool) preg_match( $regex, $current );
	}
}
