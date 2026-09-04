<?php
/**
 * When a message goes out, and to whom.
 *
 * One rule runs the whole plugin: an order reaching a status sends the message
 * for that status, once. Nothing is sent while the customer waits at the
 * checkout, and nothing that fails here is ever allowed to break an order.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Notifier {

	/** The queued job that does the actual sending. */
	const HOOK = 'njiwa_wc_send';

	/**
	 * The statuses that are worth telling a customer about, and what each one
	 * is called on the settings page.
	 *
	 * @return array<string,string>
	 */
	public static function customer_events() {
		return array(
			'on-hold'    => __( 'Order placed, payment not in yet', 'njiwa-for-woocommerce' ),
			'processing' => __( 'Payment received', 'njiwa-for-woocommerce' ),
			'completed'  => __( 'Order completed', 'njiwa-for-woocommerce' ),
			'cancelled'  => __( 'Order cancelled', 'njiwa-for-woocommerce' ),
			'refunded'   => __( 'Order refunded', 'njiwa-for-woocommerce' ),
		);
	}

	/**
	 * When you get told about a new order.
	 *
	 * Not when the order row appears, which happens the moment somebody
	 * reaches the payment page and often means nothing. These are the statuses
	 * that mean an order is real, and the alert goes out on the first of them
	 * to arrive, once per order.
	 *
	 * @return array<int,string>
	 */
	public static function admin_alert_statuses() {
		return array( 'on-hold', 'processing', 'completed' );
	}

	public static function boot() {
		// One hook per status rather than woocommerce_order_status_changed,
		// which does not fire when an order is created directly into a status.
		// Some gateways and every admin-created order do exactly that, and
		// those are orders somebody would never hear about.
		foreach ( array_keys( self::customer_events() ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'on_status' ), 20, 2 );
		}

		add_action( self::HOOK, array( __CLASS__, 'deliver' ), 10, 3 );
	}

	public static function is_on() {
		return 'yes' === get_option( 'njiwa_wc_enabled', 'yes' ) && Njiwa_WC_Client::is_configured();
	}

	/**
	 * @param int      $order_id
	 * @param WC_Order $order
	 */
	public static function on_status( $order_id, $order = null ) {
		if ( ! self::is_on() ) {
			return;
		}

		$status = substr( (string) current_action(), strlen( 'woocommerce_order_status_' ) );
		$order  = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		try {
			self::tell_the_customer( $order, $status );
			self::tell_the_shop( $order, $status );
		} catch ( Exception $e ) {
			// An order must never fail to change status because a message
			// could not be arranged.
			Njiwa_WC_Client::log( 'Could not queue a message for order ' . $order_id . ': ' . $e->getMessage() );
		}
	}

	protected static function tell_the_customer( $order, $status ) {
		if ( ! array_key_exists( $status, self::customer_events() ) ) {
			return;
		}
		if ( 'yes' !== get_option( 'njiwa_wc_event_' . $status, 'no' ) ) {
			return;
		}
		if ( $order->get_meta( '_njiwa_queued_' . $status ) ) {
			// An order can arrive at the same status twice. The customer does
			// not need telling twice.
			return;
		}

		$number = Njiwa_WC_Numbers::to_msisdn( $order->get_billing_phone(), $order->get_billing_country() );
		if ( '' === $number ) {
			$order->add_order_note( __( 'Njiwa: no WhatsApp message, because this order has no billing phone number.', 'njiwa-for-woocommerce' ) );
			return;
		}

		$order->update_meta_data( '_njiwa_queued_' . $status, current_time( 'mysql' ) );
		$order->save();

		self::queue( $order->get_id(), $status, $number );
	}

	protected static function tell_the_shop( $order, $status ) {
		if ( 'yes' !== get_option( 'njiwa_wc_event_admin', 'no' ) ) {
			return;
		}
		if ( ! in_array( $status, self::admin_alert_statuses(), true ) ) {
			return;
		}
		if ( $order->get_meta( '_njiwa_alerted' ) ) {
			return;
		}

		$numbers = Njiwa_WC_Numbers::parse_list( get_option( 'njiwa_wc_admin_numbers', '' ) );
		if ( empty( $numbers ) ) {
			return;
		}

		$order->update_meta_data( '_njiwa_alerted', current_time( 'mysql' ) );
		$order->save();

		foreach ( $numbers as $number ) {
			self::queue( $order->get_id(), 'admin', $number );
		}
	}

	/**
	 * Hand the send to a background worker.
	 *
	 * WooCommerce ships Action Scheduler, so on any real shop this returns
	 * immediately and the message goes out a moment later. The checkout is
	 * never held up by somebody else's network.
	 */
	protected static function queue( $order_id, $event, $number ) {
		$args = array( $order_id, $event, $number );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, $args, 'njiwa' );
			return;
		}
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK, $args );
			return;
		}

		self::deliver( $order_id, $event, $number );
	}

	/**
	 * The worker. Runs after the customer has been sent on their way.
	 */
	public static function deliver( $order_id, $event, $number ) {
		if ( ! self::is_on() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// The default matters: a shop that ticked an event but never opened the
		// template box still has a message to send.
		$template = get_option( 'njiwa_wc_template_' . $event, Njiwa_WC_Templates::default_for( $event ) );
		$message  = Njiwa_WC_Templates::render( $template, $order );
		if ( '' === $message ) {
			Njiwa_WC_Client::log( 'The message template for "' . $event . '" is empty, so order ' . $order_id . ' sent nothing.', 'warning' );
			return;
		}

		try {
			$answer = Njiwa_WC_Client::send_text( $number, $message, self::idempotency_key( $order_id, $event, $number ) );

			$order->add_order_note(
				sprintf(
					/* translators: 1: the WhatsApp number, 2: Njiwa's message id */
					__( 'Njiwa: WhatsApp sent to %1$s (%2$s).', 'njiwa-for-woocommerce' ),
					'+' . $number,
					isset( $answer['id'] ) ? $answer['id'] : '?'
				)
				. ( Njiwa_WC_Client::is_test_key() ? ' ' . __( 'Test key, so nothing reached WhatsApp.', 'njiwa-for-woocommerce' ) : '' )
			);
		} catch ( Njiwa_WC_Exception $e ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: the WhatsApp number, 2: the reason */
					__( 'Njiwa: could not WhatsApp %1$s. %2$s', 'njiwa-for-woocommerce' ),
					'+' . $number,
					$e->getMessage()
				)
			);
			Njiwa_WC_Client::log(
				sprintf( 'Order %d, %s: %s (%s)', $order_id, $event, $e->getMessage(), $e->get_error_code() )
			);
		}
	}

	/**
	 * One key per order, event and recipient.
	 *
	 * Njiwa honours it for 24 hours, so a job that runs twice, or a queue that
	 * retries after a timeout, replays the first answer instead of messaging
	 * the customer again. The recipient is part of the key because one alert
	 * can go to several of your own numbers, and they must not collapse into
	 * one another.
	 */
	protected static function idempotency_key( $order_id, $event, $number ) {
		return 'wc-' . substr( md5( home_url() ), 0, 8 ) . '-' . $order_id . '-' . $event . '-' . substr( md5( $number ), 0, 6 );
	}
}
