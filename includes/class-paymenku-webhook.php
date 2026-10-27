<?php
/**
 * Verifikasi tanda tangan webhook Paymenku.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper statis tanpa ketergantungan WordPress.
 */
class Paymenku_Webhook {

	/**
	 * Verifikasi header X-PaymenKu-Signature.
	 *
	 * Rumus: HMAC-SHA256(timestamp + "." + body mentah, secret).
	 *
	 * @param string $raw_body  Body mentah.
	 * @param string $timestamp Header X-PaymenKu-Timestamp.
	 * @param string $signature Header X-PaymenKu-Signature.
	 * @param string $secret    Webhook secret.
	 *
	 * @return bool
	 */
	public static function verify( $raw_body, $timestamp, $signature, $secret ) {
		$raw_body  = (string) $raw_body;
		$timestamp = (string) $timestamp;
		$signature = strtolower( trim( (string) $signature ) );
		$secret    = (string) $secret;

		if ( '' === $secret || '' === $timestamp || '' === $signature ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );

		return hash_equals( $expected, $signature );
	}
}
