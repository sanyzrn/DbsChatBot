<?php
/**
 * Export and import of everything an administrator configured: settings,
 * business profile, modules, products, knowledge entries, FAQ bank, imported
 * documents and (optionally) API keys.
 *
 * Contract:
 * - One UTF-8 JSON file, no serialized PHP: importing a file can never run code.
 * - Nothing in a file is trusted. Every value goes back through the same
 *   schema-driven sanitizers the admin pages use; unknown keys are dropped.
 * - API keys travel only inside a block encrypted with a password chosen at
 *   export time (PBKDF2 + AES-256-CBC + HMAC). They are bound to this site's
 *   keys at rest, so a plain copy would be unreadable elsewhere anyway.
 * - Visitor data (conversations, requests, safety reports, statistics) is
 *   never exported.
 * - Before an import changes anything, the current state is snapshotted so the
 *   last import can be undone.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfer class.
 */
class SSC_Transfer {

	const FORMAT        = 'nexachat-ai-export';
	const VERSION       = 1;
	const MAX_BYTES     = 16777216; // 16 MB: the file, its decoded copy and the staged copy are all held in memory.
	const KDF_ROUNDS    = 150000;
	const MIN_PASSWORD  = 8;
	const BACKUP_OPTION = 'ssc_import_backup';

	/**
	 * Sections a file can hold, in display order.
	 *
	 * @return array<string, array{label: string, hint: string, icon: string}>
	 */
	public static function sections() {
		return array(
			'business'  => array(
				'label' => __( 'Business profile', 'nexachat-ai' ),
				'hint'  => __( 'Name, contacts, description and tone.', 'nexachat-ai' ),
				'icon'  => 'building',
			),
			'settings'  => array(
				'label' => __( 'Settings & appearance', 'nexachat-ai' ),
				'hint'  => __( 'Look, behaviour, privacy, limits and module options.', 'nexachat-ai' ),
				'icon'  => 'sliders',
			),
			'modules'   => array(
				'label' => __( 'Active modules', 'nexachat-ai' ),
				'hint'  => __( 'Which optional features are switched on.', 'nexachat-ai' ),
				'icon'  => 'puzzle',
			),
			'products'  => array(
				'label' => __( 'Products & services', 'nexachat-ai' ),
				'hint'  => __( 'Every product with its summary and specifications.', 'nexachat-ai' ),
				'icon'  => 'box',
			),
			'knowledge' => array(
				'label' => __( 'Knowledge entries', 'nexachat-ai' ),
				'hint'  => __( 'Policies, prices and other facts.', 'nexachat-ai' ),
				'icon'  => 'book',
			),
			'faq'       => array(
				'label' => __( 'FAQ answer bank', 'nexachat-ai' ),
				'hint'  => __( 'Question and answer pairs.', 'nexachat-ai' ),
				'icon'  => 'chat',
			),
			'documents' => array(
				'label' => __( 'Documents', 'nexachat-ai' ),
				'hint'  => __( 'Uploaded files and imported pages. Website pages are re-learned automatically.', 'nexachat-ai' ),
				'icon'  => 'file',
			),
			'secrets'   => array(
				'label' => __( 'API keys & tokens', 'nexachat-ai' ),
				'hint'  => __( 'AI provider keys, bot token, SMS key. Protected with a password.', 'nexachat-ai' ),
				'icon'  => 'key',
			),
		);
	}

	/**
	 * Settings that stay with the site: they are not part of the "settings"
	 * section. Publication is a decision to make on the receiving site,
	 * user and post ids mean nothing elsewhere, and the rest has sections of
	 * its own.
	 *
	 * @return string[]
	 */
	public static function site_bound_settings() {
		return array_merge(
			array( 'enabled', 'privacy_acknowledged', 'live_operators', 'sitesync_exclude', 'business', 'trusted_proxy_header', 'trusted_proxies' ),
			array_keys( SSC_Settings::SPLIT_OPTIONS ),
			SSC_Settings::secret_fields()
		);
	}

	/**
	 * Settings that decide where information is sent: the AI endpoint that
	 * receives the stored API key, and who is notified about requests and
	 * safety reports. A file the administrator did not make must not be able
	 * to redirect them while the site's own keys stay in place, so they are
	 * imported only together with the file's password-protected keys (which
	 * prove the file is the administrator's own).
	 *
	 * @return string[]
	 */
	public static function destination_settings() {
		return array( 'custom_endpoint', 'ai_webhook_url', 'notify_platform', 'notify_chat_id', 'notify_email_to', 'sms_admin_phone' );
	}

	/*
	 * --------------------------------------------------------------
	 * Export
	 * --------------------------------------------------------------
	 */

	/**
	 * Counts shown next to each section on the export screen.
	 *
	 * @return array<string, int|null> section => count (null = not a list).
	 */
	public static function live_counts() {
		$documents = SSC_Schema::kb_export_documents();
		$secrets   = 0;
		foreach ( SSC_Settings::secret_fields() as $key ) {
			if ( SSC_Settings::has_secret( $key ) ) {
				++$secrets;
			}
		}
		return array(
			'business'  => null,
			'settings'  => null,
			'modules'   => count( SSC_Modules::active_ids() ),
			'products'  => count( (array) SSC_Settings::get( 'products', array() ) ),
			'knowledge' => count( (array) SSC_Settings::get( 'knowledge_items', array() ) ),
			'faq'       => SSC_Schema::qa_count(),
			'documents' => count( $documents ),
			'secrets'   => $secrets,
		);
	}

