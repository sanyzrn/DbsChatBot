<?php
/**
 * Three-way merge for a settings form that was opened some time ago.
 *
 * The Knowledge form posts the whole profile, the entries and the products.
 * If a second tab (or another administrator) saved in between, saving the old
 * form again would silently put the old values back. So the form carries a
 * small snapshot (one short hash per field or entry) of what it was built
 * from. On save, what this form changed is applied onto what is stored NOW,
 * and what it did not touch is left as it is. Only when both sides changed the
 * same thing is there a conflict, and then nothing is saved until the person
 * decides. Pure logic, no WordPress calls.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form merge.
 */
class SSC_Merge {

	/**
	 * Keys of an entry that are its content (the id is not: it can be assigned later).
	 *
	 * @param string $section ki | products.
	 * @return string[]
	 */
	protected static function content_keys( $section ) {
		return 'products' === $section
			? array( 'name', 'summary', 'brochure', 'image', 'attributes' )
			: array( 'type', 'title', 'content' );
	}

	/**
	 * A value in a fixed written form, so the same content always gives the same text.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	protected static function canonical( $value ) {
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( ! $is_list ) {
				ksort( $value );
			}
			return array_map( array( __CLASS__, 'canonical' ), $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Short fingerprint of a value.
	 *
	 * @param mixed $value Value.
	 * @return string 12 hex characters.
	 */
	public static function hash( $value ) {
		return substr( sha1( (string) json_encode( self::canonical( $value ), JSON_UNESCAPED_UNICODE ) ), 0, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- a stable fingerprint, not output.
	}

	/**
	 * The content of an entry as it is compared.
	 *
	 * @param string $section ki | products.
	 * @param array  $entry   Entry.
	 * @return array
	 */
	protected static function content( $section, $entry ) {
		$out = array();
		foreach ( self::content_keys( $section ) as $key ) {
			$out[ $key ] = isset( $entry[ $key ] ) ? $entry[ $key ] : '';
		}
		return $out;
	}

	/**
	 * Give every entry an id: the one it has, or a stable one made from its
	 * content (the same on every tab), so entries saved before ids existed can
	 * be told apart.
	 *
	 * @param string  $section ki | products.
	 * @param array[] $entries Entries.
	 * @return array[]
	 */
	public static function with_ids( $section, $entries ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id = isset( $entry['id'] ) && is_scalar( $entry['id'] ) ? (string) $entry['id'] : '';
			if ( '' === $id ) {
				$base          = ( 'products' === $section ? 'pr-' : 'ki-' ) . self::hash( self::content( $section, $entry ) );
				$n             = isset( $seen[ $base ] ) ? $seen[ $base ] + 1 : 0;
				$seen[ $base ] = $n;
				$id            = 0 === $n ? $base : $base . '-' . $n;
			}
			$entry['id'] = $id;
			$out[]       = $entry;
		}
		return $out;
	}

	/**
	 * What a form is built from: a hash per business field and per entry.
	 *
	 * @param array   $business Business profile.
	 * @param array[] $items    Knowledge entries.
	 * @param array[] $products Products.
	 * @return array business => field => hash, ki => id => hash, products => id => hash.
	 */
	public static function snapshot( $business, $items, $products ) {
		$out = array(
			'business' => array(),
			'ki'       => array(),
			'products' => array(),
		);
		foreach ( (array) $business as $field => $value ) {
			$out['business'][ (string) $field ] = self::hash( $value );
		}
		foreach ( array(
			'ki'       => $items,
			'products' => $products,
		) as $section => $list ) {
			foreach ( self::with_ids( $section, $list ) as $entry ) {
				$out[ $section ][ $entry['id'] ] = self::hash( self::content( $section, $entry ) );
			}
		}
		return $out;
	}

	/**
	 * A snapshot received from a form, or null when it is missing or not in the right shape.
	 *
	 * @param mixed $raw JSON text or an array.
	 * @return array|null
	 */
	public static function parse_snapshot( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$out = array();
		foreach ( array( 'business', 'ki', 'products' ) as $section ) {
			if ( ! isset( $raw[ $section ] ) || ! is_array( $raw[ $section ] ) ) {
				return null;
			}
			$out[ $section ] = array();
			foreach ( $raw[ $section ] as $key => $hash ) {
				if ( ! is_string( $hash ) || ! preg_match( '/^[0-9a-f]{12}$/', $hash ) ) {
					return null;
				}
				$out[ $section ][ (string) $key ] = $hash;
			}
		}
		return $out;
	}

