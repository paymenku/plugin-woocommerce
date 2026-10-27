<?php
/**
 * Integrasi plugin dengan WordPress dan WooCommerce.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton pengatur hook.
 */
class Paymenku_Plugin {

	/** @var Paymenku_Plugin|null */
	private static $instance = null;

	/**
	 * @return Paymenku_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Daftarkan hook.
	 */
	private function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_order_details' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_pay_action' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( PAYMENKU_WC_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * @param array<int, string> $gateways Gateway terdaftar.
	 *
	 * @return array<int, string>
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = 'WC_Gateway_Paymenku';

		return $gateways;
	}

	/**
	 * Pengaturan gateway.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings() {
		$settings = get_option( 'woocommerce_paymenku_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Daftarkan endpoint webhook.
	 */
	public function register_routes() {
		register_rest_route(
			'paymenku/v1',
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Tangani webhook pembayaran.
	 *
	 * @param WP_REST_Request $request Permintaan.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$settings = self::settings();
		$secret   = isset( $settings['webhook_secret'] ) ? (string) $settings['webhook_secret'] : '';
		$raw      = (string) $request->get_body();
		$stamp    = (string) $request->get_header( 'x_paymenku_timestamp' );
		$signature = (string) $request->get_header( 'x_paymenku_signature' );

		if ( ! Paymenku_Webhook::verify( $raw, $stamp, $signature, $secret ) ) {
			$this->log( 'Webhook ditolak: tanda tangan tidak valid.' );

			return new WP_REST_Response( array( 'message' => 'Invalid signature' ), 401 );
		}

		$payload = json_decode( $raw, true );

		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid JSON' ), 400 );
		}

		$event = isset( $payload['event'] ) ? (string) $payload['event'] : '';

		if ( 'payment.status_updated' !== $event ) {
			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		$reference = isset( $payload['reference_id'] ) ? (string) $payload['reference_id'] : '';
		$order_id  = 0;

		if ( preg_match( '/^WC-(\d+)$/', $reference, $matches ) ) {
			$order_id = (int) $matches[1];
		}

		if ( ! $order_id && '' !== $reference ) {
			$found = wc_get_orders(
				array(
					'limit'      => 1,
					'return'     => 'ids',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_paymenku_reference_id',
							'value' => $reference,
						),
					),
				)
			);

			if ( ! empty( $found ) ) {
				$order_id = (int) $found[0];
			}
		}

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			$this->log( 'Webhook untuk pesanan tidak dikenal: ' . $reference );

			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		if ( 'paymenku' !== $order->get_payment_method() ) {
			$this->log( 'Webhook bukan untuk gateway Paymenku: pesanan ' . $order->get_id() );

			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		$stored_reference = (string) $order->get_meta( '_paymenku_reference_id' );

		if ( '' !== $stored_reference && ! hash_equals( $stored_reference, $reference ) ) {
			$this->log( 'Webhook reference tidak cocok untuk pesanan ' . $order->get_id() );

			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		$trx_id     = isset( $payload['trx_id'] ) ? (string) $payload['trx_id'] : '';
		$stored_trx = (string) $order->get_meta( '_paymenku_trx_id' );

		if ( '' !== $stored_trx && '' !== $trx_id && ! hash_equals( $stored_trx, $trx_id ) ) {
			$this->log( 'Webhook trx_id tidak cocok untuk pesanan ' . $order->get_id() );

			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		$api_key  = isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
		$is_test  = 0 === strpos( $api_key, 'sk_test_' );
		$is_sandbox = isset( $payload['is_sandbox'] ) ? filter_var( $payload['is_sandbox'], FILTER_VALIDATE_BOOLEAN ) : null;

		if ( null !== $is_sandbox && $is_sandbox !== $is_test ) {
			$this->log( 'Webhook mode sandbox tidak cocok untuk pesanan ' . $order->get_id() );

			return new WP_REST_Response( array( 'ignored' => true ), 200 );
		}

		$status = isset( $payload['status'] ) ? strtolower( (string) $payload['status'] ) : '';
		$amount = isset( $payload['amount'] ) ? (string) $payload['amount'] : '';

		if ( 'paid' === $status ) {
			if ( $order->get_meta( '_paymenku_paid_at' ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}

			if ( ! $order->is_paid() ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: nominal dibayar. */
						__( 'Pembayaran Paymenku diterima sebesar %s.', 'paymenku-woocommerce' ),
						'' !== $amount ? wc_price( (float) $amount ) : '-'
					)
				);

				$order->payment_complete( '' !== $trx_id ? $trx_id : $stored_trx );
			}

			$order->update_meta_data( '_paymenku_paid_at', current_time( 'mysql', true ) );
			$order->update_meta_data( '_paymenku_paid_amount', $amount );
			$order->save();

			$target = isset( $settings['order_status'] ) ? (string) $settings['order_status'] : 'processing';

			if ( 'completed' === $target && ! $order->has_status( 'completed' ) ) {
				$order->update_status( 'completed', __( 'Status diselesaikan otomatis oleh Paymenku.', 'paymenku-woocommerce' ) );
			}

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( in_array( $status, array( 'expired', 'failed', 'cancelled' ), true ) ) {
			if ( ! $order->is_paid() && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				$new_status = 'failed' === $status ? 'failed' : 'cancelled';

				$order->update_status(
					$new_status,
					sprintf(
						/* translators: %s: status dari Paymenku. */
						__( 'Paymenku: pembayaran %s.', 'paymenku-woocommerce' ),
						$status
					)
				);
			}

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( 'refunded' === $status ) {
			if ( ! $order->has_status( 'refunded' ) ) {
				$order->update_status( 'refunded', __( 'Refund dikonfirmasi oleh Paymenku.', 'paymenku-woocommerce' ) );
			}

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		return new WP_REST_Response( array( 'ignored' => true ), 200 );
	}

	/**
	 * Muat CSS di halaman pesanan.
	 */
	public function enqueue_assets() {
		if ( ! is_order_received_page() && ! is_view_order_page() ) {
			return;
		}

		wp_enqueue_style(
			'paymenku-woocommerce',
			PAYMENKU_WC_URL . 'assets/css/frontend.css',
			array(),
			PAYMENKU_WC_VERSION
		);
	}

	/**
	 * Tampilkan detail pembayaran di halaman terima kasih.
	 *
	 * @param int $order_id ID pesanan.
	 */
	public function render_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || 'paymenku' !== $order->get_payment_method() || $order->is_paid() ) {
			return;
		}

		$this->render_payment_box( $order );
	}

	/**
	 * Tampilkan detail pembayaran di halaman detail pesanan.
	 *
	 * @param WC_Order $order Pesanan.
	 */
	public function render_order_details( $order ) {
		if ( is_order_received_page() || ! is_view_order_page() ) {
			return;
		}

		if ( ! $order instanceof WC_Order || 'paymenku' !== $order->get_payment_method() || $order->is_paid() ) {
			return;
		}

		$this->render_payment_box( $order );
	}

	/**
	 * Kotak instruksi pembayaran.
	 *
	 * @param WC_Order $order Pesanan.
	 */
	private function render_payment_box( $order ) {
		$info    = $order->get_meta( '_paymenku_payment_info' );
		$pay_url = (string) $order->get_meta( '_paymenku_pay_url' );
		$info    = is_array( $info ) ? $info : array();

		$settings     = self::settings();
		$instructions = isset( $settings['instructions'] ) ? (string) $settings['instructions'] : '';

		echo '<section class="paymenku-payment-box">';
		echo '<h2>' . esc_html__( 'Pembayaran Paymenku', 'paymenku-woocommerce' ) . '</h2>';

		if ( $instructions ) {
			echo '<p>' . esc_html( $instructions ) . '</p>';
		}

		if ( ! empty( $info['qr_url'] ) ) {
			echo '<p class="paymenku-qr"><img src="' . esc_url( $info['qr_url'] ) . '" alt="' . esc_attr__( 'Kode QR pembayaran', 'paymenku-woocommerce' ) . '" /></p>';
		}

		if ( ! empty( $info['va_number'] ) ) {
			echo '<p class="paymenku-field"><span>' . esc_html__( 'Nomor Virtual Account', 'paymenku-woocommerce' ) . '</span><strong>' . esc_html( $info['va_number'] ) . '</strong>';

			if ( ! empty( $info['bank'] ) ) {
				echo ' <em>' . esc_html( $info['bank'] ) . '</em>';
			}

			echo '</p>';
		}

		if ( ! empty( $info['payment_code'] ) ) {
			echo '<p class="paymenku-field"><span>' . esc_html__( 'Kode pembayaran', 'paymenku-woocommerce' ) . '</span><strong>' . esc_html( $info['payment_code'] ) . '</strong>';

			if ( ! empty( $info['store'] ) ) {
				echo ' <em>' . esc_html( $info['store'] ) . '</em>';
			}

			echo '</p>';
		}

		if ( ! empty( $info['expiration_date'] ) ) {
			echo '<p class="paymenku-field"><span>' . esc_html__( 'Batas waktu', 'paymenku-woocommerce' ) . '</span><strong>' . esc_html( $info['expiration_date'] ) . '</strong></p>';
		}

		if ( $pay_url ) {
			echo '<p><a class="button paymenku-pay-button" href="' . esc_url( $pay_url ) . '">' . esc_html__( 'Lanjutkan pembayaran', 'paymenku-woocommerce' ) . '</a></p>';
		}

		echo '<p class="paymenku-hint">' . esc_html__( 'Status pesanan diperbarui otomatis setelah pembayaran diterima. Muat ulang halaman ini bila status belum berubah.', 'paymenku-woocommerce' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Tambahkan aksi Bayar di daftar pesanan akun.
	 *
	 * @param array<string, array<string, string>> $actions Aksi.
	 * @param WC_Order                             $order   Pesanan.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function add_pay_action( $actions, $order ) {
		if ( ! $order instanceof WC_Order || 'paymenku' !== $order->get_payment_method() || $order->is_paid() ) {
			return $actions;
		}

		$url = (string) $order->get_meta( '_paymenku_pay_url' );

		if ( '' !== $url ) {
			$actions['paymenku_pay'] = array(
				'url'  => $url,
				'name' => __( 'Bayar', 'paymenku-woocommerce' ),
			);
		}

		return $actions;
	}

	/**
	 * Tautan Pengaturan di daftar plugin.
	 *
	 * @param array<int, string> $links Tautan.
	 *
	 * @return array<int, string>
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymenku' );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Pengaturan', 'paymenku-woocommerce' ) . '</a>' );

		return $links;
	}

	/**
	 * Log bila debug aktif.
	 *
	 * @param string $message Pesan.
	 */
	private function log( $message ) {
		$settings = self::settings();

		if ( ! isset( $settings['debug'] ) || 'yes' !== $settings['debug'] ) {
			return;
		}

		$logger = wc_get_logger();

		if ( $logger ) {
			$logger->info( $message, array( 'source' => 'paymenku' ) );
		}
	}
}
