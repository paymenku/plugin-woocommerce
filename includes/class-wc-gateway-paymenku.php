<?php
/**
 * Gateway pembayaran Paymenku untuk WooCommerce.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gateway tunggal dengan pilihan kanal di halaman checkout.
 */
class WC_Gateway_Paymenku extends WC_Payment_Gateway {

	/**
	 * Inisialisasi gateway.
	 */
	public function __construct() {
		$this->id                 = 'paymenku';
		$this->has_fields         = true;
		$this->method_title       = __( 'Paymenku', 'paymenku-woocommerce' );
		$this->method_description = __( 'Terima QRIS, Virtual Account, e-wallet, dan gerai ritel melalui Paymenku.', 'paymenku-woocommerce' );
		$this->supports           = array( 'products', 'refunds' );

		if ( file_exists( PAYMENKU_WC_PATH . 'assets/icon.png' ) ) {
			$this->icon = PAYMENKU_WC_URL . 'assets/icon.png';
		}

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Daftar kanal yang dapat dipilih merchant.
	 *
	 * @return array<string, string>
	 */
	public static function channel_options() {
		$channels = array(
			'qris'       => __( 'QRIS', 'paymenku-woocommerce' ),
			'qris_own'   => __( 'QRIS Custom', 'paymenku-woocommerce' ),
			'bca_va'     => __( 'BCA Virtual Account', 'paymenku-woocommerce' ),
			'bni_va'     => __( 'BNI Virtual Account', 'paymenku-woocommerce' ),
			'bri_va'     => __( 'BRI Virtual Account', 'paymenku-woocommerce' ),
			'mandiri_va' => __( 'Mandiri Virtual Account', 'paymenku-woocommerce' ),
			'permata_va' => __( 'Permata Virtual Account', 'paymenku-woocommerce' ),
			'cimb_va'    => __( 'CIMB Virtual Account', 'paymenku-woocommerce' ),
			'danamon_va' => __( 'Danamon Virtual Account', 'paymenku-woocommerce' ),
			'bsi_va'     => __( 'BSI Virtual Account', 'paymenku-woocommerce' ),
			'ocbc_va'    => __( 'OCBC Virtual Account', 'paymenku-woocommerce' ),
			'maybank_va' => __( 'Maybank Virtual Account', 'paymenku-woocommerce' ),
			'muamalat_va' => __( 'Muamalat Virtual Account', 'paymenku-woocommerce' ),
			'bnc_va'     => __( 'BNC Virtual Account', 'paymenku-woocommerce' ),
			'dana'       => __( 'DANA', 'paymenku-woocommerce' ),
			'ovo'        => __( 'OVO', 'paymenku-woocommerce' ),
			'shopeepay'  => __( 'ShopeePay', 'paymenku-woocommerce' ),
			'indomaret'  => __( 'Indomaret', 'paymenku-woocommerce' ),
			'alfamart'   => __( 'Alfamart', 'paymenku-woocommerce' ),
		);

		return apply_filters( 'paymenku_woocommerce_channels', $channels );
	}

	/**
	 * Pengaturan gateway.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Aktifkan', 'paymenku-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Tampilkan Paymenku di checkout', 'paymenku-woocommerce' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Judul', 'paymenku-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Nama metode pembayaran di checkout.', 'paymenku-woocommerce' ),
				'default'     => __( 'Paymenku', 'paymenku-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'   => __( 'Deskripsi', 'paymenku-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Bayar dengan QRIS, Virtual Account, e-wallet, atau gerai ritel.', 'paymenku-woocommerce' ),
			),
			'api_key' => array(
				'title'       => __( 'API key', 'paymenku-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Kunci rahasia dari dashboard Paymenku. Gunakan sk_test_... untuk sandbox.', 'paymenku-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'channels' => array(
				'title'       => __( 'Kanal pembayaran', 'paymenku-woocommerce' ),
				'type'        => 'multiselect',
				'options'     => self::channel_options(),
				'description' => __( 'Hanya kanal yang aktif di akun merchant yang akan berhasil. Tahan Ctrl/Cmd untuk memilih beberapa kanal.', 'paymenku-woocommerce' ),
				'default'     => array( 'qris' ),
				'desc_tip'    => true,
			),
			'order_status' => array(
				'title'   => __( 'Status setelah dibayar', 'paymenku-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'processing' => __( 'Diproses (processing)', 'paymenku-woocommerce' ),
					'completed'  => __( 'Selesai (completed)', 'paymenku-woocommerce' ),
				),
				'default' => 'processing',
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook secret', 'paymenku-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Samakan dengan nilai di dashboard Paymenku. Wajib agar webhook diterima.', 'paymenku-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_url' => array(
				'title'       => __( 'URL webhook', 'paymenku-woocommerce' ),
				'type'        => 'title',
				'description' => '<code>' . esc_html( rest_url( 'paymenku/v1/webhook' ) ) . '</code>',
			),
			'instructions' => array(
				'title'       => __( 'Instruksi pembayaran', 'paymenku-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Ditampilkan di halaman terima kasih saat menunggu pembayaran.', 'paymenku-woocommerce' ),
				'default'     => __( 'Selesaikan pembayaran sebelum batas waktu. Status pesanan diperbarui otomatis setelah pembayaran diterima.', 'paymenku-woocommerce' ),
			),
			'debug' => array(
				'title'   => __( 'Log debug', 'paymenku-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Catat permintaan API dan webhook', 'paymenku-woocommerce' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Kanal yang dipilih merchant dan masih valid.
	 *
	 * @return array<string, string>
	 */
	public function get_enabled_channels() {
		$options  = self::channel_options();
		$selected = $this->get_option( 'channels', array( 'qris' ) );

		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		$enabled = array();

		foreach ( $selected as $code ) {
			$code = sanitize_key( $code );

			if ( isset( $options[ $code ] ) ) {
				$enabled[ $code ] = $options[ $code ];
			}
		}

		return $enabled;
	}

	/**
	 * Apakah gateway dapat dipakai.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		if ( 'IDR' !== get_woocommerce_currency() ) {
			return false;
		}

		if ( '' === trim( (string) $this->get_option( 'api_key' ) ) ) {
			return false;
		}

		return ! empty( $this->get_enabled_channels() );
	}

	/**
	 * Tampilkan pilihan kanal di checkout.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p>' . esc_html( $this->description ) . '</p>';
		}

		$channels = $this->get_enabled_channels();

		if ( empty( $channels ) ) {
			echo '<p>' . esc_html__( 'Belum ada kanal pembayaran yang aktif.', 'paymenku-woocommerce' ) . '</p>';
			return;
		}

		echo '<fieldset class="paymenku-channels" id="paymenku-channels">';
		echo '<legend>' . esc_html__( 'Pilih metode pembayaran', 'paymenku-woocommerce' ) . '</legend>';

		$first = true;

		foreach ( $channels as $code => $label ) {
			printf(
				'<label class="paymenku-channel" for="paymenku-channel-%1$s">'
				. '<input type="radio" id="paymenku-channel-%1$s" name="paymenku_channel" value="%1$s" %2$s /> '
				. '<span>%3$s</span></label>',
				esc_attr( $code ),
				checked( $first, true, false ),
				esc_html( $label )
			);

			$first = false;
		}

		echo '</fieldset>';
	}

	/**
	 * Buat transaksi dan arahkan ke halaman pembayaran.
	 *
	 * @param int $order_id ID pesanan.
	 *
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Pesanan tidak ditemukan.', 'paymenku-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( $order->is_paid() ) {
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			);
		}

		$existing_url = (string) $order->get_meta( '_paymenku_pay_url' );

		if ( '' !== $existing_url && $order->has_status( 'pending' ) && ! $order->get_meta( '_paymenku_paid_at' ) ) {
			return array(
				'result'   => 'success',
				'redirect' => $existing_url,
			);
		}

		$channel = isset( $_POST['paymenku_channel'] ) ? sanitize_key( wp_unslash( $_POST['paymenku_channel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$enabled = $this->get_enabled_channels();

		if ( '' === $channel || ! isset( $enabled[ $channel ] ) ) {
			wc_add_notice( __( 'Pilih metode pembayaran yang tersedia.', 'paymenku-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$api_key = trim( (string) $this->get_option( 'api_key' ) );

		if ( '' === $api_key ) {
			wc_add_notice( __( 'Paymenku belum dikonfigurasi.', 'paymenku-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$amount = (int) round( (float) $order->get_total() );

		if ( $amount < 1 ) {
			wc_add_notice( __( 'Total pesanan tidak valid untuk Paymenku.', 'paymenku-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		if ( '' === $name ) {
			$name = __( 'Pelanggan', 'paymenku-woocommerce' );
		}

		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => (int) $item->get_quantity(),
			);
		}

		$reference = 'WC-' . $order->get_id();

		$payload = array(
			'channel_code'   => $channel,
			'amount'         => $amount,
			'reference_id'   => $reference,
			'customer_name'  => $name,
			'customer_email' => $order->get_billing_email(),
			'customer_phone' => $order->get_billing_phone(),
			'return_url'     => $order->get_checkout_order_received_url(),
			'order_items'    => $items,
		);

		$idempotency = 'wc-' . $order->get_id() . '-' . md5( $channel . '|' . $amount );

		try {
			$api = new Paymenku_Api( $api_key, $this->api_base_url() );
			$trx = $api->create_transaction( $payload, $idempotency );
		} catch ( Paymenku_Api_Exception $e ) {
			$this->log( 'create gagal: ' . $e->getMessage() . ' ' . wp_json_encode( $e->get_errors() ) );
			wc_add_notice(
				sprintf(
					/* translators: %s: pesan galat API. */
					__( 'Pembayaran gagal dibuat: %s', 'paymenku-woocommerce' ),
					$e->getMessage()
				),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		$trx_id  = isset( $trx['trx_id'] ) ? (string) $trx['trx_id'] : '';
		$pay_url = isset( $trx['pay_url'] ) ? esc_url_raw( (string) $trx['pay_url'] ) : '';

