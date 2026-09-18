<?php
/** Shared input boundaries for REST and legacy forms. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SSC_Input {

	public static function text( $value, $limit = 1000, $multiline = false ) {
		if ( ! is_scalar( $value ) ) { return ''; }
		$value = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		return mb_substr( $value, 0, $limit );
	}

	public static function consent( $value ) {
		return in_array( $value, array( true, 1, '1', 'yes', 'on', 'true' ), true );
	}

	public static function consent_text( $pharma = false ) {
		$text = trim( (string) SSC_Settings::get( 'consent_text', '' ) );
		if ( '' !== $text ) { return $text; }
		return $pharma
			? __( 'I consent to the processing of my contact and health information for safety review and follow-up.', 'smart-support-chatbot' )
			: __( 'I consent to the processing of my contact information and message for follow-up.', 'smart-support-chatbot' );
	}

	public static function phone( $value ) {
		return strtr( self::text( $value, 50 ), array_combine(
			preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
			str_split( '01234567890123456789' )
		) );
	}

	public static function list_value( $value ) {
		if ( is_string( $value ) ) { $value = json_decode( $value, true ); }
		return is_array( $value ) ? array_values( array_filter( $value, 'is_scalar' ) ) : array();
	}

	/** Protect spreadsheet imports from formulas, including leading whitespace. */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $value ) ? "'" . $value : $value;
	}

	/** One export boundary, including PHP 8.4's explicit escape requirement. */
	public static function write_csv( $stream, $row ) {
		return fputcsv( $stream, array_map( array( __CLASS__, 'csv_cell' ), $row ), ',', '"', '' );
	}
}
