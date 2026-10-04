<?php
/**
 * How well the assistant knows the business: one number per section and an
 * overall percentage, so the Business Knowledge page can show progress and
 * the next worthwhile step instead of one intimidating form.
 *
 * Pure logic (no WordPress calls): the same rules are mirrored by the page
 * script for live updates, and unit tests pin them.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge score.
 */
class SSC_Knowledge_Score {

	/**
	 * Sections in display order. Field sections count filled fields; list
	 * sections count items up to a target (more is never penalised).
	 *
	 * @return array<string, array>
	 */
	public static function panels() {
		return array(
			'identity'  => array(
				'weight' => 25,
				'fields' => array( 'org_name', 'brand_name', 'category', 'industry', 'description', 'differentiators' ),
			),
			'contact'   => array(
				'weight' => 15,
				'fields' => array( 'url', 'location', 'phone', 'email', 'support_phone', 'support_email', 'hours' ),
			),
			'persona'   => array(
				'weight' => 10,
				'fields' => array( 'assistant_name', 'assistant_role', 'language' ),
			),
			'facts'     => array(
				'weight' => 20,
				'target' => 5,
			),
			'products'  => array(
				'weight' => 10,
				'target' => 3,
			),
			'documents' => array(
				'weight' => 10,
				'target' => 2,
			),
			'website'   => array(
				'weight' => 10,
				'target' => 5,
			),
		);
	}

	/**
	 * Levels by the lowest percentage that reaches them.
	 *
	 * @return array<string, int>
	 */
	public static function levels() {
		return array(
			'start' => 0,
			'basic' => 25,
			'good'  => 50,
			'great' => 75,
		);
	}

	/**
	 * Level for a percentage.
	 *
	 * @param int $percent 0-100.
	 * @return string
	 */
	public static function level_for( $percent ) {
		$level = 'start';
		foreach ( self::levels() as $id => $min ) {
			if ( $percent >= $min ) {
				$level = $id;
			}
		}
		return $level;
	}

