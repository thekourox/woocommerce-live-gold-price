<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_Calculator {

	/**
	 * Calculates the final live price for a given product or variation ID.
	 *
	 * @param int $product_id Product or Variation ID.
	 * @return float|false The calculated price, or false if not a live gold product.
	 */
	public static function calculate_price( $product_id ) {
		$is_enabled = get_post_meta( $product_id, '_lgp_enabled', true );
		if ( $is_enabled !== 'yes' ) {
			return false; // Not enabled for this product/variation
		}

		$purity = get_post_meta( $product_id, '_lgp_purity', true );

		if ( empty( $purity ) ) {
			return false; // Not a live gold product
		}

		$prices = LGP_API_Handler::get_prices();

		if ( ! isset( $prices[ $purity ] ) ) {
			return false; // Price not found in API for this purity
		}

		$live_price_per_unit = floatval( $prices[ $purity ] );

		$weight = floatval( get_post_meta( $product_id, '_lgp_weight', true ) );
		// For coins, weight might be 0 or empty, default to 1 unit.
		if ( $weight <= 0 ) {
			$weight = 1;
		}

		$wage_type = get_post_meta( $product_id, '_lgp_wage_type', true );
		$wage_val  = floatval( get_post_meta( $product_id, '_lgp_wage', true ) );
		
		$profit_val = get_post_meta( $product_id, '_lgp_profit', true );
		if ( $profit_val === '' ) {
			$profit_val = get_option( 'lgp_global_profit', '7' );
		}
		$profit_percent = floatval( $profit_val );

		$tax_val = get_post_meta( $product_id, '_lgp_tax', true );
		if ( $tax_val === '' ) {
			$tax_val = get_option( 'lgp_global_tax', '2' );
		}
		$tax_percent = floatval( $tax_val );

		// Step 1: Base Gold Value
		$base_gold_value = $weight * $live_price_per_unit;

		// Step 2: Wage (Ojrat)
		$wage_amount = 0;
		if ( $wage_type === 'percent' ) {
			$wage_amount = $base_gold_value * ( $wage_val / 100 );
		} elseif ( $wage_type === 'per_gram' ) {
			$wage_amount = $weight * $wage_val;
		} elseif ( $wage_type === 'fixed' ) {
			$wage_amount = $wage_val;
		}

		$value_with_wage = $base_gold_value + $wage_amount;

		// Step 3: Seller Profit
		$profit_amount = $value_with_wage * ( $profit_percent / 100 );

		// Step 4: Tax (VAT)
		// Standard calculation usually applies tax to (Base + Wage + Profit)
		$value_with_profit = $value_with_wage + $profit_amount;
		$tax_amount = $value_with_profit * ( $tax_percent / 100 );

		// Final Price
		$final_price = $value_with_profit + $tax_amount;

		return round( $final_price ); // Round to nearest whole number (Toman)
	}

	/**
	 * Formats the price for display.
	 */
	public static function format_price( $price ) {
		return wc_price( $price );
	}
}
