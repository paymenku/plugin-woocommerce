<?php
/**
 * Klien API Paymenku untuk plugin WooCommerce.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pembungkus tipis untuk endpoint yang dipakai checkout.
 */
class Paymenku_Api {

	const DEFAULT_BASE_URL = 'https://paymenku.com/api/v1';

	/** @var string */
	private $api_key;

	/** @var string */
	private $base_url;

	/** @var Paymenku_Transport */
	private $transport;

	/**
	 * @param string                  $api_key   Kunci rahasia sk_live_/sk_test_.
	 * @param string                  $base_url  Base URL API.
	 * @param Paymenku_Transport|null $transport Transport kustom untuk pengujian.
	 */
	public function __construct( $api_key, $base_url = '', $transport = null ) {
		$this->api_key   = (string) $api_key;
		$this->base_url  = rtrim( '' !== $base_url ? $base_url : self::DEFAULT_BASE_URL, '/\\' );
		$this->transport = $transport instanceof Paymenku_Transport ? $transport : new Paymenku_Wp_Transport();
	}

	/**
	 * @param array<string, mixed> $data            Payload transaksi.
	 * @param string               $idempotency_key Kunci idempotensi.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws Paymenku_Api_Exception Bila API menolak.
	 */
	public function create_transaction( array $data, $idempotency_key = '' ) {
		return $this->request( 'POST', '/transaction/create', $data, array(), $idempotency_key );
	}

	/**
	 * @param string $order_id Trx ID atau reference ID.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws Paymenku_Api_Exception Bila API menolak.
	 */
	public function get_transaction( $order_id ) {
		return $this->request( 'GET', '/check-status/' . rawurlencode( (string) $order_id ) );
	}

	/**
	 * @param string $trx_id Trx ID.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws Paymenku_Api_Exception Bila API menolak.
	 */
	public function cancel_transaction( $trx_id ) {
		return $this->request( 'POST', '/transaction/cancel', array( 'trx_id' => (string) $trx_id ) );
	}

	/**
	 * @param string $trx_id Trx ID.
	 * @param float  $amount Nominal refund.
	 * @param string $reason Alasan.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws Paymenku_Api_Exception Bila API menolak.
	 */
	public function refund_transaction( $trx_id, $amount, $reason = '' ) {
		$body = array(
			'trx_id'        => (string) $trx_id,
			'refund_amount' => (float) $amount,
		);

		if ( '' !== $reason ) {
			$body['reason'] = (string) $reason;
		}

		return $this->request( 'POST', '/transaction/refund', $body );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws Paymenku_Api_Exception Bila API menolak.
	 */
	public function get_channels() {
		$data = $this->request( 'GET', '/payment-channels' );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Kirim permintaan dan bongkar envelope.
	 *
	 * @param string                    $method          Method HTTP.
	 * @param string                    $path            Path API.
	 * @param array<string, mixed>|null $body            Body JSON.
	 * @param array<string, string>     $query           Query string.
	 * @param string                    $idempotency_key Kunci idempotensi.
	 *
	 * @return mixed
	 *
	 * @throws Paymenku_Api_Exception Bila API atau jaringan gagal.
	 */
	private function request( $method, $path, $body = null, array $query = array(), $idempotency_key = '' ) {
		$url = $this->base_url . $path;

		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'Paymenku-WooCommerce/' . ( defined( 'PAYMENKU_WC_VERSION' ) ? PAYMENKU_WC_VERSION : '0.1.0' ),
		);

		if ( '' !== $idempotency_key ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$payload = null;

		if ( null !== $body ) {
			$payload = $this->encode_json( $body );

			if ( false === $payload ) {
				throw new Paymenku_Api_Exception( 'Gagal menyusun body JSON.' );
			}
		}

		$response = $this->transport->request(
			$method,
			$url,
			array(
				'headers' => $headers,
				'body'    => $payload,
			)
		);

		$status  = isset( $response['status'] ) ? (int) $response['status'] : 0;
		$decoded = json_decode( isset( $response['body'] ) ? (string) $response['body'] : '', true );

		if ( ! is_array( $decoded ) ) {
			throw new Paymenku_Api_Exception(
				sprintf( 'Respons API bukan JSON (HTTP %d).', $status ),
				$status
			);
		}

		$is_error = $status >= 400
			|| ( isset( $decoded['status'] ) && 'error' === $decoded['status'] )
			|| ( array_key_exists( 'success', $decoded ) && false === $decoded['success'] );

		if ( $is_error ) {
			$message = isset( $decoded['message'] ) && '' !== $decoded['message']
				? (string) $decoded['message']
				: sprintf( 'Permintaan gagal (HTTP %d).', $status );

			$errors = isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ? $decoded['errors'] : array();
			$code   = isset( $decoded['error_code'] ) ? (string) $decoded['error_code'] : '';

			throw new Paymenku_Api_Exception( $message, $status, $errors, $code );
		}

		return array_key_exists( 'data', $decoded ) ? $decoded['data'] : $decoded;
	}

	/**
	 * Encode JSON memakai wp_json_encode bila tersedia.
	 *
	 * @param mixed $value Nilai.
	 *
	 * @return string|false
	 */
	private function encode_json( $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $value );
		}

		return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
