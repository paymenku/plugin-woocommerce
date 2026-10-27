<?php
/**
 * Transport HTTP untuk API Paymenku.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kontrak transport agar API dapat diuji tanpa WordPress.
 */
interface Paymenku_Transport {

	/**
	 * Kirim permintaan HTTP.
	 *
	 * @param string               $method GET atau POST.
	 * @param string               $url    URL lengkap.
	 * @param array<string, mixed> $args   Berisi headers dan body.
	 *
	 * @return array{status:int, headers:array<string,string>, body:string}
	 *
	 * @throws Paymenku_Api_Exception Bila koneksi gagal.
	 */
	public function request( $method, $url, array $args );
}

/**
 * Transport bawaan memakai WP HTTP API.
 */
class Paymenku_Wp_Transport implements Paymenku_Transport {

	/**
	 * @param string               $method Method HTTP.
	 * @param string               $url    URL.
	 * @param array<string, mixed> $args   Args.
	 *
	 * @return array{status:int, headers:array<string,string>, body:string}
	 *
	 * @throws Paymenku_Api_Exception Bila koneksi gagal.
	 */
	public function request( $method, $url, array $args ) {
		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 30,
				'headers' => isset( $args['headers'] ) ? $args['headers'] : array(),
				'body'    => isset( $args['body'] ) ? $args['body'] : null,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Paymenku_Api_Exception( $response->get_error_message() );
		}

		$headers = wp_remote_retrieve_headers( $response );

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => is_object( $headers ) && method_exists( $headers, 'getAll' ) ? $headers->getAll() : (array) $headers,
			'body'    => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
