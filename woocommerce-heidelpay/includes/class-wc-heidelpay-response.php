<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Direct debit
 */
require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Heidelpay\PhpPaymentApi\Response;

class WC_Heidelpay_Response
{

    public static $response;

    public function init(array $post_data, $secret)
    {
        if (empty(self::$response)) {
            self::$response = new Response($post_data);
        }

        $identificationTransactionId = self::$response->getIdentification()->getTransactionId();


        try {
            self::$response->verifySecurityHash($secret, $identificationTransactionId);
        } catch (\Exception $exception) {
            $callers = debug_backtrace();
            wc_get_logger()->log(WC_Log_Levels::NOTICE, print_r("Heidelpay - " .
                $callers [0] ['function'] . ": Invalid response hash from ".
                $_SERVER ['REMOTE_ADDR'] . ", suspecting manipulation", 1));
            exit(); //error
        }

        $orderId = self::$response->getIdentification()->getTransactionId();

        $this->handleResult($post_data, $orderId);

        //TODO: if case for distinction between result and push
    }

    /*
     * handle result post
     */

    public function handleResult($post_data, $orderId)
    {

        $uid = self::$response->getIdentification()->getUniqueId();;

        $order = wc_get_order($orderId);

        if (self::$response->isSuccess()) {
            $payCode = explode('.', $post_data ['PAYMENT_CODE']);

            if (strtoupper($payCode [0]) != 'PP' AND strtoupper($payCode [0]) != 'IV') {

                //let wc take care of it
                $order->payment_complete();

                //show thank you page
                echo $order->get_checkout_order_received_url();

            } else {
                echo $order->get_checkout_order_received_url();
            }
            /* redirect customer to success page */
            //echo $this->getReturnURL($order);
        } elseif (self::$response->isError()) {
            $error = self::$response->getError();


            //haven't really figured out error notices yet
            wc_add_notice(
                __('Payment error: ' . $error['message'], 'woothemes'),
                'error'
            );

            //echo $order->get_cancel_order_url_raw();

            echo apply_filters( 'woocommerce_get_cancel_order_url_raw', add_query_arg( array(
                'cancel_order' => 'true',
                'order'        => $order->get_order_key(),
                'order_id'     => $order->get_id(),
                '_wpnonce'     => wp_create_nonce( 'woocommerce-cancel_order' ),
                'errorCode'    => $error['code'],
            ), $order->get_cancel_endpoint() ) );
        } elseif (self::$response->isPending()) {
            //update status to on hold
            $order->update_status('on-hold', __('Awaiting payment', 'woocommerce-heidelpay'));

            //empty cart
            wc()->cart->empty_cart();

            //show thank you page
            echo $order->get_checkout_order_received_url();
        }
    }

    /*
     * handle push post
     */

    public function handlePush()
    {
        //TODO
    }
}