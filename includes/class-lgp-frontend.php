<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_Frontend {

	public static function init() {
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_head', array( __CLASS__, 'inject_custom_css' ) );

		// Override Price HTML
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'override_price_html' ), 99, 2 );

		// Force comma for thousands separator
		add_filter( 'wc_get_price_thousand_separator', function( $separator ) {
			return ',';
		} );

		// Register REST API
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
	}

	public static function enqueue_scripts() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script( 'lgp-frontend-js', LGP_PLUGIN_URL . 'assets/js/lgp-frontend.js', array(), LGP_VERSION, true );
		wp_localize_script( 'lgp-frontend-js', 'lgp_data', array(
			'rest_url' => esc_url_raw( rest_url( 'lgp/v1/prices' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' )
		) );
	}

	public static function inject_custom_css() {
		if ( ! is_product() && ! is_shop() && ! is_product_category() ) {
			// It's okay to print it globally if we want, but let's restrict it slightly
		}
		
		?>
		<style type="text/css">
			.lgp-live-icon {
				display: inline-block;
				width: 8px;
				height: 8px;
				background-color: #ff3b30;
				border-radius: 50%;
				margin-left: 6px;
				margin-right: 6px;
				position: relative;
				top: -1px;
				box-shadow: 0 0 0 rgba(255, 59, 48, 0.7);
				animation: lgp-pulse 1.5s infinite;
			}
			@keyframes lgp-pulse {
				0% { box-shadow: 0 0 0 0 rgba(255, 59, 48, 0.7); }
				70% { box-shadow: 0 0 0 6px rgba(255, 59, 48, 0); }
				100% { box-shadow: 0 0 0 0 rgba(255, 59, 48, 0); }
			}
			.lgp-live-price-wrapper {
				display: inline-flex;
				align-items: center;
			}
			.woocommerce-variation-price .lgp-live-price-wrapper,
			.woocommerce-variation-price .price .lgp-live-price-wrapper {
				font-size: 1.65em !important;
				font-weight: 900 !important;
				color: #fff !important; /* Make it red/accent color so it's super obvious */
			}
			.lgp-loading-skeleton {
				display: inline-block;
				width: 140px;
				height: 28px;
				background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
				background-size: 200% 100%;
				animation: lgp-skeleton-loading 1.5s infinite;
				border-radius: 6px;
				vertical-align: middle;
				margin-top: 10px;
				margin-bottom: 10px;
			}
			@keyframes lgp-skeleton-loading {
				0% { background-position: 200% 0; }
				100% { background-position: -200% 0; }
			}
		</style>
		<?php
		if ( get_option( 'lgp_dark_mode_fix', 'no' ) === 'yes' ) {
			?>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var fixDarkText = function() {
						var elements = document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, a, label, strong, b, div, li, th, td');
						for (var i = 0; i < elements.length; i++) {
							var color = window.getComputedStyle(elements[i]).color;
							if (color === 'rgb(34, 34, 34)' || color === '#222222') {
								elements[i].style.setProperty('color', '#ffffff', 'important');
							}
						}
					};
					fixDarkText();
					if (typeof jQuery !== 'undefined') {
						jQuery(document).on('ajaxComplete', fixDarkText);
					}
					setTimeout(fixDarkText, 500);
					setTimeout(fixDarkText, 2000);
				});
			</script>
			<?php
		}
	}

	public static function override_price_html( $price_html, $product ) {
		// Only wrap it if it's a gold product (or has gold variations)
		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_visible_children();
			$is_gold = false;
			foreach ( $children as $child_id ) {
				if ( get_post_meta( $child_id, '_lgp_enabled', true ) === 'yes' ) {
					$is_gold = true;
					break;
				}
			}
			if ( ! $is_gold ) {
				return $price_html;
			}
		} else {
			$is_enabled = get_post_meta( $product->get_id(), '_lgp_enabled', true );
			if ( $is_enabled !== 'yes' ) {
				return $price_html;
			}
		}

		// Calculate the latest price to embed, so search engines see something reasonable
		// and it doesn't blink too much if not heavily cached.
		$current_calculated = self::get_product_price_html( $product );
		if ( $current_calculated ) {
			$price_html = $current_calculated;
		}

		// Wrap with our identifier so JS can replace it, and add the live pulsing icon!
		return '<span class="lgp-live-price-wrapper" data-product-id="' . esc_attr( $product->get_id() ) . '"><span class="lgp-live-icon" title="قیمت لحظه‌ای"></span>' . $price_html . '</span>';
	}

	/**
	 * Generates the price HTML dynamically.
	 */
	public static function get_product_price_html( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$prices = array();
			$children = $product->get_visible_children();
			foreach ( $children as $child_id ) {
				if ( get_post_meta( $child_id, '_lgp_enabled', true ) !== 'yes' ) {
					continue;
				}
				$calc_price = LGP_Calculator::calculate_price( $child_id );
				if ( $calc_price !== false ) {
					$prices[] = $calc_price;
				} else {
					$variation = wc_get_product( $child_id );
					if ( $variation ) {
						$prices[] = floatval( $variation->get_price( 'edit' ) );
					}
				}
			}

			if ( empty( $prices ) ) {
				return '<span class="lgp-price-error" style="color:red; font-size: 14px;">خطا: اطلاعات قیمت یا تنظیمات محصول ناقص است</span>';
			}

			$min_price = min( $prices );
			$max_price = max( $prices );

			if ( $min_price !== $max_price ) {
				return wc_format_price_range( $min_price, $max_price );
			} else {
				return wc_price( $min_price );
			}
		} else {
			$calc_price = LGP_Calculator::calculate_price( $product->get_id() );
			if ( $calc_price !== false ) {
				return wc_price( $calc_price );
			} else {
				// Show why it's failing!
				return '<span class="lgp-price-error" style="color:red; font-size: 14px;">در حال محاسبه... (اگر تغییر نکرد تنظیمات API یا محصول ناقص است)</span>';
			}
		}
	}

	public static function register_rest_route() {
		register_rest_route( 'lgp/v1', '/prices', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_get_prices' ),
			'permission_callback' => '__return_true', // Public access for frontend read
		) );
	}

	public static function rest_get_prices( $request ) {
		$ids_param = $request->get_param( 'ids' );
		if ( empty( $ids_param ) ) {
			return new WP_Error( 'missing_ids', 'No product IDs provided', array( 'status' => 400 ) );
		}

		$ids = explode( ',', $ids_param );
		$ids = array_map( 'absint', $ids );
		$ids = array_unique( array_filter( $ids ) );

		$response_data = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) continue;

			$html = self::get_product_price_html( $product );
			if ( $html ) {
				$response_data[ $id ] = '<span class="lgp-live-icon" title="قیمت لحظه‌ای"></span>' . $html;
			}
		}

		return rest_ensure_response( $response_data );
	}
}
