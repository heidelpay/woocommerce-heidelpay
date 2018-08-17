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
     * @param $amount float
     * @param $renewalOrder WC_Order
     * @return array|null
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     */
    public function scheduledSubscriptionPayment($amount, $renewalOrder)
    {
        /** @var WC_Order $order */
        $order = WC_Subscriptions_Renewal_Order::get_parent_order($renewalOrder->get_id());
        //TODO: Debit on Registration
        parent::prepareRequest($renewalOrder);

        wc_get_logger()->log(
            WC_Log_Levels::DEBUG,
            'REQUEST::::::::::::: ' . print_r($this->payMethod->getRequest(), 1)
        );

        try {
            $this->payMethod->debitOnRegistration($order->get_meta('heidelpay-Registration'));
        } catch (Exception $e) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, $e);
            return null;
        }

        if ($this->payMethod->getResponse()->isSuccess()) {
            wc_get_logger()->log(
                WC_Log_Levels::DEBUG,
                'RESPONSE:::::::' . print_r($this->payMethod->getResponse(), 1)
            );
            //####################################
            // Ab hier komme ich nicht weiter Der Initial Request wird durchgeführt, allerdings findet keine Weiterleitung statt, dementsprechend auch keine Transaktion
            // folgendes habe ich schon ausprobiert:
            //
            // return $this->payMethod->getResponse()->getPaymentFormUrl();
            //
            // echo $this->payMethod->getResponse()->getPaymentFormUrl();
            //
            // header('Location: ' . $this->payMethod->getResponse()->getPaymentFormUrl());
            //
            // return [
            //      'result' => 'success',
            //      'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl(),
            //   ];
        }
        if ($this->payMethod->getResponse()->isError()) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getResponse()->getError(), 1));
        }
    }

    public function prepareRequest(WC_Order $order)
    {
        $this->setAuthentification();
        $this->setCustomer($order);
        $this->setBasket($order->get_id());
        $this->setCriterions();
    }
}
