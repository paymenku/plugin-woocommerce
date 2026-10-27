<?php
/**
 * Smoke test memuat gateway dengan stub WooCommerce.
 *
 * @package Paymenku_WooCommerce
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'PAYMENKU_WC_VERSION', '0.1.0' );
define( 'PAYMENKU_WC_FILE', __DIR__ . '/../paymenku-woocommerce.php' );
define( 'PAYMENKU_WC_PATH', __DIR__ . '/../' );
define( 'PAYMENKU_WC_URL', 'https://example.test/plugins/paymenku-woocommerce/' );

/**
 * @param string $text Teks.
 * @param string $domain Domain.
 *
 * @return string
 */
function __( $text, $domain = null ) { // phpcs:ignore
	return $text;
}

/**
 * @param string $text Teks.
 *
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text Teks.
 *
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text Teks.
 * @param string $domain Domain.
 *
 * @return string
 */
function esc_html__( $text, $domain = null ) { // phpcs:ignore
	return esc_html( $text );
}

/**
 * @param mixed $checked Nilai.
 * @param mixed $current Pembanding.
 * @param bool  $display Tampilkan.
 *
 * @return string
 */
function checked( $checked, $current = true, $display = true ) {
	$result = ( $checked == $current ) ? ' checked="checked"' : ''; // phpcs:ignore
	if ( $display ) {
		echo $result; // phpcs:ignore
	}
	return $result;
}

/**
 * @param string $hook Hook.
 * @param mixed  $callback Callback.
 * @param int    $priority Prioritas.
 * @param int    $args Argumen.
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

/**
 * @param string $tag Tag.
 * @param mixed  $value Nilai.
 *
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	return $value;
}

/**
 * @return string
 */
function get_woocommerce_currency() {
	return 'IDR';
}

/**
 * @param string $path Path.
 *
 * @return string
 */
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

/**
 * @param string $key Kunci.
 *
 * @return string
 */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/**
 * Stub minimal WC_Payment_Gateway.
 */
class WC_Payment_Gateway {
	public $id;
	public $has_fields;
	public $method_title;
	public $method_description;
	public $supports = array();
	public $icon;
	public $title;
	public $description;
	public $enabled;
	public $form_fields = array();

	public function init_settings() {}

	public function get_option( $key, $default = '' ) {
		if ( 'channels' === $key && class_exists( 'WC_Gateway_Paymenku' ) ) {
			return array_keys( WC_Gateway_Paymenku::channel_options() );
		}

		return $default;
	}

	public function is_available() {
		return true;
	}
}

require_once __DIR__ . '/../includes/class-wc-gateway-paymenku.php';

$gateway = new WC_Gateway_Paymenku();

$failures = 0;
$checks   = 0;

/**
 * @param bool   $condition Kondisi.
 * @param string $label     Label.
 */
function smoke_check( $condition, $label ) {
	global $failures, $checks;
	$checks++;
	if ( ! $condition ) {
		$failures++;
		echo "GAGAL: {$label}\n";
	}
}

smoke_check( 'paymenku' === $gateway->id, 'ID gateway' );
smoke_check( $gateway->has_fields, 'has_fields aktif' );
smoke_check( in_array( 'refunds', $gateway->supports, true ), 'dukungan refund' );
smoke_check( 19 === count( WC_Gateway_Paymenku::channel_options() ), '19 kanal tersedia' );

ob_start();
$gateway->payment_fields();
$html = ob_get_clean();

smoke_check( false !== strpos( $html, 'name="paymenku_channel"' ), 'input kanal dirender' );
smoke_check( false !== strpos( $html, 'value="qris"' ), 'kanal default qris' );
smoke_check( 19 === substr_count( $html, 'name="paymenku_channel"' ), 'semua kanal dirender' );
smoke_check( false === strpos( $html, 'undefined' ), 'tidak ada undefined' );

echo "{$checks} pemeriksaan gateway, {$failures} gagal\n";
exit( $failures > 0 ? 1 : 0 );
