<?php
/**
 * Business Knowledge page: business profile, knowledge items, product
 * catalog, document import (KB).
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge page.
 */
class SSC_Admin_Knowledge {

	/**
	 * Constructor: PRG form handling.
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'vazho' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_actions' ), 5 );
	}

	/**
	 * Where the posted form holds something other than plain text or numbers
	 * (a list in a text field), as "section:row:field"; '' when it is well formed.
	 *
	 * @return string
	 */
	protected static function malformed_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller; only the shape is read here.
		if ( isset( $_POST['business'] ) ) {
			$field = is_array( $_POST['business'] ) ? SSC_Input::bad_field( $_POST['business'], array(), array( 'locations' ) ) : '*'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only inspected.
			if ( '' !== $field ) {
				return 'business::' . $field;
			}
		}
		foreach ( array( 'ki' => array(), 'products' => array(), 'product_attributes' => array( '*' ) ) as $section => $unused ) {
			if ( ! isset( $_POST[ $section ] ) ) {
				continue;
			}
			if ( ! is_array( $_POST[ $section ] ) ) {
				return $section . '::*';
			}
			foreach ( $_POST[ $section ] as $row => $fields ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only inspected.
				if ( 'product_attributes' === $section ) {
					// A flat list of names and values, or key/value pairs.
					if ( ! is_array( $fields ) ) {
						return $section . ':' . $row . ':*';
					}
					foreach ( $fields as $entry ) {
						if ( is_array( $entry ) ? '' !== SSC_Input::bad_field( $entry ) : ! is_scalar( $entry ) ) {
							return $section . ':' . $row . ':value';
						}
					}
					continue;
				}
				$field = SSC_Input::bad_field( $fields, array( 'attributes' ) );
				if ( '' !== $field ) {
					return $section . ':' . $row . ':' . $field;
				}
			}
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Where a save that clashed with a newer change is kept while the person decides.
	 *
	 * @return string
	 */
	public static function conflict_key() {
		return 'ssc_kn_conflict_' . get_current_user_id();
	}

	/**
	 * Save the Knowledge form on top of what is stored now.
	 *
	 * @param array      $mine   business, ki, products as the form posted them (cleaned).
	 * @param array|null $base   Snapshot the form was built from (null for an old form).
	 * @param bool       $prefer On a clash take the form's value (otherwise the stored one).
	 * @param bool       $force  Save even when there are clashes (the person has decided).
	 * @return array Clashes (section => fields or ids) when nothing was saved, else empty.
	 */
	protected static function save_form( $mine, $base, $prefer, $force = false ) {
		$current = array(
			'business' => SSC_Settings::sanitize_business( (array) SSC_Settings::get( 'business', array() ) ),
			'ki'       => (array) SSC_Settings::get( 'knowledge_items', array() ),
			'products' => (array) SSC_Settings::get( 'products', array() ),
		);
		$merged  = SSC_Merge::knowledge( $base, $mine, $current, $prefer );
		if ( $merged['conflicts'] && ! $force ) {
			return $merged['conflicts'];
		}
		SSC_Settings::update(
			array(
				'business'        => SSC_Settings::sanitize_business( $merged['business'] ),
				'knowledge_items' => $merged['ki'],
				'products'        => $merged['products'],
			)
		);
		return array();
	}

	/**
	 * Form actions (all PRG).
	 */
	public function handle_actions() {
		if ( isset( $_POST['ssc_knowledge_save'] ) && check_admin_referer( 'ssc_knowledge' ) ) {
			// A form with a list where a single value belongs is refused whole: nothing is saved, and the place is named.
			$malformed = self::malformed_post();
			if ( '' !== $malformed ) {
				self::prg( array( 'malformed' => $malformed ) );
			}
			// Business profile block.
			$business = array();
			foreach ( array( 'org_name', 'brand_name', 'category', 'industry', 'location', 'phone', 'email', 'support_phone', 'support_email', 'hours', 'assistant_name', 'assistant_role', 'tone', 'language' ) as $f ) {
				$business[ $f ] = isset( $_POST['business'][ $f ] ) ? sanitize_text_field( wp_unslash( $_POST['business'][ $f ] ) ) : '';
			}
			foreach ( array( 'description', 'products', 'differentiators' ) as $f ) {
				$business[ $f ] = isset( $_POST['business'][ $f ] ) ? wp_kses_post( wp_unslash( $_POST['business'][ $f ] ) ) : '';
			}
			$business['url'] = isset( $_POST['business']['url'] ) ? esc_url_raw( wp_unslash( $_POST['business']['url'] ) ) : '';
			// The places list (empty when every row was removed; the old single address then falls away with it).
			$business['locations'] = SSC_Locations::sanitize( isset( $_POST['business']['locations'] ) ? wp_unslash( $_POST['business']['locations'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- SSC_Locations::sanitize() cleans every field.

			// Knowledge entries.
			$items = array();
			if ( isset( $_POST['ki'] ) && is_array( $_POST['ki'] ) ) {
				foreach ( wp_unslash( $_POST['ki'] ) as $item ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below.
					if ( ! is_array( $item ) ) {
						continue;
					}
					$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
					$body  = isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '';
					if ( '' === trim( $title ) && '' === trim( $body ) ) {
						continue;
					}
					$type    = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'general';
					$item_id = isset( $item['id'] ) ? sanitize_key( $item['id'] ) : '';
					$items[] = array(
						// New rows arrive without an id: give them a stable one (export and import match entries by it).
						'id'      => '' !== $item_id ? $item_id : 'ki-' . uniqid(),
						'type'    => $type,
						'title'   => $title,
						'content' => $body,
					);
				}
			}

			// Product catalog.
			$products = array();
			if ( isset( $_POST['products'] ) && is_array( $_POST['products'] ) ) {
				$raw_products = wp_unslash( $_POST['products'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
				$attributes   = isset( $_POST['product_attributes'] ) && is_array( $_POST['product_attributes'] ) ? wp_unslash( $_POST['product_attributes'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
				$taken        = array();
				foreach ( $raw_products as $i => $p ) {
					if ( ! is_array( $p ) || '' === trim( isset( $p['name'] ) ? $p['name'] : '' ) ) {
						continue;
					}
					$entry = array(
						'id'       => SSC_Settings::make_unique_id( isset( $p['id'] ) ? $p['id'] : '', $p['name'], $taken ),
						'name'     => sanitize_text_field( $p['name'] ),
						'summary'  => isset( $p['summary'] ) ? wp_kses_post( $p['summary'] ) : '',
						'brochure' => isset( $p['brochure'] ) ? esc_url_raw( $p['brochure'] ) : '',
						'image'    => isset( $p['image'] ) ? esc_url_raw( $p['image'] ) : '',
						'attributes' => array(),
					);
					// Flexible attributes (key:value rows per product).
					// The form posts a flat list (name, value, name, value…); array
					// pairs with key/value are accepted too.
					if ( isset( $attributes[ $i ] ) && is_array( $attributes[ $i ] ) ) {
						$flat  = array_values( $attributes[ $i ] );
						$pairs = array();
						for ( $n = 0, $count = count( $flat ); $n < $count; $n++ ) {
							if ( is_array( $flat[ $n ] ) ) {
								$pairs[] = array( isset( $flat[ $n ]['key'] ) ? $flat[ $n ]['key'] : '', isset( $flat[ $n ]['value'] ) ? $flat[ $n ]['value'] : '' );
							} else {
								$pairs[] = array( $flat[ $n ], isset( $flat[ $n + 1 ] ) && ! is_array( $flat[ $n + 1 ] ) ? $flat[ $n + 1 ] : '' );
								++$n;
							}
						}
						foreach ( $pairs as $pair ) {
							// Not sanitize_key(): it strips non-Latin names (e.g. Persian) to nothing.
							$name  = sanitize_text_field( (string) $pair[0] );
							$value = sanitize_text_field( (string) $pair[1] );
							if ( '' !== $name && '' !== $value ) {
								$entry['attributes'][ $name ] = $value;
							}
						}
					}
					$products[] = $entry;
				}
			}

			// What this form changed is applied onto what is stored now; if another tab or person changed the same thing, nothing is saved until the person decides.
			$mine      = array(
				'business' => SSC_Settings::sanitize_business( $business ),
				'ki'       => $items,
				'products' => $products,
			);
			$base      = SSC_Merge::parse_snapshot( isset( $_POST['ssc_base'] ) && is_string( $_POST['ssc_base'] ) ? wp_unslash( $_POST['ssc_base'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and validated hash by hash.
			$conflicts = self::save_form( $mine, $base, false );
			if ( $conflicts ) {
				set_transient(
					self::conflict_key(),
					array(
						'mine'      => $mine,
						'base'      => $base,
						'conflicts' => $conflicts,
						'panel'     => self::posted_panel(),
					),
					15 * MINUTE_IN_SECONDS
				);
				self::prg(
					array(
						'conflict' => 1,
						'panel'    => self::posted_panel(),
					)
				);
			}
			delete_transient( self::conflict_key() );
			self::prg(
				array(
					'saved' => 1,
					'panel' => self::posted_panel(),
				)
			);
		}

		// The person's decision about a save that clashed with a newer change.
		if ( isset( $_POST['ssc_knowledge_resolve'] ) && check_admin_referer( 'ssc_knowledge_resolve' ) ) {
			$stash = get_transient( self::conflict_key() );
			delete_transient( self::conflict_key() );
			$panel = is_array( $stash ) && ! empty( $stash['panel'] ) ? (string) $stash['panel'] : '';
			$choice = sanitize_key( wp_unslash( $_POST['ssc_knowledge_resolve'] ) );
			if ( is_array( $stash ) && isset( $stash['mine'], $stash['base'] ) && in_array( $choice, array( 'mine', 'latest' ), true ) ) {
				// Everything that did not clash is saved either way; only the clashing values follow the choice.
				self::save_form( $stash['mine'], $stash['base'], 'mine' === $choice, true );
				self::prg(
					array(
						'saved' => 1,
						'panel' => $panel,
					)
				);
			}
			self::prg( array( 'panel' => $panel ) );
		}

		// Document import (URL).
		if ( isset( $_POST['ssc_kb_import_url'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$url    = esc_url_raw( wp_unslash( $_POST['kb_url'] ) );
			$chunks = $this->import_url( $url );
			self::prg( $chunks > 0 ? array( 'kb' => 'added', 'chunks' => $chunks ) : array( 'kb' => 'failed' ) );
		}

		// Document import (file upload).
		if ( isset( $_POST['ssc_kb_import_file'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$result = $this->import_file();
			self::prg( array( 'kb' => $result['status'], 'chunks' => $result['chunks'] ) );
		}

		// Semantic search settings + manual index run.
		if ( isset( $_POST['ssc_kb_semantic_save'] ) && check_admin_referer( 'ssc_kb' ) ) {
			SSC_Settings::update(
				array(
					'kb_semantic'  => isset( $_POST['kb_semantic'] ) ? 'yes' : 'no',
					'show_sources' => isset( $_POST['show_sources'] ) ? 'yes' : 'no',
				)
			);
			$args = array( 'kb' => 'semantic' );
			if ( SSC_Embeddings::enabled() ) {
				// Index a few batches right away; the rest continues in the background.
				for ( $i = 0; $i < 3; ++$i ) {
					$result = SSC_Embeddings::index_batch();
					if ( '' !== $result['error'] ) {
						$args['kb'] = 'semantic_error';
						set_transient( 'ssc_kb_embed_error', $result['error'], 10 * MINUTE_IN_SECONDS );
						break;
					}
					if ( 0 === $result['remaining'] ) {
						break;
					}
				}
				SSC_Embeddings::schedule();
			}
			self::prg( $args );
		}

		// Learn from my website: settings, sync now, remove.
		if ( isset( $_POST['ssc_sitesync_action'] ) && check_admin_referer( 'ssc_kb' ) && SSC_Modules::is_active( 'sitesync' ) ) {
			$action = sanitize_key( wp_unslash( $_POST['ssc_sitesync_action'] ) );
			if ( 'save' === $action || 'run' === $action ) {
				$types   = isset( $_POST['sitesync_types'] ) ? (array) wp_unslash( $_POST['sitesync_types'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by sanitize_value().
				$exclude = isset( $_POST['sitesync_exclude'] ) ? preg_split( '/[\s,،]+/u', sanitize_text_field( wp_unslash( $_POST['sitesync_exclude'] ) ) ) : array();
				SSC_Settings::update(
					array(
						'sitesync_types'   => SSC_Settings::sanitize_value( 'sitesync_types', $types ),
						'sitesync_exclude' => SSC_Settings::sanitize_value( 'sitesync_exclude', $exclude ),
					)
				);
			}
			if ( 'run' === $action ) {
				SSC_Module_Sitesync::start();
				SSC_Module_Sitesync::run_batch( 15 ); // A head start; the rest continues in the background.
			}
			if ( 'clear' === $action ) {
				SSC_Module_Sitesync::clear();
			}
			self::prg( array( 'kb' => 'sitesync_' . $action ) );
		}

		// AI suggestions (FAQs and persona): generate, then apply what the admin picks.
		if ( isset( $_POST['ssc_suggest'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$result = SSC_Suggest::generate();
			if ( is_wp_error( $result ) ) {
				set_transient( 'ssc_suggest_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS );
				self::prg( array( 'kb' => 'suggest_error' ) );
			}
			set_transient( 'ssc_suggest_' . get_current_user_id(), $result, HOUR_IN_SECONDS );
			self::prg( array( 'kb' => 'suggest_ready' ) );
		}
		if ( isset( $_POST['ssc_suggest_apply'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$persona = array();
			$picked  = isset( $_POST['persona_pick'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['persona_pick'] ) ) : array();
			foreach ( $picked as $key ) {
				if ( isset( $_POST['persona'][ $key ] ) ) {
					$persona[ $key ] = sanitize_text_field( wp_unslash( $_POST['persona'][ $key ] ) );
				}
			}
			$faqs = array();
			foreach ( isset( $_POST['faq'] ) ? (array) wp_unslash( $_POST['faq'] ) : array() as $faq ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized below.
				if ( ! empty( $faq['pick'] ) && ! empty( $faq['q'] ) && ! empty( $faq['a'] ) ) {
					$faqs[] = array(
						'q' => sanitize_text_field( $faq['q'] ),
						'a' => sanitize_textarea_field( $faq['a'] ),
					);
				}
			}
			$applied = SSC_Suggest::apply( $persona, $faqs );
			delete_transient( 'ssc_suggest_' . get_current_user_id() );
			self::prg(
				array(
					'kb'     => 'suggest_applied',
					'faqs'   => $applied['faqs'],
					'target' => $applied['target'],
				)
			);
		}
		if ( isset( $_POST['ssc_suggest_discard'] ) && check_admin_referer( 'ssc_kb' ) ) {
			delete_transient( 'ssc_suggest_' . get_current_user_id() );
			self::prg( array() );
		}

		// KB document delete.
		if ( isset( $_GET['ssc_kb_action'], $_GET['doc'], $_GET['_wpnonce'] ) ) {
			$doc = sanitize_key( wp_unslash( $_GET['doc'] ) );
			if ( 'delete' === sanitize_key( wp_unslash( $_GET['ssc_kb_action'] ) ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_kb_' . $doc ) ) {
				SSC_Schema::kb_delete_document( $doc );
				self::prg( array( 'kb' => 'deleted' ) );
			}
		}
	}

	/**
	 * Sections the page is divided into.
	 *
	 * @return string[]
	 */
	public static function panel_ids() {
		return array_merge( array_keys( SSC_Knowledge_Score::panels() ), array( 'ai' ) );
	}

	/**
	 * The section that was open when a form was sent.
	 *
	 * @return string
	 */
	protected static function posted_panel() {
		$panel = isset( $_POST['ssc_panel'] ) ? sanitize_key( wp_unslash( $_POST['ssc_panel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read after the caller verified the nonce.
		return in_array( $panel, self::panel_ids(), true ) ? $panel : '';
	}

	/**
	 * Which section to open after a redirect.
	 *
	 * @param string $kb Result state from the query string.
	 * @return string
	 */
	protected static function initial_panel( $kb ) {
		$panel = isset( $_GET['panel'] ) ? sanitize_key( wp_unslash( $_GET['panel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( in_array( $panel, self::panel_ids(), true ) ) {
			return $panel;
		}
		if ( 0 === strpos( $kb, 'sitesync' ) ) {
			return 'website';
		}
		if ( 0 === strpos( $kb, 'suggest' ) ) {
			return 'ai';
		}
		if ( '' !== $kb ) {
			return 'documents';
		}
		return 'identity';
	}

	/**
	 * Import a URL into the KB (admin-initiated, SSRF-guarded).
	 *
	 * @param string $url URL.
	 * @return int Number of chunks stored (0 on failure).
	 */
	protected function import_url( $url ) {
		if ( '' === $url || ! SSC_HTTP::is_safe_url( $url, true ) ) {
			return 0;
		}
		$response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'limit_response_size' => 2 * MB_IN_BYTES ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return 0;
		}
		$html = (string) wp_remote_retrieve_body( $response );
		$title = '';
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
			$title = sanitize_text_field( html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}
		$text = preg_replace( '/<(script|style|noscript|nav|header|footer)[^>]*>.*?<\/\1>/is', ' ', $html );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) wp_strip_all_tags( (string) $text ) ) );
		if ( mb_strlen( $text ) < 200 ) {
			return 0;
		}
		$doc_id = 'url-' . substr( md5( $url ), 0, 12 );
		// Importing the same address again replaces its document; if the new version cannot be built the old one stays.
		return (int) SSC_Schema::kb_replace_document( $doc_id, '' !== $title ? $title : $url, $text, 'general', $url );
	}

	/**
	 * Import a text/markdown/CSV file into the KB (validated + capped).
	 *
	 * @return array status + chunk count.
	 */
	protected function import_file() {
		$out = array( 'status' => 'failed', 'chunks' => 0 );
		if ( empty( $_FILES['kb_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['kb_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- upload validated; content parsed below.
			$out['status'] = 'nofile';
			return $out;
		}
		$allowed = array( 'txt', 'md', 'csv', 'json', 'pdf', 'docx' );
		$ext     = strtolower( pathinfo( (string) $_FILES['kb_file']['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			$out['status'] = 'badtype';
			return $out;
		}
		// Documents (brochures, catalogues) may be larger than plain text files.
		$limit = in_array( $ext, array( 'pdf', 'docx' ), true ) ? 10 * MB_IN_BYTES : 2 * MB_IN_BYTES;
		if ( (int) $_FILES['kb_file']['size'] > $limit ) {
			$out['status'] = 'toobig'; // Reported honestly (4.x bug fixed).
			return $out;
		}
		if ( in_array( $ext, array( 'pdf', 'docx' ), true ) ) {
			$content = SSC_Doc_Extract::file( $_FILES['kb_file']['tmp_name'], $ext ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- is_uploaded_file() checked above.
			if ( is_wp_error( $content ) ) {
				set_transient( 'ssc_kb_import_error_' . get_current_user_id(), $content->get_error_message(), 5 * MINUTE_IN_SECONDS );
				$out['status'] = 'docerror';
				return $out;
			}
		} else {
			$content = (string) file_get_contents( $_FILES['kb_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- validated upload with whitelisted extension.
		}
		if ( '' === trim( $content ) ) {
			$out['status'] = 'empty';
			return $out;
		}
		$title  = sanitize_text_field( pathinfo( (string) $_FILES['kb_file']['name'], PATHINFO_FILENAME ) );
		$doc_id = 'doc-' . substr( md5( $title . time() ), 0, 12 );
		$chunks = SSC_Schema::kb_insert_document( $doc_id, $title, $content );
		if ( $chunks > 0 ) {
			return array( 'status' => 'added', 'chunks' => $chunks );
		}
		return $out;
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-knowledge';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render.
	 */
	public function render() {
		$business = SSC_Settings::business();
		$items    = (array) SSC_Settings::get( 'knowledge_items', array() );
		$products = (array) SSC_Settings::get( 'products', array() );
		$kb_docs  = SSC_Schema::kb_documents();
		$kb_count = SSC_Schema::kb_count();

		// Pages mirrored from the website are tracked by the sync module, not counted as documents.
		$site_docs = array_filter(
			$kb_docs,
			function ( $doc ) {
				return 0 === strpos( (string) $doc['doc_id'], 'wp-' );
			}
		);
		$own_docs  = array_diff_key( $kb_docs, $site_docs );

		$score = SSC_Knowledge_Score::compute(
			array(
				'business'    => $business,
				'items'       => $items,
				'products'    => $products,
				'documents'   => count( $own_docs ),
				'site_pages'  => count( $site_docs ),
				'site_active' => SSC_Modules::is_active( 'sitesync' ),
				'knowledge'   => SSC_Setup::knowledge_ready(),
			)
		);
		$kb_state = isset( $_GET['kb'] ) ? sanitize_key( wp_unslash( $_GET['kb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$initial  = self::initial_panel( $kb_state );
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-knowledge.php';
	}
}