	/**
	 * Build the export bundle.
	 *
	 * @param string[] $wanted     Section ids.
	 * @param string   $passphrase Password for the secrets block.
	 * @return array|WP_Error
	 */
	public static function build( $wanted, $passphrase = '' ) {
		$known  = array_keys( self::sections() );
		$wanted = array_values( array_intersect( $known, array_map( 'strval', (array) $wanted ) ) );
		if ( ! $wanted ) {
			return new WP_Error( 'ssc_transfer_empty', __( 'Choose at least one thing to export.', 'nexachat-ai' ) );
		}
		if ( in_array( 'secrets', $wanted, true ) && self::MIN_PASSWORD > strlen( (string) $passphrase ) ) {
			return new WP_Error(
				'ssc_transfer_password',
				/* translators: %d: minimum password length. */
				sprintf( __( 'Choose a password of at least %d characters to protect the API keys.', 'nexachat-ai' ), self::MIN_PASSWORD )
			);
		}

		$settings = SSC_Settings::all();
		$bundle   = array(
			'format'         => self::FORMAT,
			'format_version' => self::VERSION,
			'plugin_version' => SSC_CHATBOT_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site'           => home_url(),
			'site_name'      => get_bloginfo( 'name' ),
			'language'       => get_locale(),
			'sections'       => $wanted,
		);

		if ( in_array( 'business', $wanted, true ) ) {
			$bundle['business'] = SSC_Settings::business();
		}
		if ( in_array( 'settings', $wanted, true ) ) {
			$skip = self::site_bound_settings();
			$out  = array();
			foreach ( array_keys( SSC_Settings::defaults() ) as $key ) {
				if ( ! in_array( $key, $skip, true ) && array_key_exists( $key, $settings ) ) {
					$out[ $key ] = $settings[ $key ];
				}
			}
			$bundle['settings'] = $out;
		}
		if ( in_array( 'modules', $wanted, true ) ) {
			$bundle['modules'] = SSC_Modules::active_ids();
		}
		if ( in_array( 'products', $wanted, true ) ) {
			$bundle['products'] = array_values( (array) $settings['products'] );
		}
		if ( in_array( 'knowledge', $wanted, true ) ) {
			$bundle['knowledge'] = array_values( (array) $settings['knowledge_items'] );
		}
		if ( in_array( 'faq', $wanted, true ) ) {
			$faq = array();
			foreach ( SSC_Schema::qa_all() as $row ) {
				$faq[] = array(
					'question'   => (string) $row['question'],
					'keywords'   => (string) $row['keywords'],
					'answer'     => (string) $row['answer'],
					'product_id' => (string) $row['product_id'],
				);
			}
			$bundle['faq'] = $faq;
		}
		if ( in_array( 'documents', $wanted, true ) ) {
			$bundle['documents'] = SSC_Schema::kb_export_documents();
		}
		if ( in_array( 'secrets', $wanted, true ) ) {
			$plain = array();
			foreach ( SSC_Settings::secret_fields() as $key ) {
				$value = SSC_Settings::get_secret( $key );
				if ( '' !== $value ) {
					$plain[ $key ] = $value;
				}
			}
			$sealed = self::seal( $plain, (string) $passphrase );
			if ( false === $sealed ) {
				return new WP_Error( 'ssc_transfer_crypto', __( 'The API keys could not be encrypted on this server (OpenSSL is required). Export without them.', 'nexachat-ai' ) );
			}
			$bundle['secret_names'] = array_keys( $plain );
			$bundle['secrets']      = $sealed;
		}
		return $bundle;
	}

