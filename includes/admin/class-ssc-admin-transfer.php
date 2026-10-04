<?php
/**
 * Export & Import page: download everything as one file, restore it here or
 * on another site, review before anything changes, undo the last import.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfer page.
 */
class SSC_Admin_Transfer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nexachat-ai' ) );
		}
		add_action( 'admin_init', array( $this, 'handle_actions' ), 5 );
	}

	/**
	 * Section ids ticked in a form.
	 *
	 * @return string[]
	 */
	protected static function posted_sections() {
		$picked = isset( $_POST['sections'] ) ? (array) wp_unslash( $_POST['sections'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; filtered against the known ids below.
		return array_values( array_intersect( array_keys( SSC_Transfer::sections() ), array_map( 'sanitize_key', $picked ) ) );
	}

	/**
	 * A password field, untouched: passwords may hold any character.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected static function posted_password( $field ) {
		return isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; a password must not be altered.
	}

	/**
	 * Form actions.
	 */
	public function handle_actions() {
		// Export: build the file and stream it.
		if ( isset( $_POST['ssc_transfer_export'] ) && check_admin_referer( 'ssc_transfer_export' ) ) {
			$bundle = SSC_Transfer::build( self::posted_sections(), self::posted_password( 'export_password' ) );
			if ( is_wp_error( $bundle ) ) {
				set_transient( 'ssc_transfer_error_' . get_current_user_id(), $bundle->get_error_message(), 5 * MINUTE_IN_SECONDS );
				self::prg( array( 'transfer' => 'error' ) );
			}
			$json = SSC_Transfer::encode( $bundle );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . SSC_Transfer::filename() . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Length: ' . strlen( $json ) );
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download, not HTML.
			exit;
		}

		// Import, step 1: read the file and keep it for review.
		if ( isset( $_POST['ssc_transfer_upload'] ) && check_admin_referer( 'ssc_transfer_import' ) ) {
			$result = $this->read_upload();
			if ( is_wp_error( $result ) ) {
				set_transient( 'ssc_transfer_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS );
				self::prg( array( 'transfer' => 'error' ) );
			}
			if ( ! SSC_Transfer::stage( $result, get_current_user_id() ) ) {
				set_transient( 'ssc_transfer_error_' . get_current_user_id(), __( 'The file could not be held for review (the database refused it). Try a smaller file, for example without documents.', 'nexachat-ai' ), 5 * MINUTE_IN_SECONDS );
				self::prg( array( 'transfer' => 'error' ) );
			}
			self::prg( array( 'transfer' => 'review' ) );
		}

		// Import, step 2: apply what was ticked.
		if ( isset( $_POST['ssc_transfer_apply'] ) && check_admin_referer( 'ssc_transfer_apply' ) ) {
			$bundle = SSC_Transfer::staged( get_current_user_id() );
			if ( null === $bundle ) {
				set_transient( 'ssc_transfer_error_' . get_current_user_id(), __( 'The uploaded file is no longer available. Upload it again.', 'nexachat-ai' ), 5 * MINUTE_IN_SECONDS );
				self::prg( array( 'transfer' => 'error' ) );
			}
			$mode   = isset( $_POST['mode'] ) && 'replace' === sanitize_key( wp_unslash( $_POST['mode'] ) ) ? 'replace' : 'merge';
			$report = SSC_Transfer::apply( $bundle, self::posted_sections(), $mode, self::posted_password( 'import_password' ) );
			SSC_Transfer::unstage( get_current_user_id() );
			set_transient( 'ssc_transfer_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
			self::prg( array( 'transfer' => 'done' ) );
		}

		// Import: drop the staged file.
		if ( isset( $_POST['ssc_transfer_cancel'] ) && check_admin_referer( 'ssc_transfer_apply' ) ) {
			SSC_Transfer::unstage( get_current_user_id() );
			self::prg( array() );
		}

		// Undo the last import.
		if ( isset( $_POST['ssc_transfer_undo'] ) && check_admin_referer( 'ssc_transfer_undo' ) ) {
			self::prg( array( 'transfer' => SSC_Transfer::undo() ? 'undone' : 'noundo' ) );
		}
	}

	/**
	 * Read and validate the uploaded file.
	 *
	 * @return array|WP_Error Parsed bundle.
	 */
	protected function read_upload() {
		// The nonce was verified by the caller.
		if ( empty( $_FILES['transfer_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['transfer_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- upload validated here.
			return new WP_Error( 'ssc_transfer_nofile', __( 'Choose the export file first.', 'nexachat-ai' ) );
		}
		$file = $_FILES['transfer_file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- name is only read for its extension; the content is parsed as JSON.
		if ( 'json' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'ssc_transfer_ext', __( 'Choose a .json file created by the export.', 'nexachat-ai' ) );
		}
		if ( (int) $file['size'] > SSC_Transfer::MAX_BYTES ) {
			return new WP_Error( 'ssc_transfer_big', __( 'The file is too large to import (16 MB at most).', 'nexachat-ai' ) );
		}
		$json = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- validated upload.
		return SSC_Transfer::parse( $json );
	}

	/**
	 * PRG.
	 *
	 * @param array $args Args.
	 */
	protected static function prg( $args ) {
		$args['page'] = 'ssc-transfer';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render.
	 */
	public function render() {
		$state    = isset( $_GET['transfer'] ) ? sanitize_key( wp_unslash( $_GET['transfer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$user_id  = get_current_user_id();
		$error    = 'error' === $state ? (string) get_transient( 'ssc_transfer_error_' . $user_id ) : '';
		$report   = 'done' === $state ? get_transient( 'ssc_transfer_report_' . $user_id ) : null;
		$staged   = 'review' === $state ? SSC_Transfer::staged( $user_id ) : null;
		$summary  = is_array( $staged ) ? SSC_Transfer::summarize( $staged ) : null;
		$sections = SSC_Transfer::sections();
		$counts   = SSC_Transfer::live_counts();
		$backup   = SSC_Transfer::backup_time();
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-transfer.php';
	}
}
