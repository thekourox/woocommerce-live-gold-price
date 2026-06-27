<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_Cart {

	public static function init() {
		// Use priority 9999 to ensure it runs after other pricing plugins
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'update_cart_prices' ), 9999, 1 );
	}

	public static function update_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Prevent infinite loops if plugins recursively call calculations
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];

			// Check if live gold pricing is enabled for this product/variation
			$is_enabled = get_post_meta( $product_id, '_lgp_enabled', true );
			
			if ( $is_enabled === 'yes' ) {
				$calc_price = LGP_Calculator::calculate_price( $product_id );
				
				if ( $calc_price !== false ) {
					$cart_item['data']->set_price( $calc_price );
				}
			}
		}
	}
}
