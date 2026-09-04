<?php
/**
 * Anything Njiwa refused, or could not be asked.
 *
 * get_error_code() is the stable, machine readable reason and is the thing to
 * branch on. The wording of the message can change; the code does not.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Exception extends Exception {

	protected $error_code;
	protected $status;
	protected $docs;

	public function __construct( $message, $error_code = 'unknown', $status = 0, $docs = null ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
		$this->status     = (int) $status;
		$this->docs       = $docs;
	}

	public function get_error_code() {
		return $this->error_code;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_docs() {
		return $this->docs;
	}
}
