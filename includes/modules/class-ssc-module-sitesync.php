<?php
/**
 * Learn from the site: published pages, posts and products become
 * knowledge documents automatically and stay up to date.
 *
 * - Saving content re-indexes it shortly after (a single cron event, so
 *   page builders render outside the editor request); trashing,
 *   unpublishing or password-protecting removes it.
 * - "Sync now" indexes everything in batches, with progress on the
 *   Knowledge page; a daily run catches changes made outside the editor.
 *
 * Documents are stored with the id "wp-<post id>" and the page URL, so
 * answers can cite the page they came from.
 *
 * @package SmartSupportChatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SSC_Module_Sitesync
 */
class SSC_Module_Sitesync extends SSC_Module {

	const POST_HOOK  = 'ssc_sitesync_post';
	const BATCH_HOOK = 'ssc_sitesync_batch';
	const DAILY_HOOK = 'ssc_sitesync_daily';
	const STATE      = 'ssc_sitesync_state';
	const BATCH      = 25;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'sitesync';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Learn from my website', 'nexachat-ai' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Your published pages, posts and products become the assistant\'s knowledge automatically, and stay up to date when you edit them.', 'nexachat-ai' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'The assistant knows your site from minute one, without copying anything by hand.', 'nexachat-ai' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category() {
		return 'knowledge';
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/><path d="M8 12h8M8 16h5"/></svg>';
	}

