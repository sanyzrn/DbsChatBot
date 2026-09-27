<?php
/**
 * Semantic retrieval: embeddings for knowledge chunks and questions.
 *
 * Keyword overlap misses questions phrased differently from the documents
 * ("how much is it?" vs "pricing"). When the connected provider offers an
 * embeddings API (OpenAI, Gemini, or an OpenAI-compatible custom endpoint),
 * every chunk gets a compact vector and retrieval blends cosine similarity
 * with the keyword score. Everything degrades to keyword-only retrieval on
 * any failure, so a missing index never breaks answering.
 *
 * Vectors are reduced to 512 dimensions where the API allows it, L2-normalized
 * (cosine = dot product) and stored as base64 float32.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embeddings class.
 */
class SSC_Embeddings {

	const DIMENSIONS = 512;
	const BATCH      = 32;
	const CRON_HOOK  = 'ssc_kb_embed';

	/**
	 * Is semantic retrieval switched on and possible with the current provider?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === SSC_Settings::get( 'kb_semantic', 'no' ) && '' !== self::model();
	}

	/**
	 * Embedding model for the connected provider ('' = unsupported).
	 *
	 * @return string
	 */
	public static function model() {
		$provider = (string) SSC_Settings::get( 'ai_provider', 'none' );
		switch ( $provider ) {
			case 'openai':
				$model = 'text-embedding-3-small';
				break;
			case 'gemini':
				$model = 'gemini-embedding-001';
				break;
			case 'custom':
				$model = 'text-embedding-3-small';
				break;
			default:
				$model = '';
		}
		/**
		 * Embedding model id for the current provider ('' disables).
		 *
		 * @param string $model    Model id.
		 * @param string $provider Provider id.
		 */
		return (string) apply_filters( 'ssc_embedding_model', $model, $provider );
	}

	/**
	 * Request parts for an embeddings call (PURE - unit tested).
	 *
	 * @param string   $provider Provider id.
	 * @param string   $api_key  Key.
	 * @param string   $model    Model.
	 * @param string[] $texts    Texts.
	 * @param string   $endpoint Custom chat endpoint (custom provider only).
	 * @return array|null url, headers, body; null when unsupported.
	 */
	public static function request_parts( $provider, $api_key, $model, $texts, $endpoint = '' ) {
		$texts = array_values( array_map( 'strval', (array) $texts ) );
		if ( 'gemini' === $provider ) {
			$requests = array();
			foreach ( $texts as $text ) {
				$requests[] = array(
					'model'                => 'models/' . $model,
					'content'              => array( 'parts' => array( array( 'text' => $text ) ) ),
					'outputDimensionality' => self::DIMENSIONS,
				);
			}
			return array(
				'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':batchEmbedContents',
				'headers' => array( 'x-goog-api-key' => (string) $api_key ),
				'body'    => array( 'requests' => $requests ),
			);
		}
		if ( 'openai' === $provider || 'custom' === $provider ) {
			$url = 'https://api.openai.com/v1/embeddings';
			if ( 'custom' === $provider ) {
				if ( '' === (string) $endpoint ) {
					return null;
				}
				$url = preg_replace( '#/chat/completions/?$#', '', rtrim( (string) $endpoint, '/' ) ) . '/embeddings';
			}
			$body = array(
				'model' => $model,
				'input' => $texts,
			);
			if ( 'openai' === $provider ) {
				$body['dimensions'] = self::DIMENSIONS; // Custom servers may not support it.
			}
			$headers = array();
			if ( '' !== (string) $api_key ) {
				$headers['Authorization'] = 'Bearer ' . $api_key;
			}
			return array(
				'url'     => $url,
				'headers' => $headers,
				'body'    => $body,
			);
		}
		return null;
	}

	/**
	 * Vectors from an embeddings response (PURE - unit tested).
	 *
	 * @param string $provider Provider id.
	 * @param array  $data     Decoded response.
	 * @return array[] One float list per input, in input order.
	 */
	public static function extract_vectors( $provider, $data ) {
		$out = array();
		if ( 'gemini' === $provider && isset( $data['embeddings'] ) && is_array( $data['embeddings'] ) ) {
			foreach ( $data['embeddings'] as $row ) {
				$out[] = isset( $row['values'] ) && is_array( $row['values'] ) ? $row['values'] : array();
			}
			return $out;
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$rows = $data['data'];
			usort(
				$rows,
				function ( $a, $b ) {
					return ( isset( $a['index'] ) ? (int) $a['index'] : 0 ) - ( isset( $b['index'] ) ? (int) $b['index'] : 0 );
				}
			);
			foreach ( $rows as $row ) {
				$out[] = isset( $row['embedding'] ) && is_array( $row['embedding'] ) ? $row['embedding'] : array();
			}
		}
		return $out;
	}

