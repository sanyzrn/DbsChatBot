<?php
/**
 * Spots a knowledge entry that repeats (or contradicts) what the business
 * profile already says: opening hours, phone numbers, email addresses and
 * addresses. The assistant reads both, so two versions can make it answer
 * differently from one question to the next. Pure logic, no WordPress calls.
 *
 * @package SmartSupportChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge overlap check.
 */
class SSC_Knowledge_Overlap {

	/**
	 * Words in a title that say what an entry is about.
	 *
	 * @return array kind => regular expression.
	 */
	protected static function title_patterns() {
		return array(
			'hours'   => '/ساعت\s*(?:کار|کاری|فعالیت|پاسخ)|ساعات|opening\s*hours|working\s*hours|business\s*hours|office\s*hours|\bhours\b/iu',
			'phone'   => '/تلفن|شماره\s*(?:تماس|تلفن|موبایل)|موبایل|واتس|phone|telephone|mobile|whatsapp|call\s*us/iu',
			'email'   => '/ایمیل|پست\s*الکترونیک|e-?mail/iu',
			'address' => '/آدرس|نشانی|address|location|موقعیت\s*مکانی|دفتر\s*مرکزی|کارخانه|head\s*office|\bfactory\b/iu',
		);
	}

	/**
	 * Western digits for Persian and Arabic-Indic ones.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function western_digits( $text ) {
		return strtr(
			(string) $text,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}

	/**
	 * A phone number reduced to what identifies it: the last ten digits, so
	 * 09123456789, +98 912 345 6789 and 0098-912-3456789 are the same number.
	 *
	 * @param string $phone Phone as written.
	 * @return string '' when it has too few digits to be a phone number.
	 */
	public static function phone_key( $phone ) {
		$digits = preg_replace( '/\D+/', '', self::western_digits( $phone ) );
		if ( strlen( (string) $digits ) < 7 ) {
			return '';
		}
		return substr( (string) $digits, -10 );
	}

	/**
	 * Phone numbers written in a text (they start with 0 or +, as numbers do
	 * when people write them; prices and quantities are left alone).
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function phones_in( $text ) {
		$text = self::western_digits( $text );
		preg_match_all( '/(?<![\d.,])(?:\+\d{1,3}|0\d{1,3})(?:[\s\-()]*\d){7,11}(?![\d])/u', $text, $found );
		$out = array();
		foreach ( $found[0] as $raw ) {
			$key = self::phone_key( $raw );
			if ( '' !== $key ) {
				$out[ $key ] = trim( $raw );
			}
		}
		return $out;
	}

	/**
	 * Email addresses written in a text.
	 *
	 * @param string $text Text.
	 * @return string[] lower-case address => as written.
	 */
	public static function emails_in( $text ) {
		preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', (string) $text, $found );
		$out = array();
		foreach ( $found[0] as $raw ) {
			$out[ strtolower( $raw ) ] = $raw;
		}
		return $out;
	}

	/**
	 * Check one entry.
	 *
	 * @param array $item     Knowledge entry: title, content.
	 * @param array $business Business profile (phone, support_phone, email, support_email, hours, location, locations).
	 * @return array|null null when the entry is fine, else:
	 *     kind  hours | phone | email | address
	 *     state repeats (the profile has it too) | differs (it names a value the profile does not) | belongs (the profile has nothing yet)
	 *     found The value that differs, as written.
	 */
	public static function check( $item, $business ) {
		$title   = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		$content = isset( $item['content'] ) ? trim( (string) $item['content'] ) : '';
		if ( '' === $title && '' === $content ) {
			return null;
		}
		$has = function ( $keys ) use ( $business ) {
			foreach ( $keys as $key ) {
				if ( ! empty( $business[ $key ] ) && '' !== trim( (string) ( is_array( $business[ $key ] ) ? 'x' : $business[ $key ] ) ) ) {
					return true;
				}
			}
			return false;
		};

		// What the profile says.
		$profile_phones = array();
		foreach ( array( 'phone', 'support_phone' ) as $key ) {
			$k = isset( $business[ $key ] ) ? self::phone_key( $business[ $key ] ) : '';
			if ( '' !== $k ) {
				$profile_phones[ $k ] = true;
			}
		}
		$profile_emails = array();
		foreach ( array( 'email', 'support_email' ) as $key ) {
			if ( ! empty( $business[ $key ] ) ) {
				$profile_emails[ strtolower( trim( (string) $business[ $key ] ) ) ] = true;
			}
		}

		// Values written in the entry can be compared exactly.
		$phones = self::phones_in( $title . ' ' . $content );
		if ( $phones ) {
			foreach ( $phones as $key => $raw ) {
				if ( ! isset( $profile_phones[ $key ] ) ) {
					return array(
						'kind'  => 'phone',
						'state' => 'differs',
						'found' => $raw,
					);
				}
			}
			return array(
				'kind'  => 'phone',
				'state' => 'repeats',
				'found' => '',
			);
		}
		$emails = self::emails_in( $title . ' ' . $content );
		if ( $emails ) {
			foreach ( $emails as $key => $raw ) {
				if ( ! isset( $profile_emails[ $key ] ) ) {
					return array(
						'kind'  => 'email',
						'state' => 'differs',
						'found' => $raw,
					);
				}
			}
			return array(
				'kind'  => 'email',
				'state' => 'repeats',
				'found' => '',
			);
		}

		// Otherwise the title says what it is about; hours and addresses cannot be compared word for word.
		$present = array(
			'hours'   => $has( array( 'hours' ) ),
			'phone'   => ! empty( $profile_phones ),
			'email'   => ! empty( $profile_emails ),
			'address' => $has( array( 'location', 'locations' ) ),
		);
		foreach ( self::title_patterns() as $kind => $pattern ) {
			if ( '' !== $title && preg_match( $pattern, $title ) ) {
				return array(
					'kind'  => $kind,
					'state' => $present[ $kind ] ? 'repeats' : 'belongs',
					'found' => '',
				);
			}
		}
		return null;
	}
}
