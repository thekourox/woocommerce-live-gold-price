<?php
/**
 * Plugin Name: Persian Live Gold Price for WooCommerce
 * Description: افزونه فارسی اتصال آنلاین به وب‌سرویس‌های قیمت لحظه‌ای طلا و محاسبه قیمت لحظه‌ای محصول
 * Version: 0.3.0
 * Author: Kourosh Marandi
 * Text Domain: live-gold-price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LGP_VERSION', '0.3.0' );
define( 'LGP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LGP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class Live_Gold_Price {

	public function __construct() {
		$this->includes();
		$this->init_classes();

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	private function includes() {
		require_once LGP_PLUGIN_DIR . 'includes/class-lgp-api-handler.php';
		require_once LGP_PLUGIN_DIR . 'includes/class-lgp-calculator.php';
		require_once LGP_PLUGIN_DIR . 'includes/class-lgp-product-meta.php';
		require_once LGP_PLUGIN_DIR . 'includes/class-lgp-cart.php';
		require_once LGP_PLUGIN_DIR . 'includes/class-lgp-frontend.php';
		
		if ( is_admin() ) {
			require_once LGP_PLUGIN_DIR . 'includes/class-lgp-admin-settings.php';
		}
	}

	private function init_classes() {
		LGP_API_Handler::init();
		LGP_Product_Meta::init();
		LGP_Cart::init();
		LGP_Frontend::init();
		
		if ( is_admin() ) {
			LGP_Admin_Settings::init();
		}
	}

	public function activate() {
		if ( ! wp_next_scheduled( 'lgp_fetch_prices_cron' ) ) {
			wp_schedule_event( time(), 'lgp_1_min', 'lgp_fetch_prices_cron' );
		}
	}

	public function deactivate() {
		$timestamp = wp_next_scheduled( 'lgp_fetch_prices_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'lgp_fetch_prices_cron' );
		}
	}
}

// Add 1-minute cron interval
add_filter( 'cron_schedules', 'lgp_add_cron_interval' );
function lgp_add_cron_interval( $schedules ) {
	$schedules['lgp_1_min'] = array(
		'interval' => 60,
		'display'  => __( 'هر ۱ دقیقه (افزونه طلا)', 'live-gold-price' )
	);
	return $schedules;
}

new Live_Gold_Price();
