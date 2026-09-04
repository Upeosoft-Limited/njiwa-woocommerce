<?php
/**
 * Plugin Name:       Njiwa for WooCommerce
 * Plugin URI:        https://njiwa.upeo.ai
 * Description:       WhatsApp your customers when their order is paid, sent or cancelled, and get a message yourself when one comes in.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            UPEO.AI
 * Author URI:        https://upeo.ai
 * License:           MIT
 * Text Domain:       njiwa-for-woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 *
 * PHP 7.4 is the floor on purpose. Plenty of shops that would benefit from
 * this are on hosting that has not moved, and a plugin they cannot install is
 * worth nothing to them.
 */

defined( 'ABSPATH' ) || exit;

define( 'NJIWA_WC_VERSION', '0.1.0' );
define( 'NJIWA_WC_FILE', __FILE__ );
define( 'NJIWA_WC_PATH', plugin_dir_path( __FILE__ ) );

require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-exception.php';
require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-client.php';
require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-numbers.php';
require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-templates.php';
require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-notifier.php';

/**
 * Orders can live in their own tables now rather than in posts. This plugin
 * only ever reaches an order through wc_get_order() and the order's own
 * methods, so it does not care which, and says so: without this declaration
 * WooCommerce disables the new storage for the whole shop.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NJIWA_WC_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'njiwa_wc_start' );

function njiwa_wc_start() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'njiwa_wc_needs_woocommerce' );
		return;
	}

	Njiwa_WC_Notifier::boot();

	if ( is_admin() ) {
		require_once NJIWA_WC_PATH . 'includes/class-njiwa-wc-settings-page.php';
		add_filter( 'woocommerce_get_settings_pages', 'njiwa_wc_add_settings_page' );
		add_filter( 'plugin_action_links_' . plugin_basename( NJIWA_WC_FILE ), 'njiwa_wc_settings_link' );
	}
}

function njiwa_wc_add_settings_page( $pages ) {
	$pages[] = new Njiwa_WC_Settings_Page();
	return $pages;
}

function njiwa_wc_settings_link( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=njiwa' ) ) . '">'
		. esc_html__( 'Settings', 'njiwa-for-woocommerce' ) . '</a>'
	);
	return $links;
}

function njiwa_wc_needs_woocommerce() {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Njiwa for WooCommerce needs WooCommerce, which is not active. Nothing is being sent.', 'njiwa-for-woocommerce' )
		. '</p></div>';
}
