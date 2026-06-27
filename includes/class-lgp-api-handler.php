<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_API_Handler {

	public static function init() {
		add_action( 'lgp_fetch_prices_cron', array( __CLASS__, 'fetch_prices_from_api' ) );
	}

	public static function get_prices() {
		$prices = get_transient( 'lgp_gold_prices' );

		if ( false === $prices ) {
			$prices = self::fetch_prices_from_api();
		}

		return $prices;
	}

	public static function fetch_prices_from_api() {
		$api_key = get_option( 'lgp_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$url = 'https://api.brsapi.ir/Market/Gold_Currency.php?key=' . $api_key;
		
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return self::handle_api_failure();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || ! isset( $data['gold'] ) ) {
			return self::handle_api_failure();
		}

		$parsed_prices = array();

		// Parse the gold items. We need "طلای 18 عیار" and all coins.
		foreach ( $data['gold'] as $item ) {
			if ( isset( $item['name'] ) && isset( $item['price'] ) ) {
				$name = trim( $item['name'] );
				// Store the price without formatting
				$parsed_prices[ $name ] = floatval( $item['price'] );
			}
		}

		if ( empty( $parsed_prices ) ) {
			return self::handle_api_failure();
		}

		// Store in transient for 1 minute (minus 5 seconds buffer)
		set_transient( 'lgp_gold_prices', $parsed_prices, 55 );
		// Store backup that never expires
		update_option( 'lgp_gold_prices_backup', $parsed_prices );
		update_option( 'lgp_last_api_fetch', time() );

		return $parsed_prices;
	}

	private static function handle_api_failure() {
		$old_prices = get_option( 'lgp_gold_prices_backup' );
		if ( $old_prices ) {
			// Restore transient with old data so we don't spam the failing API
			set_transient( 'lgp_gold_prices', $old_prices, 55 );
			return $old_prices;
		}
		return false;
	}
}
