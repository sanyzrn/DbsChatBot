<?php
/**
 * Do two questions ask opposite things? Word overlap cannot tell "is it
 * allowed?" from "is it not allowed?", so a stored answer could be given to the
 * reverse question. This class spots the differences that flip the meaning:
 * a negation on one side only, opposite directions (stop / continue, before /
 * after, adult / child…) and different numbers (doses, ages). When it finds one
 * the stored answer is NOT given and the question goes on to the assistant.
 * Pure logic, no WordPress calls.
 *
 * @package Vazho
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Question polarity.
 */
class SSC_Knowledge_Polarity {

	/**
	 * A word of the (already normalized) text: not glued to letters or digits.
	 *
	 * @param string $alternatives Regular expression alternatives.
	 * @return string
	 */
	protected static function words( $alternatives ) {
		return '/(?<![\p{L}\p{N}])(?:' . $alternatives . ')(?![\p{L}\p{N}])/u';
	}

	/**
	 * Words that negate or that mean "not allowed / harmful" (a question about
	 * them is the reverse of one about their opposite). Persian first, then English.
	 *
	 * @return string
	 */
	protected static function negation_pattern() {
		$persian = 'نه|نیست\p{L}*|نبود\p{L}*|نباش\p{L}*|نباید|نشود|نشه|نشد\p{L}*|نشو\p{L}*|نمی\p{L}*|نکن\p{L}*|نکرد\p{L}*|نخور\p{L}*|نخواه\p{L}*|نگیر\p{L}*|ندار\p{L}*|نداش\p{L}*|نده\p{L}*|ندید\p{L}*|نرو\p{L}*|نرفت\p{L}*|نتوان\p{L}*|نتونست\p{L}*|هرگز|بدون|بی|غیر\p{L}*|نا(?:مجاز|ایمن|امن|مناسب|سالم|ممکن|درست|مفید|مطمئن|موجود|کافی|معتبر)\p{L}*|ممنوع\p{L}*|منع|حرام|خطرناک|مضر';
		$english = 'not|no|never|without|cannot|neither|nor|nothing|unsafe|illegal|prohibited|forbidden|banned|unable|dangerous|harmful|(?:can|don|doesn|didn|isn|aren|wasn|weren|won|wouldn|shouldn|couldn|haven|hasn|hadn|mustn|needn)\s+t';
		return self::words( $persian . '|' . $english );
	}

	/**
	 * Opposite directions. Each group has two sides; a question that mentions
	 * only one side of a group is the opposite of one that mentions only the other.
	 *
	 * @return array[] name => array( a pattern, b pattern ).
	 */
	protected static function poles() {
		return array(
			'stop'      => array( 'قطع|توقف|متوقف|ترک|stop|stopping|discontinue|quit|cease|withdraw', 'ادامه|شروع|آغاز|continue|continuing|start|starting|begin|resume' ),
			'amount'    => array( 'افزایش|زیادتر|زیاد|بیشتر|اضافه|بالاتر|بالا|increase|increasing|more|higher|raise|double|extra', 'کاهش|کمتر|کم|پایین‌تر|پایین|decrease|less|lower|reduce|reducing|halve|fewer' ),
			'when'      => array( 'قبل|پیش|before|prior', 'بعد|پس|after|following' ),
			'daytime'   => array( 'صبح|صبحانه|morning|breakfast', 'شب|شبانه|عصر|شام|evening|night|bedtime|dinner' ),
			'age'       => array( 'بزرگسال|بزرگسالان|adult|adults', 'کودک|کودکان|بچه|بچه ها|نوزاد|child|children|infant|baby|kid|kids|pediatric' ),
			'situation' => array( 'بارداری|باردار|حامله|حاملگی|pregnant|pregnancy', 'شیردهی|شیرده|شیر\s+دادن|breastfeeding|breastfeed|lactation|nursing' ),
			'form'      => array( 'خوراکی|قرص|کپسول|شربت|oral|tablet|tablets|capsule|pill|syrup', 'تزریق|تزریقی|آمپول|injection|injectable|inject|vial|syringe' ),
			'stomach'   => array( 'ناشتا|معده\s+خالی|empty\s+stomach|fasting', 'سیر|همراه\s+غذا|با\s+غذا|with\s+food|with\s+meals|after\s+meals?|full\s+stomach' ),
			'rhythm'    => array( 'روزانه|روزی|هر\s+روز|daily|every\s+day|per\s+day|once\s+a\s+day|twice\s+a\s+day', 'هفتگی|هفته|هر\s+هفته|weekly|per\s+week|once\s+a\s+week' ),
		);
	}

