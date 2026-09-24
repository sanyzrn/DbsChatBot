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
			? __( 'I consent to the processing of my contact and health information for safety review and follow-up.', 'smart-support-chatbot' )
			: __( 'I consent to the processing of my contact information and message for follow-up.', 'smart-support-chatbot' );
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
