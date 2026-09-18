<?php
/**
 * Business Knowledge page: business profile, knowledge items, product
 * catalog, document import (KB).
 *
 * @package SmartSupportChatbot
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
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_actions' ), 5 );
	}

	/**
	 * Form actions (all PRG).
	 */
	public function handle_actions() {
		if ( isset( $_POST['ssc_knowledge_save'] ) && check_admin_referer( 'ssc_knowledge' ) ) {
			// Business profile block.
			$business = array();
			foreach ( array( 'org_name', 'brand_name', 'category', 'industry', 'location', 'phone', 'email', 'support_phone', 'support_email', 'hours', 'assistant_name', 'assistant_role', 'tone', 'language' ) as $f ) {
				$business[ $f ] = isset( $_POST['business'][ $f ] ) ? sanitize_text_field( wp_unslash( $_POST['business'][ $f ] ) ) : '';
			}
			foreach ( array( 'description', 'products', 'differentiators' ) as $f ) {
				$business[ $f ] = isset( $_POST['business'][ $f ] ) ? wp_kses_post( wp_unslash( $_POST['business'][ $f ] ) ) : '';
			}
			$business['url'] = isset( $_POST['business']['url'] ) ? esc_url_raw( wp_unslash( $_POST['business']['url'] ) ) : '';

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
					$type   = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'general';
					$items[] = array(
						'id'      => isset( $item['id'] ) ? sanitize_key( $item['id'] ) : 'ki-' . uniqid(),
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
					if ( isset( $attributes[ $i ] ) && is_array( $attributes[ $i ] ) ) {
						foreach ( $attributes[ $i ] as $pair ) {
							if ( is_array( $pair ) && ! empty( $pair['key'] ) && ! empty( $pair['value'] ) ) {
								$entry['attributes'][ sanitize_key( $pair['key'] ) ] = sanitize_text_field( $pair['value'] );
							}
						}
					}
					$products[] = $entry;
				}
			}

			SSC_Settings::update(
				array(
					'business'        => SSC_Settings::sanitize_business( $business ),
					'knowledge_items' => $items,
					'products'        => $products,
				)
			);
			self::prg( array( 'saved' => 1 ) );
		}

		// Document import (URL).
		if ( isset( $_POST['ssc_kb_import_url'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$url    = esc_url_raw( wp_unslash( $_POST['kb_url'] ) );
			$result = $this->import_url( $url );
			self::prg( array( 'kb' => $result ? 'added' : 'failed' ) );
		}

		// Document import (file upload).
		if ( isset( $_POST['ssc_kb_import_file'] ) && check_admin_referer( 'ssc_kb' ) ) {
			$result = $this->import_file();
			self::prg( array( 'kb' => $result['status'], 'chunks' => $result['chunks'] ) );
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
	 * Import a URL into the KB (admin-initiated, SSRF-guarded).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	protected function import_url( $url ) {
		if ( '' === $url || ! SSC_HTTP::is_safe_url( $url, true ) ) {
			return false;
		}
		$response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'limit_response_size' => 2 * MB_IN_BYTES ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$html = (string) wp_remote_retrieve_body( $response );
		$title = '';
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
			$title = sanitize_text_field( trim( $m[1] ) );
		}
		$text = preg_replace( '/<(script|style|noscript|nav|header|footer)[^>]*>.*?<\/\1>/is', ' ', $html );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) wp_strip_all_tags( (string) $text ) ) );
		if ( mb_strlen( $text ) < 200 ) {
			return false;
		}
		$doc_id = 'url-' . substr( md5( $url ), 0, 12 );
		SSC_Schema::kb_delete_document( $doc_id );
		return SSC_Schema::kb_insert_document( $doc_id, '' !== $title ? $title : $url, $text ) > 0;
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
		if ( (int) $_FILES['kb_file']['size'] > 2 * MB_IN_BYTES ) {
			$out['status'] = 'toobig'; // Reported honestly (4.x bug fixed).
			return $out;
		}
		$allowed = array( 'txt', 'md', 'csv', 'json' );
		$ext     = strtolower( pathinfo( (string) $_FILES['kb_file']['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			$out['status'] = 'badtype';
			return $out;
		}
		$content = (string) file_get_contents( $_FILES['kb_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- validated upload with whitelisted extension.
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
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-knowledge.php';
	}
}
