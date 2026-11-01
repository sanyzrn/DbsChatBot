<?php
/**
 * Public answer provenance, never a correctness or cost guarantee.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless receipt helpers; called only behind the module gate.
 */
class SSC_Answer_Receipt {

	/**
	 * Capture references at generation time, not at a later cache lookup.
	 *
	 * @param array $context Retrieved documents.
	 * @param array $web Provider citations.
	 * @return array
	 */
	public static function snapshot( $context, $web ) {
		$refs = array();
		foreach ( array(
			'context' => $context,
			'web'     => $web,
		) as $type => $items ) {
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) ) {
					$item['type'] = $type;
					$refs[]       = $item;
				}
			}
		}
		return array(
			'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'sources'      => self::public_sources( $refs ),
		);
	}

	/**
	 * Keep only explicitly public, safe links; never expose an imported file title.
	 *
	 * @param array $refs Captured references.
	 * @return array
	 */
	public static function public_sources( $refs ) {
		$out  = array();
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		foreach ( array_slice( (array) $refs, 0, 12 ) as $ref ) {
			if ( ! is_array( $ref ) || empty( $ref['url'] ) || ! is_string( $ref['url'] ) ) {
				continue;
			}
			$url   = $ref['url'];
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && ! in_array( $parts['port'], array( 80, 443 ), true ) ) ) {
				continue;
			}
			$type  = isset( $ref['type'] ) && 'web' === $ref['type'] ? 'web' : 'context';
			$title = '';
			if ( strtolower( $parts['host'] ) === $home ) {
				$post_id = url_to_postid( $url );
				$post    = $post_id ? get_post( $post_id ) : null;
				if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password || ! is_post_type_viewable( $post->post_type ) || in_array( (int) $post->ID, SSC_Module_Sitesync::excluded(), true ) ) {
					continue;
				}
				// Respect common explicit noindex settings; unknown imported sources stay hidden.
				$robots = (array) get_post_meta( $post->ID, 'rank_math_robots', true );
				if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) || in_array( 'noindex', $robots, true ) ) {
					continue;
				}
				$title = get_the_title( $post );
			} elseif ( 'web' === $type && SSC_HTTP::is_safe_url( $url ) ) {
				$title = isset( $ref['title'] ) && is_string( $ref['title'] ) ? $ref['title'] : '';
			} else {
				continue;
			}
			$title = wp_strip_all_tags( $title );
			if ( '' !== trim( $title ) ) {
				$out[ $url ] = array(
					'title' => mb_substr( $title, 0, 160 ),
					'url'   => esc_url_raw( $url ),
					'type'  => $type,
				);
			}
			if ( count( $out ) >= 6 ) {
				break;
			}
		}
		return array_values( $out );
	}

	/**
	 * Add an optional receipt after the normal envelope filters have finished.
	 *
	 * @param array      $out Reply envelope.
	 * @param string     $kind Actual reply path.
	 * @param array|null $snapshot Original cache/generation metadata.
	 * @return array
	 */
	public static function decorate( $out, $kind, $snapshot ) {
		if ( empty( $out['ok'] ) || empty( $out['reply'] ) || ! in_array( $kind, array( 'bank', 'ai', 'cache', 'filter', 'live' ), true ) ) {
			return $out;
		}
		$labels = array(
			'bank'   => __( 'From saved answers', 'vazho' ),
			'ai'     => __( 'AI answer', 'vazho' ),
			'cache'  => __( 'Saved response', 'vazho' ),
			'filter' => __( 'Integration response', 'vazho' ),
			'live'   => __( 'Operator response', 'vazho' ),
		);
		$known  = is_array( $snapshot ) && isset( $snapshot['generated_at'], $snapshot['sources'] );
		$time   = $known && is_string( $snapshot['generated_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $snapshot['generated_at'] ) ? $snapshot['generated_at'] : null;
		if ( 'bank' === $kind ) {
			$time = gmdate( 'Y-m-d\TH:i:s\Z' );
		}
		$sources = $known && 'yes' === SSC_Settings::get( 'show_sources', 'yes' ) ? self::public_sources( $snapshot['sources'] ) : array();
		$note    = __( 'This label describes how the answer was prepared, not a guarantee of correctness.', 'vazho' );
		$origin  = 'none';
		if ( 'cache' === $kind && ! $known ) {
			$origin = 'unknown';
			$note   = __( 'The original provenance of this saved response is unavailable.', 'vazho' );
		} elseif ( in_array( $kind, array( 'ai', 'cache' ), true ) ) {
			$origin = $sources ? 'references' : 'none';
			$note   = $sources ? __( 'These references accompanied generation. They do not verify every claim in the answer.', 'vazho' ) : __( 'No public reference is available for this answer.', 'vazho' );
		}
		$out['sources'] = $sources;
		$out['receipt'] = array(
			'version'      => 1,
			'kind'         => $kind,
			'provenance'   => $origin,
			'generated_at' => $time,
			'reviewed_at'  => null,
			'sources'      => $sources,
			'label'        => $labels[ $kind ],
			'note'         => $note,
			'detailsLabel' => __( 'About this answer', 'vazho' ),
			'timeLabel'    => __( 'Prepared:', 'vazho' ),
		);
		return $out;
	}

	/**
	 * Pass the optional field through a transport without changing disabled payloads.
	 *
	 * @param array $payload Transport payload.
	 * @param array $result Engine result.
	 * @return array
	 */
	public static function transport( $payload, $result ) {
		if ( SSC_Modules::is_active( 'answer_receipt' ) && isset( $result['receipt'] ) ) {
			$payload['receipt'] = $result['receipt'];
		}
		return $payload;
	}
}
