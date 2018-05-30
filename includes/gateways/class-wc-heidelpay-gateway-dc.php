<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * debit card
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-iframe-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\DebitCardPaymentMethod;

class WC_Gateway_HP_DC extends WC_Heidelpay_IFrame_Gateway {

    public function setPayMethod()
    {
        $this->payMethod = new DebitCardPaymentMethod();
        $this->id = 'hp_dc';
        $this->name = __('Debit Card', 'woocommerce-heidelpay');
    }
}
