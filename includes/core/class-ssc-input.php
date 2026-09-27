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
