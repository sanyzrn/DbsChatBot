<?php
/**
 * Availability + display targeting.
 *
 * Server-side gate for: business hours (online/offline), device/user targeting,
 * and page include/exclude rules. The frontend mirrors the computed status;
 * it never decides alone.
 *
 * @package Vazho
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

		$now = new DateTime( 'now', $tz );
		return self::is_online_at( $now );
	}

	/**
	 * Evaluate a local time; after-midnight hours belong to the previous
	 * opening day, so a 22:00-06:00 shift stays open past midnight.
	 *
	 * @param DateTimeInterface $now Local time to evaluate.
	 * @return bool
	 */
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
		return __( 'We are currently away. Leave a message and we will get back to you during business hours.', 'vazho' );
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

		// Device targeting is refined client-side (the server cannot know the
		// viewport reliably); the config carries the rule so JS can hide the
		// launcher. Page rules are the server's job.
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
			'online'         => $online,
			'offlineMessage' => $online ? '' : self::offline_message(),
			'device'         => (string) SSC_Settings::get( 'display_devices', 'all' ),
			'sound'          => 'yes' === SSC_Settings::get( 'sound_enabled', 'no' ),
			'streaming'      => 'yes' === SSC_Settings::get( 'streaming_enabled', 'yes' ),
			// Page caches freeze the rendered status; when business hours are
			// on, the widget re-reads it live from the REST status endpoint.
			'dynamic'        => 'yes' === SSC_Settings::get( 'business_hours_enabled', 'no' ),
		);
	}

	/**
	 * Live, cache-independent status (REST GET /status).
	 *
	 * @return array
	 */
	public static function live_status() {
		$online = self::is_online();
		return array(
			'online'         => $online,
			'offlineMessage' => $online ? '' : self::offline_message(),
		);
	}

	/**
	 * Proactive invitation text for the current page: the first matching
	 * "path | message" rule, else the default text. "-" disables it.
	 *
	 * @param string $rules    Rule lines.
	 * @param string $fallback Default text.
	 * @param string $path     Request path (null = current request).
	 * @return string
	 */
	public static function proactive_text_for( $rules, $fallback, $path = null ) {
		$path = null === $path ? self::current_request_path() : (string) $path;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $rules ) as $line ) {
			$parts = explode( '|', $line, 2 );
			if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) ) {
				continue;
			}
			if ( self::path_matches( $path, trim( $parts[0] ) ) ) {
				$message = trim( $parts[1] );
				return '-' === $message ? '' : $message;
			}
		}
		return (string) $fallback;
	}

	/**
	 * The schedule in words, as a visitor would read it: "Sat–Wed, 09:00–18:00".
	 * Empty while the schedule is off. Days use the site language's names, and
	 * a run of days is written as a range even across the weekend.
	 *
	 * @return string
	 */
	public static function describe_schedule() {
		if ( 'yes' !== SSC_Settings::get( 'business_hours_enabled', 'no' ) ) {
			return '';
		}
		$start = self::parse_hhmm( (string) SSC_Settings::get( 'business_hours_start', '09:00' ), 9 * 60 );
		$end   = self::parse_hhmm( (string) SSC_Settings::get( 'business_hours_end', '18:00' ), 18 * 60 );
		$days  = self::day_ranges( self::allowed_days() );
		$time  = $start === $end ? __( 'all day', 'vazho' ) : sprintf( '%02d:%02d–%02d:%02d', intdiv( $start, 60 ), $start % 60, intdiv( $end, 60 ), $end % 60 );
		$tz    = trim( (string) SSC_Settings::get( 'business_timezone', '' ) );
		return $days . ', ' . $time . ( '' !== $tz ? ' (' . $tz . ')' : '' );
	}

	/**
	 * Weekdays (1 = Monday … 7 = Sunday) as text: runs become ranges, the week
	 * wraps around (Sat–Wed), and a full week is "every day".
	 *
	 * @param int[] $days Weekday numbers.
	 * @return string
	 */
	public static function day_ranges( $days ) {
		$set = array_fill( 1, 7, false );
		foreach ( $days as $d ) {
			if ( $d >= 1 && $d <= 7 ) {
				$set[ (int) $d ] = true;
			}
		}
		if ( 7 === count( array_filter( $set ) ) ) {
			return __( 'every day', 'vazho' );
		}
		$name  = function ( $n ) {
			global $wp_locale;
			if ( $wp_locale ) {
				return $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $n % 7 ) );
			}
			$fallback = array(
				1 => 'Mon',
				2 => 'Tue',
				3 => 'Wed',
				4 => 'Thu',
				5 => 'Fri',
				6 => 'Sat',
				7 => 'Sun',
			);
			return $fallback[ $n ];
		};
		$prev  = function ( $n ) {
			return 1 === $n ? 7 : $n - 1;
		};
		$next  = function ( $n ) {
			return 7 === $n ? 1 : $n + 1;
		};
		$parts = array();
		for ( $n = 1; $n <= 7; $n++ ) {
			if ( ! $set[ $n ] || $set[ $prev( $n ) ] ) {
				continue; // Not the first day of a run.
			}
			$last = $n;
			while ( $set[ $next( $last ) ] && $next( $last ) !== $n ) {
				$last = $next( $last );
			}
			$parts[] = $last === $n ? $name( $n ) : ( $next( $n ) === $last ? $name( $n ) . ', ' . $name( $last ) : $name( $n ) . '–' . $name( $last ) );
		}
		return implode( ', ', $parts );
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
		if ( (int) $m[1] > 23 || (int) $m[2] > 59 ) {
			return $fallback;
		}
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
	public static function path_matches( $current, $pattern ) {
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
