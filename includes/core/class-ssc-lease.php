<?php
/**
 * A short lock that only one request can hold at a time, kept in the options
 * table. Used to make sure a job (such as summarizing one conversation) runs
 * once even when two requests start it together. The lock always expires, so
 * a request that dies cannot block the job for good.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lease helper.
 */
class SSC_Lease {

	/**
	 * Name of the option that holds a lease.
	 *
	 * @param string $name Lease name.
	 * @return string
	 */
	protected static function option( $name ) {
		return 'ssc_lease_' . substr( md5( (string) $name ), 0, 24 );
	}

	/**
	 * Forget what WordPress cached about the option (another request changed it directly).
	 *
	 * @param string $option Option name.
	 */
	protected static function forget( $option ) {
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Take the lease.
	 *
	 * @param string $name Lease name.
	 * @param int    $ttl  Seconds before it expires on its own.
	 * @return string|false A token to release it with, or false when someone else holds it.
	 */
	public static function acquire( $name, $ttl = 120 ) {
		global $wpdb;
		$option = self::option( $name );
		$token  = wp_generate_password( 16, false );
		$value  = ( time() + max( 1, (int) $ttl ) ) . ':' . $token;
		// One statement decides who gets it: a second INSERT of the same name is ignored.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an atomic lock cannot go through the options API.
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $option, $value ) );
		if ( 1 === $taken ) {
			self::forget( $option );
			return $token;
		}
		// Held. If it has expired, take it over; the old value in the WHERE makes sure only one request does.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		if ( is_string( $current ) && (int) $current < time() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
			$won = (int) $wpdb->update(
				$wpdb->options,
				array( 'option_value' => $value ),
				array(
					'option_name'  => $option,
					'option_value' => $current,
				)
			);
			self::forget( $option );
			if ( 1 === $won ) {
				return $token;
			}
		}
		return false;
	}

	/**
	 * Give the lease back (only by the one that holds it).
	 *
	 * @param string $name  Lease name.
	 * @param string $token Token from acquire().
	 */
	public static function release( $name, $token ) {
		global $wpdb;
		$option = self::option( $name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see acquire().
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		if ( is_string( $current ) && substr( $current, -strlen( (string) $token ) - 1 ) === ':' . $token ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see acquire().
			$wpdb->delete(
				$wpdb->options,
				array(
					'option_name'  => $option,
					'option_value' => $current,
				)
			);
		}
		self::forget( $option );
	}
}
