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
require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'traits' .
    DIRECTORY_SEPARATOR . 'trait-wc-heidelpay-subscription-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod;

class WC_Gateway_HP_CC extends WC_Heidelpay_IFrame_Gateway
{
    use WC_Heidelpay_Subscription_Gateway;

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

        if (class_exists('WC_Subscriptions_Order')) {
            add_action(
                'woocommerce_scheduled_subscription_payment_' . $this->id,
                array($this, 'scheduledSubscriptionPayment'),
                10,
                2
            );
        }
    }

    /**
     * sets the Payment Method.
     */
    public function setPayMethod()
    {
        /** @var \Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod payMethod */
        $this->payMethod = new CreditCardPaymentMethod();
        $this->id = 'hp_cc';
        $this->name = __('Credit Card', 'woocommerce-heidelpay');
    }
}
