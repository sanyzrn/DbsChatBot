<?php
/**
 * Inline SVG icons for the admin screens (stroke style, currentColor).
 * Static, trusted markup: callers may echo the result as is.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon set.
 */
class SSC_Icons {

	/**
	 * Inner SVG of each icon (24x24 grid).
	 *
	 * @return array<string, string>
	 */
	protected static function paths() {
		return array(
			'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-3h4v3"/>',
			'sliders'  => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
			'puzzle'   => '<path d="M10 4a2 2 0 1 1 4 0v2h4a1 1 0 0 1 1 1v4h-2a2 2 0 1 0 0 4h2v4a1 1 0 0 1-1 1h-4v-2a2 2 0 1 0-4 0v2H6a1 1 0 0 1-1-1v-4h2a2 2 0 1 0 0-4H5V7a1 1 0 0 1 1-1h4z"/>',
			'box'      => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
			'book'     => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7M8 11h5"/>',
			'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/>',
			'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
			'key'      => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
			'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
			'upload'   => '<path d="M12 15V3M7 8l5-5 5 5M5 21h14"/>',
			'undo'     => '<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
			'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
			'check'    => '<path d="M5 12l5 5 9-10"/>',
			'alert'    => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
			'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'sparkles' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
			'chevron'  => '<path d="M6 9l6 6 6-6"/>',
			'code'     => '<path d="M8 8l-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
			'heart'    => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.5A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
			'cpu'      => '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
			'layers'   => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5M3 17.5l9 5 9-5"/>',
			'zap'      => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			'pin'      => '<path d="M12 21s-7-6-7-12a7 7 0 0 1 14 0c0 6-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
			'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
			'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
			'database' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
			'server'   => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
			'star'     => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
			'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'scale'    => '<path d="M12 3v18M5 21h14M5 7h14"/><path d="M5 7l-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z"/>',
			'wrench'   => '<path d="M14.5 6.5a4 4 0 0 0 5 5L21 13l-8 8-3-3 8-8z"/><path d="M9 4l3 3-3 3-3-3z"/>',
		);
	}

	/**
	 * A progress ring (CSS conic gradient, so a script can move it by changing
	 * the --p custom property and the label).
	 *
	 * @param int    $percent 0-100.
	 * @param string $class   Extra CSS classes (size modifiers).
	 * @param string $label   Accessible name.
	 * @return string
	 */
	public static function ring( $percent, $class = '', $label = '' ) {
		$percent = max( 0, min( 100, (int) $percent ) );
		$class   = trim( 'ssc-ring ' . preg_replace( '/[^a-zA-Z0-9_ -]/', '', $class ) );
		return '<span class="' . esc_attr( $class ) . '" style="--p:' . $percent . '" role="img" aria-label="' . esc_attr( '' !== $label ? $label : $percent . '%' ) . '"><span class="ssc-ring__val" aria-hidden="true">' . $percent . '%</span></span>';
	}

	/**
	 * Does an icon exist?
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public static function has( $name ) {
		return array_key_exists( $name, self::paths() );
	}

	/**
	 * An icon as inline SVG.
	 *
	 * @param string $name  Icon name (unknown names give an empty string).
	 * @param string $class Extra CSS classes.
	 * @return string
	 */
	public static function get( $name, $class = '' ) {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		$class = trim( 'ssc-ico ssc-ico--' . preg_replace( '/[^a-z0-9-]/', '', $name ) . ' ' . preg_replace( '/[^a-zA-Z0-9_ -]/', '', $class ) );
		return '<svg class="' . $class . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