	/**
	 * Embed texts with the connected provider.
	 *
	 * @param string[] $texts Texts (<= BATCH).
	 * @return array[]|WP_Error Unit vectors.
	 */
	public static function embed( $texts ) {
		$provider_id = (string) SSC_Settings::get( 'ai_provider', 'none' );
		$provider    = SSC_Providers::current();
		$model       = self::model();
		if ( null === $provider || '' === $model ) {
			return new WP_Error( 'ssc_embed_unsupported', __( 'The connected AI provider does not offer embeddings.', 'nexachat-ai' ) );
		}
		$creds = $provider->saved_credentials();
		$parts = self::request_parts( $provider_id, $creds['api_key'], $model, $texts, isset( $creds['endpoint'] ) ? $creds['endpoint'] : '' );
		if ( null === $parts ) {
			return new WP_Error( 'ssc_embed_unsupported', __( 'The connected AI provider does not offer embeddings.', 'nexachat-ai' ) );
		}
		$response = SSC_HTTP::post_json(
			$parts['url'],
			$parts['headers'],
			$parts['body'],
			array(
				'timeout'     => 30,
				'needs_https' => true,
			)
		);
		if ( ! $response['ok'] ) {
			$message = isset( $response['error']['message'] ) ? $response['error']['message'] : 'embedding request failed';
			return new WP_Error( 'ssc_embed_failed', $message );
		}
		$vectors = self::extract_vectors( $provider_id, $response['data'] );
		if ( count( $vectors ) !== count( $texts ) ) {
			return new WP_Error( 'ssc_embed_failed', __( 'The embeddings response did not match the request.', 'nexachat-ai' ) );
		}
		return array_map( array( __CLASS__, 'normalize' ), $vectors );
	}

	/**
	 * L2-normalize (so cosine similarity is a plain dot product).
	 *
	 * @param array $vector Floats.
	 * @return float[]
	 */
	public static function normalize( $vector ) {
		$vector = array_map( 'floatval', (array) $vector );
		$norm   = 0.0;
		foreach ( $vector as $v ) {
			$norm += $v * $v;
		}
		$norm = sqrt( $norm );
		if ( $norm <= 0.0 ) {
			return array();
		}
		foreach ( $vector as $i => $v ) {
			$vector[ $i ] = $v / $norm;
		}
		return $vector;
	}

	/**
	 * Pack a vector for storage.
	 *
	 * @param float[] $vector Unit vector.
	 * @return string
	 */
	public static function pack( $vector ) {
		return base64_encode( pack( 'g*', ...array_values( $vector ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary vector storage.
	}

	/**
	 * Unpack a stored vector.
	 *
	 * @param string $blob Stored value.
	 * @return float[]
	 */
	public static function unpack( $blob ) {
		$raw = base64_decode( (string) $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary vector storage.
		if ( false === $raw || '' === $raw || 0 !== strlen( $raw ) % 4 ) {
			return array();
		}
		return array_values( unpack( 'g*', $raw ) );
	}

	/**
	 * Cosine similarity of two unit vectors.
	 *
	 * @param float[] $a Vector.
	 * @param float[] $b Vector.
	 * @return float
	 */
	public static function cosine( $a, $b ) {
		$n = min( count( $a ), count( $b ) );
		if ( 0 === $n || count( $a ) !== count( $b ) ) {
			return 0.0;
		}
		$dot = 0.0;
		for ( $i = 0; $i < $n; ++$i ) {
			$dot += $a[ $i ] * $b[ $i ];
		}
		return $dot;
	}

	/**
	 * Question vector (cached for a day: repeated questions cost nothing).
	 *
	 * @param string $question Question.
	 * @return float[] Empty on failure.
	 */
	public static function query_vector( $question ) {
		$key    = 'ssc_qvec_' . md5( self::model() . '|' . SSC_Setup::connection_fingerprint() . '|' . $question );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return self::unpack( $cached );
		}
		$vectors = self::embed( array( mb_substr( (string) $question, 0, 2000 ) ) );
		if ( is_wp_error( $vectors ) || empty( $vectors[0] ) ) {
			return array();
		}
		set_transient( $key, self::pack( $vectors[0] ), DAY_IN_SECONDS );
		return $vectors[0];
	}

	/**
	 * Embed one batch of chunks that are missing a vector for the current model.
	 *
	 * @return array{done:int,remaining:int,error:string}
	 */
	public static function index_batch() {
		$model = self::model();
		if ( '' === $model ) {
			return array(
				'done'      => 0,
				'remaining' => 0,
				'error'     => __( 'The connected AI provider does not offer embeddings.', 'nexachat-ai' ),
			);
		}
		$rows = SSC_Schema::kb_pending_embeddings( $model, self::BATCH );
		if ( empty( $rows ) ) {
			return array(
				'done'      => 0,
				'remaining' => 0,
				'error'     => '',
			);
		}
		$texts = array();
		foreach ( $rows as $row ) {
			$texts[] = trim( $row['source_title'] . "\n" . $row['chunk'] );
		}
		$vectors = self::embed( $texts );
		if ( is_wp_error( $vectors ) ) {
			return array(
				'done'      => 0,
				'remaining' => SSC_Schema::kb_pending_count( $model ),
				'error'     => $vectors->get_error_message(),
			);
		}
		foreach ( $rows as $i => $row ) {
			if ( ! empty( $vectors[ $i ] ) ) {
				SSC_Schema::kb_set_embedding( (int) $row['id'], self::pack( $vectors[ $i ] ), $model );
			}
		}
		return array(
			'done'      => count( $rows ),
			'remaining' => SSC_Schema::kb_pending_count( $model ),
			'error'     => '',
		);
	}

	/**
	 * Background indexing: a few batches per run, re-scheduled until done.
	 */
	public static function cron_run() {
		if ( ! self::enabled() ) {
			return;
		}
		for ( $i = 0; $i < 5; ++$i ) {
			$result = self::index_batch();
			if ( '' !== $result['error'] || 0 === $result['remaining'] ) {
				break;
			}
		}
		if ( '' === $result['error'] && $result['remaining'] > 0 ) {
			self::schedule();
		}
	}

	/**
	 * Queue background indexing (after imports or enabling the feature).
	 */
	public static function schedule() {
		if ( self::enabled() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}
}
