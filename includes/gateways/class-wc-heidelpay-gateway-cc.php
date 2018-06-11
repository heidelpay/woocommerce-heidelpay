<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * credit card
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-iframe-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod;

class WC_Gateway_HP_CC extends WC_Heidelpay_IFrame_Gateway
{
    public function setPayMethod()
    {
        $this->payMethod = new CreditCardPaymentMethod();
        $this->id = 'hp_cc';
        $this->name = __('Credit Card', 'woocommerce-heidelpay');
    }
}
