<?php
/**
 * Building blocks shared by the admin screens: hub tiles, section headers,
 * the sticky save bar and collapsible cards. Every value is escaped here, so
 * a view can call them without repeating the markup.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI helpers.
 */
class SSC_Admin_UI {

	/**
	 * Colours a section or tile can wear.
	 *
	 * @return string[]
	 */
	public static function hues() {
		return array( 'blue', 'green', 'violet', 'amber', 'cyan', 'rose', 'teal', 'indigo', 'red', 'slate' );
	}

	/**
	 * Hue name that exists (anything else becomes slate).
	 *
	 * @param string $hue Requested hue.
	 * @return string
	 */
	public static function hue( $hue ) {
		return in_array( $hue, self::hues(), true ) ? $hue : 'slate';
	}

	/**
	 * CSS class that lays a tile row out in even columns.
	 *
	 * @param int $count Number of tiles.
	 * @return string
	 */
	public static function tiles_class( $count ) {
		return 'ssc-htiles ssc-htiles--n' . max( 1, min( 8, (int) $count ) );
	}

	/**
	 * Print a tile of a hub's tab row.
	 *
	 * @param array $args {
	 *     @type string   $id     Section id.
	 *     @type string   $hue    Colour.
	 *     @type string   $icon   Icon name.
	 *     @type string   $title  Name.
	 *     @type string   $status One line under the name.
	 *     @type int|null $ring   Percent for a progress ring, null for none.
	 *     @type string   $badge  Icon shown instead of a ring (e.g. lock).
	 *     @type string   $note   Small status chip text (after the title).
	 *     @type string   $attrs  Extra, already-escaped attributes.
	 * }
	 */
	public static function tile( $args ) {
		$a   = array_merge(
			array(
				'id'     => '',
				'hue'    => 'slate',
				'icon'   => 'info',
				'title'  => '',
				'status' => '',
				'ring'   => null,
				'badge'  => '',
				'locked' => false,
				'attrs'  => '',
			),
			$args
		);
		$out = '<button type="button" role="tab" class="ssc-htile' . ( $a['locked'] ? ' is-locked' : '' ) . '" id="ssc-htab-' . esc_attr( $a['id'] ) . '" data-ssc-hubtab="' . esc_attr( $a['id'] ) . '" data-ssc-hue="' . esc_attr( self::hue( $a['hue'] ) ) . '" aria-controls="ssc-hpanel-' . esc_attr( $a['id'] ) . '" aria-selected="false"' . $a['attrs'] . '>';
		$out .= '<span class="ssc-htile__ico">' . SSC_Icons::get( $a['icon'] ) . '</span>';
		$out .= '<span class="ssc-htile__txt"><strong>' . esc_html( $a['title'] ) . '</strong>';
		if ( '' !== $a['status'] ) {
			$out .= '<small>' . esc_html( $a['status'] ) . '</small>';
		}
		$out .= '</span>';
		if ( null !== $a['ring'] ) {
			$class = 'ssc-ring--sm' . ( $a['ring'] >= 100 ? ' is-ok' : ( $a['ring'] <= 0 ? ' is-low' : '' ) );
			$out  .= '<span class="ssc-htile__ring">' . SSC_Icons::ring( (int) $a['ring'], $class, $a['title'] ) . '</span>';
		} elseif ( '' !== $a['badge'] ) {
			$out .= '<span class="ssc-htile__badge">' . SSC_Icons::get( $a['badge'] ) . '</span>';
		}
		$out .= '</button>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; icons are static SVG.
	}