	/**
	 * How many negating words a text has.
	 *
	 * @param string $normalized Normalized text.
	 * @return int
	 */
	public static function negations( $normalized ) {
		return (int) preg_match_all( self::negation_pattern(), (string) $normalized );
	}

	/**
	 * Groups whose subject changes the answer entirely: a general answer must not
	 * go to someone who says they are pregnant or asks about a child, and an
	 * answer written for them must not go to a general question.
	 *
	 * @return string[]
	 */
	protected static function critical_groups() {
		return array( 'age', 'situation' );
	}

	/**
	 * Which side of each direction group a text mentions.
	 *
	 * @param string $normalized Normalized text.
	 * @return array name => array( 'a' => bool, 'b' => bool ).
	 */
	public static function mentions( $normalized ) {
		$out = array();
		foreach ( self::poles() as $name => $pair ) {
			$out[ $name ] = array(
				'a' => (bool) preg_match( self::words( $pair[0] ), (string) $normalized ),
				'b' => (bool) preg_match( self::words( $pair[1] ), (string) $normalized ),
			);
		}
		return $out;
	}

	/**
	 * Is a word in one text the negated form of a word in the other ("نکنم" and
	 * "کنم", "غیرمجاز" and "مجاز")? This catches negations that the word list
	 * above does not know.
	 *
	 * @param string $a Normalized text.
	 * @param string $b Normalized text.
	 * @return bool
	 */
	protected static function negated_form( $a, $b ) {
		$words_a = array_flip( array_filter( preg_split( '/\s+/u', $a ) ) );
		$words_b = array_flip( array_filter( preg_split( '/\s+/u', $b ) ) );
		foreach ( array( array( $words_a, $words_b ), array( $words_b, $words_a ) ) as $pair ) {
			foreach ( array_keys( $pair[0] ) as $word ) {
				if ( isset( $pair[1][ $word ] ) ) {
					continue;
				}
				foreach ( array(
					'ن'   => 3,
					'نا'  => 2,
					'غیر' => 2,
					'بی'  => 3,
				) as $prefix => $min ) {
					if ( 0 !== mb_strpos( $word, $prefix ) ) {
						continue;
					}
					$rest = mb_substr( $word, mb_strlen( $prefix ) );
					if ( mb_strlen( $rest ) >= $min && isset( $pair[1][ $rest ] ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Do the two questions differ in something that flips the answer?
	 *
	 * @param string $question  What the visitor asked.
	 * @param string $reference The stored question.
	 * @return string '' when they agree, else why: negation | direction | subject | number.
	 */
	public static function conflict( $question, $reference ) {
		$a = SSC_Knowledge::normalize( $question );
		$b = SSC_Knowledge::normalize( $reference );
		if ( $a === $b ) {
			// Normalizing drops the sign and the case of a unit: -20 and 20 read the same afterwards.
			return SSC_Quantities::differ( (string) $question, (string) $reference ) ? 'number' : '';
		}
		if ( self::negations( $a ) !== self::negations( $b ) || self::negated_form( $a, $b ) ) {
			return 'negation';
		}
		$mentions_a = self::mentions( $a );
		$mentions_b = self::mentions( $b );
		foreach ( $mentions_a as $name => $one ) {
			$two    = $mentions_b[ $name ];
			$side_a = ( $one['a'] && ! $one['b'] ) ? 'a' : ( ( $one['b'] && ! $one['a'] ) ? 'b' : '' );
			$side_b = ( $two['a'] && ! $two['b'] ) ? 'a' : ( ( $two['b'] && ! $two['a'] ) ? 'b' : '' );
			if ( '' !== $side_a && '' !== $side_b && $side_a !== $side_b ) {
				return 'direction';
			}
			$has_a = $one['a'] || $one['b'];
			$has_b = $two['a'] || $two['b'];
			if ( $has_a !== $has_b && in_array( $name, self::critical_groups(), true ) ) {
				return 'subject';
			}
		}
		if ( SSC_Quantities::differ( (string) $question, (string) $reference ) ) {
			return 'number';
		}
		return '';
	}
}

