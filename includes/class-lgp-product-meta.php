<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_Product_Meta {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_gold_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'gold_tab_content' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_gold_meta' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_gold_content' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_gold_meta' ), 10, 2 );
	}

	public static function add_gold_tab( $tabs ) {
		$tabs['lgp_gold'] = array(
			'label'  => __( 'قیمت زنده طلا', 'live-gold-price' ),
			'target' => 'lgp_gold_product_data',
			'class'  => array( 'show_if_simple' ),
		);
		return $tabs;
	}

	private static function get_purity_options() {
		return array(
			''                 => __( 'محصول طلا نیست (غیرفعال)', 'live-gold-price' ),
			'طلای 18 عیار'      => __( 'طلای 18 عیار', 'live-gold-price' ),
			'سکه امامی'        => __( 'سکه امامی', 'live-gold-price' ),
			'سکه بهار آزادی'    => __( 'سکه بهار آزادی', 'live-gold-price' ),
			'نیم سکه'          => __( 'نیم سکه', 'live-gold-price' ),
			'ربع سکه'          => __( 'ربع سکه', 'live-gold-price' ),
			'سکه گرمی'         => __( 'سکه گرمی', 'live-gold-price' ),
		);
	}

	public static function gold_tab_content() {
		global $post;
		$post_id = $post->ID;

		echo '<div id="lgp_gold_product_data" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_checkbox( array(
			'id'          => '_lgp_enabled',
			'label'       => __( 'فعال‌سازی قیمت زنده', 'live-gold-price' ),
			'description' => __( 'با تیک زدن این گزینه، قیمت این محصول به صورت زنده و با فرمول طلا محاسبه می‌شود.', 'live-gold-price' ),
			'desc_tip'    => true,
		) );

		woocommerce_wp_select( array(
			'id'          => '_lgp_purity',
			'label'       => __( 'نوع طلا / نوع سکه', 'live-gold-price' ),
			'description' => __( 'نوع محصول را برای دریافت قیمت متناظر از API انتخاب کنید.', 'live-gold-price' ),
			'desc_tip'    => true,
			'options'     => self::get_purity_options(),
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_lgp_weight',
			'label'       => __( 'وزن (گرم)', 'live-gold-price' ),
			'description' => __( 'وزن طلا را وارد کنید. برای سکه معمولاً ۱ وارد می‌شود.', 'live-gold-price' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' )
		) );

		woocommerce_wp_select( array(
			'id'          => '_lgp_wage_type',
			'label'       => __( 'نوع محاسبه اجرت', 'live-gold-price' ),
			'options'     => array(
				'percent'  => __( 'درصدی از قیمت خام طلا', 'live-gold-price' ),
				'per_gram' => __( 'مبلغ ثابت به ازای هر گرم', 'live-gold-price' ),
				'fixed'    => __( 'مبلغ ثابت کل', 'live-gold-price' ),
			),
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_lgp_wage',
			'label'       => __( 'مقدار اجرت', 'live-gold-price' ),
			'description' => __( 'مقدار اجرت را بر اساس نوع انتخاب شده در بالا وارد کنید.', 'live-gold-price' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' )
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_lgp_profit',
			'label'       => __( 'سود فروشنده (%)', 'live-gold-price' ),
			'description' => __( 'در صورت خالی گذاشتن، از تنظیمات پیش‌فرض افزونه استفاده می‌شود.', 'live-gold-price' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' )
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_lgp_tax',
			'label'       => __( 'مالیات ارزش افزوده (%)', 'live-gold-price' ),
			'description' => __( 'در صورت خالی گذاشتن، از تنظیمات پیش‌فرض افزونه استفاده می‌شود.', 'live-gold-price' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' )
		) );

		echo '</div>';
	}

	public static function save_gold_meta( $post_id ) {
		$fields = array( '_lgp_purity', '_lgp_weight', '_lgp_wage_type', '_lgp_wage', '_lgp_profit', '_lgp_tax' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
			} else {
				delete_post_meta( $post_id, $field );
			}
		}
		
		$is_enabled = isset( $_POST['_lgp_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_lgp_enabled', $is_enabled );

		if ( $is_enabled === 'yes' ) {
			$reg_price = get_post_meta( $post_id, '_regular_price', true );
			if ( empty( $reg_price ) ) {
				update_post_meta( $post_id, '_regular_price', '1' );
				update_post_meta( $post_id, '_price', '1' );
			}
		}
	}

	public static function variation_gold_content( $loop, $variation_data, $variation ) {
		$variation_id = $variation->ID;

		echo '<div class="options_group form-row form-row-full lgp-variation-settings">';
		echo '<h4>' . esc_html__( 'قیمت زنده طلا (جایگزین قیمت عادی می‌شود)', 'live-gold-price' ) . '</h4>';

		woocommerce_wp_checkbox( array(
			'id'            => "_lgp_enabled[{$loop}]",
			'name'          => "_lgp_enabled[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_enabled', true ),
			'label'         => __( 'فعال‌سازی قیمت زنده برای این متغیر', 'live-gold-price' ),
			'wrapper_class' => 'form-row form-row-full',
		) );

		woocommerce_wp_select( array(
			'id'            => "_lgp_purity[{$loop}]",
			'name'          => "_lgp_purity[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_purity', true ),
			'label'         => __( 'نوع طلا / نوع سکه', 'live-gold-price' ),
			'options'       => self::get_purity_options(),
			'wrapper_class' => 'form-row form-row-full',
		) );

		woocommerce_wp_text_input( array(
			'id'            => "_lgp_weight[{$loop}]",
			'name'          => "_lgp_weight[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_weight', true ),
			'label'         => __( 'وزن (گرم)', 'live-gold-price' ),
			'type'          => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
			'wrapper_class' => 'form-row form-row-first',
		) );

		woocommerce_wp_select( array(
			'id'            => "_lgp_wage_type[{$loop}]",
			'name'          => "_lgp_wage_type[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_wage_type', true ),
			'label'         => __( 'نوع محاسبه اجرت', 'live-gold-price' ),
			'options'       => array(
				'percent'  => __( 'درصدی', 'live-gold-price' ),
				'per_gram' => __( 'ثابت هر گرم', 'live-gold-price' ),
				'fixed'    => __( 'ثابت کل', 'live-gold-price' ),
			),
			'wrapper_class' => 'form-row form-row-last',
		) );

		woocommerce_wp_text_input( array(
			'id'            => "_lgp_wage[{$loop}]",
			'name'          => "_lgp_wage[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_wage', true ),
			'label'         => __( 'مقدار اجرت', 'live-gold-price' ),
			'type'          => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
			'wrapper_class' => 'form-row form-row-first',
		) );

		woocommerce_wp_text_input( array(
			'id'            => "_lgp_profit[{$loop}]",
			'name'          => "_lgp_profit[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_profit', true ),
			'label'         => __( 'سود فروشنده (%)', 'live-gold-price' ),
			'type'          => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
			'wrapper_class' => 'form-row form-row-last',
		) );

		woocommerce_wp_text_input( array(
			'id'            => "_lgp_tax[{$loop}]",
			'name'          => "_lgp_tax[{$loop}]",
			'value'         => get_post_meta( $variation_id, '_lgp_tax', true ),
			'label'         => __( 'مالیات (%)', 'live-gold-price' ),
			'type'          => 'number',
			'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
			'wrapper_class' => 'form-row form-row-first',
		) );

		echo '</div>';
	}

	public static function save_variation_gold_meta( $variation_id, $i ) {
		$fields = array( '_lgp_purity', '_lgp_weight', '_lgp_wage_type', '_lgp_wage', '_lgp_profit', '_lgp_tax' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ][ $i ] ) ) {
				update_post_meta( $variation_id, $field, sanitize_text_field( $_POST[ $field ][ $i ] ) );
			} else {
				delete_post_meta( $variation_id, $field );
			}
		}

		$is_enabled = isset( $_POST['_lgp_enabled'][ $i ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, '_lgp_enabled', $is_enabled );

		if ( $is_enabled === 'yes' ) {
			$reg_price = get_post_meta( $variation_id, '_regular_price', true );
			if ( empty( $reg_price ) ) {
				update_post_meta( $variation_id, '_regular_price', '1' );
				update_post_meta( $variation_id, '_price', '1' );
			}
		}
	}
}
