<?php
/**
 * Removing the plugin removes the key.
 *
 * A live API key left in wp_options after somebody deleted the plugin is a key
 * nobody is looking after any more. The order notes stay, because they are a
 * record of what was sent and they belong to the order rather than to us.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$njiwa_wc_options = array(
	'njiwa_wc_enabled',
	'njiwa_wc_api_key',
	'njiwa_wc_base_url',
	'njiwa_wc_from',
	'njiwa_wc_admin_numbers',
	'njiwa_wc_event_admin',
	'njiwa_wc_template_admin',
);

foreach ( array( 'on-hold', 'processing', 'completed', 'cancelled', 'refunded' ) as $njiwa_wc_status ) {
	$njiwa_wc_options[] = 'njiwa_wc_event_' . $njiwa_wc_status;
	$njiwa_wc_options[] = 'njiwa_wc_template_' . $njiwa_wc_status;
}

foreach ( $njiwa_wc_options as $njiwa_wc_option ) {
	delete_option( $njiwa_wc_option );
}
