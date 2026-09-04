<?php
/**
 * Turning what a customer typed into a number WhatsApp can reach.
 *
 * People write their number the way they say it: 0712 345 678, (071) 234-5678,
 * +254 712 345 678. WhatsApp needs one form. The country on the order is what
 * makes a local number unambiguous, which is why nothing here guesses.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Numbers {

	/**
	 * @param string $phone   As the customer typed it.
	 * @param string $country ISO code from the order, such as KE.
	 * @return string Digits only, in full international form, or '' if there
	 *                is nothing usable.
	 */
	public static function to_msisdn( $phone, $country = '' ) {
		$raw    = trim( (string) $phone );
		$digits = preg_replace( '/\D/', '', $raw );
		if ( '' === $digits ) {
			return '';
		}

		// A leading + or 00 is the customer saying "this is the whole number".
		// Believe them, and stop before the country on the order gets a say:
		// somebody living abroad who buys with a card billed at home would
		// otherwise have their own country code treated as a local number and
		// a second one stuck in front of it.
		$already_international = strpos( $raw, '+' ) === 0 || strpos( $digits, '00' ) === 0;

		// 00 is how much of the world dials out.
		if ( strpos( $digits, '00' ) === 0 ) {
			$digits = substr( $digits, 2 );
		}

		if ( $already_international ) {
			return $digits;
		}

		$code = self::calling_code( $country );
		if ( '' === $code ) {
			// No country to reason with. Send it as written and let Njiwa
			// resolve it against the sending number's own country.
			return $digits;
		}

		// Already international. The length test is what stops a national
		// number that happens to open with its own country's digits being
		// mistaken for one, which is a real hazard in +1 countries.
		if ( strpos( $digits, $code ) === 0 && strlen( $digits ) >= strlen( $code ) + 7 ) {
			return $digits;
		}

		// The trunk prefix: the 0 you dial at home and never abroad.
		return $code . ltrim( $digits, '0' );
	}

	/**
	 * WooCommerce already knows every calling code and keeps the list current,
	 * so this asks it rather than shipping a copy that goes stale.
	 */
	protected static function calling_code( $country ) {
		$country = strtoupper( trim( (string) $country ) );
		if ( '' === $country || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}

		$code = WC()->countries->get_country_calling_code( $country );
		if ( is_array( $code ) ) {
			$code = reset( $code );
		}

		return preg_replace( '/\D/', '', (string) $code );
	}

	/**
	 * A list typed by the shop owner: one number per line or comma separated.
	 *
	 * @return array<int,string>
	 */
	public static function parse_list( $raw ) {
		$numbers = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $raw ) as $piece ) {
			$digits = preg_replace( '/\D/', '', $piece );
			if ( strlen( $digits ) >= 7 ) {
				$numbers[] = $digits;
			}
		}
		return array_values( array_unique( $numbers ) );
	}
}
