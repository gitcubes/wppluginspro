<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Price Calculator (FREE)
 *
 * Calculates a new price from an old price using:
 * - method: percent | fixed
 * - action: increase | decrease
 * - value: numeric
 * - rounding: none | int | 99
 *
 * Notes:
 * - Negative prices are not allowed (floored at 0).
 * - Rounding is intentionally simple in the FREE version.
 */
class WSH_WCBPM_Price_Calculator {

	/**
	 * Calculate a new price based on an old price and update settings.
	 *
	 * @param float|string|null $old_price
	 * @param array             $update
	 * @return float|null
	 */
	public static function calculate( $old_price, $update ) {

		if ( null === $old_price || '' === $old_price ) {
			return null;
		}

		$old = (float) $old_price;

		$method   = isset( $update['method'] ) ? sanitize_text_field( (string) $update['method'] ) : 'percent';
		$action   = isset( $update['action'] ) ? sanitize_text_field( (string) $update['action'] ) : 'decrease';
		$value_in = isset( $update['value'] ) ? (string) $update['value'] : '';

		if ( '' === trim( $value_in ) ) {
			return null;
		}

		$value = (float) $value_in;
		if ( $value < 0 ) {
			$value = abs( $value );
		}

		$new = $old;

		if ( 'percent' === $method ) {

			// 10 => 10%
			$delta = $old * ( $value / 100 );

			if ( 'increase' === $action ) {
				$new = $old + $delta;
			} else {
				$new = $old - $delta;
			}

		} else { // fixed

			if ( 'increase' === $action ) {
				$new = $old + $value;
			} else {
				$new = $old - $value;
			}
		}

		// Floor to 0.
		if ( $new < 0 ) {
			$new = 0;
		}

		// Rounding.
		$rounding = isset( $update['rounding'] ) ? sanitize_text_field( (string) $update['rounding'] ) : 'none';
		$new      = self::apply_rounding( $new, $rounding );

		// Woo stores prices with wc_format_decimal; keep 4 decimals max.
		$new = (float) wc_format_decimal( $new, 4 );

		return $new;
	}

	/**
	 * Calculate SALE price from REGULAR price (discount mode).
	 *
	 * Rules:
	 * - Base is regular price (not current sale).
	 * - Result is applied ONLY if it is lower than regular price.
	 * - If result is not lower (or cannot be calculated), returns null.
	 * - This mode only makes sense for "decrease" actions; if action is "increase",
	 *   it returns null by default to prevent invalid sales.
	 *
	 * @param float|string|null $regular_price
	 * @param array             $update
	 * @return float|null
	 */
	public static function calculate_sale_from_regular( $regular_price, $update ) {

		if ( null === $regular_price || '' === $regular_price ) {
			return null;
		}

		$regular = (float) $regular_price;

		// Guard: "sale from regular" is a discount concept.
		$action = isset( $update['action'] ) ? sanitize_text_field( (string) $update['action'] ) : 'decrease';
		if ( 'decrease' !== $action ) {
			return null;
		}

		$new_sale = self::calculate( $regular, $update );
		if ( null === $new_sale ) {
			return null;
		}

		// Only set sale if it is lower than regular.
		if ( $new_sale >= $regular ) {
			return null;
		}

		return $new_sale;
	}

	/**
	 * Apply rounding rules.
	 *
	 * @param float  $price
	 * @param string $rounding none|int|99
	 * @return float
	 */
	protected static function apply_rounding( $price, $rounding ) {

		$price = (float) $price;

		switch ( $rounding ) {

			case 'int':
				// Round to nearest integer.
				return (float) round( $price );

			case '99':
				// Round DOWN to integer and add .99 (common pricing).
				$base = (int) floor( $price );
				$out  = $base + 0.99;

				if ( $out < 0 ) {
					$out = 0;
				}
				return (float) $out;

			case 'none':
			default:
				return $price;
		}
	}
}
