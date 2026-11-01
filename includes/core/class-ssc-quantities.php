<?php
/**
 * Quantities in a question or an answer ("-20 degrees", "2.5 mg", "10 GB"):
 * the number with its sign, the unit it counts, in the order they are written.
 *
 * Two questions that differ only in such a quantity (10 GB and 10 Gb, -20 and
 * 20, millimeter and milligram) are different questions, whatever words they
 * share. The comparison is deliberately cautious: an unknown unit is compared
 * as a word, and a missing unit never makes two quantities differ.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quantity extraction and comparison (pure logic).
 */
class SSC_Quantities {

	/**
	 * Do two texts state different quantities? Only checked when both state
	 * some: a different count of numbers, a different number or sign at the same
	 * place, or the same number counting something else, all differ.
	 *
	 * @param string $a Text as written.
	 * @param string $b Text as written.
	 * @return bool
	 */
	public static function differ( $a, $b ) {
		$qa = self::extract( $a );
		$qb = self::extract( $b );
		if ( ! $qa || ! $qb ) {
			return false;
		}
		if ( count( $qa ) !== count( $qb ) ) {
			return true;
		}
		foreach ( $qa as $i => $one ) {
			if ( $one['value'] !== $qb[ $i ]['value'] || ! self::units_compatible( $one['unit'], $qb[ $i ]['unit'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The quantities of a text, in reading order.
	 *
	 * @param string $text Text as written (not normalized: the sign and the case of a unit matter).
	 * @return array[] Each: value (a canonical number, "-" for a negative one), unit (a key from unit_key(), or '').
	 */
	public static function extract( $text ) {
		$text = self::fold( (string) $text );
		$re   = '/(?<![\p{L}\p{N}])([+\-\x{2212}])?(\d+(?:[.,]\d+)*)(?:[ \t]?(%|\x{00B0}[cCfF]?|[\x{2103}\x{2109}]|\p{L}+)(?:[ \t](\p{L}+))?)?/u';
		if ( ! preg_match_all( $re, $text, $found, PREG_SET_ORDER ) ) {
			return array();
		}
		$out = array();
		foreach ( $found as $match ) {
			$negative = isset( $match[1] ) && '' !== $match[1] && '+' !== $match[1];
			$out[]    = array(
				'value' => self::canonical_number( $match[2], $negative ),
				'unit'  => isset( $match[3] ) ? self::unit_key( $match[3], isset( $match[4] ) ? $match[4] : '' ) : '',
			);
		}
		return $out;
	}

	/**
	 * Persian and Arabic digits and separators become plain ones, and the words
	 * "minus" / "منفی" before a number become its sign.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected static function fold( $text ) {
		$map  = array(
			'۰'            => '0',
			'۱'            => '1',
			'۲'            => '2',
			'۳'            => '3',
			'۴'            => '4',
			'۵'            => '5',
			'۶'            => '6',
			'۷'            => '7',
			'۸'            => '8',
			'۹'            => '9',
			'٠'            => '0',
			'١'            => '1',
			'٢'            => '2',
			'٣'            => '3',
			'٤'            => '4',
			'٥'            => '5',
			'٦'            => '6',
			'٧'            => '7',
			'٨'            => '8',
			'٩'            => '9',
			'٫'            => '.',
			'٬'            => ',',
			"\xE2\x80\x8C" => ' ',
			"\xE2\x80\x8F" => '',
			"\xE2\x80\x8E" => '',
		);
		$text = strtr( $text, $map );
		return (string) preg_replace( '/(?<![\p{L}\p{N}])(?:منفی|minus|negative)\s+(?=\d)/iu', '-', $text );
	}

	/**
	 * One spelling for a number: no thousands separator, one decimal mark, no
	 * leading or trailing zeros ("025" and "25.0" are 25), a sign only on a
	 * number that is not zero.
	 *
	 * @param string $digits   Digits with optional "." or "," marks.
	 * @param bool   $negative Whether it was written with a minus.
	 * @return string
	 */
	protected static function canonical_number( $digits, $negative ) {
		if ( preg_match( '/^\d{1,3}(?:,\d{3})+$/', $digits ) ) {
			$digits = str_replace( ',', '', $digits );
		} else {
			$digits = str_replace( ',', '.', $digits );
		}
		if ( 1 === substr_count( $digits, '.' ) ) {
			$digits = rtrim( rtrim( $digits, '0' ), '.' );
		}
		$digits = (string) preg_replace( '/^0+(?=\d)/', '', $digits );
		if ( '' === $digits ) {
			$digits = '0';
		}
		return ( $negative && '0' !== $digits ) ? '-' . $digits : $digits;
	}

	/**
	 * A key for the unit a number counts. Known units (weights, lengths,
	 * volumes, time, percent, degrees, money, data sizes) get one key whatever
	 * the spelling or language; any other word is kept as itself.
	 *
	 * Data sizes keep what only their capitals tell: GB is bytes, Gb is bits,
	 * and a lowercase "gb" cannot say.
	 *
	 * @param string $word First word after the number (or "%", a degree sign).
	 * @param string $next The word after that (some Persian units are two words).
	 * @return string Key such as "u:mg", "d:g|B", "w:user"; '' when none.
	 */
	public static function unit_key( $word, $next = '' ) {
		$word = trim( (string) $word );
		if ( '' === $word ) {
			return '';
		}
		if ( '%' === $word ) {
			return 'u:pct';
		}
		if ( preg_match( '/^(?:\x{00B0}[cCfF]?|[\x{2103}\x{2109}])$/u', $word ) ) {
			return 'u:deg';
		}
		if ( preg_match( '/^([KMGTPkmgtp])([Bb])$/', $word, $m ) ) {
			$kind = 'B' === $m[2] ? 'B' : ( ctype_upper( $m[1] ) ? 'b' : '?' );
			return 'd:' . strtolower( $m[1] ) . '|' . $kind;
		}
		$single = preg_replace( '/\s+/u', ' ', SSC_Knowledge::normalize( $word ) );
		$pair   = '' !== $next ? preg_replace( '/\s+/u', ' ', SSC_Knowledge::normalize( $word . ' ' . $next ) ) : '';
		foreach ( self::unit_table() as $key => $aliases ) {
			if ( '' !== $pair && in_array( $pair, $aliases, true ) ) {
				return $key;
			}
		}
		foreach ( self::unit_table() as $key => $aliases ) {
			if ( in_array( $single, $aliases, true ) ) {
				return $key;
			}
		}
		return 'w:' . self::stem( $single );
	}

	/**
	 * Can two unit keys name the same unit? An absent unit can; known units must
	 * be equal; data sizes must agree on the prefix and, unless one cannot say,
	 * on bytes or bits; other words may differ only by an ending (user / users).
	 *
	 * @param string $x Unit key.
	 * @param string $y Unit key.
	 * @return bool
	 */
	public static function units_compatible( $x, $y ) {
		if ( '' === $x || '' === $y || $x === $y ) {
			return true;
		}
		$tx = substr( $x, 0, 2 );
		$ty = substr( $y, 0, 2 );
		if ( 'd:' === $tx && 'd:' === $ty ) {
			$px = explode( '|', substr( $x, 2 ) . '|' );
			$py = explode( '|', substr( $y, 2 ) . '|' );
			return $px[0] === $py[0] && ( $px[1] === $py[1] || '?' === $px[1] || '?' === $py[1] );
		}
		if ( 'w:' === $tx && 'w:' === $ty ) {
			$a = substr( $x, 2 );
			$b = substr( $y, 2 );
			if ( mb_strlen( $a ) > mb_strlen( $b ) ) {
				list( $a, $b ) = array( $b, $a );
			}
			return mb_strlen( $a ) >= 3 && 0 === mb_strpos( $b, $a );
		}
		return false;
	}

	/**
	 * The stem of a word used as a unit (users → user, کاربران → کاربر).
	 *
	 * @param string $word Normalized word (spaces allowed).
	 * @return string
	 */
	protected static function stem( $word ) {
		$word = str_replace( ' ', '', $word );
		if ( mb_strlen( $word ) > 4 ) {
			$word = (string) preg_replace( '/(?:ها|های|ان|ات)$/u', '', $word );
		}
		if ( preg_match( '/[a-z]{4,}$/', $word ) ) {
			$word = (string) preg_replace( '/ies$/', 'y', $word );
			$word = (string) preg_replace( '/(?<!s)s$/', '', $word );
		}
		return $word;
	}

	/**
	 * Spellings (already normalized) of the units that matter in answers.
	 *
	 * @return array<string, string[]> key => spellings.
	 */
	protected static function unit_table() {
		return array(
			'd:g|B' => array( 'گیگابایت', 'گیگ بایت', 'gigabyte', 'gigabytes' ),
			'd:g|b' => array( 'گیگابیت', 'گیگ بیت', 'gigabit', 'gigabits' ),
			'd:g|?' => array( 'گیگ' ),
			'd:m|B' => array( 'مگابایت', 'مگ بایت', 'megabyte', 'megabytes' ),
			'd:m|b' => array( 'مگابیت', 'مگ بیت', 'megabit', 'megabits' ),
			'd:m|?' => array( 'مگ' ),
			'd:k|B' => array( 'کیلوبایت', 'کیلو بایت', 'kilobyte', 'kilobytes' ),
			'd:k|b' => array( 'کیلوبیت', 'کیلو بیت', 'kilobit', 'kilobits' ),
			'd:t|B' => array( 'ترابایت', 'ترا بایت', 'terabyte', 'terabytes' ),
			'd:|B'  => array( 'بایت', 'byte', 'bytes' ),
			'd:|b'  => array( 'بیت', 'bit', 'bits' ),
			'u:mg'  => array( 'mg', 'milligram', 'milligrams', 'milligramme', 'milligrammes', 'میلی گرم', 'میلیگرم' ),
			'u:g'   => array( 'g', 'gr', 'gram', 'grams', 'gramme', 'grammes', 'گرم' ),
			'u:kg'  => array( 'kg', 'kilogram', 'kilograms', 'kilo', 'kilos', 'کیلو گرم', 'کیلوگرم', 'کیلو' ),
			'u:mcg' => array( 'mcg', 'µg', 'μg', 'ug', 'microgram', 'micrograms', 'میکرو گرم', 'میکروگرم' ),
			'u:ml'  => array( 'ml', 'cc', 'milliliter', 'milliliters', 'millilitre', 'millilitres', 'میلی لیتر', 'میلیلیتر', 'سی سی' ),
			'u:l'   => array( 'l', 'liter', 'liters', 'litre', 'litres', 'لیتر' ),
			'u:mm'  => array( 'mm', 'millimeter', 'millimeters', 'millimetre', 'millimetres', 'میلی متر', 'میلیمتر' ),
			'u:cm'  => array( 'cm', 'centimeter', 'centimeters', 'centimetre', 'centimetres', 'سانتی متر', 'سانتیمتر' ),
			'u:m'   => array( 'm', 'meter', 'meters', 'metre', 'metres', 'متر' ),
			'u:km'  => array( 'km', 'kilometer', 'kilometers', 'kilometre', 'kilometres', 'کیلو متر', 'کیلومتر' ),
			'u:pct' => array( 'percent', 'درصد' ),
			'u:deg' => array( 'deg', 'degree', 'degrees', 'درجه' ),
			'u:sec' => array( 'sec', 'secs', 'second', 'seconds', 'ثانیه' ),
			'u:min' => array( 'min', 'mins', 'minute', 'minutes', 'دقیقه' ),
			'u:h'   => array( 'h', 'hr', 'hrs', 'hour', 'hours', 'ساعت' ),
			'u:day' => array( 'day', 'days', 'روز' ),
			'u:wk'  => array( 'week', 'weeks', 'هفته' ),
			'u:mon' => array( 'month', 'months', 'ماه' ),
			'u:yr'  => array( 'year', 'years', 'yr', 'yrs', 'سال' ),
			'c:irt' => array( 'تومان', 'toman', 'tomans' ),
			'c:irr' => array( 'ریال', 'rial', 'rials' ),
			'c:usd' => array( 'دلار', 'dollar', 'dollars', 'usd' ),
			'c:eur' => array( 'یورو', 'euro', 'euros', 'eur' ),
		);
	}
}
