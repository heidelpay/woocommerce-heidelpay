<?php
/**
 * Subscription trait
 *
 * Trait supporting Subscriptions
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2018-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Florian Evertz
 *
 * @package  woocommerce-heidelpay
 * @category WooCommerce
 */

trait WC_Heidelpay_Subscription_Gateway
{
    public function constructerAddon()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            $this->supports = array(
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change'
            );

            add_action(
                'woocommerce_scheduled_subscription_payment_' . $this->id,
                array($this, 'scheduledSubscriptionPayment'),
                10,
                2
            );
        }
    }

    /**
     * @param $amount float
     * @param $renewalOrder WC_Order
     * @return array|null
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     */
    public function scheduledSubscriptionPayment($amount, $renewalOrder)
    {
        /** @var WC_Order $order */
        //$order = wcs_get_order
        $order = WC_Subscriptions_Renewal_Order::get_parent_order($renewalOrder->get_id());
        parent::prepareRequest($renewalOrder);
        $this->payMethod->getRequest()->getFrontend()->setEnabled('FALSE');

        try {
            $this->payMethod->debitOnRegistration($order->get_meta('heidelpay-Registration'));
        } catch (Exception $e) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, $e);
            return null;
        }

        /** @var \Heidelpay\PhpPaymentApi\Response $response */
        $response = $this->payMethod->getResponse();

        if ($this->payMethod->getResponse()->isSuccess()) {
            $renewalOrder->payment_complete($response->getIdentification()->getShortId());
            /* funktioniert fast. Response muss noch Subscription tauglich gemacht werden
            wc_get_logger()->log(WC_Log_Levels::DEBUG, $_POST);
            if (!empty($_POST)) {
                $response = new WC_Heidelpay_Response();
                $response->init($_POST, $this->get_option('secret'));
            }
            */
        }
        if ($this->payMethod->getResponse()->isError()) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getResponse()->getError(), 1));
        }
        return null;
    }
}
