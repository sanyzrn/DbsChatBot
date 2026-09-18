<?php
/**
 * FAQ module: curated offline answer bank management, CSV/JSON import,
 * suggestion endpoints.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ module.
 */
class SSC_Module_Faq extends SSC_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'faq';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'FAQ Answer Bank', 'smart-support-chatbot' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Curated question/answer pairs with smart Persian/English matching. Works even when the AI engine is unavailable or set to bank-only mode.', 'smart-support-chatbot' );
	}

	/**
	 * Benefit.
	 *
	 * @return string
	 */
	public function benefit() {
		return __( 'Instant, zero-cost, perfectly controlled answers for your most common questions.', 'smart-support-chatbot' );
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
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
	}

	/**
	 * Admin hooks.
	 */
	public function register_admin() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		if ( ! SSC_Modules::is_active( 'faq' ) ) {
			return;
		}
		add_submenu_page(
			'ssc-dashboard',
			__( 'FAQ Bank', 'smart-support-chatbot' ),
			__( 'FAQ Bank', 'smart-support-chatbot' ),
			'manage_options',
			'ssc-faq',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Form actions (add / bulk import / delete) - PRG pattern.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Single add.
		if ( isset( $_POST['ssc_faq_add'] ) && check_admin_referer( 'ssc_faq' ) ) {
			$question = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
			$answer   = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';
			$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
			if ( '' !== $question && '' !== $answer ) {
				SSC_Schema::qa_insert(
					array(
						'question'   => $question,
						'answer'     => $answer,
						'keywords'   => $keywords,
						'product_id' => 'general',
					)
				);
				self::redirect( array( 'added' => 1 ) );
			}
			self::redirect( array( 'error' => 'empty' ) );
		}

		// Delete (GET row action).
		if ( isset( $_GET['ssc_faq_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			$id = (int) $_GET['id'];
			if ( 'delete' === sanitize_key( wp_unslash( $_GET['ssc_faq_action'] ) ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ssc_faq_' . $id ) ) {
				SSC_Schema::qa_delete( array( $id ) );
				self::redirect( array( 'deleted' => 1 ) );
			}
		}

		// CSV import.
		if ( isset( $_POST['ssc_faq_import'] ) && check_admin_referer( 'ssc_faq' ) ) {
			$result = $this->import_file();
			self::redirect( array( 'imported' => $result['inserted'], 'skipped' => $result['skipped'], 'error' => $result['error'] ) );
		}

		// Export.
		if ( isset( $_POST['ssc_faq_export'] ) && check_admin_referer( 'ssc_faq' ) ) {
			$this->export_csv();
		}
	}

	/**
	 * PRG redirect helper.
	 *
	 * @param array $args Query args.
	 */
	protected static function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=ssc-faq' ) ) );
		exit;
	}

	/**
	 * Import a CSV/JSON upload (validated, size-capped, errors REPORTED - a
	 * 4.x bug silently reported oversized files as success).
	 *
	 * @return array inserted/skipped/error.
	 */
	protected function import_file() {
		$out = array( 'inserted' => 0, 'skipped' => 0, 'error' => '' );
		if ( empty( $_FILES['faq_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['faq_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- is_uploaded_file check; content parsed below.
			$out['error'] = 'nofile';
			return $out;
		}
		$size = (int) $_FILES['faq_file']['size'];
		if ( $size > 2 * MB_IN_BYTES ) {
			$out['error'] = 'toobig';
			return $out;
		}
		$content = (string) file_get_contents( $_FILES['faq_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- validated upload.
		$rows    = array();
		if ( false !== strpos( $content, '{"' ) && null !== json_decode( $content, true ) ) {
			$decoded = json_decode( $content, true );
			foreach ( (array) $decoded as $row ) {
				if ( is_array( $row ) && isset( $row['question'], $row['answer'] ) ) {
					$rows[] = array(
						'question' => (string) $row['question'],
						'answer'   => (string) $row['answer'],
						'keywords' => isset( $row['keywords'] ) ? (string) $row['keywords'] : '',
					);
				} else {
					++$out['skipped'];
				}
			}
		} else {
			$lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
			if ( is_array( $lines ) && count( $lines ) > 1 ) {
				$header = str_getcsv( array_shift( $lines ) );
				$map    = array_flip( array_map( 'trim', array_map( 'strtolower', $header ) ) );
				foreach ( $lines as $line ) {
					if ( '' === trim( $line ) ) {
						continue;
					}
					$cells = str_getcsv( $line );
					$q     = isset( $map['question'] ) && isset( $cells[ $map['question'] ] ) ? $cells[ $map['question'] ] : ( isset( $cells[0] ) ? $cells[0] : '' );
					$a     = isset( $map['answer'] ) && isset( $cells[ $map['answer'] ] ) ? $cells[ $map['answer'] ] : ( isset( $cells[1] ) ? $cells[1] : '' );
					$k     = isset( $map['keywords'] ) && isset( $cells[ $map['keywords'] ] ) ? $cells[ $map['keywords'] ] : '';
					if ( '' !== trim( $q ) && '' !== trim( $a ) ) {
						$rows[] = array( 'question' => $q, 'answer' => $a, 'keywords' => $k );
					} else {
						++$out['skipped'];
					}
				}
			}
		}
		$replace = isset( $_POST['import_mode'] ) && 'replace' === sanitize_key( wp_unslash( $_POST['import_mode'] ) );
		$out['inserted'] = SSC_Schema::qa_import( $rows, $replace );
		return $out;
	}

	/**
	 * CSV export.
	 */
	protected function export_csv() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ssc-faq-' . gmdate( 'Ymd-Hi' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		SSC_Input::write_csv( $out, array( 'question', 'keywords', 'answer', 'usage_count' ) );
		$candidates = SSC_Schema::qa_candidates( 'general' );
		foreach ( $candidates as $row ) {
			SSC_Input::write_csv( $out, array( $row['question'], $row['keywords'], $row['answer'], '' ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Render the FAQ bank page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'smart-support-chatbot' ) );
		}
		// Batched listing (no giant single form - 4.x fragility removed).
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per    = 30;
		$all    = SSC_Schema::qa_candidates( 'general' );
		$total  = count( $all );
		$rows   = array_slice( array_reverse( $all ), ( $page - 1 ) * $per, $per );
		$pages  = (int) ceil( $total / $per );
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-faq.php';
	}
}
