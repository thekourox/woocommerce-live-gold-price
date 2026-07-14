<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LGP_Admin_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_lgp_manual_fetch', array( __CLASS__, 'manual_fetch_prices' ) );
	}

	public static function manual_fetch_prices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'شما اجازه دسترسی به این بخش را ندارید.' );
		}
		check_admin_referer( 'lgp_manual_fetch_nonce' );

		// Clear the transient first to force a fresh fetch
		delete_transient( 'lgp_gold_prices' );
		LGP_API_Handler::fetch_prices_from_api();

		wp_safe_redirect( admin_url( 'admin.php?page=lgp-settings&updated=1' ) );
		exit;
	}

	public static function add_admin_menu() {
		add_menu_page(
			'تنظیمات قیمت زنده طلا',
			'قیمت زنده طلا',
			'manage_woocommerce',
			'lgp-settings',
			array( __CLASS__, 'settings_page_html' ),
			'dashicons-chart-line',
			55
		);
	}

	public static function register_settings() {
		register_setting( 'lgp_settings_group', 'lgp_api_url' );
		register_setting( 'lgp_settings_group', 'lgp_api_key' );
		register_setting( 'lgp_settings_group', 'lgp_global_profit' );
		register_setting( 'lgp_settings_group', 'lgp_global_tax' );
		register_setting( 'lgp_settings_group', 'lgp_dark_mode_fix' );
		register_setting( 'lgp_settings_group', 'lgp_dropdown_dark_mode_fix' );

		add_settings_section(
			'lgp_main_section',
			'تنظیمات API و فرمول پایه',
			null,
			'lgp-settings'
		);

		add_settings_field(
			'lgp_api_url',
			'لینک وب‌سرویس (API Endpoint)',
			array( __CLASS__, 'render_api_url_field' ),
			'lgp-settings',
			'lgp_main_section'
		);

		add_settings_field(
			'lgp_api_key',
			'کلید وب‌سرویس (API Key)',
			array( __CLASS__, 'render_api_key_field' ),
			'lgp-settings',
			'lgp_main_section'
		);

		add_settings_field(
			'lgp_global_profit',
			'سود پیش‌فرض فروشنده (%)',
			array( __CLASS__, 'render_global_profit_field' ),
			'lgp-settings',
			'lgp_main_section'
		);

		add_settings_field(
			'lgp_global_tax',
			'مالیات پیش‌فرض (%)',
			array( __CLASS__, 'render_global_tax_field' ),
			'lgp-settings',
			'lgp_main_section'
		);

		add_settings_field(
			'lgp_dark_mode_fix',
			'اصلاح رنگ تیره قالب (Dark Mode)',
			array( __CLASS__, 'render_dark_mode_fix_field' ),
			'lgp-settings',
			'lgp_main_section'
		);

		add_settings_field(
			'lgp_dropdown_dark_mode_fix',
			'اصلاح رنگ گزینه‌ها (لیست متغیرها)',
			array( __CLASS__, 'render_dropdown_dark_mode_fix_field' ),
			'lgp-settings',
			'lgp_main_section'
		);
	}

	public static function render_api_url_field() {
		$value = get_option( 'lgp_api_url', 'https://api.brsapi.ir/Market/Gold_Currency.php' );
		echo '<input type="text" name="lgp_api_url" value="' . esc_attr( $value ) . '" class="regular-text" style="width: 100%; max-width: 600px;">';
		echo '<p class="description">آدرس دریافت قیمت لحظه‌ای طلا. برای دریافت کلید وب‌سرویس به <a href="https://brsapi.ir/free-api-gold-currency-webservice/" target="_blank">https://brsapi.ir/free-api-gold-currency-webservice/</a> مراجعه کنید.</p>';
	}

	public static function render_api_key_field() {
		$value = get_option( 'lgp_api_key', 'BpPAcAtIbRzRMrUTRN18BePUdbIBQiNr' );
		echo '<input type="password" name="lgp_api_key" value="' . esc_attr( $value ) . '" class="regular-text" style="width: 100%; max-width: 600px;">';
		echo '<p class="description">کلید تایید هویت برای وب‌سرویس</p>';
	}

	public static function render_global_profit_field() {
		$value = get_option( 'lgp_global_profit', '7' );
		echo '<input type="number" step="any" name="lgp_global_profit" value="' . esc_attr( $value ) . '" class="small-text">';
		echo '<p class="description">درصد سود پیش‌فرض برای محصولاتی که سود اختصاصی ندارند.</p>';
	}

	public static function render_global_tax_field() {
		$value = get_option( 'lgp_global_tax', '2' );
		echo '<input type="number" step="any" name="lgp_global_tax" value="' . esc_attr( $value ) . '" class="small-text">';
		echo '<p class="description">درصد مالیات پیش‌فرض (ارزش افزوده).</p>';
	}

	public static function render_dark_mode_fix_field() {
		$value = get_option( 'lgp_dark_mode_fix', 'no' );
		echo '<label><input type="checkbox" name="lgp_dark_mode_fix" value="yes" ' . checked( $value, 'yes', false ) . '> تبدیل خودکار متن‌ها و عناوین با رنگ تیره (#222222) به رنگ سفید (#fff)</label>';
		echo '<p class="description">اگر از قالب تیره استفاده می‌کنید و برخی متون سایت به دلیل رنگ تیره ناخوانا هستند، این گزینه را فعال کنید.</p>';
	}

	public static function render_dropdown_dark_mode_fix_field() {
		$value = get_option( 'lgp_dropdown_dark_mode_fix', 'no' );
		echo '<label><input type="checkbox" name="lgp_dropdown_dark_mode_fix" value="yes" ' . checked( $value, 'yes', false ) . '> اصلاح رنگ لیست‌های بازشونده (Select/Dropdown) در قالب تیره</label>';
		echo '<p class="description">در صورتی که در صفحه محصول، متن گزینه‌های منوی بازشونده (مثل اندازه انگشتر) قابل خواندن نیستند، این گزینه را فعال کنید تا پس‌زمینه تیره و متن سفید شود.</p>';
	}

	public static function settings_page_html() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		
		$fetch_url = wp_nonce_url( admin_url( 'admin-post.php?action=lgp_manual_fetch' ), 'lgp_manual_fetch_nonce' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'lgp_settings_group' );
				do_settings_sections( 'lgp-settings' );
				submit_button( 'ذخیره تنظیمات' );
				?>
			</form>
			
			<hr>
			
			<div style="margin-top:20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<h3>وضعیت قیمت‌های زنده</h3>
				<?php 
				$prices = get_transient( 'lgp_gold_prices' );
				if ( ! $prices ) {
					$prices = get_option( 'lgp_gold_prices_backup' );
				}
				?>
				<?php if ( $prices ) : ?>
					<p style="color: green; font-weight: bold;">قیمت‌های زنده با موفقیت دریافت شده و در حافظه موقت (کش) موجود هستند.</p>
					<ul style="background: #f9f9f9; padding: 15px; border: 1px solid #eee;">
						<?php foreach( array_slice($prices, 0, 5) as $name => $price ) : ?>
							<li><strong><?php echo esc_html($name); ?>:</strong> <?php echo number_format($price); ?> تومان</li>
						<?php endforeach; ?>
						<li>...</li>
					</ul>
				<?php else : ?>
					<p style="color: red; font-weight: bold;">در حال حاضر قیمت زنده‌ای در کش موجود نیست (ممکن است منقضی شده باشد یا API پاسخ نداده باشد).</p>
				<?php endif; ?>
				<a href="<?php echo esc_url( $fetch_url ); ?>" class="button button-secondary">دریافت دستی قیمت‌ها همین الان</a>
			</div>
			<div style="margin-top: 30px; text-align: center; color: #777;">
				<p>Developed by : <a href="https://kourox.ir" target="_blank" style="text-decoration: none; font-weight: bold; color: #0073aa;">Kourox</a></p>
			</div>
		</div>
		<?php
	}
}