		if ( '' === $trx_id || '' === $pay_url ) {
			$this->log( 'respons create tanpa trx_id/pay_url' );
			wc_add_notice( __( 'Respons Paymenku tidak lengkap. Coba lagi.', 'paymenku-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_paymenku_trx_id', $trx_id );
		$order->update_meta_data( '_paymenku_reference_id', $reference );
		$order->update_meta_data( '_paymenku_channel', $channel );
		$order->update_meta_data( '_paymenku_pay_url', $pay_url );
		$order->update_meta_data( '_paymenku_amount', isset( $trx['amount'] ) ? (string) $trx['amount'] : (string) $amount );
		$order->update_meta_data( '_paymenku_is_sandbox', 0 === strpos( $api_key, 'sk_test_' ) ? 'yes' : 'no' );

		if ( isset( $trx['payment_info'] ) && is_array( $trx['payment_info'] ) ) {
			$order->update_meta_data( '_paymenku_payment_info', $trx['payment_info'] );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: nama kanal, 2: trx id. */
				__( 'Paymenku: menunggu pembayaran %1$s (trx %2$s).', 'paymenku-woocommerce' ),
				$enabled[ $channel ],
				$trx_id
			)
		);

		$order->save();

		if ( ! $order->has_status( 'pending' ) ) {
			$order->update_status( 'pending', __( 'Menunggu pembayaran Paymenku.', 'paymenku-woocommerce' ) );
		}

		return array(
			'result'   => 'success',
			'redirect' => $pay_url,
		);
	}

