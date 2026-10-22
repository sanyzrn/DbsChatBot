<?php
/**
 * Shared input boundaries for REST and legacy forms.
 *
 * Every visitor-supplied value crosses one of these helpers before it reaches
 * storage, a provider, or an export, so the sanitizing rules live in one place.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Input sanitizing helpers.
 */
class SSC_Input {

	/**
	 * Sanitize free text and cap its length in characters (not bytes).
	 *
	 * @param mixed $value     Raw value; anything non-scalar becomes ''.
	 * @param int   $limit     Maximum length in characters.
	 * @param bool  $multiline Keep line breaks.
	 * @return string
	 */
	public static function text( $value, $limit = 1000, $multiline = false ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		return mb_substr( $value, 0, $limit );
	}

	/**
	 * A value as a plain string: text and numbers as they are, anything else
	 * (array, object, null) as ''. Used before the sanitizers that only accept
	 * strings, so a malformed form or file cannot raise a fatal error.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function scalar( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * The first field of a posted or imported row that is not plain text or a
	 * number (an array, an object, null), or '' when the row is well formed.
	 *
	 * @param mixed    $row    The row.
	 * @param string[] $arrays Fields that must hold a list of plain values (e.g. product attributes).
	 * @param string[] $skip   Fields checked elsewhere.
	 * @return string
	 */
	public static function bad_field( $row, $arrays = array(), $skip = array() ) {
		if ( ! is_array( $row ) ) {
			return '*';
		}
		foreach ( $row as $key => $value ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( in_array( $key, $arrays, true ) ) {
				if ( ! is_array( $value ) ) {
					return (string) $key;
				}
				foreach ( $value as $leaf ) {
					if ( ! is_scalar( $leaf ) ) {
						return (string) $key;
					}
				}
				continue;
			}
			if ( ! is_scalar( $value ) ) {
				return (string) $key;
			}
		}
		return '';
	}

	/**
	 * A key for a visitor's address that cannot be turned back into the address:
	 * a keyed hash (the site's own salt), not a plain MD5, which anyone with
	 * the stored value can reverse for an IPv4 address in seconds. Used for
	 * rate-limit counters and short-lived transients; 32 characters, like the
	 * MD5 it replaces.
	 *
	 * @param string $value Address (or any visitor identifier).
	 * @return string
	 */
	public static function visitor_key( $value ) {
		return substr( hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * An email address exactly as typed, or a refusal. Nothing is repaired: an
	 * address that WordPress would only accept after changing it (a space inside,
	 * a stray character) is refused, so a wrong address is never stored as a
	 * different one. Only outer whitespace is dropped.
	 *
	 * @param mixed $value Raw value.
	 * @return string|false The address; '' when nothing was typed; false when it is not a valid address (a list or an object included).
	 */
	public static function strict_email( $value ) {
		if ( null === $value ) {
			return '';
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		$typed = trim( (string) $value );
		if ( '' === $typed ) {
			return '';
		}
		if ( mb_strlen( $typed ) > 191 || preg_match( '/[\x00-\x1F\x7F\s]/u', $typed ) ) {
			return false;
		}
		$valid = is_email( $typed );
		if ( ! $valid || sanitize_email( $typed ) !== $typed ) {
			return false;
		}
		return $typed;
	}

	/**
	 * Chat message text: keeps what the visitor actually typed.
	 *
	 * WordPress sanitize_textarea_field() strips anything that looks like a tag, so a
	 * question such as "is 2<5?" or a pasted code snippet reached the model
	 * truncated. Chat text is never rendered as HTML (the widget escapes it
	 * and admin screens use esc_html), so only invalid UTF-8 and control
	 * characters are removed here.
	 *
	 * @param mixed $value Raw value; anything non-scalar becomes ''.
	 * @param int   $limit Maximum length in characters.
	 * @return string
	 */
	public static function message( $value, $limit = 2000 ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = wp_check_invalid_utf8( (string) $value, true );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		return trim( mb_substr( (string) $value, 0, $limit ) );
	}

	/**
	 * REST sanitize_callback form of message() (WordPress passes the request
	 * as a second argument, which must not become the length limit).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function rest_message( $value ) {
		return self::message( $value, 2000 );
	}

	/**
	 * Store an IP according to the privacy setting.
	 *
	 * @param string $ip   Client IP.
	 * @param string $mode anonymize (default) | full | none.
	 * @return string
	 */
	public static function stored_ip( $ip, $mode = 'anonymize' ) {
		$ip = (string) $ip;
		if ( 'full' === $mode ) {
			return $ip;
		}
		if ( 'none' === $mode || '' === $ip ) {
			return '';
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip ); // Drop the host octet (/24).
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			return inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) ); // Keep the /48 network.
		}
		return '';
	}

	/**
	 * Strict opt-in check: only an explicit affirmative counts as consent.
	 *
	 * @param mixed $value Submitted consent value.
	 * @return bool
	 */
	public static function consent( $value ) {
		return in_array( $value, array( true, 1, '1', 'yes', 'on', 'true' ), true );
	}

	/**
	 * Consent wording: the configured text, or a context-aware default.
	 *
	 * @param bool $pharma Use the health-data wording.
	 * @return string
	 */
	public static function consent_text( $pharma = false ) {
		$text = trim( (string) SSC_Settings::get( 'consent_text', '' ) );
		if ( '' !== $text ) {
			return $text;
		}
		return $pharma
			? __( 'I consent to the processing of my contact and health information for safety review and follow-up.', 'nexachat-ai' )
			: __( 'I consent to the processing of my contact information and message for follow-up.', 'nexachat-ai' );
	}

	/**
	 * Normalize a phone number, folding Persian and Arabic-Indic digits to ASCII.
	 *
	 * @param mixed $value Raw phone input.
	 * @return string
	 */
	public static function phone( $value ) {
		return strtr(
			self::text( $value, 50 ),
			array_combine(
				preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
				str_split( '01234567890123456789' )
			)
		);
	}

	/**
	 * A number as visitors type it, in ASCII: Persian and Arabic digits become
	 * 0-9, the Arabic decimal point becomes ".", the Arabic thousands mark is
	 * dropped. Returns '' for anything that is then not a plain decimal number
	 * (letters, exponents, several dots, a list, infinity).
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function ascii_number( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = strtr(
			trim( (string) $value ),
			array_merge(
				array_combine(
					preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
					str_split( '01234567890123456789' )
				),
				array(
					'٫' => '.',
					'٬' => '',
				)
			)
		);
		return preg_match( '/^-?\d{1,9}(?:\.\d{1,6})?$/', $text ) ? $text : '';
	}

	/**
	 * Coerce a JSON string or array into a flat list of scalars.
	 *
	 * @param mixed $value JSON string or array.
	 * @return array
	 */
	public static function list_value( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		return is_array( $value ) ? array_values( array_filter( $value, 'is_scalar' ) ) : array();
	}

	/**
	 * Protect spreadsheet imports from formulas, including leading whitespace.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $value ) ? "'" . $value : $value;
	}

	/**
	 * One export boundary, including PHP 8.4's explicit escape requirement.
	 *
	 * Every cell is passed through csv_cell(); callers must not pre-escape.
	 *
	 * @param resource $stream Open output stream.
	 * @param array    $row    Cell values.
	 * @return int|false Bytes written, or false on failure.
	 */
	public static function write_csv( $stream, $row ) {
		return fputcsv( $stream, array_map( array( __CLASS__, 'csv_cell' ), $row ), ',', '"', '' );
	}
}
