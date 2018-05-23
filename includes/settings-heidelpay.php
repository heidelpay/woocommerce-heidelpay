<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_heidelpay_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-heidelpay' ),
			'label'       => __( 'Enable Heidelpay', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-heidelpay' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-heidelpay' ),
			'default'     => __( 'Credit Card (Heidelpay)', 'woocommerce-heidelpay' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-heidelpay' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-heidelpay' ),
			'default'     => __( 'Pay with your credit card via Heidelpay.', 'woocommerce-heidelpay' ),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woocommerce-heidelpay' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-heidelpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'secret_key' => array(
			'title'       => __( 'Live Secret Key', 'woocommerce-heidelpay' ),
			'type'        => 'text',
			'description' => __( 'secret', 'woocommerce-heidelpay' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'capture' => array(
			'title'       => __( 'Capture', 'woocommerce-heidelpay' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-heidelpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'heidelpay_after_checkout' => array(
			'title'       => __( 'Heidelpay Checkout', 'woocommerce-heidelpay' ),
			'label'       => __( 'Enable Heidelpay after Checkout', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, this option shows a "pay" button and modal credit card form on the checkout, instead of credit card fields directly on the page.', 'woocommerce-heidelpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'saved_cards' => array(
			'title'       => __( 'Saved Cards', 'woocommerce-heidelpay' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Heidelpay servers, not on your store.', 'woocommerce-heidelpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-heidelpay' ),
			'label'       => __( 'Log debug messages', 'woocommerce-heidelpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-heidelpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
