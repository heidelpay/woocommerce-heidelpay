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
    /**
     * constructor for subscription support
     */
    public function constructorAddon()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            $this->supports = array(
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
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
     * additional formfields for admin backend
     */
    public function initFormFieldsAddon()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            $this->form_fields['transaction_channel_subscription'] = array(
                'title' => __('Transaction Channel for Subscriptions', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_transaction_channel_subscriptions',
                'description' => 'Transaction Channel for Subscriptions',
                'default' => '');
        }
    }

    /**
     * @param $amount float
     * @param $renewalOrder WC_Order
     * @return array|null
     * @throws Exception
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
            wc_get_logger()->error($e, array('source' => 'heidelpay'));
            return null;
        }

        /** @var Response $response */
        $response = $this->payMethod->getResponse();

        if ($this->payMethod->getResponse()->isSuccess()) {
            $renewalOrder->payment_complete($response->getIdentification()->getShortId());
        }
        if ($this->payMethod->getResponse()->isError()) {
            wc_get_logger()->error(
                print_r($this->payMethod->getResponse()->getError(), 1),
                array('source' => 'heidelpay')
            );
        }
        return null;
    }
}
