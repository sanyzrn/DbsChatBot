<?php
/**
 * Which address is the visitor's: the connecting address by default, and the
 * address a proxy reports (X-Forwarded-For, CF-Connecting-IP…) ONLY when the
 * connection really comes from a proxy the site owner listed. A header sent
 * by a visitor who reaches the server directly proves nothing, so it is never
 * believed on its own. Pure logic: no WordPress calls.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client IP resolution.
 */
class SSC_Client_IP {

	/**
	 * Headers a proxy may use to pass the visitor's address.
	 *
	 * @return string[]
	 */
	public static function headers() {
		return array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_FASTLY_CLIENT_IP' );
	}

	/**
	 * The machine itself. A proxy here (nginx in front of PHP on the same server)
	 * cannot be a stranger on the internet, so its header is believed without
	 * listing it.
	 *
	 * @return string[]
	 */
	public static function loopback_ranges() {
		return array( '127.0.0.0/8', '::1/128' );
	}

	/**
	 * Private networks (a load balancer or container network). Whether a proxy
	 * there is real depends on how the server can be reached, which only the site
	 * owner knows, so they are believed only when the owner turned that on.
	 * Carrier-grade NAT (100.64.0.0/10) is not in the list: it is shared address
	 * space, not a network the owner controls; list it as a proxy to trust it.
	 *
	 * @return string[]
	 */
	public static function private_ranges() {
		return array( '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', 'fc00::/7', 'fe80::/10' );
	}

	/**
	 * Cloudflare's published ranges (https://www.cloudflare.com/ips/). Offered as
	 * a starting point in Settings; check the page above for the current list.
	 *
	 * @return string[]
	 */
	public static function cloudflare_ranges() {
		return array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		);
	}

	/**
	 * A valid address without a port or brackets, or ''. IPv4-mapped IPv6
	 * addresses (::ffff:1.2.3.4) become plain IPv4.
	 *
	 * @param string $value Address as received.
	 * @return string
	 */
	public static function clean( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\[([0-9a-fA-F:.]+)\](?::\d+)?$/', $value, $m ) ) {
			$value = $m[1];
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $value, $m ) ) {
			$value = $m[1];
		}
		if ( false === filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$packed = @inet_pton( $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the address was validated above.
		if ( false !== $packed && 16 === strlen( $packed ) && 0 === strncmp( $packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff", 12 ) ) {
			return inet_ntop( substr( $packed, 12 ) );
		}
		return $value;
	}

	/**
	 * Is the address inside the range ("1.2.3.0/24", "2001:db8::/32" or a single address)?
	 *
	 * @param string $ip    Address.
	 * @param string $range Range.
	 * @return bool
	 */
	public static function in_range( $ip, $range ) {
		$ip = self::clean( $ip );
		if ( '' === $ip ) {
			return false;
		}
		$range = trim( (string) $range );
		$bits  = null;
		if ( false !== strpos( $range, '/' ) ) {
			list( $base, $prefix ) = explode( '/', $range, 2 );
			if ( ! ctype_digit( $prefix ) ) {
				return false;
			}
			$bits = (int) $prefix;
		} else {
			$base = $range;
		}
		$base = self::clean( $base );
		if ( '' === $base ) {
			return false;
		}
		$a = inet_pton( $ip );
		$b = inet_pton( $base );
		if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
			return false; // Different families never match.
		}
		$max = strlen( $a ) * 8;
		if ( null === $bits ) {
			$bits = $max;
		}
		if ( $bits < 0 || $bits > $max ) {
			return false;
		}
		$full = intdiv( $bits, 8 );
		if ( 0 !== strncmp( $a, $b, $full ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $a[ $full ] ) & $mask ) === ( ord( $b[ $full ] ) & $mask );
	}

	/**
	 * Is the address a proxy that is believed: a listed one, this machine, or (only
	 * when the owner allowed it) one on a private network?
	 *
	 * @param string   $ip            Address.
	 * @param string[] $trusted       Ranges the site owner listed.
	 * @param bool     $allow_private Believe proxies on private networks too.
	 * @return bool
	 */
	public static function is_trusted( $ip, $trusted, $allow_private = false ) {
		foreach ( (array) $trusted as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		foreach ( self::loopback_ranges() as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		if ( $allow_private ) {
			foreach ( self::private_ranges() as $range ) {
				if ( self::in_range( $ip, $range ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * A list typed in Settings (one range per line or comma separated) as clean
	 * ranges: addresses and CIDR blocks only, each written once.
	 *
	 * @param string|array $text Text or list.
	 * @return string[]
	 */
	public static function parse_list( $text ) {
		$items = is_array( $text ) ? $text : preg_split( '/[\s,;]+/', (string) $text );
		$out   = array();
		foreach ( (array) $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			$base   = $item;
			$prefix = null;
			if ( false !== strpos( $item, '/' ) ) {
				list( $base, $prefix ) = explode( '/', $item, 2 );
				if ( ! ctype_digit( $prefix ) ) {
					continue;
				}
			}
			$base = self::clean( $base );
			if ( '' === $base ) {
				continue;
			}
			$max = false === strpos( $base, ':' ) ? 32 : 128;
			if ( null !== $prefix && (int) $prefix > $max ) {
				continue;
			}
			$out[ $base . ( null !== $prefix ? '/' . (int) $prefix : '' ) ] = true;
			if ( count( $out ) >= 100 ) {
				break;
			}
		}
		return array_keys( $out );
	}

	/**
	 * The visitor's address.
	 *
	 * @param array    $server  $_SERVER (REMOTE_ADDR and the proxy header).
	 * @param string   $header  Header the site owner chose ('' = none).
	 * @param string[] $trusted Listed proxy ranges.
	 * @param bool     $allow_private Believe proxies on private networks too.
	 * @param string[] $allowed Header names that may be used (null = the usual ones).
	 * @return string
	 */
	public static function resolve( $server, $header, $trusted, $allow_private = false, $allowed = null ) {
		$allowed = null === $allowed ? self::headers() : (array) $allowed;
		$remote  = self::clean( isset( $server['REMOTE_ADDR'] ) ? (string) $server['REMOTE_ADDR'] : '' );
		if ( '' === $remote || '' === $header || ! in_array( $header, $allowed, true ) || empty( $server[ $header ] ) || ! is_string( $server[ $header ] ) ) {
			return $remote;
		}
		// The header is only evidence when the connection comes from a proxy we know.
		if ( ! self::is_trusted( $remote, $trusted, $allow_private ) ) {
			return $remote;
		}
		$chain = array_map( 'trim', explode( ',', $server[ $header ] ) );
		// Walk from the proxy's side: skip every address that is itself a trusted proxy; the next one is the visitor.
		for ( $i = count( $chain ) - 1; $i >= 0; $i-- ) {
			$ip = self::clean( $chain[ $i ] );
			if ( '' === $ip ) {
				return $remote; // A malformed entry: do not guess.
			}
			if ( self::is_trusted( $ip, $trusted, $allow_private ) ) {
				continue;
			}
			return $ip;
		}
		return $remote;
	}
}
