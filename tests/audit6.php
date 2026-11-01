<?php
/**
 * Checks closing the 24 Oct 2026 audit (T-01, C-01, O-01, O-02).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// C-01: hosts without the intl extension still fold PDF presentation forms.
$glyphs = "\u{FE91}\u{FEAE}\u{FEED}\u{FEB7}\u{FEEE}\u{FEAD}"; // Presentation forms of "برو" + "شور".
check( 'بروشور' === SSC_Arabic_Forms::fold( $glyphs ), 'Presentation forms fold to base letters without intl' );
check( 'لا' === SSC_Arabic_Forms::fold( "\u{FEFB}" ) && 'Vitamin D3 ۱۲۳ عادی' === SSC_Arabic_Forms::fold( 'Vitamin D3 ۱۲۳ عادی' ), 'Ligatures expand and ordinary text is untouched' );
check( SSC_Knowledge::normalize( SSC_Arabic_Forms::fold( $glyphs ) ) === SSC_Knowledge::normalize( 'بروشور' ), 'Folded text matches an ordinary question in the knowledge search' );
if ( class_exists( 'Normalizer' ) ) {
	$same = true;
	foreach ( array( array( 0xFB50, 0xFDFF ), array( 0xFE70, 0xFEFF ) ) as $range ) {
		for ( $cp = $range[0]; $cp <= $range[1]; $cp++ ) {
			if ( ( $cp >= 0xFDD0 && $cp <= 0xFDEF ) || 0xFEFF === $cp ) { continue; }
			$ch = mb_chr( $cp, 'UTF-8' );
			$n  = Normalizer::normalize( $ch, Normalizer::FORM_KC );
			if ( false !== $n && SSC_Arabic_Forms::fold( $ch ) !== $n ) { $same = false; }
		}
	}
	check( $same, 'The fallback table equals Normalizer (NFKC) for every presentation form' );
} else {
	echo "SKIP: intl is not loaded, so the table was not compared with Normalizer\n";
}

// Marketplace readiness: every address points to DbsStudio and the bundled fonts carry their full licences.
$ssc_header = (string) file_get_contents( SSC_CHATBOT_DIR . 'vazho.php' );
check( 1 === preg_match( '/Plugin URI:\s+https:\/\/dbsstudio\.ir\//', $ssc_header ) && false === strpos( $ssc_header, 'saeedzarrini' ), 'The plugin page and author page are both on dbsstudio.ir' );
$ssc_support = SSC_Admin_About::support_url();
check( 0 === strpos( $ssc_support, 'https://dbsstudio.ir/issues?' ) && false !== strpos( $ssc_support, 'product=vazho' ) && false !== strpos( $ssc_support, 'version=' . rawurlencode( SSC_CHATBOT_VERSION ) ), 'The problem-report link opens with the product and installed version filled in' );
check( false !== strpos( (string) file_get_contents( SSC_CHATBOT_DIR . 'SECURITY.md' ), 'https://dbsstudio.ir/issues?product=vazho&kind=security' ), 'Security reports use the private DbsStudio form' );
foreach ( array( 'OFL-Vazirmatn.txt' => 'SIL OPEN FONT LICENSE Version 1.1', 'OFL-Inter.txt' => 'SIL OPEN FONT LICENSE Version 1.1', 'Apache-2.0-Roboto.txt' => 'Apache License' ) as $ssc_file => $ssc_needle ) {
	$ssc_text = (string) file_get_contents( SSC_CHATBOT_DIR . 'assets/fonts/' . $ssc_file );
	check( false !== strpos( $ssc_text, $ssc_needle ) && strlen( $ssc_text ) > 3500, 'Full licence text ships for the bundled fonts: ' . $ssc_file );
}