	/**
	 * Runtime hooks.
	 */
	public function register() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'queue_post' ), 20, 2 );
		add_action( 'wp_trash_post', array( __CLASS__, 'forget_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_post' ) );
		add_action( self::POST_HOOK, array( __CLASS__, 'index_post' ) );
		add_action( self::BATCH_HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'start' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
	}

	/**
	 * First activation: index the site right away.
	 */
	public function on_activate() {
		self::start();
	}

	/**
	 * Daily full resync while the module is on.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	/**
	 * Content types that are indexed.
	 *
	 * @return string[]
	 */
	public static function types() {
		$types = array_filter( (array) SSC_Settings::get( 'sitesync_types', array() ), 'post_type_exists' );
		if ( ! $types ) {
			$types = array_values( array_filter( array( 'page', 'post', 'product' ), 'post_type_exists' ) );
		}
		return array_values( $types );
	}

	/**
	 * Post ids the admin excluded.
	 *
	 * @return int[]
	 */
	public static function excluded() {
		return array_map( 'intval', (array) SSC_Settings::get( 'sitesync_exclude', array() ) );
	}

	/**
	 * Should this post be in the knowledge base?
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function wanted( $post ) {
		return $post instanceof WP_Post
			&& 'publish' === $post->post_status
			&& '' === (string) $post->post_password
			&& in_array( $post->post_type, self::types(), true )
			&& ! in_array( (int) $post->ID, self::excluded(), true );
	}

	/**
	 * Document id for a post.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function doc_id( $post_id ) {
		return 'wp-' . (int) $post_id;
	}

	/**
	 * After a save: re-index shortly (or remove when no longer published).
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public static function queue_post( $post_id, $post ) {
		if ( ! SSC_Modules::is_active( 'sitesync' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! self::wanted( $post ) ) {
			self::forget_post( $post_id );
			return;
		}
		if ( ! wp_next_scheduled( self::POST_HOOK, array( (int) $post_id ) ) ) {
			wp_schedule_single_event( time() + 30, self::POST_HOOK, array( (int) $post_id ) );
		}
	}

	/**
	 * Remove a post's document.
	 *
	 * @param int $post_id Post id.
	 */
	public static function forget_post( $post_id ) {
		SSC_Schema::kb_delete_document( self::doc_id( $post_id ) );
	}

	/**
	 * Plain text of a post as a visitor reads it.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function post_text( $post ) {
		$parts = array( get_the_title( $post ) );
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			$parts[] = $post->post_excerpt;
		}
		// Render like the front end so shortcodes and blocks become text;
		// a page builder that fails here must not break indexing.
		$html  = '';
		$saved = array();
		foreach ( array( 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' ) as $name ) {
			$saved[ $name ] = isset( $GLOBALS[ $name ] ) ? $GLOBALS[ $name ] : null;
		}
		try {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the_content expects it; restored below.
			setup_postdata( $post );
			ob_start();
			$html = (string) apply_filters( 'the_content', $post->post_content );
			$echo = (string) ob_get_clean();
			$html = '' !== trim( $html ) ? $html : $echo;
		} catch ( Throwable $e ) {
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			$html = strip_shortcodes( (string) $post->post_content );
		}
		wp_reset_postdata();
		// setup_postdata() overwrites these template globals; put them back.
		foreach ( $saved as $name => $value ) {
			$GLOBALS[ $name ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the caller's values.
		}
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$parts[] = wp_strip_all_tags( (string) $product->get_short_description() );
				if ( $product->get_sku() ) {
					$parts[] = 'SKU: ' . $product->get_sku();
				}
			}
		}
		// Keep block structure as line breaks, drop scripts and styles.
		$html    = preg_replace( '#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $html );
		$html    = preg_replace( '#</(p|div|li|h[1-6]|tr|br|section|article)>|<br\s*/?>#i', "\n", (string) $html );
		$parts[] = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return SSC_Doc_Extract::tidy( implode( "\n\n", array_filter( array_map( 'trim', $parts ) ) ) );
	}

	/**
	 * (Re)index one post.
	 *
	 * @param int $post_id Post id.
	 * @return int Chunks stored.
	 */
	public static function index_post( $post_id ) {
		$post = get_post( $post_id );
		self::forget_post( $post_id );
		if ( ! SSC_Modules::is_active( 'sitesync' ) || ! self::wanted( $post ) ) {
			return 0;
		}
		$text = self::post_text( $post );
		if ( mb_strlen( $text ) < 40 ) {
			return 0; // Nothing worth answering from.
		}
		$chunks = (int) SSC_Schema::kb_insert_document( self::doc_id( $post_id ), mb_substr( wp_strip_all_tags( get_the_title( $post ) ), 0, 180 ), $text, 'general', (string) get_permalink( $post ) );
		if ( $chunks && 'yes' === SSC_Settings::get( 'kb_semantic', 'no' ) && class_exists( 'SSC_Embeddings' ) ) {
			SSC_Embeddings::schedule();
		}
		return $chunks;
	}

	/**
	 * Sync state.
	 *
	 * @return array running, cursor, done, total, docs, finished.
	 */
	public static function state() {
		$state = get_option( self::STATE, array() );
		return array_merge(
			array(
				'running'  => false,
				'cursor'   => 0,
				'done'     => 0,
				'total'    => 0,
				'finished' => '',
			),
			is_array( $state ) ? $state : array()
		);
	}

	/**
	 * Start a full sync (old documents of removed content are dropped at the end).
	 */
	public static function start() {
		if ( ! SSC_Modules::is_active( 'sitesync' ) ) {
			return;
		}
		$total = 0;
		foreach ( self::types() as $type ) {
			$counts = wp_count_posts( $type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		update_option(
			self::STATE,
			array_merge(
				self::state(),
				array(
					'running' => true,
					'cursor'  => 0,
					'done'    => 0,
					'total'   => $total,
					'seen'    => array(),
				)
			),
			false
		);
		if ( ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::BATCH_HOOK );
		}
	}

	/**
	 * Index the next batch; reschedules itself until the site is covered.
	 *
	 * @param int $budget Seconds to spend (0 = one batch).
	 * @return array State.
	 */
	public static function run_batch( $budget = 0 ) {
		$state = self::state();
		if ( ! $state['running'] || ! SSC_Modules::is_active( 'sitesync' ) ) {
			return $state;
		}
		$until = microtime( true ) + max( 0, (int) $budget );
		do {
			global $wpdb;
			$types = self::types();
			$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one placeholder per type, built above.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_type IN ({$in}) ORDER BY ID ASC LIMIT %d", array_merge( array( (int) $state['cursor'] ), $types, array( self::BATCH ) ) ) );
			foreach ( $ids as $id ) {
				self::index_post( (int) $id );
				$state['cursor'] = (int) $id;
				++$state['done'];
			}
			if ( count( $ids ) < self::BATCH ) {
				$state['running']  = false;
				$state['finished'] = current_time( 'mysql' );
				self::drop_orphans();
				break;
			}
		} while ( microtime( true ) < $until );
		update_option( self::STATE, $state, false );
		if ( $state['running'] && ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::BATCH_HOOK );
		}
		return $state;
	}

	/**
	 * Remove documents of posts that are no longer published or included.
	 */
	protected static function drop_orphans() {
		foreach ( self::documents() as $doc ) {
			$post = get_post( (int) substr( $doc['doc_id'], 3 ) );
			if ( ! self::wanted( $post ) ) {
				SSC_Schema::kb_delete_document( $doc['doc_id'] );
			}
		}
	}

	/**
	 * Knowledge documents that came from the site.
	 *
	 * @return array[]
	 */
	public static function documents() {
		return array_values(
			array_filter(
				(array) SSC_Schema::kb_documents(),
				function ( $doc ) {
					return 0 === strpos( (string) $doc['doc_id'], 'wp-' );
				}
			)
		);
	}

	/**
	 * Remove every document that came from the site.
	 *
	 * @return int Documents removed.
	 */
	public static function clear() {
		$docs = self::documents();
		foreach ( $docs as $doc ) {
			SSC_Schema::kb_delete_document( $doc['doc_id'] );
		}
		update_option( self::STATE, array(), false );
		return count( $docs );
	}
}
