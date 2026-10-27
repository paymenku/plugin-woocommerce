<?php
/**
 * Bootstrap pengujian tanpa WordPress.
 *
 * @package Paymenku_WooCommerce
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'PAYMENKU_WC_VERSION', '0.1.0' );

require_once __DIR__ . '/../includes/class-paymenku-api-exception.php';
require_once __DIR__ . '/../includes/class-paymenku-transport.php';
require_once __DIR__ . '/../includes/class-paymenku-api.php';
require_once __DIR__ . '/../includes/class-paymenku-webhook.php';

/**
 * Transport palsu untuk merekam permintaan.
 */
class Fake_Transport implements Paymenku_Transport {

	/** @var array<int, array<string, mixed>> */
	public $requests = array();

	/** @var array<int, array<string, mixed>> */
	public $responses = array();

	/**
	 * @param string               $method Method.
	 * @param string               $url    URL.
	 * @param array<string, mixed> $args   Args.
	 *
	 * @return array{status:int, headers:array<string,string>, body:string}
	 */
	public function request( $method, $url, array $args ) {
		$this->requests[] = array(
			'method' => $method,
			'url'    => $url,
			'args'   => $args,
		);

		if ( empty( $this->responses ) ) {
			return array(
				'status'  => 200,
				'headers' => array(),
				'body'    => '{"status":"success","data":{}}',
			);
		}

		return array_shift( $this->responses );
	}
}
