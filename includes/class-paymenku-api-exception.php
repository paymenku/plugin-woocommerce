<?php
/**
 * Exception dari API Paymenku.
 *
 * @package Paymenku_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Galat API dengan status HTTP dan detail validasi.
 */
class Paymenku_Api_Exception extends Exception {

	/** @var int */
	private $status_code;

	/** @var array<string, array<int, string>> */
	private $errors;

	/** @var string */
	private $error_code;

	/**
	 * @param string                               $message    Pesan galat.
	 * @param int                                  $status_code Status HTTP (0 untuk galat jaringan).
	 * @param array<string, array<int, string>>    $errors     Galat per field.
	 * @param string                               $error_code Kode galat penyedia bila ada.
	 */
	public function __construct( $message, $status_code = 0, array $errors = array(), $error_code = '' ) {
		parent::__construct( $message );

		$this->status_code = (int) $status_code;
		$this->errors      = $errors;
		$this->error_code  = (string) $error_code;
	}

	/**
	 * @return int
	 */
	public function get_status_code() {
		return $this->status_code;
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * @return string
	 */
	public function get_error_code() {
		return $this->error_code;
	}
}