	/**
	 * Is a text value filled in?
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected static function filled( $value ) {
		return is_scalar( $value ) && '' !== trim( (string) $value );
	}

	/**
	 * Compute the score.
	 *
	 * @param array $in {
	 *     @type array $business    Business profile.
	 *     @type array $items       Knowledge entries.
	 *     @type array $products    Products.
	 *     @type int   $documents   Uploaded or imported documents (not website pages).
	 *     @type int   $site_pages  Website pages in the knowledge base.
	 *     @type bool  $site_active Is the website-learning module on?
	 *     @type bool  $knowledge   Does the knowledge base hold anything usable?
	 * }
	 * @return array
	 */
	public static function compute( $in ) {
		$business    = isset( $in['business'] ) && is_array( $in['business'] ) ? $in['business'] : array();
		$items       = isset( $in['items'] ) && is_array( $in['items'] ) ? $in['items'] : array();
		$products    = isset( $in['products'] ) && is_array( $in['products'] ) ? $in['products'] : array();
		$documents   = isset( $in['documents'] ) ? max( 0, (int) $in['documents'] ) : 0;
		$site_pages  = isset( $in['site_pages'] ) ? max( 0, (int) $in['site_pages'] ) : 0;
		$site_active = ! empty( $in['site_active'] );

		$facts = 0;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['content'] ) && self::filled( $item['content'] ) ) {
				++$facts;
			}
		}
		$named = 0;
		foreach ( $products as $product ) {
			if ( is_array( $product ) && isset( $product['name'] ) && self::filled( $product['name'] ) ) {
				++$named;
			}
		}
		$count = array(
			'facts'     => $facts,
			'products'  => $named,
			'documents' => $documents,
			'website'   => $site_pages,
		);

		$panels = array();
		foreach ( self::panels() as $id => $def ) {
			$row = array(
				'weight' => $def['weight'],
				'locked' => false,
				'count'  => isset( $count[ $id ] ) ? $count[ $id ] : null,
			);
			if ( isset( $def['fields'] ) ) {
				$filled = 0;
				foreach ( $def['fields'] as $field ) {
					if ( isset( $business[ $field ] ) && self::filled( $business[ $field ] ) ) {
						++$filled;
					}
				}
				$row['filled'] = $filled;
				$row['total']  = count( $def['fields'] );
			} else {
				$row['total']  = $def['target'];
				$row['filled'] = min( $count[ $id ], $def['target'] );
				if ( 'products' === $id ) {
					// The one-line overview of what is offered counts as a step of its own.
					++$row['total'];
					if ( isset( $business['products'] ) && self::filled( $business['products'] ) ) {
						++$row['filled'];
					}
				}
				if ( 'website' === $id && ! $site_active ) {
					$row['locked'] = true;
					$row['filled'] = 0;
				}
			}
			$row['ratio']  = $row['total'] > 0 ? $row['filled'] / $row['total'] : 0;
			$panels[ $id ] = $row;
		}

		$weights = 0;
		$earned  = 0;
		foreach ( $panels as $row ) {
			if ( $row['locked'] ) {
				continue;
			}
			$weights += $row['weight'];
			$earned  += $row['weight'] * $row['ratio'];
		}
		$percent = $weights > 0 ? (int) round( 100 * $earned / $weights ) : 0;

		$has_name  = isset( $business['org_name'] ) && self::filled( $business['org_name'] );
		$knowledge = isset( $in['knowledge'] ) ? (bool) $in['knowledge'] : ( $facts > 0 || $named > 0 || $documents > 0 || $site_pages > 0 );

		return array(
			'panels'  => $panels,
			'percent' => $percent,
			'level'   => self::level_for( $percent ),
			// The least the assistant needs to go live: who you are, and something to say.
			'ready'   => $has_name && $knowledge,
			'steps'   => self::steps( $business, $panels, $weights, $site_active ),
			'weights' => $weights,
		);
	}

	/**
	 * The next worthwhile steps, most useful first (at most three).
	 *
	 * @param array $business    Business profile.
	 * @param array $panels      Computed sections.
	 * @param int   $weights     Total weight of the unlocked sections.
	 * @param bool  $site_active Website module on.
	 * @return array[] id, panel, gain (percentage points, 0 when unknown).
	 */
	protected static function steps( $business, $panels, $weights, $site_active ) {
		$has   = function ( $key ) use ( $business ) {
			return isset( $business[ $key ] ) && self::filled( $business[ $key ] );
		};
		$field = function ( $panel ) use ( $panels, $weights ) {
			return $weights > 0 && $panels[ $panel ]['total'] > 0 ? max( 1, (int) round( 100 * $panels[ $panel ]['weight'] / $weights / $panels[ $panel ]['total'] ) ) : 0;
		};
		$unit  = $field;

		$candidates = array(
			array( 'org_name', 'identity', ! $has( 'org_name' ), $field( 'identity' ) ),
			array( 'description', 'identity', ! $has( 'description' ), $field( 'identity' ) ),
			array( 'first_fact', 'facts', 0 === $panels['facts']['count'], $unit( 'facts' ) ),
			array( 'contact', 'contact', ! $has( 'phone' ) && ! $has( 'email' ), $field( 'contact' ) ),
			array( 'hours', 'contact', ! $has( 'hours' ), $field( 'contact' ) ),
			array( 'more_facts', 'facts', $panels['facts']['count'] > 0 && $panels['facts']['count'] < 3, $unit( 'facts' ) ),
			array( 'persona_name', 'persona', ! $has( 'assistant_name' ), $field( 'persona' ) ),
			array( 'products', 'products', 0 === $panels['products']['count'], $unit( 'products' ) ),
			array( 'documents', 'documents', 0 === $panels['documents']['count'], $unit( 'documents' ) ),
			array( 'website', 'website', ! $site_active, 0 ),
			array( 'website_sync', 'website', $site_active && 0 === $panels['website']['count'], $unit( 'website' ) ),
		);
		$steps      = array();
		foreach ( $candidates as $candidate ) {
			if ( $candidate[2] ) {
				$steps[] = array(
					'id'    => $candidate[0],
					'panel' => $candidate[1],
					'gain'  => $candidate[3],
				);
			}
			if ( count( $steps ) >= 3 ) {
				break;
			}
		}
		return $steps;
	}
}
