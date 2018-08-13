<?php
/**
 * Credit Card
 *
 * WooCommerce payment gateway for Credit Card
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2018-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Daniel Kraut, David Owusu, Florian Evertz
 *
 * @package  woocommerce-heidelpay
 * @category WooCommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-iframe-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod;

class WC_Gateway_HP_CC extends WC_Heidelpay_IFrame_Gateway
{
    /**
     * WC_Gateway_HP_CC constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->supports = array(
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change'
        );
    }

    /**
     * sets the Payment Method.
     */
    public function setPayMethod()
    {
        $this->payMethod = new CreditCardPaymentMethod();
        $this->id = 'hp_cc';
        $this->name = __('Credit Card', 'woocommerce-heidelpay');
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $this->prepareRequest($order);

        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::get_total_initial_payment($order));
        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::get_recurring_total($order));
        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::get_subscription_period($order));
        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::get_subscription_length($order));
        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::order_contains_subscription($order));
        wc_get_logger()->log(WC_Log_Levels::DEBUG, WC_Subscriptions_Order::get_subscription_interval($order));

        //return $this->performRequest($order_id);
    }

}
