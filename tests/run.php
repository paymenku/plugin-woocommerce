<?php
/**
 * Uji unit tanpa WordPress: jalankan `php tests/run.php`.
 *
 * @package Paymenku_WooCommerce
 */

require_once __DIR__ . '/bootstrap.php';

$failures = 0;
$checks   = 0;

/**
 * @param bool   $condition Kondisi.
 * @param string $label     Label.
 */
function check( $condition, $label ) {
	global $failures, $checks;

	$checks++;

	if ( ! $condition ) {
		$failures++;
		echo "GAGAL: {$label}\n";
	}
}

$transport = new Fake_Transport();
$api       = new Paymenku_Api( 'sk_test_abc', 'https://api.example.test/api/v1/', $transport );

$transport->responses[] = array(
	'status'  => 200,
	'headers' => array(),
	'body'    => '{"status":"success","data":{"trx_id":"TRX-1","pay_url":"https://pay.example/1","amount":"15000.00","payment_info":{"qr_url":"https://qr.example/1"}}}',
);

$trx = $api->create_transaction(
	array(
		'channel_code' => 'qris',
		'amount'       => 15000,
		'reference_id' => 'WC-1',
	),
	'wc-1-abc'
);

$request = $transport->requests[0];

check( 'POST' === $request['method'], 'create memakai POST' );
check( 'https://api.example.test/api/v1/transaction/create' === $request['url'], 'base URL tanpa garis miring ganda' );
check( 'Bearer sk_test_abc' === $request['args']['headers']['Authorization'], 'header Authorization' );
check( 'wc-1-abc' === $request['args']['headers']['Idempotency-Key'], 'header Idempotency-Key' );
check( 'application/json' === $request['args']['headers']['Content-Type'], 'header Content-Type' );
check( false !== strpos( $request['args']['body'], '"channel_code":"qris"' ), 'body berisi channel_code' );
check( 'TRX-1' === $trx['trx_id'], 'data trx_id terbaca' );
check( 'https://pay.example/1' === $trx['pay_url'], 'data pay_url terbaca' );

$transport->responses[] = array(
	'status'  => 422,
	'headers' => array(),
	'body'    => '{"status":"error","message":"Validation failed","errors":{"amount":["validation.min"]},"error_code":"VALIDATION"}',
);

try {
	$api->create_transaction( array( 'amount' => 0 ) );
	check( false, 'galat 422 harus dilempar' );
} catch ( Paymenku_Api_Exception $e ) {
	check( 422 === $e->get_status_code(), 'status 422 terbaca' );
	check( 'Validation failed' === $e->getMessage(), 'pesan galat terbaca' );
	check( isset( $e->get_errors()['amount'] ), 'galat per field terbaca' );
	check( 'VALIDATION' === $e->get_error_code(), 'error_code terbaca' );
}

$transport->responses[] = array(
	'status'  => 200,
	'headers' => array(),
	'body'    => '{"status":"error","success":false,"message":"Kanal tidak aktif"}',
);

try {
	$api->create_transaction( array( 'amount' => 1000 ) );
	check( false, 'envelope error 200 harus dilempar' );
} catch ( Paymenku_Api_Exception $e ) {
	check( 'Kanal tidak aktif' === $e->getMessage(), 'pesan envelope error 200' );
}

$transport->responses[] = array(
	'status'  => 200,
	'headers' => array(),
	'body'    => '<html>bukan json</html>',
);

try {
	$api->get_transaction( 'TRX/1' );
	check( false, 'respons non-JSON harus dilempar' );
} catch ( Paymenku_Api_Exception $e ) {
	check( 0 < $e->get_status_code(), 'status pada respons non-JSON' );
}

check( 'https://api.example.test/api/v1/check-status/TRX%2F1' === $transport->requests[3]['url'], 'path get di-escape' );

$transport->responses[] = array(
	'status'  => 200,
	'headers' => array(),
	'body'    => '{"status":"success","data":{"message":"Refund diproses"}}',
);

$api->refund_transaction( 'TRX-1', 5000, 'Barang rusak' );
$refund = $transport->requests[4];

check( false !== strpos( $refund['url'], '/transaction/refund' ), 'path refund' );
check( false !== strpos( $refund['args']['body'], '"refund_amount":5000' ), 'nominal refund' );
check( false !== strpos( $refund['args']['body'], '"reason":"Barang rusak"' ), 'alasan refund' );

$transport->responses[] = array(
	'status'  => 200,
	'headers' => array(),
	'body'    => '{"status":"success","data":[{"code":"qris","name":"QRIS"}]}',
);

$channels = $api->get_channels();
check( 'qris' === $channels[0]['code'], 'daftar kanal terbaca' );

$body      = '{"event":"payment.status_updated","status":"paid"}';
$timestamp = '1750000000';
$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, 'secret-123' );

check( Paymenku_Webhook::verify( $body, $timestamp, $signature, 'secret-123' ), 'signature valid' );
check( ! Paymenku_Webhook::verify( $body, $timestamp, $signature, 'salah' ), 'signature secret salah ditolak' );
check( ! Paymenku_Webhook::verify( $body . ' ', $timestamp, $signature, 'secret-123' ), 'body berubah ditolak' );
check( ! Paymenku_Webhook::verify( $body, $timestamp, '', 'secret-123' ), 'signature kosong ditolak' );
check( Paymenku_Webhook::verify( $body, $timestamp, strtoupper( $signature ), 'secret-123' ), 'signature huruf besar diterima' );

echo "\n{$checks} pemeriksaan, {$failures} gagal\n";
exit( $failures > 0 ? 1 : 0 );