	/**
	 * Open a hub section: wrapper and header. Close it with panel_close().
	 *
	 * @param string $id      Section id.
	 * @param string $hue     Colour.
	 * @param string $icon    Icon name.
	 * @param string $title   Heading.
	 * @param string $intro   One line saying what it is for.
	 * @param bool   $in_form Inside the page's main form (the save bar shows).
	 * @param string $extra   Extra CSS classes.
	 */
	public static function panel_open( $id, $hue, $icon, $title, $intro, $in_form = true, $extra = '' ) {
		$out  = '<div class="ssc-hpanel ssc-hpanel--group ' . esc_attr( $extra ) . '" id="ssc-hpanel-' . esc_attr( $id ) . '" role="tabpanel" aria-labelledby="ssc-htab-' . esc_attr( $id ) . '" data-ssc-hubpanel="' . esc_attr( $id ) . '" data-ssc-hue="' . esc_attr( self::hue( $hue ) ) . '"' . ( $in_form ? ' data-ssc-form="1"' : '' ) . '>';
		$out .= '<header class="ssc-hpanel__head"><span class="ssc-hpanel__ico">' . SSC_Icons::get( $icon ) . '</span><div><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $intro ) {
			$out .= '<p>' . esc_html( $intro ) . '</p>';
		}
		$out .= '</div></header>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; the icon is static SVG.
	}

	/**
	 * Close a section opened with panel_open().
	 */
	public static function panel_close() {
		echo '</div>';
	}

	/**
	 * The sticky bar with the save button and the unsaved-changes note.
	 *
	 * @param string $label Button text.
	 */
	public static function save_bar( $label ) {
		echo '<div class="ssc-hsave" data-ssc-hubsave><span class="ssc-hsave__state" data-ssc-hubdirty hidden><span class="ssc-hsave__dot"></span> ' . esc_html__( 'You have unsaved changes', 'vazho' ) . '</span><button type="submit" class="ssc-btn ssc-btn--primary">' . esc_html( $label ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Open a collapsible card (a module's settings, an advanced group).
	 *
	 * @param string $id    Anchor id ("mod-voice").
	 * @param string $icon  Inline SVG (a module icon) or an icon name.
	 * @param string $title Heading.
	 * @param string $hue   Colour.
	 * @param bool   $open  Start open.
	 * @param string $note  Small chip next to the title.
	 * @param string $sub   One line under the title saying what is inside.
	 */
	public static function acc_open( $id, $icon, $title, $hue = 'slate', $open = false, $note = '', $sub = '' ) {
		$svg  = 0 === strpos( $icon, '<svg' ) ? $icon : SSC_Icons::get( $icon );
		$out  = '<details class="ssc-card ssc-hacc" id="' . esc_attr( $id ) . '" data-ssc-hue="' . esc_attr( self::hue( $hue ) ) . '"' . ( $open ? ' open' : '' ) . '>';
		$out .= '<summary><span class="ssc-hacc__ico">' . $svg . '</span><span class="ssc-hacc__title">' . esc_html( $title ) . ( '' !== $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</span>';
		if ( '' !== $note ) {
			$out .= '<span class="ssc-hbadge">' . esc_html( $note ) . '</span>';
		}
		$out .= '<span class="ssc-hacc__chev">' . SSC_Icons::get( 'chevron' ) . '</span></summary><div class="ssc-hacc__body">';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; module icons are static SVG.
	}

	/**
	 * Close a collapsible card.
	 */
	public static function acc_close() {
		echo '</div></details>';
	}

	/**
	 * A small value tile: icon, big number, caption (and optional link).
	 *
	 * @param string $icon    Icon name.
	 * @param string $hue     Colour.
	 * @param string $value   Number or short text.
	 * @param string $caption Caption.
	 * @param string $url     Link target ('' for none).
	 * @param int    $ring    Percent for a ring in place of the icon (-1 for none).
	 */
	public static function stat( $icon, $hue, $value, $caption, $url = '', $ring = -1 ) {
		$tag  = '' !== $url ? 'a' : 'div';
		$out  = '<' . $tag . ' class="ssc-hstat" data-ssc-hue="' . esc_attr( self::hue( $hue ) ) . '"' . ( '' !== $url ? ' href="' . esc_url( $url ) . '"' : '' ) . '>';
		$out .= '<span class="ssc-hstat__ico">' . ( $ring >= 0 ? SSC_Icons::ring( $ring, 'ssc-ring--sm', $caption ) : SSC_Icons::get( $icon ) ) . '</span>';
		$out .= '<span class="ssc-hstat__txt"><strong>' . esc_html( $value ) . '</strong><small>' . esc_html( $caption ) . '</small></span>';
		$out .= '</' . $tag . '>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; icons are static SVG.
	}

	/**
	 * An empty list: an icon, what is missing, why, and (optionally) the next step.
	 *
	 * @param string $icon   Icon name.
	 * @param string $hue    Colour.
	 * @param string $title  Short heading.
	 * @param string $text   One or two sentences.
	 * @param string $label  Button text ('' for no button).
	 * @param string $url    Button target.
	 */
	public static function empty_state( $icon, $hue, $title, $text, $label = '', $url = '' ) {
		$out  = '<div class="ssc-empty2" data-ssc-hue="' . esc_attr( self::hue( $hue ) ) . '"><span class="ssc-empty2__ico">' . SSC_Icons::get( $icon ) . '</span>';
		$out .= '<h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p>';
		if ( '' !== $label && '' !== $url ) {
			$out .= '<a class="ssc-btn ssc-btn--primary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$out .= '</div>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; the icon is static SVG.
	}

	/**
	 * A row of filter chips that are links, each with a count.
	 *
	 * @param array[] $items Each: label, url, count (int|null), active (bool), hue (optional).
	 * @param string  $aria  Accessible name of the group.
	 */
	public static function chips( $items, $aria ) {
		$out = '<nav class="ssc-fchips" aria-label="' . esc_attr( $aria ) . '">';
		foreach ( $items as $item ) {
			$out .= '<a class="ssc-fchip' . ( ! empty( $item['active'] ) ? ' is-on' : '' ) . '" data-ssc-hue="' . esc_attr( self::hue( isset( $item['hue'] ) ? $item['hue'] : 'blue' ) ) . '" href="' . esc_url( $item['url'] ) . '"' . ( ! empty( $item['active'] ) ? ' aria-current="true"' : '' ) . '><span>' . esc_html( $item['label'] ) . '</span>';
			if ( isset( $item['count'] ) && null !== $item['count'] ) {
				$out .= '<b>' . esc_html( number_format_i18n( (int) $item['count'] ) ) . '</b>';
			}
			$out .= '</a>';
		}
		$out .= '</nav>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Card heading with an icon, for the cards of the data screens.
	 *
	 * @param string $icon  Icon name.
	 * @param string $hue   Colour.
	 * @param string $title Heading.
	 * @param string $sub   One line under it.
	 */
	public static function card_title( $icon, $hue, $title, $sub = '' ) {
		$out  = '<header class="ssc-ctitle" data-ssc-hue="' . esc_attr( self::hue( $hue ) ) . '"><span class="ssc-ctitle__ico">' . SSC_Icons::get( $icon ) . '</span><div><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $sub ) {
			$out .= '<p>' . esc_html( $sub ) . '</p>';
		}
		$out .= '</div></header>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; the icon is static SVG.
	}
}
