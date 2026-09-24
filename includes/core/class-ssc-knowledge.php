<?php
/**
 * Knowledge engine: Persian/Latin text pipeline, chunking, hybrid retrieval.
 *
 * Pure static functions (unit-tested without WordPress I/O) plus thin
 * DB-backed retrieval. The scoring model is preserved from 4.x (battle-tested)
 * with deterministic candidate windows.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge class.
 */
class SSC_Knowledge {

	/**
	 * Persian/Arabic character folding + noise removal.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = (string) $text;
		// Arabic -> Persian letter folding.
		$map  = array(
			'ي' => 'ی',
			'ك' => 'ک',
			'ة' => 'ه',
			'ۀ' => 'ه',
			'أ' => 'ا',
			'إ' => 'ا',
			'آ' => 'ا',
			'ؤ' => 'و',
			'ئ' => 'ی',
			'ٱ' => 'ا',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
		);
		$text = strtr( $text, $map );
		// Diacritics + kashida removal.
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text );
		// ZWNJ becomes a space so "می‌کنم" matches "می کنم".
		$text = str_replace( "\xE2\x80\x8C", ' ', $text );
		// Lowercase + strip non letter/digit (keeps Persian letters).
		$text = mb_strtolower( $text, 'UTF-8' );
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Tokens with Persian stopwords removed.
	 *
	 * @param string $text Normalized text.
	 * @return string[] Unique tokens.
	 */
	public static function tokenize( $text ) {
		$stop  = array(
			'از',
			'به',
			'با',
			'در',
			'که',
			'را',
			'و',
			'این',
			'آن',
			'برای',
			'است',
			'هست',
			'بود',
			'می',
			'های',
			'ها',
			'یا',
			'هم',
			'چه',
			'چطور',
			'کدام',
			'شما',
			'من',
			'ما',
			'اون',
			'بر',
			'the',
			'a',
			'an',
			'of',
			'to',
			'in',
			'is',
			'are',
			'and',
			'or',
			'for',
			'on',
			'it',
		);
		$parts = preg_split( '/\s+/u', trim( (string) $text ) );
		$out   = array();
		if ( is_array( $parts ) ) {
			foreach ( $parts as $p ) {
				if ( mb_strlen( $p ) > 1 && ! in_array( $p, $stop, true ) ) {
					$out[ $p ] = true;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Persian synonym groups (representative token appended on match).
	 *
	 * @return array[]
	 */
	public static function synonym_groups() {
		$groups = array(
			array( 'قیمت', 'چند', 'تومان', 'هزینه', 'نرخ', 'price', 'cost' ),
			array( 'خرید', 'سفارش', 'بخرم', 'order', 'buy', 'purchase' ),
			array( 'گارانتی', 'ضمانت', 'warranty', 'guarantee' ),
			array( 'ارسال', 'پست', 'تحویل', 'delivery', 'shipping' ),
			array( 'ساعت', 'زمان', 'وقت', 'hours', 'time' ),
			array( 'تماس', 'شماره', 'تلفن', 'phone', 'contact', 'call' ),
			array( 'آدرس', 'محل', 'موقعیت', 'address', 'location' ),
			array( 'عوارض', 'عارضه', 'side', 'effect', 'effects' ),
			array( 'مصرف', 'دوز', 'نحوه', 'استفاده', 'dosage', 'usage', 'use' ),
		);
		/**
		 * Filterable synonym groups for domain tuning.
		 *
		 * @param array $groups Token groups.
		 */
		return apply_filters( 'ssc_synonym_groups', $groups );
	}

	/**
	 * Expand tokens with synonym-group representatives.
	 *
	 * @param string[] $tokens Tokens.
	 * @return string[] Tokens + representatives (deduped).
	 */
	public static function expand_synonyms( $tokens ) {
		$out = array();
		foreach ( (array) $tokens as $t ) {
			$out[ $t ] = true;
		}
		foreach ( self::synonym_groups() as $i => $group ) {
			foreach ( $group as $word ) {
				if ( isset( $out[ $word ] ) ) {
					$out[ 'syn' . $i ] = true;
					break;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Overlap score between user tokens and a reference text.
	 *
	 * Score = 0.7 * coverage(user) + 0.3 * density(reference).
	 *
	 * @param string[] $user_tokens   Tokenized user question.
	 * @param string   $reference_text Normalized reference text.
	 * @return float 0..1
	 */
	public static function overlap_score( $user_tokens, $reference_text ) {
		$ref_tokens = self::tokenize( $reference_text );
		if ( empty( $user_tokens ) || empty( $ref_tokens ) ) {
			return 0.0;
		}
		$user_expanded = self::expand_synonyms( $user_tokens );
		$ref_expanded  = self::expand_synonyms( $ref_tokens );
		$common        = array_intersect( $user_expanded, $ref_expanded );
		$common_count  = count( $common );
		if ( 0 === $common_count ) {
			return 0.0;
		}
		$coverage = $common_count / max( 1, count( $user_expanded ) );
		$density  = $common_count / max( 1, count( $ref_expanded ) );
		return 0.7 * $coverage + 0.3 * $density;
	}

	/**
	 * Split a long text into retrievable chunks.
	 *
	 * @param string $text      Full text.
	 * @param int    $size      Approx characters per chunk.
	 * @param int    $max_chunks Safety cap.
	 * @return string[]
	 */
	public static function chunk_text( $text, $size = 900, $max_chunks = 200 ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}
		$paras   = preg_split( '/\n\s*\n/u', $text );
		$paras   = is_array( $paras ) ? array_filter( array_map( 'trim', $paras ) ) : array();
		$chunks  = array();
		$current = '';
		foreach ( $paras as $para ) {
			// Overlong single paragraphs are hard-split by sentence-ish bounds.
			if ( mb_strlen( $para ) > $size * 2 ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}
				$sentences = preg_split( '/(?<=[.!؟?])\s+/u', $para );
				$buf       = '';
				if ( is_array( $sentences ) ) {
					foreach ( $sentences as $s ) {
						if ( mb_strlen( $buf . ' ' . $s ) > $size && '' !== $buf ) {
							$chunks[] = trim( $buf );
							$buf      = $s;
						} else {
							$buf .= ( '' === $buf ? '' : ' ' ) . $s;
						}
					}
				}
				if ( '' !== trim( (string) $buf ) ) {
					$chunks[] = trim( $buf );
				}
				continue;
			}
			if ( mb_strlen( $current . "\n\n" . $para ) > $size && '' !== $current ) {
				$chunks[] = $current;
				$current  = $para;
			} else {
				$current .= ( '' === $current ? '' : "\n\n" ) . $para;
			}
		}
		if ( '' !== trim( $current ) ) {
			$chunks[] = $current;
		}
		return array_slice( $chunks, 0, $max_chunks );
	}

	/*
	 * --------------------------------------------------------------
	 * Retrieval (DB-backed).
	 * --------------------------------------------------------------
	 */

	/**
	 * Best KB chunks for a question (RAG-lite injection).
	 *
	 * @param string $product_id Product scope.
	 * @param string $question   User message.
	 * @param int    $max        Max chunks.
	 * @return array[] rows: title, chunk, score.
	 */
	public static function retrieve_chunks( $product_id, $question, $max = 3 ) {
		$candidates = SSC_Schema::kb_candidates( $product_id );
		if ( empty( $candidates ) ) {
			return array();
		}
		$user_tokens = self::tokenize( self::normalize( $question ) );
		if ( empty( $user_tokens ) ) {
			return array();
		}

		$threshold = (float) apply_filters( 'ssc_kb_threshold', 0.08 );
		$scored    = array();
		foreach ( $candidates as $row ) {
			$score = self::overlap_score( $user_tokens, self::normalize( $row['chunk'] ) );
			if ( $score >= $threshold ) {
				$scored[] = array(
					'title' => $row['source_title'],
					'chunk' => $row['chunk'],
					'score' => $score,
				);
			}
		}
		usort(
			$scored,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] > $b['score'] ) ? -1 : 1;
			}
		);
		return array_slice( $scored, 0, max( 1, min( 8, (int) $max ) ) );
	}

	/**
	 * Best QA bank answer for a question.
	 *
	 * @param string $product_id Product scope.
	 * @param string $question   User message.
	 * @return array|null row: id, answer, score.
	 */
	public static function bank_answer( $product_id, $question ) {
		$candidates = SSC_Schema::qa_candidates( $product_id );
		if ( empty( $candidates ) ) {
			return null;
		}
		$user_tokens = self::tokenize( self::normalize( $question ) );
		if ( empty( $user_tokens ) ) {
			return null;
		}

		$threshold = (float) apply_filters( 'ssc_bank_threshold', 0.32 );
		$best      = null;
		foreach ( $candidates as $row ) {
			$reference = self::normalize( $row['question'] . ' ' . str_replace( array( '|', '،', ',' ), ' ', (string) $row['keywords'] ) );
			$score     = self::overlap_score( $user_tokens, $reference );

			// Keyword intersection bonus.
			if ( ! empty( $row['keywords'] ) ) {
				$kw_tokens = self::tokenize( self::normalize( (string) $row['keywords'] ) );
				if ( array_intersect( $user_tokens, $kw_tokens ) ) {
					$score += 0.2;
				}
			}
			if ( $score >= $threshold && ( null === $best || $score > $best['score'] ) ) {
				$best = array(
					'id'     => (int) $row['id'],
					'answer' => $row['answer'],
					'score'  => $score,
				);
			}
		}
		return $best;
	}

	/**
	 * Related questions from the bank (suggestion chips / autocomplete).
	 *
	 * @param string $product_id Product scope.
	 * @param string $term       Partial input.
	 * @param int    $limit      Max rows.
	 * @return array[] id, question.
	 */
	public static function related_questions( $product_id, $term, $limit = 5 ) {
		$candidates = SSC_Schema::qa_candidates( $product_id );
		$term_norm  = self::normalize( $term );
		if ( '' === $term_norm || empty( $candidates ) ) {
			return array();
		}
		$term_tokens = self::tokenize( $term_norm );
		$scored      = array();
		foreach ( $candidates as $row ) {
			$score = self::overlap_score( $term_tokens, self::normalize( $row['question'] ) );
			if ( $score > 0.15 ) {
				$scored[] = array(
					'id'       => (int) $row['id'],
					'question' => $row['question'],
					'score'    => $score,
				);
			}
		}
		usort(
			$scored,
			function ( $a, $b ) {
				return ( $a['score'] > $b['score'] ) ? -1 : ( ( $a['score'] < $b['score'] ) ? 1 : 0 );
			}
		);
		return array_slice( $scored, 0, max( 1, (int) $limit ) );
	}

	/*
	 * --------------------------------------------------------------
	 * Business knowledge compilation (for the prompt builder).
	 * --------------------------------------------------------------
	 */

	/**
	 * Compact "always included" business context (identity + curated items).
	 *
	 * @param string $product_id Optional product focus.
	 * @return string Empty string when nothing is configured.
	 */
	public static function business_context( $product_id = '' ) {
		$parts = array();

		// Product catalog entry (focused product gets priority placement).
		if ( '' !== $product_id ) {
			foreach ( (array) SSC_Settings::get( 'products', array() ) as $p ) {
				if ( isset( $p['id'] ) && $p['id'] === $product_id ) {
					$body = ! empty( $p['summary'] ) ? (string) $p['summary'] : '';
					if ( ! empty( $p['attributes'] ) && is_array( $p['attributes'] ) ) {
						foreach ( $p['attributes'] as $k => $v ) {
							$body .= "\n- " . $k . ': ' . $v;
						}
					}
					// Title AND body go inside the fence; see SSC_Prompt_Builder::fence().
					$entry = SSC_Prompt_Builder::fence( 'PRODUCT', isset( $p['name'] ) ? $p['name'] : '', $body );
					if ( '' !== $entry ) {
						$parts[] = $entry;
					}
					break;
				}
			}
		}

		// Curated knowledge items (wizard step 2) - compact, token-budgeted.
		$items  = (array) SSC_Settings::get( 'knowledge_items', array() );
		$budget = 4000; // chars of curated knowledge max.
		$used   = 0;
		foreach ( $items as $item ) {
			$body = isset( $item['content'] ) ? trim( (string) $item['content'] ) : '';
			if ( '' === $body ) {
				continue;
			}
			if ( $used + mb_strlen( $body ) > $budget ) {
				$body = mb_substr( $body, 0, max( 0, $budget - $used ) ) . '…';
			}
			$used   += mb_strlen( $body );
			$title   = ! empty( $item['title'] ) ? $item['title'] : __( 'Reference', 'smart-support-chatbot' );
			$parts[] = SSC_Prompt_Builder::fence( 'KNOWLEDGE', $title, $body );
			if ( $used >= $budget ) {
				break;
			}
		}

		return implode( "\n\n", $parts );
	}
}
