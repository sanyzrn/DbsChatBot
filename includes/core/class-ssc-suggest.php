<?php
/**
 * AI-written setup suggestions from the site's own material: a persona for
 * the assistant (name, role, tone, welcome text) and the questions visitors
 * are likely to ask, answered only from that material. Nothing is applied
 * until the administrator reviews and picks the suggestions.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Suggest
 */
class SSC_Suggest {

	/** Most characters of source material sent to the model. */
	const MAX_MATERIAL = 14000;

	/**
	 * Source material: business profile, knowledge entries, then documents
	 * (site pages first).
	 *
	 * @return string
	 */
	public static function material() {
		$business = SSC_Settings::business();
		$parts    = array();
		foreach ( array( 'org_name', 'brand_name', 'category', 'industry', 'description', 'products', 'differentiators', 'location', 'hours', 'phone', 'email', 'url' ) as $key ) {
			if ( ! empty( $business[ $key ] ) ) {
				$parts[] = $key . ': ' . wp_strip_all_tags( (string) $business[ $key ] );
			}
		}
		foreach ( (array) SSC_Settings::get( 'knowledge_items', array() ) as $item ) {
			$parts[] = trim( ( isset( $item['title'] ) ? $item['title'] . ': ' : '' ) . wp_strip_all_tags( (string) ( isset( $item['content'] ) ? $item['content'] : '' ) ) );
		}
		foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
			$parts[] = ( isset( $p['name'] ) ? $p['name'] : '' ) . ': ' . wp_strip_all_tags( (string) ( isset( $p['summary'] ) ? $p['summary'] : '' ) );
		}
		$text = implode( "\n", array_filter( $parts ) );

