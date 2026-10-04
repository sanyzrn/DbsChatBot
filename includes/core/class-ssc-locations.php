<?php
/**
 * The places of a business (head office, factory, branches…): a short list,
 * each with an address, an optional phone and a map link (Google Maps or
 * any other map service). Stored inside the business profile as
 * "locations"; the older single "location" text still works and is shown as
 * the first place until a list is saved.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business locations.
 */
class SSC_Locations {

	/**
	 * Most places that can be kept.
	 */
	const MAX = 20;

	/**
	 * Kinds of place.
	 *
	 * @return array id => label.
	 */
	public static function types() {
		return array(
			'hq'        => __( 'Head office', 'nexachat-ai' ),
			'factory'   => __( 'Factory', 'nexachat-ai' ),
			'branch'    => __( 'Branch', 'nexachat-ai' ),
			'store'     => __( 'Store', 'nexachat-ai' ),
			'warehouse' => __( 'Warehouse', 'nexachat-ai' ),
			'other'     => __( 'Other place', 'nexachat-ai' ),
		);
	}

	/**
	 * A map link: http(s) only, never longer than a browser would accept.
	 *
	 * @param string $url Raw link.
	 * @return string Clean link, or '' when it is not a usable web link.
	 */
	public static function map_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || strlen( $url ) > 600 ) {
			return '';
		}
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		return ( 0 === strpos( $clean, 'https://' ) || 0 === strpos( $clean, 'http://' ) ) ? $clean : '';
	}

	/**
	 * A phone number as typed: digits (any script) and the usual separators.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	protected static function phone( $phone ) {
		$phone = preg_replace( '/[^0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}+\-().\s]/u', '', sanitize_text_field( (string) $phone ) );
		$phone = trim( (string) preg_replace( '/\s+/', ' ', (string) $phone ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $phone, 0, 30 ) : substr( $phone, 0, 30 );
	}

	/**
	 * Cut a string to a number of characters.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Characters.
	 * @return string
	 */
	protected static function cut( $text, $limit ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}

	/**
	 * Clean a posted or imported list. Rows with nothing in them are dropped,
	 * unknown kinds become "other", and a row that is not an array is ignored.
	 *
	 * @param mixed $raw Raw list.
	 * @return array[] type, name, address, phone, map.
	 */
	public static function sanitize( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		$types = self::types();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX ) {
				continue;
			}
			$scalar = function ( $key ) use ( $row ) {
				return ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) ? (string) $row[ $key ] : '';
			};
			$type   = sanitize_key( $scalar( 'type' ) );
			$item   = array(
				'type'    => isset( $types[ $type ] ) ? $type : 'other',
				'name'    => self::cut( sanitize_text_field( $scalar( 'name' ) ), 80 ),
				'address' => self::cut( sanitize_textarea_field( $scalar( 'address' ) ), 300 ),
				'phone'   => self::phone( $scalar( 'phone' ) ),
				'map'     => self::map_url( $scalar( 'map' ) ),
			);
			if ( '' === $item['name'] && '' === $item['address'] && '' === $item['phone'] && '' === $item['map'] ) {
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * The places the assistant knows: the saved list, or the old single
	 * address as one place when no list has been saved yet.
	 *
	 * @param array $business Business profile.
	 * @return array[]
	 */
	public static function effective( $business ) {
		$list = isset( $business['locations'] ) ? self::sanitize( $business['locations'] ) : array();
		if ( $list ) {
			return $list;
		}
		$legacy = isset( $business['location'] ) ? trim( (string) $business['location'] ) : '';
		if ( '' === $legacy ) {
			return array();
		}
		return array(
			array(
				'type'    => 'hq',
				'name'    => '',
				'address' => $legacy,
				'phone'   => '',
				'map'     => '',
			),
		);
	}

	/**
	 * What to call a place: the name when there is one, else its kind.
	 *
	 * @param array $place A place.
	 * @return string
	 */
	public static function label( $place ) {
		if ( ! empty( $place['name'] ) ) {
			return (string) $place['name'];
		}
		$types = self::types();
		return isset( $place['type'], $types[ $place['type'] ] ) ? $types[ $place['type'] ] : $types['other'];
	}

	/**
	 * One line for the first place, kept in the old single "location" field
	 * so everything that still reads it keeps working.
	 *
	 * @param array[] $places Places.
	 * @return string
	 */
	public static function primary_text( $places ) {
		if ( empty( $places[0] ) ) {
			return '';
		}
		return '' !== $places[0]['address'] ? $places[0]['address'] : self::label( $places[0] );
	}

	/**
	 * Lines for the assistant's reference data. Each place on its own line
	 * with its address, phone and map link, so a question such as "where is
	 * the factory?" can be answered with the right place and its link.
	 *
	 * @param array $business Business profile.
	 * @return string[]
	 */
	public static function prompt_lines( $business ) {
		$places = self::effective( $business );
		if ( ! $places ) {
			return array();
		}
		$clean = function ( $text ) {
			return trim( wp_strip_all_tags( (string) $text ) );
		};
		$out   = array( '- Places (share the address and the map link when asked where something is):' );
		foreach ( $places as $place ) {
			$parts = array();
			if ( '' !== $place['address'] ) {
				$parts[] = 'address: ' . preg_replace( '/\s*\R\s*/u', ', ', $clean( $place['address'] ) );
			}
			if ( '' !== $place['phone'] ) {
				$parts[] = 'phone: ' . $clean( $place['phone'] );
			}
			if ( '' !== $place['map'] ) {
				$parts[] = 'map: ' . $place['map'];
			}
			$out[] = '  - ' . $clean( self::label( $place ) ) . ( $parts ? ' | ' . implode( ' | ', $parts ) : '' );
		}
		return $out;
	}
}
