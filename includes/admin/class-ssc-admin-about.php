<?php
/**
 * About page: who makes the plugin, technical details, a system check and a
 * copyable report for support requests.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * About page.
 */
class SSC_Admin_About {

	const MAKER       = 'DbsStudio';
	const MAKER_URL   = 'https://dbsstudio.ir';
	const SUPPORT_URL = 'https://dbsstudio.ir/issues';

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nexachat-ai' ) );
		}
	}

	/**
	 * Header fields of the main plugin file.
	 *
	 * @return array
	 */
	public static function plugin_headers() {
		return get_file_data(
			NEXACHATAI_MAIN_FILE,
			array(
				'version'      => 'Version',
				'requires_wp'  => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'license'      => 'License',
			),
			'plugin'
		);
	}

	/**
	 * Environment checks.
	 *
	 * @return array[] id, label, status (ok|warn|fail), detail.
	 */
	public static function checks() {
		global $wp_version;
		$headers = self::plugin_headers();
		$rows    = array();
		// Versions and sizes are left-to-right tokens: isolate them inside right-to-left sentences.
		$ltr     = function ( $text ) {
			return "\u{2066}" . $text . "\u{2069}";
		};
		$add     = function ( $id, $label, $status, $detail ) use ( &$rows ) {
			$rows[] = array(
				'id'     => $id,
				'label'  => $label,
				'status' => $status,
				'detail' => $detail,
			);
		};

		$add(
			'wp',
			__( 'WordPress version', 'nexachat-ai' ),
			version_compare( $wp_version, $headers['requires_wp'], '>=' ) ? 'ok' : 'fail',
			/* translators: 1: installed version, 2: required version. */
			sprintf( __( '%1$s (needs %2$s or newer)', 'nexachat-ai' ), $ltr( $wp_version ), $ltr( $headers['requires_wp'] ) )
		);
		$add(
			'php',
			__( 'PHP version', 'nexachat-ai' ),
			version_compare( PHP_VERSION, $headers['requires_php'], '>=' ) ? 'ok' : 'fail',
			/* translators: 1: installed version, 2: required version. */
			sprintf( __( '%1$s (needs %2$s or newer)', 'nexachat-ai' ), $ltr( PHP_VERSION ), $ltr( $headers['requires_php'] ) )
		);
		$add(
			'openssl',
			__( 'OpenSSL (encrypts API keys)', 'nexachat-ai' ),
			function_exists( 'openssl_encrypt' ) && defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ? 'ok' : 'fail',
			function_exists( 'openssl_encrypt' ) ? ( defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ? __( 'Available', 'nexachat-ai' ) : __( 'AUTH_KEY is missing from wp-config.php', 'nexachat-ai' ) ) : __( 'Not installed', 'nexachat-ai' )
		);
		$add(
			'mbstring',
			__( 'mbstring (Persian text handling)', 'nexachat-ai' ),
			function_exists( 'mb_substr' ) ? 'ok' : 'fail',
			function_exists( 'mb_substr' ) ? __( 'Available', 'nexachat-ai' ) : __( 'Not installed', 'nexachat-ai' )
		);
		$add(
			'zip',
			__( 'ZipArchive (Word .docx import)', 'nexachat-ai' ),
			class_exists( 'ZipArchive' ) ? 'ok' : 'warn',
			class_exists( 'ZipArchive' ) ? __( 'Available', 'nexachat-ai' ) : __( 'Not installed: Word files cannot be imported', 'nexachat-ai' )
		);
		$add(
			'zlib',
			__( 'zlib (PDF import, export review)', 'nexachat-ai' ),
			function_exists( 'gzuncompress' ) ? 'ok' : 'warn',
			function_exists( 'gzuncompress' ) ? __( 'Available', 'nexachat-ai' ) : __( 'Not installed: some PDF files cannot be read', 'nexachat-ai' )
		);
		$add(
			'https',
			__( 'Site address uses HTTPS', 'nexachat-ai' ),
			0 === strpos( home_url(), 'https://' ) ? 'ok' : 'warn',
			0 === strpos( home_url(), 'https://' ) ? __( 'Yes', 'nexachat-ai' ) : __( 'No: visitors’ messages travel unencrypted', 'nexachat-ai' )
		);
		$add(
			'cron',
			__( 'Background tasks (WP-Cron)', 'nexachat-ai' ),
			defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'warn' : 'ok',
			defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Disabled in wp-config.php: make sure a server cron calls wp-cron.php', 'nexachat-ai' ) : __( 'Enabled', 'nexachat-ai' )
		);

		$missing = array();
		foreach ( self::tables() as $table ) {
			if ( ! self::table_exists( $table ) ) {
				$missing[] = $table;
			}
		}
		$add(
			'tables',
			__( 'Database tables', 'nexachat-ai' ),
			$missing ? 'fail' : 'ok',
			$missing
				/* translators: %s: table names. */
				? sprintf( __( 'Missing: %s. Deactivate and activate the plugin to create them.', 'nexachat-ai' ), implode( ', ', $missing ) )
				/* translators: 1: number of tables, 2: schema version. */
				: sprintf( __( 'All %1$d tables present (schema version %2$s)', 'nexachat-ai' ), count( self::tables() ), $ltr( SSC_Schema::DB_VERSION ) )
		);

		$memory = (int) wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$add(
			'memory',
			__( 'PHP memory limit', 'nexachat-ai' ),
			( $memory < 0 || $memory >= 64 * MB_IN_BYTES ) ? 'ok' : 'warn',
			self::memory_label()
		);
		return $rows;
	}

	/**
	 * PHP memory limit for people ("-1" means unlimited).
	 *
	 * @return string
	 */
	protected static function memory_label() {
		$limit = (string) ini_get( 'memory_limit' );
		return '-1' === $limit ? __( 'Unlimited', 'nexachat-ai' ) : $limit;
	}

	/**
	 * Tables the plugin needs in every installation.
	 *
	 * @return string[]
	 */
	public static function tables() {
		return array(
			SSC_Schema::table_name(),
			SSC_Schema::chatlog_table_name(),
			SSC_Schema::qa_table_name(),
			SSC_Schema::kb_table_name(),
			SSC_Schema::stats_table_name(),
			SSC_Schema::audit_table_name(),
		);
	}

	/**
	 * Does a table exist?
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	protected static function table_exists( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- diagnostics read.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Plain-text report to paste into a support request. Holds no secrets and
	 * no visitor data.
	 *
	 * @return string
	 */
	public static function report() {
		global $wp_version;
		$headers  = self::plugin_headers();
		$provider = SSC_Providers::current();
		$lines    = array(
			'NexaChatAI ' . $headers['version'] . ' by ' . self::MAKER . ' (' . self::MAKER_URL . ')',
			'Site: ' . home_url(),
			'WordPress: ' . $wp_version . ( is_multisite() ? ' (multisite)' : '' ) . ', locale ' . get_locale(),
			'PHP: ' . PHP_VERSION . ', memory ' . self::memory_label(),
			'Server: ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'n/a' ),
			'Database schema: ' . get_option( SSC_Schema::DB_VERSION_OPTION, '?' ),
			'Published: ' . ( SSC_Setup::is_live() ? 'yes' : 'no' ),
			'AI provider: ' . ( $provider ? $provider->id() : 'none' ),
			'Active modules: ' . ( SSC_Modules::active_ids() ? implode( ', ', SSC_Modules::active_ids() ) : 'none' ),
			'Active plugins: ' . count( (array) get_option( 'active_plugins', array() ) ),
		);
		foreach ( self::checks() as $row ) {
			// The report is plain text for a support ticket: drop the bidi isolates used on screen.
			$lines[] = strtoupper( $row['status'] ) . ' ' . $row['label'] . ': ' . str_replace( array( "\u{2066}", "\u{2069}" ), '', $row['detail'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Bundled release notes, available without a network request or repository access.
	 *
	 * Each note carries its kind (the word before the first colon: Fixed, New,
	 * Changed, Security, Privacy, Docs) so the screen can label it.
	 *
	 * @return array[] Version and notes (type + plain text).
	 */
	public static function changelog() {
		$file = SSC_CHATBOT_DIR . 'readme.txt';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$text = file_get_contents( $file );
		if ( false === $text ) {
			return array();
		}
		$parts = explode( '== Changelog ==', $text, 2 );
		if ( count( $parts ) < 2 ) {
			return array();
		}
		$section = preg_split( '/^== /m', $parts[1], 2 );
		$entries = array();
		if ( preg_match_all( '/^= ([^=\r\n]+) =\s*\r?\n(.*?)(?=^= |\z)/ms', $section[0], $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$lines = array_filter( array_map( 'trim', preg_split( '/\R/', trim( $match[2] ) ) ), 'strlen' );
				$notes = array();
				foreach ( $lines as $line ) {
					$line = preg_replace( '/^\*\s+/', '', $line );
					$type = '';
					if ( preg_match( '/^(Fixed|New|Changed|Security|Privacy|Docs|Removed|Improved):\s+(.*)$/s', $line, $kind ) ) {
						$type = strtolower( $kind[1] );
						$line = $kind[2];
					}
					$notes[] = array(
						'type' => $type,
						'text' => ucfirst( $line ),
					);
				}
				$entries[] = array(
					'version' => trim( $match[1] ),
					'notes'   => $notes,
				);
			}
		}
		return $entries;
	}

	/**
	 * Label of a note kind.
	 *
	 * @param string $type Kind from changelog().
	 * @return string
	 */
	public static function note_label( $type ) {
		$labels = array(
			'fixed'    => __( 'Fixed', 'nexachat-ai' ),
			'new'      => __( 'New', 'nexachat-ai' ),
			'changed'  => __( 'Changed', 'nexachat-ai' ),
			'improved' => __( 'Improved', 'nexachat-ai' ),
			'removed'  => __( 'Removed', 'nexachat-ai' ),
			'security' => __( 'Security', 'nexachat-ai' ),
			'privacy'  => __( 'Privacy', 'nexachat-ai' ),
			'docs'     => __( 'Docs', 'nexachat-ai' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'Note', 'nexachat-ai' );
	}

	/**
	 * Escaped note text; `code` spans become <code>.
	 *
	 * @param string $text Plain note.
	 * @return string Safe HTML.
	 */
	public static function note_html( $text ) {
		return preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( (string) $text ) );
	}

	/**
	 * Render.
	 */
	public function render() {
		global $wpdb;
		$prefix    = $wpdb->prefix . 'ssc_chatbot_';
		$headers   = self::plugin_headers();
		$checks    = self::checks();
		$passed    = count(
			array_filter(
				$checks,
				function ( $row ) {
					return 'ok' === $row['status'];
				}
			)
		);
		$report    = self::report();
		$providers = SSC_Providers::all();
		$modules   = SSC_Modules::all();
		$active    = SSC_Modules::active_ids();
		$changelog = self::changelog();
		require SSC_CHATBOT_DIR . 'includes/admin/views/page-about.php';
	}
}