	/**
	 * Encode a bundle as readable JSON.
	 *
	 * @param array $bundle Bundle.
	 * @return string
	 */
	public static function encode( $bundle ) {
		return (string) wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * File name for a download.
	 *
	 * @return string
	 */
	public static function filename() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $host ) ), '-' );
		return 'nexachat-ai-' . ( '' !== $host ? $host . '-' : '' ) . gmdate( 'Ymd-His' ) . '.json';
	}

	/*
	 * --------------------------------------------------------------
	 * Password-protected secrets
	 * --------------------------------------------------------------
	 */

	/**
	 * Encrypt the secrets map with a password.
	 *
	 * @param array  $secrets    key => value.
	 * @param string $passphrase Password.
	 * @return array|false Sealed block, or false when encryption is unavailable.
	 */
	public static function seal( $secrets, $passphrase ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'hash_pbkdf2' ) || self::MIN_PASSWORD > strlen( (string) $passphrase ) ) {
			return false;
		}
		$salt = openssl_random_pseudo_bytes( 16 );
		$iv   = openssl_random_pseudo_bytes( 16 );
		$keys = hash_pbkdf2( 'sha256', (string) $passphrase, $salt, self::KDF_ROUNDS, 64, true );
		$data = openssl_encrypt( (string) wp_json_encode( $secrets ), 'aes-256-cbc', substr( $keys, 0, 32 ), OPENSSL_RAW_DATA, $iv );
		if ( false === $data ) {
			return false;
		}
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe transport inside JSON.
		return array(
			'kdf'    => 'pbkdf2-sha256',
			'rounds' => self::KDF_ROUNDS,
			'salt'   => base64_encode( $salt ),
			'iv'     => base64_encode( $iv ),
			'data'   => base64_encode( $data ),
			'mac'    => base64_encode( hash_hmac( 'sha256', $iv . $data, substr( $keys, 32, 32 ), true ) ),
		);
		// phpcs:enable
	}

	/**
	 * Decrypt a sealed block.
	 *
	 * @param array  $sealed     Block from a file.
	 * @param string $passphrase Password.
	 * @return array|false Secrets map; false when the password is wrong or the block is damaged.
	 */
	public static function unseal( $sealed, $passphrase ) {
		if ( ! is_array( $sealed ) || ! function_exists( 'openssl_decrypt' ) || ! function_exists( 'hash_pbkdf2' ) || 'pbkdf2-sha256' !== ( isset( $sealed['kdf'] ) ? $sealed['kdf'] : '' ) ) {
			return false;
		}
		$rounds = isset( $sealed['rounds'] ) ? (int) $sealed['rounds'] : 0;
		if ( $rounds < 10000 || $rounds > 1000000 ) {
			return false;
		}
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary-safe transport inside JSON.
		$salt = base64_decode( isset( $sealed['salt'] ) ? (string) $sealed['salt'] : '', true );
		$iv   = base64_decode( isset( $sealed['iv'] ) ? (string) $sealed['iv'] : '', true );
		$data = base64_decode( isset( $sealed['data'] ) ? (string) $sealed['data'] : '', true );
		$mac  = base64_decode( isset( $sealed['mac'] ) ? (string) $sealed['mac'] : '', true );
		// phpcs:enable
		if ( false === $salt || false === $iv || false === $data || false === $mac || 16 !== strlen( $iv ) || '' === $data ) {
			return false;
		}
		$keys = hash_pbkdf2( 'sha256', (string) $passphrase, $salt, $rounds, 64, true );
		if ( ! hash_equals( hash_hmac( 'sha256', $iv . $data, substr( $keys, 32, 32 ), true ), $mac ) ) {
			return false;
		}
		$json = openssl_decrypt( $data, 'aes-256-cbc', substr( $keys, 0, 32 ), OPENSSL_RAW_DATA, $iv );
		$map  = false === $json ? null : json_decode( $json, true );
		return is_array( $map ) ? $map : false;
	}

	/*
	 * --------------------------------------------------------------
	 * Reading a file
	 * --------------------------------------------------------------
	 */

	/**
	 * Parse and validate an uploaded file's text. Structure only: the values
	 * are sanitized when they are applied.
	 *
	 * @param string $json File contents.
	 * @return array|WP_Error
	 */
	public static function parse( $json ) {
		$json = (string) $json;
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'ssc_transfer_big', __( 'The file is too large to import (16 MB at most).', 'nexachat-ai' ) );
		}
		// A byte-order mark from a Windows editor would break the decoder.
		if ( 0 === strpos( $json, "\xEF\xBB\xBF" ) ) {
			$json = substr( $json, 3 );
		}
		$bundle = json_decode( $json, true, 48 );
		if ( ! is_array( $bundle ) ) {
			return new WP_Error( 'ssc_transfer_json', __( 'This is not a valid export file: it could not be read as JSON.', 'nexachat-ai' ) );
		}
		if ( ! isset( $bundle['format'] ) || self::FORMAT !== $bundle['format'] ) {
			return new WP_Error( 'ssc_transfer_format', __( 'This file was not created by NexaChatAI export.', 'nexachat-ai' ) );
		}
		if ( ! isset( $bundle['format_version'] ) || (int) $bundle['format_version'] > self::VERSION ) {
			return new WP_Error( 'ssc_transfer_version', __( 'This file comes from a newer version of NexaChatAI. Update the plugin and try again.', 'nexachat-ai' ) );
		}
		$shape = self::shape_problem( $bundle );
		if ( null !== $shape && 'settings' === $shape['section'] ) {
			return new WP_Error(
				'ssc_transfer_shape',
				sprintf(
					/* translators: %s: setting name. */
					__( 'This file cannot be imported: the setting “%s” holds a value of the wrong kind (a list where text belongs, or the other way round). Nothing was changed.', 'nexachat-ai' ),
					$shape['field']
				)
			);
		}
		if ( null !== $shape ) {
			return new WP_Error(
				'ssc_transfer_shape',
				sprintf(
					/* translators: 1: section name, 2: row number, 3: field name. */
					__( 'This file cannot be imported: %1$s, row %2$d has an invalid value in the field “%3$s”. Nothing was changed.', 'nexachat-ai' ),
					$shape['section'],
					$shape['row'],
					$shape['field']
				)
			);
		}
		return $bundle;
	}

	/**
	 * A row of the file that holds a list, an object or null where text belongs
	 * (a hand-edited or damaged file). Such a file is refused whole, with the
	 * place named, instead of being half imported.
	 *
	 * @param array $bundle Parsed bundle.
	 * @return array|null section, row (from 1), field; null when every row is well formed.
	 */
	protected static function shape_problem( $bundle ) {
		$checks = array(
			'products'  => array( 'attributes' ),
			'knowledge' => array(),
			'faq'       => array(),
			'documents' => array( 'chunks' ),
		);
		foreach ( $checks as $section => $arrays ) {
			if ( ! isset( $bundle[ $section ] ) || ! is_array( $bundle[ $section ] ) ) {
				continue;
			}
			$row_no = 0;
			foreach ( $bundle[ $section ] as $row ) {
				++$row_no;
				$field = SSC_Input::bad_field( $row, $arrays );
				// A document without an id or without text is not a document: refused, not skipped in silence.
				if ( '' === $field && 'documents' === $section ) {
					if ( '' === trim( SSC_Input::scalar( isset( $row['doc_id'] ) ? $row['doc_id'] : '' ) ) ) {
						$field = 'doc_id';
					} elseif ( empty( $row['chunks'] ) || ! is_array( $row['chunks'] ) ) {
						$field = 'chunks';
					}
				}
				if ( '' !== $field ) {
					return array(
						'section' => $section,
						'row'     => $row_no,
						'field'   => '*' === $field ? '(row)' : preg_replace( '/[^A-Za-z0-9_\-]/', '', $field ),
					);
				}
			}
		}
		if ( isset( $bundle['business'] ) && is_array( $bundle['business'] ) ) {
			$field = SSC_Input::bad_field( $bundle['business'], array(), array( 'locations' ) );
			if ( '' !== $field ) {
				return array(
					'section' => 'business',
					'row'     => 1,
					'field'   => preg_replace( '/[^A-Za-z0-9_\-]/', '', $field ),
				);
			}
		}
		if ( array_key_exists( 'settings', $bundle ) && null !== $bundle['settings'] ) {
			$field = self::settings_problem( $bundle['settings'] );
			if ( '' !== $field ) {
				return array(
					'section' => 'settings',
					'row'     => 0,
					'field'   => $field,
				);
			}
		}
		return null;
	}

	/**
	 * The first setting of the file whose value is not the kind of value that
	 * setting holds: a list where text belongs, text where a list belongs, a
	 * word where a number belongs, and the same one level down inside the lists
	 * of rows (a form field whose label is a list). Only settings that would be
	 * applied are looked at; unknown and site-bound ones are ignored as before.
	 *
	 * @param mixed $values The settings section.
	 * @return string Where it is ("welcome_text", "form_fields[0].label"), '(settings)' when the section is not an object, '' when all is well.
	 */
	protected static function settings_problem( $values ) {
		if ( ! is_array( $values ) ) {
			return '(settings)';
		}
		$skip   = self::site_bound_settings();
		$known  = SSC_Settings::defaults();
		$nested = self::nested_settings();
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( ! array_key_exists( $key, $known ) || in_array( $key, $skip, true ) ) {
				continue;
			}
			$default = $known[ $key ];
			if ( is_array( $default ) ) {
				$where = isset( $nested[ $key ] ) ? self::nested_problem( $value, $nested[ $key ], $key ) : ( is_array( $value ) ? '' : $key );
			} elseif ( is_int( $default ) || is_float( $default ) ) {
				$where = ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( trim( $value ) ) ) ) ? '' : $key;
			} elseif ( is_bool( $default ) ) {
				$where = ( is_bool( $value ) || is_int( $value ) || is_string( $value ) ) ? '' : $key;
			} else {
				$where = ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ? '' : $key;
			}
			if ( '' !== $where ) {
				return preg_replace( '/[^A-Za-z0-9_\-\[\].]/', '', $where );
			}
		}
		return '';
	}

	/**
	 * What the list-valued settings hold inside: "s" a plain value, "l" a list
	 * of plain values, "sl" either, array( 'list' => columns ) a list of rows,
	 * array( 'row' => columns ) one row, array( 'map' => spec ) rows by name.
	 * A column that is not named holds a plain value.
	 *
	 * @return array
	 */
	protected static function nested_settings() {
		$choices = array( 'list' => array( 'options' => 'sl' ) );
		return array(
			'form_fields'    => $choices,
			'sitesync_types' => 'l',
			'adr_form'       => array(
				'row' => array(
					'fields' => array( 'map' => array( 'row' => array() ) ),
					'custom' => $choices,
				),
			),
		);
	}

	/**
	 * Check a value against a nested_settings() shape.
	 *
	 * @param mixed        $value Value from the file.
	 * @param string|array $spec  Shape.
	 * @param string       $path  Where the value is, for the message.
	 * @return string The path of the first value that does not fit, '' when all fit.
	 */
	protected static function nested_problem( $value, $spec, $path ) {
		if ( 's' === $spec ) {
			return is_scalar( $value ) ? '' : $path;
		}
		if ( 'sl' === $spec && is_scalar( $value ) ) {
			return '';
		}
		if ( 'l' === $spec || 'sl' === $spec ) {
			if ( ! is_array( $value ) ) {
				return $path;
			}
			foreach ( $value as $i => $leaf ) {
				if ( ! is_scalar( $leaf ) ) {
					return $path . '[' . $i . ']';
				}
			}
			return '';
		}
		if ( ! is_array( $value ) ) {
			return $path;
		}
		if ( isset( $spec['list'] ) || isset( $spec['map'] ) ) {
			$inner = isset( $spec['list'] ) ? array( 'row' => $spec['list'] ) : $spec['map'];
			foreach ( $value as $i => $item ) {
				$where = self::nested_problem( $item, $inner, isset( $spec['list'] ) ? $path . '[' . $i . ']' : $path . '.' . $i );
				if ( '' !== $where ) {
					return $where;
				}
			}
			return '';
		}
		foreach ( $value as $column => $cell ) {
			$where = self::nested_problem( $cell, isset( $spec['row'][ $column ] ) ? $spec['row'][ $column ] : 's', $path . '.' . $column );
			if ( '' !== $where ) {
				return $where;
			}
		}
		return '';
	}

	/**
	 * What a parsed file holds, for the review screen.
	 *
	 * @param array $bundle Parsed bundle.
	 * @return array{meta: array, counts: array<string, int|null>}
	 */
	public static function summarize( $bundle ) {
		$counts = array();
		foreach ( array_keys( self::sections() ) as $id ) {
			$counts[ $id ] = null;
		}
		$present = array();
		foreach ( array_keys( self::sections() ) as $id ) {
			if ( isset( $bundle[ $id ] ) && is_array( $bundle[ $id ] ) ) {
				$present[ $id ] = true;
			}
		}
		$counts['modules']   = isset( $present['modules'] ) ? count( $bundle['modules'] ) : null;
		$counts['products']  = isset( $present['products'] ) ? count( $bundle['products'] ) : null;
		$counts['knowledge'] = isset( $present['knowledge'] ) ? count( $bundle['knowledge'] ) : null;
		$counts['faq']       = isset( $present['faq'] ) ? count( $bundle['faq'] ) : null;
		$counts['documents'] = isset( $present['documents'] ) ? count( $bundle['documents'] ) : null;
		$counts['secrets']   = isset( $present['secrets'] ) ? ( isset( $bundle['secret_names'] ) && is_array( $bundle['secret_names'] ) ? count( $bundle['secret_names'] ) : 0 ) : null;
		return array(
			'meta'    => array(
				'site'      => isset( $bundle['site'] ) ? esc_url_raw( (string) $bundle['site'] ) : '',
				'site_name' => isset( $bundle['site_name'] ) ? sanitize_text_field( (string) $bundle['site_name'] ) : '',
				'version'   => isset( $bundle['plugin_version'] ) ? sanitize_text_field( (string) $bundle['plugin_version'] ) : '',
				'exported'  => isset( $bundle['exported_at'] ) ? sanitize_text_field( (string) $bundle['exported_at'] ) : '',
				'language'  => isset( $bundle['language'] ) ? sanitize_text_field( (string) $bundle['language'] ) : '',
			),
			'present' => array_keys( $present ),
			'counts'  => $counts,
		);
	}

	/*
	 * --------------------------------------------------------------
	 * Review step: the parsed file waits server-side between the upload
	 * and the confirmation (compressed; secrets stay encrypted).
	 * --------------------------------------------------------------
	 */

	/**
	 * Keep a parsed bundle for the review screen.
	 *
	 * @param array $bundle  Bundle.
	 * @param int   $user_id User.
	 * @return bool
	 */
	public static function stage( $bundle, $user_id ) {
		$raw = (string) wp_json_encode( $bundle );
		$raw = function_exists( 'gzcompress' ) ? 'z:' . base64_encode( (string) gzcompress( $raw, 6 ) ) : 'r:' . base64_encode( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- opaque storage of a compressed blob.
		return (bool) set_transient( 'ssc_import_' . (int) $user_id, $raw, 30 * MINUTE_IN_SECONDS );
	}

	/**
	 * The staged bundle, if still there.
	 *
	 * @param int $user_id User.
	 * @return array|null
	 */
	public static function staged( $user_id ) {
		$raw = get_transient( 'ssc_import_' . (int) $user_id );
		if ( ! is_string( $raw ) || strlen( $raw ) < 3 ) {
			return null;
		}
		$body = base64_decode( substr( $raw, 2 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- opaque storage of a compressed blob.
		if ( false === $body ) {
			return null;
		}
		if ( 'z:' === substr( $raw, 0, 2 ) ) {
			$body = function_exists( 'gzuncompress' ) ? @gzuncompress( $body ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a damaged blob simply means "nothing staged".
		}
		$bundle = is_string( $body ) ? json_decode( $body, true, 48 ) : null;
		return is_array( $bundle ) ? $bundle : null;
	}

	/**
	 * Forget the staged bundle.
	 *
	 * @param int $user_id User.
	 */
	public static function unstage( $user_id ) {
		delete_transient( 'ssc_import_' . (int) $user_id );
	}

	/*
	 * --------------------------------------------------------------
	 * Import
	 * --------------------------------------------------------------
	 */

	/**
	 * Apply a bundle.
	 *
	 * @param array    $bundle     Parsed bundle.
	 * @param string[] $wanted     Section ids to import.
	 * @param string   $mode       'merge' (keep what is here, add and update) or 'replace' (lists match the file).
	 * @param string   $passphrase Password for the secrets block.
	 * @return array[] Report rows: id, status (ok|skipped|warn), count, note.
	 */
	public static function apply( $bundle, $wanted, $mode, $passphrase = '' ) {
		$replace = ( 'replace' === $mode );
		$wanted  = array_values( array_intersect( array_keys( self::sections() ), array_map( 'strval', (array) $wanted ) ) );
		$report  = array();

		self::snapshot();

		// The keys open (once) only with the right password; that is what makes the file trusted.
		$secrets = false;
		if ( in_array( 'secrets', $wanted, true ) && '' !== (string) $passphrase && isset( $bundle['secrets'] ) && is_array( $bundle['secrets'] ) ) {
			$secrets = self::unseal( $bundle['secrets'], (string) $passphrase );
		}

		foreach ( $wanted as $id ) {
			if ( ! isset( $bundle[ $id ] ) || ! is_array( $bundle[ $id ] ) ) {
				$report[] = self::row( $id, 'skipped', 0, __( 'Not in the file.', 'nexachat-ai' ) );
				continue;
			}
			switch ( $id ) {
				case 'business':
					SSC_Settings::update( array( 'business' => SSC_Settings::sanitize_business( $bundle['business'] ) ) );
					$report[] = self::row( $id, 'ok', 0, '' );
					break;
				case 'settings':
					$report[] = self::apply_settings( $bundle['settings'], false !== $secrets );
					break;
				case 'modules':
					$report[] = self::apply_modules( $bundle['modules'], $replace );
					break;
				case 'products':
					$report[] = self::apply_products( $bundle['products'], $replace );
					break;
				case 'knowledge':
					$report[] = self::apply_knowledge( $bundle['knowledge'], $replace );
					break;
				case 'faq':
					$report[] = self::apply_faq( $bundle['faq'], $replace );
					break;
				case 'documents':
					$report[] = self::apply_documents( $bundle['documents'], $replace );
					break;
				case 'secrets':
					$report[] = self::apply_secrets( $secrets, (string) $passphrase );
					break;
			}
		}
		SSC_Settings::flush_ai_cache();
		return $report;
	}

	/**
	 * One report row.
	 *
	 * @param string $id     Section.
	 * @param string $status ok | skipped | warn.
	 * @param int    $count  Items handled.
	 * @param string $note   Detail.
	 * @return array
	 */
	protected static function row( $id, $status, $count, $note ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'count'  => (int) $count,
			'note'   => (string) $note,
		);
	}

	/**
	 * Settings: only keys this version knows, each through its sanitizer.
	 * Destination settings need a trusted file (see destination_settings()).
	 *
	 * @param array $values  key => value.
	 * @param bool  $trusted The file's keys were opened with the right password.
	 * @return array
	 */
	protected static function apply_settings( $values, $trusted ) {
		$skip    = self::site_bound_settings();
		$known   = SSC_Settings::defaults();
		$guarded = self::destination_settings();
		$patch   = array();
		$held    = 0;
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( ! array_key_exists( $key, $known ) || in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( ! $trusted && in_array( $key, $guarded, true ) ) {
				++$held;
				continue;
			}
			$clean = SSC_Settings::sanitize_value( $key, $value );
			if ( null !== $clean ) {
				$patch[ $key ] = $clean;
			}
		}
		if ( $patch ) {
			SSC_Settings::update( $patch );
		}
		if ( $held ) {
			return self::row( 'settings', 'warn', count( $patch ), __( 'The settings that decide where information is sent (AI endpoint, notification recipients) were kept as they were: they are imported only together with the API keys.', 'nexachat-ai' ) );
		}
		return self::row( 'settings', 'ok', count( $patch ), '' );
	}

	/**
	 * Modules: switch on what the file lists (dependencies first); replace
	 * mode also switches off the rest. Data of a module is never deleted.
	 *
	 * @param array $ids     Module ids from the file.
	 * @param bool  $replace Replace mode.
	 * @return array
	 */
	protected static function apply_modules( $ids, $replace ) {
		$all     = SSC_Modules::all();
		$want    = array_values( array_unique( array_filter( array_map( 'strval', $ids ), 'strlen' ) ) );
		$want    = array_values( array_intersect( array_keys( $all ), $want ) );
		$pending = $want;
		$failed  = array();
		// A module may need another one first: keep going while something changes.
		for ( $pass = 0; $pass < 5 && $pending; ++$pass ) {
			$left = array();
			foreach ( $pending as $id ) {
				if ( true === SSC_Modules::activate( $id ) ) {
					continue;
				}
				$left[] = $id;
			}
			if ( count( $left ) === count( $pending ) ) {
				$failed = $left;
				break;
			}
			$pending = $left;
		}
		if ( $replace ) {
			foreach ( SSC_Modules::active_ids() as $id ) {
				if ( ! in_array( $id, $want, true ) ) {
					SSC_Modules::deactivate( $id );
				}
			}
		}
		$count = count( $want ) - count( $failed );
		if ( $failed ) {
			return self::row( 'modules', 'warn', $count, __( 'Some modules could not be switched on because a module they need is missing.', 'nexachat-ai' ) );
		}
		return self::row( 'modules', 'ok', $count, '' );
	}

	/**
	 * Products.
	 *
	 * @param array $products Products from the file.
	 * @param bool  $replace Replace mode.
	 * @return array
	 */
	protected static function apply_products( $products, $replace ) {
		$incoming = SSC_Settings::sanitize_value( 'products', array_values( $products ) );
		if ( ! $replace ) {
			$merged = array();
			foreach ( (array) SSC_Settings::get( 'products', array() ) as $product ) {
				$merged[ (string) $product['id'] ] = $product;
			}
			foreach ( $incoming as $product ) {
				$merged[ (string) $product['id'] ] = $product; // Same id: the file's version wins.
			}
			$incoming = array_values( $merged );
		}
		SSC_Settings::update( array( 'products' => $incoming ) );
		return self::row( 'products', 'ok', count( $products ), '' );
	}

	/**
	 * Knowledge entries.
	 *
	 * @param array $entries Entries from the file.
	 * @param bool  $replace Replace mode.
	 * @return array
	 */
	protected static function apply_knowledge( $entries, $replace ) {
		$incoming = SSC_Settings::sanitize_value( 'knowledge_items', array_values( $entries ) );
		if ( ! $replace ) {
			$merged = array();
			foreach ( (array) SSC_Settings::get( 'knowledge_items', array() ) as $item ) {
				$merged[ self::entry_key( $item ) ] = $item;
			}
			foreach ( $incoming as $item ) {
				$merged[ self::entry_key( $item ) ] = $item;
			}
			$incoming = array_values( $merged );
		}
		SSC_Settings::update( array( 'knowledge_items' => $incoming ) );
		return self::row( 'knowledge', 'ok', count( $entries ), '' );
	}

	/**
	 * Identity of a knowledge entry: its id, else its title.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected static function entry_key( $item ) {
		return ! empty( $item['id'] ) ? 'id:' . $item['id'] : 'title:' . mb_strtolower( (string) $item['title'] );
	}

	/**
	 * FAQ bank.
	 *
	 * @param array $rows    Rows from the file.
	 * @param bool  $replace Replace mode.
	 * @return array
	 */
	protected static function apply_faq( $rows, $replace ) {
		$have = array();
		if ( $replace ) {
			SSC_Schema::qa_import( array(), true );
		} else {
			foreach ( SSC_Schema::qa_all() as $row ) {
				$have[ self::normalize_question( $row['question'] ) ] = true;
			}
		}
		$added = 0;
		foreach ( array_slice( $rows, 0, 5000 ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['question'] ) || empty( $row['answer'] ) ) {
				continue;
			}
			$key = self::normalize_question( (string) $row['question'] );
			if ( isset( $have[ $key ] ) ) {
				continue;
			}
			$have[ $key ] = true;
			SSC_Schema::qa_insert(
				array(
					'question'   => (string) $row['question'],
					'keywords'   => isset( $row['keywords'] ) ? (string) $row['keywords'] : '',
					'answer'     => (string) $row['answer'],
					'product_id' => isset( $row['product_id'] ) && '' !== (string) $row['product_id'] ? (string) $row['product_id'] : 'general',
				)
			);
			++$added;
		}
		return self::row( 'faq', 'ok', $added, '' );
	}

	/**
	 * Case- and space-insensitive question key.
	 *
	 * @param string $question Question.
	 * @return string
	 */
	protected static function normalize_question( $question ) {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', sanitize_textarea_field( $question ) ) ) );
	}

	/**
	 * Documents. Replace mode first removes the uploaded documents that are
	 * here but not in the file (website pages are never touched).
	 *
	 * @param array $docs    Documents from the file.
	 * @param bool  $replace Replace mode.
	 * @return array
	 */
	protected static function apply_documents( $docs, $replace ) {
		// Read every document first: nothing is written or removed until the whole file has been looked at.
		$ready  = array();
		$known  = array();
		$failed = 0;
		foreach ( array_slice( (array) $docs, 0, 500 ) as $doc ) {
			if ( ! is_array( $doc ) || ! isset( $doc['doc_id'] ) || ! is_scalar( $doc['doc_id'] ) ) {
				continue;
			}
			$id = substr( sanitize_key( (string) $doc['doc_id'] ), 0, 40 );
			// Pages from the website belong to the sync module, whatever the file says.
			if ( '' === $id || 0 === strpos( $id, 'wp-' ) ) {
				continue;
			}
			// A document the file names is kept in replace mode even when it cannot be read.
			$known[ $id ] = true;
			$chunks       = array();
			foreach ( array_slice( isset( $doc['chunks'] ) && is_array( $doc['chunks'] ) ? $doc['chunks'] : array(), 0, 400 ) as $chunk ) {
				$chunk = is_scalar( $chunk ) ? mb_substr( sanitize_textarea_field( (string) $chunk ), 0, 6000 ) : '';
				if ( '' !== trim( $chunk ) ) {
					$chunks[] = $chunk;
				}
			}
			if ( ! $chunks ) {
				++$failed;
				continue;
			}
			$ready[ $id ] = array(
				'title'   => isset( $doc['title'] ) && is_scalar( $doc['title'] ) && '' !== trim( (string) $doc['title'] ) ? (string) $doc['title'] : $id,
				'chunks'  => $chunks,
				'product' => isset( $doc['product_id'] ) && is_scalar( $doc['product_id'] ) && '' !== trim( (string) $doc['product_id'] ) ? (string) $doc['product_id'] : 'general',
				'url'     => isset( $doc['url'] ) && is_scalar( $doc['url'] ) ? (string) $doc['url'] : '',
			);
		}
		$count = 0;
		foreach ( $ready as $id => $doc ) {
			// All or nothing per document: a failed insert leaves the document that was there.
			if ( false === SSC_Schema::kb_replace_chunks( $id, $doc['title'], $doc['chunks'], $doc['product'], $doc['url'] ) ) {
				++$failed;
				$known[ $id ] = true;
				continue;
			}
			++$count;
		}
		// Replace mode removes what the file does not name, and only once everything it does name is in place.
		if ( $replace && ! $failed ) {
			foreach ( SSC_Schema::kb_documents() as $doc ) {
				$old = (string) $doc['doc_id'];
				if ( 0 !== strpos( $old, 'wp-' ) && ! isset( $known[ $old ] ) ) {
					SSC_Schema::kb_delete_document( $old );
				}
			}
		}
		if ( $failed ) {
			return self::row( 'documents', 'warn', $count, __( 'Some documents could not be imported and were left as they were. Documents that are not in the file were not removed.', 'nexachat-ai' ) );
		}
		return self::row( 'documents', 'ok', $count, '' );
	}

	/**
	 * API keys and tokens.
	 *
	 * @param array|false $map        Opened secrets (false when they could not be opened).
	 * @param string      $passphrase Password that was entered.
	 * @return array
	 */
	protected static function apply_secrets( $map, $passphrase ) {
		if ( '' === $passphrase ) {
			return self::row( 'secrets', 'warn', 0, __( 'No password was entered, so the API keys were not imported.', 'nexachat-ai' ) );
		}
		if ( false === $map ) {
			return self::row( 'secrets', 'warn', 0, __( 'The password is wrong (or the keys in the file are damaged), so the API keys were not imported. Everything else was.', 'nexachat-ai' ) );
		}
		$allowed = SSC_Settings::secret_fields();
		$count   = 0;
		$failed  = false;
		foreach ( $map as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) || ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}
			if ( SSC_Settings::set_secret( $key, sanitize_text_field( $value ) ) ) {
				++$count;
			} else {
				$failed = true;
			}
		}
		if ( $failed ) {
			return self::row( 'secrets', 'warn', $count, __( 'Some keys could not be encrypted on this server and were not saved.', 'nexachat-ai' ) );
		}
		return self::row( 'secrets', 'ok', $count, '' );
	}

	/*
	 * --------------------------------------------------------------
	 * Undo the last import
	 * --------------------------------------------------------------
	 */

	/**
	 * Option rows an import can change (and an undo may restore).
	 *
	 * @return string[]
	 */
	protected static function backed_up_options() {
		return array_merge( array( SSC_Settings::OPTION_KEY, SSC_Modules::OPTION ), array_values( SSC_Settings::SPLIT_OPTIONS ) );
	}

	/**
	 * Remember the current state so the import can be undone. Uploaded
	 * documents are not part of it: an import only adds or replaces them.
	 */
	public static function snapshot() {
		$options = array();
		foreach ( self::backed_up_options() as $name ) {
			$options[ $name ] = get_option( $name, null );
		}
		update_option(
			self::BACKUP_OPTION,
			array(
				'at'      => time(),
				'options' => $options,
				'faq'     => SSC_Schema::qa_all(),
			),
			false
		);
	}

	/**
	 * Forget an undo snapshot that is older than the given age.
	 *
	 * @param int $max_age Seconds.
	 */
	public static function purge_backup( $max_age ) {
		$time = self::backup_time();
		if ( $time > 0 && time() - $time > (int) $max_age ) {
			delete_option( self::BACKUP_OPTION );
		}
	}

	/**
	 * Is there something to undo?
	 *
	 * @return int Timestamp of the snapshot, 0 when none.
	 */
	public static function backup_time() {
		$backup = get_option( self::BACKUP_OPTION, null );
		return is_array( $backup ) && ! empty( $backup['options'] ) ? (int) $backup['at'] : 0;
	}

	/**
	 * Put back what the last import replaced.
	 *
	 * @return bool
	 */
	public static function undo() {
		$backup = get_option( self::BACKUP_OPTION, null );
		if ( ! is_array( $backup ) || empty( $backup['options'] ) || ! is_array( $backup['options'] ) ) {
			return false;
		}
		$allowed = self::backed_up_options();
		foreach ( $backup['options'] as $name => $value ) {
			if ( ! in_array( $name, $allowed, true ) ) {
				continue;
			}
			if ( null === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value, false );
			}
		}
		SSC_Schema::qa_import( array(), true );
		foreach ( isset( $backup['faq'] ) && is_array( $backup['faq'] ) ? $backup['faq'] : array() as $row ) {
			SSC_Schema::qa_insert( $row );
		}
		SSC_Settings::reset_cache();
		SSC_Settings::flush_ai_cache();
		delete_option( self::BACKUP_OPTION );
		return true;
	}
}
