<?php
/**
 * The message itself.
 *
 * A template is plain text with placeholders in braces. Every placeholder the
 * shop can use is listed in PLACEHOLDERS below, and that same list is what the
 * settings page prints, so the documentation cannot drift from the code.
 */

defined( 'ABSPATH' ) || exit;

class Njiwa_WC_Templates {

	/** WhatsApp takes 4096 characters. Stopping short leaves room for a footer. */
	const MAX_LENGTH = 4000;

	/** How many order lines {items} prints before it starts counting instead. */
	const MAX_ITEMS = 10;

	/**
	 * Placeholder => what it is replaced with, in the shop's own words.
	 *
	 * @return array<string,string>
	 */
	public static function placeholders() {
		return array(
			'{first_name}'     => __( 'The billing first name, or "there" if the order has none.', 'njiwa-for-woocommerce' ),
			'{last_name}'      => __( 'The billing last name.', 'njiwa-for-woocommerce' ),
			'{customer_name}'  => __( 'Both names together.', 'njiwa-for-woocommerce' ),
			'{order_number}'   => __( 'The order number as the customer sees it, including any prefix.', 'njiwa-for-woocommerce' ),
			'{order_total}'    => __( 'The total, with your currency symbol.', 'njiwa-for-woocommerce' ),
			'{order_date}'     => __( 'The date the order was placed, in your site format.', 'njiwa-for-woocommerce' ),
			'{order_status}'   => __( 'The status the order has just moved to.', 'njiwa-for-woocommerce' ),
			'{payment_method}' => __( 'How they paid, as shown on the order.', 'njiwa-for-woocommerce' ),
			'{items}'          => __( 'One line per item, as "2 x Blue shirt".', 'njiwa-for-woocommerce' ),
			'{item_count}'     => __( 'How many items in total.', 'njiwa-for-woocommerce' ),
			'{shop_name}'      => __( 'Your shop name.', 'njiwa-for-woocommerce' ),
			'{order_url}'      => __( 'A link the customer can open to see their own order.', 'njiwa-for-woocommerce' ),
			'{admin_url}'      => __( 'A link that opens the order in your dashboard. Only put this in the message to yourself.', 'njiwa-for-woocommerce' ),
		);
	}

	/**
	 * What each message says before anybody edits it.
	 *
	 * These live here rather than on the settings page because the queue
	 * worker that sends a message never loads the admin, and a shop that has
	 * saved the settings page exactly zero times must still send something
	 * sensible.
	 *
	 * They are deliberately short. A WhatsApp message that reads like an email
	 * gets read like an email, which is to say not at all.
	 */
	public static function default_for( $event ) {
		$defaults = array(
			'on-hold'    => __( "Hi {first_name}, we have your order {order_number} for {order_total}. We will let you know the moment your payment comes through.\n\n{shop_name}", 'njiwa-for-woocommerce' ),
			'processing' => __( "Hi {first_name}, thank you. Your payment for order {order_number} came through and we are getting it ready.\n\n{items}\n\nTotal {order_total}\n{shop_name}", 'njiwa-for-woocommerce' ),
			'completed'  => __( "Hi {first_name}, order {order_number} is done and on its way to you. Thank you for shopping with {shop_name}.", 'njiwa-for-woocommerce' ),
			'cancelled'  => __( "Hi {first_name}, order {order_number} has been cancelled and you have not been charged. If that was not you, reply to this message and we will look into it.\n\n{shop_name}", 'njiwa-for-woocommerce' ),
			'refunded'   => __( "Hi {first_name}, we have refunded {order_total} for order {order_number}. Banks take a few days to show it.\n\n{shop_name}", 'njiwa-for-woocommerce' ),
			'admin'      => __( "New order {order_number} on {shop_name}.\n\n{customer_name}\n{item_count} item(s), {order_total}\nPaid by {payment_method}\n\n{admin_url}", 'njiwa-for-woocommerce' ),
		);

		return isset( $defaults[ $event ] ) ? $defaults[ $event ] : '';
	}

	/**
	 * @param string   $template Raw template text.
	 * @param WC_Order $order
	 * @return string The message, or '' if the template is empty.
	 */
	public static function render( $template, $order ) {
		$template = trim( (string) $template );
		if ( '' === $template ) {
			return '';
		}

		$values  = self::values( $order );
		$message = strtr( $template, $values );

		// Anything still in braces is a placeholder that does not exist,
		// usually a typo. Sending "{order_no}" to a customer looks broken, so
		// it comes out and the shop is told where to look.
		if ( preg_match_all( '/\{[a-z_]+\}/', $message, $found ) ) {
			Njiwa_WC_Client::log(
				sprintf(
					'Unknown placeholder %s in a message template. It was removed before sending.',
					implode( ', ', array_unique( $found[0] ) )
				),
				'warning'
			);
			$message = preg_replace( '/\{[a-z_]+\}/', '', $message );
		}

		$message = trim( preg_replace( '/\n{3,}/', "\n\n", $message ) );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) > self::MAX_LENGTH ) {
			$message = mb_substr( $message, 0, self::MAX_LENGTH - 1 ) . '…';
		}

		return $message;
	}

	/**
	 * @param WC_Order $order
	 * @return array<string,string>
	 */
	protected static function values( $order ) {
		$first = $order->get_billing_first_name();

		return array(
			'{first_name}'     => $first !== '' ? $first : __( 'there', 'njiwa-for-woocommerce' ),
			'{last_name}'      => $order->get_billing_last_name(),
			'{customer_name}'  => trim( $order->get_formatted_billing_full_name() ),
			'{order_number}'   => $order->get_order_number(),
			'{order_total}'    => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			'{order_date}'     => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
			'{order_status}'   => wc_get_order_status_name( $order->get_status() ),
			'{payment_method}' => $order->get_payment_method_title(),
			'{items}'          => self::items( $order ),
			'{item_count}'     => (string) $order->get_item_count(),
			'{shop_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{order_url}'      => $order->get_view_order_url(),
			'{admin_url}'      => $order->get_edit_order_url(),
		);
	}

	/**
	 * @param WC_Order $order
	 */
	protected static function items( $order ) {
		$lines = array();
		$more  = 0;

		foreach ( $order->get_items() as $item ) {
			if ( count( $lines ) >= self::MAX_ITEMS ) {
				++$more;
				continue;
			}
			$lines[] = sprintf( '%d x %s', (int) $item->get_quantity(), wp_strip_all_tags( $item->get_name() ) );
		}

		if ( $more > 0 ) {
			/* translators: %d: how many further items are on the order */
			$lines[] = sprintf( _n( 'and %d more item', 'and %d more items', $more, 'njiwa-for-woocommerce' ), $more );
		}

		return implode( "\n", $lines );
	}
}