	/**
	 * Merge a flat map of fields (the business profile).
	 *
	 * @param string[] $base    Field => hash the form was built from.
	 * @param array    $mine    Field => value the form posted.
	 * @param array    $current Field => value stored now.
	 * @param bool     $prefer  On a conflict take the posted value (otherwise the stored one).
	 * @return array value (the merged map), conflicts (field names).
	 */
	public static function merge_map( $base, $mine, $current, $prefer = false ) {
		$value     = $current;
		$conflicts = array();
		foreach ( $mine as $field => $posted ) {
			$known = isset( $base[ $field ] ) ? $base[ $field ] : null;
			if ( null !== $known && self::hash( $posted ) === $known ) {
				continue; // This form did not touch it: what is stored now stays.
			}
			$stored = array_key_exists( $field, $current ) ? $current[ $field ] : null;
			if ( null === $stored || ( null !== $known && self::hash( $stored ) === $known ) || self::hash( $stored ) === self::hash( $posted ) ) {
				$value[ $field ] = $posted;
				continue;
			}
			$conflicts[] = $field;
			if ( $prefer ) {
				$value[ $field ] = $posted;
			}
		}
		return array(
			'value'     => $value,
			'conflicts' => $conflicts,
		);
	}

	/**
	 * Merge a list of entries that have ids (knowledge entries, products).
	 *
	 * @param string   $section ki | products.
	 * @param string[] $base    Id => hash the form was built from.
	 * @param array[]  $mine    Entries the form posted.
	 * @param array[]  $current Entries stored now.
	 * @param bool     $prefer  On a conflict take the posted entry (otherwise the stored one).
	 * @return array list (the merged entries), conflicts (ids).
	 */
	public static function merge_list( $section, $base, $mine, $current, $prefer = false ) {
		$mine    = self::with_ids( $section, $mine );
		$current = self::with_ids( $section, $current );
		$by_mine = array();
		$by_now  = array();
		foreach ( $mine as $entry ) {
			$by_mine[ $entry['id'] ] = $entry;
		}
		foreach ( $current as $entry ) {
			$by_now[ $entry['id'] ] = $entry;
		}
		$list      = array();
		$conflicts = array();
		foreach ( $current as $entry ) {
			$id    = $entry['id'];
			$known = isset( $base[ $id ] ) ? $base[ $id ] : null;
			$now   = self::hash( self::content( $section, $entry ) );
			if ( ! isset( $by_mine[ $id ] ) ) {
				if ( null === $known || $now === $known ) {
					if ( null !== $known ) {
						continue; // This form removed it and nobody changed it since.
					}
					$list[] = $entry; // Added elsewhere after this form was built.
					continue;
				}
				$conflicts[] = $id; // Removed here, changed elsewhere.
				if ( ! $prefer ) {
					$list[] = $entry;
				}
				continue;
			}
			$posted_entry = $by_mine[ $id ];
			$posted       = self::hash( self::content( $section, $posted_entry ) );
			if ( null !== $known && $posted === $known ) {
				$list[] = $entry; // Not touched by this form.
				continue;
			}
			if ( null === $known || $now === $known || $now === $posted ) {
				$list[] = $posted_entry;
				continue;
			}
			$conflicts[] = $id; // Changed here and elsewhere.
			$list[]      = $prefer ? $posted_entry : $entry;
		}
		foreach ( $mine as $entry ) {
			$id = $entry['id'];
			if ( isset( $by_now[ $id ] ) ) {
				continue;
			}
			$known = isset( $base[ $id ] ) ? $base[ $id ] : null;
			if ( null === $known ) {
				$list[] = $entry; // New in this form.
				continue;
			}
			if ( self::hash( self::content( $section, $entry ) ) === $known ) {
				continue; // Removed elsewhere, untouched here: stays removed.
			}
			$conflicts[] = $id; // Changed here, removed elsewhere.
			if ( $prefer ) {
				$list[] = $entry;
			}
		}
		return array(
			'list'      => $list,
			'conflicts' => $conflicts,
		);
	}

	/**
	 * Merge the whole Knowledge form.
	 *
	 * @param array|null $base    Snapshot the form was built from (null: an old form, which replaces everything as before).
	 * @param array      $mine    business, ki, products as posted (already cleaned).
	 * @param array      $current business, ki, products as stored now.
	 * @param bool       $prefer  On a conflict take the posted value.
	 * @return array business, ki, products (merged), conflicts (section => ids or fields).
	 */
	public static function knowledge( $base, $mine, $current, $prefer = false ) {
		if ( null === $base ) {
			return array(
				'business'  => array_merge( (array) $current['business'], (array) $mine['business'] ),
				'ki'        => $mine['ki'],
				'products'  => $mine['products'],
				'conflicts' => array(),
			);
		}
		$business = self::merge_map( $base['business'], (array) $mine['business'], (array) $current['business'], $prefer );
		$ki       = self::merge_list( 'ki', $base['ki'], (array) $mine['ki'], (array) $current['ki'], $prefer );
		$products = self::merge_list( 'products', $base['products'], (array) $mine['products'], (array) $current['products'], $prefer );
		return array(
			'business'  => $business['value'],
			'ki'        => $ki['list'],
			'products'  => $products['list'],
			'conflicts' => array_filter(
				array(
					'business' => $business['conflicts'],
					'ki'       => $ki['conflicts'],
					'products' => $products['conflicts'],
				)
			),
		);
	}
}

