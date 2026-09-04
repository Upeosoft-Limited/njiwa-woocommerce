<?php
/**
 * Talking to Njiwa. Transport only.
 *
 * Everything goes through WordPress's own HTTP layer rather than curl, so a
 * host that filters outbound requests, or a shop behind a proxy, behaves the
 * same here as it does for every other plugin.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Client {

	const DEFAULT_BASE_URL = 'https://njiwa.upeo.ai';

	/** Long enough for a slow line, short enough that nothing holds a queue worker. */
	const TIMEOUT = 20;

	public static function api_key() {
		return trim( (string) get_option( 'njiwa_wc_api_key', '' ) );
	}

	public static function base_url() {
		$url = trim( (string) get_option( 'njiwa_wc_base_url', self::DEFAULT_BASE_URL ) );
		return untrailingslashit( $url === '' ? self::DEFAULT_BASE_URL : $url );
	}

	public static function is_configured() {
		return self::api_key() !== '';
	}

	public static function is_test_key() {
		return strpos( self::api_key(), 'sk_test_' ) === 0;
	}

	/**
	 * Send one text message.
	 *
	 * @param string $to              Recipient, in full international form.
	 * @param string $text            The message.
	 * @param string $idempotency_key Optional. Njiwa honours it for 24 hours,
	 *                                so a retried job replays the first answer
	 *                                instead of messaging the customer twice.
	 * @return array Njiwa's answer, including the message id.
	 * @throws Njiwa_WC_Exception
	 */
	public static function send_text( $to, $text, $idempotency_key = '' ) {
		$headers = array();
		if ( $idempotency_key !== '' ) {
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$body = array(
			'to'   => $to,
			'text' => $text,
		);

		// Only when the shop named a number. Left out, Njiwa uses the account's
		// default, which is the right answer for the shops that have one number
		// and never think about this again.
		$from = preg_replace( '/\D/', '', (string) get_option( 'njiwa_wc_from', '' ) );
		if ( '' !== $from ) {
			$body['from'] = $from;
		}

		return self::request( 'POST', '/v1/messages', $body, $headers );
	}

	/**
	 * The WhatsApp numbers on this account, linked or not.
	 *
	 * @throws Njiwa_WC_Exception
	 */
	public static function numbers() {
		$answer = self::request( 'GET', '/v1/instances' );
		return isset( $answer['data'] ) && is_array( $answer['data'] ) ? $answer['data'] : array();
	}

	/**
	 * @throws Njiwa_WC_Exception
	 */
	protected static function request( $method, $path, $body = null, $headers = array() ) {
		$key = self::api_key();
		if ( $key === '' ) {
			throw new Njiwa_WC_Exception(
				__( 'There is no Njiwa API key saved, so nothing can be sent.', 'njiwa-for-woocommerce' ),
				'not_configured'
			);
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array_merge(
				array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
					'User-Agent'    => 'njiwa-woocommerce/' . NJIWA_WC_VERSION,
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::base_url() . $path, $args );

		if ( is_wp_error( $response ) ) {
			// A network failure is not a send failure: the message was never
			// accepted, so trying again later is safe.
			throw new Njiwa_WC_Exception(
				sprintf(
					/* translators: 1: the Njiwa address, 2: the underlying error */
					__( 'Could not reach Njiwa at %1$s. %2$s', 'njiwa-for-woocommerce' ),
					self::base_url(),
					$response->get_error_message()
				),
				'connection_failed'
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		if ( $status >= 400 ) {
			$error = isset( $decoded['error'] ) && is_array( $decoded['error'] ) ? $decoded['error'] : array();
			throw new Njiwa_WC_Exception(
				isset( $error['message'] ) ? $error['message'] : sprintf(
					/* translators: %d: an HTTP status code */
					__( 'Njiwa answered with HTTP %d.', 'njiwa-for-woocommerce' ),
					$status
				),
				isset( $error['code'] ) ? $error['code'] : 'unknown',
				$status,
				isset( $error['docs'] ) ? $error['docs'] : null
			);
		}

		return $decoded;
	}

	/** Everything this plugin does that is worth finding later, in one log. */
	public static function log( $message, $level = 'error' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'njiwa' ) );
		}
	}
}
