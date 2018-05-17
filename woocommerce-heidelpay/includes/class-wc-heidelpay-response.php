<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Direct debit
 */
require_once ( WC_HEIDELPAY_PLUGIN_PATH . '/vendor/autoload.php');

use Heidelpay\PhpPaymentApi\Response;

class WC_Heidelpay_Response {

    public static $response;

    /*public function __construct($post_data)
    {
        self::$response = new Response($post_data);
    }*/

    public function init (array $post_data, $secret) {

        $secretPass = 'secret';
        //TODO
        
        if ( empty( self::$response ) ) {
            self::$response = new Response($post_data);
            /*if (!self::$response->verifySecurityHash($secretPass, $orderId))
                exit(); //error*/
        }

        $orderId = self::$response->getIdentification()->getTransactionId();
        wc_get_logger()->debug('callback-response' . print_r(self::$response, 1)); // TODO: remove debugging
        wc_get_logger()->debug('callback-response' . print_r($orderId, 1));

        $this->handleResult($post_data, $orderId);

        //TODO: if case for distinction between result and push
    }

    /*
     * handle result post
     */

    public function handleResult($post_data, $orderId) {

        $uid = self::$response->getIdentification()->getUniqueId();;

        //$this->saveTransaction($post_data, $orderId, $uid);

        $order = wc_get_order( $orderId );

        if (self::$response->isSuccess()) {
            $payCode = explode('.', $post_data ['PAYMENT_CODE']);

            if (strtoupper($payCode [0]) != 'PP' AND strtoupper($payCode [0]) != 'IV') {

                //let wc take care of it
                $order->payment_complete();

                //show thank you page
                echo $order->get_checkout_order_received_url();
            } else {
                echo $order->get_checkout_payment_url( $on_checkout = false );
            }

            /* redirect customer to success page */
            //echo $this->getReturnURL($order);


        } elseif (self::$response->isError()) {
            $error = self::$response->getError();

            //haven't really figured out error notices yet
            wc_add_notice( __('Payment error:', 'woothemes') . $error, 'error' );

            echo $order->get_checkout_payment_url();
        } elseif (self::$response->isPending()) {
            //update status to on hold
            $order->update_status( 'on-hold', __( 'Awaiting payment', 'woocommerce-heidelpay' ) );

            //empty cart
            WC()->cart->empty_cart();

            //show thank you page
            echo $order->get_checkout_order_received_url();;
        }
    }

    /*
     * handle push post
     */
    
    public function handlePush () {
        //TODO
    }

    /*
     * save a transaction in the transaction table
     */

    public function saveTransaction ($post_data, $orderId, $uid) {

        global $wpdb;

        $table_name = $wpdb->prefix . 'heidelpay_transactions';

        $json = json_encode($post_data);

        $wpdb->insert(
            $table_name, //Tabellenname
            array(
                'orderid' => $orderId,
                'uid' => $uid,
                'json' => $json
            ),
            array(
                '%s',
                '%s',
                '%s'
            )
        );
    }
}