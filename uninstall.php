<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
	// Delete options.
	delete_option( 'woocommerce_heidelpay_settings' );
	delete_option( 'wc_heidelpay_show_ssl_notice' );
	delete_option( 'wc_heidelpay_show_keys_notice' );
	delete_option( 'wc_heidelpay_version' );
	delete_option( 'woocommerce_heidelpay_ideal_settings' );
	delete_option( 'woocommerce_heidelpay_giropay_settings' );
	delete_option( 'woocommerce_heidelpay_sepa_settings' );
	delete_option( 'woocommerce_heidelpay_sofort_settings' );
}
