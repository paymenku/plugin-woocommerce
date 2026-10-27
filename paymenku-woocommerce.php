<?php
/**
 * Plugin Name:       Paymenku untuk WooCommerce
 * Plugin URI:        https://github.com/paymenku/plugin-woocommerce
 * Description:       Terima pembayaran QRIS, Virtual Account, e-wallet, dan gerai ritel lewat Paymenku.
 * Version:           0.1.0
 * Author:            PT Limbungan Media Solusi
 * Author URI:        https://paymenku.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       paymenku-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.5
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'PAYMENKU_WC_VERSION', '0.1.0' );
define( 'PAYMENKU_WC_FILE', __FILE__ );
define( 'PAYMENKU_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAYMENKU_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Muat terjemahan.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'paymenku-woocommerce', false, dirname( plugin_basename( PAYMENKU_WC_FILE ) ) . '/languages' );
	}
);

/**
 * Deklarasikan kompatibilitas HPOS sebelum WooCommerce menginisialisasi fitur.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PAYMENKU_WC_FILE, true );
		}
	}
);

/**
 * Muat plugin setelah WooCommerce tersedia.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Paymenku untuk WooCommerce membutuhkan WooCommerce yang aktif.', 'paymenku-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once PAYMENKU_WC_PATH . 'includes/class-paymenku-api-exception.php';
		require_once PAYMENKU_WC_PATH . 'includes/class-paymenku-transport.php';
		require_once PAYMENKU_WC_PATH . 'includes/class-paymenku-api.php';
		require_once PAYMENKU_WC_PATH . 'includes/class-paymenku-webhook.php';
		require_once PAYMENKU_WC_PATH . 'includes/class-wc-gateway-paymenku.php';
		require_once PAYMENKU_WC_PATH . 'includes/class-paymenku-plugin.php';

		Paymenku_Plugin::instance();
	},
	20
);