	/**
	 * Refund melalui API Paymenku.
	 *
	 * @param int    $order_id ID pesanan.
	 * @param float  $amount   Nominal.
	 * @param string $reason   Alasan.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'paymenku_order_missing', __( 'Pesanan tidak ditemukan.', 'paymenku-woocommerce' ) );
		}

		$trx_id = (string) $order->get_meta( '_paymenku_trx_id' );

		if ( '' === $trx_id ) {
			return new WP_Error( 'paymenku_trx_missing', __( 'Transaksi Paymenku tidak ditemukan pada pesanan ini.', 'paymenku-woocommerce' ) );
		}

		$api_key = trim( (string) $this->get_option( 'api_key' ) );

		try {
			$api = new Paymenku_Api( $api_key, $this->api_base_url() );
			$api->refund_transaction( $trx_id, (float) $amount, (string) $reason );
		} catch ( Paymenku_Api_Exception $e ) {
			$this->log( 'refund gagal: ' . $e->getMessage() );
			return new WP_Error( 'paymenku_refund_failed', $e->getMessage() );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: nominal, 2: trx id. */
				__( 'Refund Paymenku %1$s dikirim untuk trx %2$s.', 'paymenku-woocommerce' ),
				wc_price( (float) $amount ),
				$trx_id
			)
		);

		return true;
	}

	/**
	 * Base URL API dengan filter.
	 *
	 * @return string
	 */
	public function api_base_url() {
		return (string) apply_filters( 'paymenku_woocommerce_api_base_url', Paymenku_Api::DEFAULT_BASE_URL );
	}

	/**
	 * Catat log bila debug aktif.
	 *
	 * @param string $message Pesan.
	 */
	private function log( $message ) {
		if ( 'yes' !== $this->get_option( 'debug' ) ) {
			return;
		}

		$logger = wc_get_logger();

		if ( $logger ) {
			$logger->info( $message, array( 'source' => 'paymenku' ) );
		}
	}
}