		global $wpdb;
		$table = SSC_Schema::kb_table_name();
		// Site pages first (doc ids "wp-…"), then imported documents; first chunks carry the gist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name, no input.
		$rows = $wpdb->get_results( "SELECT source_title, chunk FROM {$table} ORDER BY (doc_id LIKE 'wp-%') DESC, id ASC LIMIT 60", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( mb_strlen( $text ) >= self::MAX_MATERIAL ) {
				break;
			}
			$text .= "\n\n## " . $row['source_title'] . "\n" . $row['chunk'];
		}
		return mb_substr( $text, 0, self::MAX_MATERIAL );
	}

	/**
	 * Ask the model for suggestions.
	 *
	 * @return array|WP_Error persona (array), faqs (list of q/a).
	 */
	public static function generate() {
		$provider = SSC_Providers::current();
		if ( null === $provider ) {
			return new WP_Error( 'ssc_no_ai', __( 'Connect an AI provider first (AI Connection).', 'nexachat-ai' ) );
		}
		$material = self::material();
		if ( mb_strlen( $material ) < 200 ) {
			return new WP_Error( 'ssc_no_material', __( 'There is not enough material yet. Fill in the business profile, add knowledge or import pages first.', 'nexachat-ai' ) );
		}
		$system = 'You help set up a customer-support assistant for the organization described in the material. '
			. 'Reply with JSON only, no prose, in this shape: '
			. '{"persona":{"assistant_name":"","assistant_role":"","tone":"professional|friendly|formal|casual","welcome_title":"","welcome_text":""},'
			. '"faqs":[{"q":"","a":""}]}. '
			. 'Write 12 to 20 FAQs: the questions real visitors are most likely to ask, each answered in one to three sentences ONLY from the material. '
			. 'Skip any question the material cannot answer; never invent prices, dates, contacts or medical claims. '
			. 'Use the language the material is mostly written in. The welcome text is one or two friendly sentences.';
		$result = $provider->generate(
			$system,
			array(
				array(
					'role'    => 'user',
					'content' => "MATERIAL:\n" . $material,
				),
			)
		);
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'ssc_ai_failed', __( 'The AI provider did not answer. Please try again in a minute.', 'nexachat-ai' ) );
		}
		$data = self::parse( (string) $result['text'] );
		if ( ! $data ) {
			return new WP_Error( 'ssc_ai_format', __( 'The AI answer could not be read. Please try again.', 'nexachat-ai' ) );
		}
		return $data;
	}

	/**
	 * Parse and clean the model's JSON (code fences and prose tolerated).
	 *
	 * @param string $text Model output.
	 * @return array|null
	 */
	public static function parse( $text ) {
		$text  = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', trim( $text ) ) );
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return null;
		}
		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$persona = array();
		foreach ( array( 'assistant_name', 'assistant_role', 'welcome_title', 'welcome_text' ) as $key ) {
			if ( ! empty( $data['persona'][ $key ] ) && is_string( $data['persona'][ $key ] ) ) {
				$persona[ $key ] = mb_substr( sanitize_text_field( $data['persona'][ $key ] ), 0, 300 );
			}
		}
		if ( ! empty( $data['persona']['tone'] ) && in_array( $data['persona']['tone'], array( 'professional', 'friendly', 'formal', 'casual' ), true ) ) {
			$persona['tone'] = $data['persona']['tone'];
		}
		$faqs = array();
		foreach ( isset( $data['faqs'] ) && is_array( $data['faqs'] ) ? $data['faqs'] : array() as $faq ) {
			if ( ! is_array( $faq ) || empty( $faq['q'] ) || empty( $faq['a'] ) || ! is_string( $faq['q'] ) || ! is_string( $faq['a'] ) ) {
				continue;
			}
			$faqs[] = array(
				'q' => mb_substr( sanitize_text_field( $faq['q'] ), 0, 300 ),
				'a' => mb_substr( sanitize_textarea_field( $faq['a'] ), 0, 1500 ),
			);
			if ( count( $faqs ) >= 25 ) {
				break;
			}
		}
		if ( ! $persona && ! $faqs ) {
			return null;
		}
		return array(
			'persona' => $persona,
			'faqs'    => $faqs,
		);
	}

	/**
	 * Apply what the administrator picked.
	 *
	 * @param array $persona Persona fields to apply.
	 * @param array $faqs    List of q/a to add.
	 * @return array persona (count), faqs (count), target (bank|knowledge).
	 */
	public static function apply( $persona, $faqs ) {
		$business = SSC_Settings::business();
		$patch    = array();
		foreach ( array( 'assistant_name', 'assistant_role', 'tone' ) as $key ) {
			if ( isset( $persona[ $key ] ) && '' !== $persona[ $key ] ) {
				$business[ $key ] = $persona[ $key ];
			}
		}
		$patch['business'] = SSC_Settings::sanitize_business( $business );
		foreach ( array( 'welcome_title', 'welcome_text' ) as $key ) {
			if ( isset( $persona[ $key ] ) && '' !== $persona[ $key ] ) {
				$patch[ $key ] = SSC_Settings::sanitize_value( $key, $persona[ $key ] );
			}
		}
		SSC_Settings::update( $patch );

		$added = 0;
		if ( SSC_Modules::is_active( 'faq' ) ) {
			foreach ( $faqs as $faq ) {
				if ( SSC_Schema::qa_insert(
					array(
						'question' => $faq['q'],
						'answer'   => $faq['a'],
					)
				) ) {
					++$added;
				}
			}
			$target = 'bank';
		} else {
			$items = (array) SSC_Settings::get( 'knowledge_items', array() );
			foreach ( $faqs as $faq ) {
				$items[] = array(
					'type'    => 'faq',
					'title'   => $faq['q'],
					'content' => $faq['a'],
				);
				++$added;
			}
			SSC_Settings::update( array( 'knowledge_items' => $items ) );
			$target = 'knowledge';
		}
		return array(
			'persona' => count( array_filter( $persona ) ),
			'faqs'    => $added,
			'target'  => $target,
		);
	}
}
