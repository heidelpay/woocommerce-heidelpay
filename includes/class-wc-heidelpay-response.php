<?php
/**
 * heidelpay response
 *
 * Handle the the payment response
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
                $callers [0] ['function'] . ": Invalid response hash from " .
                $_SERVER ['REMOTE_ADDR'] . ", suspecting manipulation", 1));
            exit(); //error
        }

        $orderId = self::$response->getIdentification()->getTransactionId();
        $order = wc_get_order($orderId);


        $this->handleResult($post_data, $order);

        //TODO: if case for distinction between result and push
    }

    /**
     * handle result post
     */

    public function handleResult($post_data, WC_Order $order)
    {
        $uid = self::$response->getIdentification()->getUniqueId();

        if (self::$response->isSuccess()) {
            $payCode = explode('.', $post_data ['PAYMENT_CODE']);
            $note = '';

            // If no money has been payed yet.
            if (strtoupper($payCode[1]) === 'PA' or strtoupper($payCode[1]) === 'RG') {
                // In not Prepayment and Invoice payment can be captured manually
                if (strtoupper($payCode [0]) !== 'PP' and strtoupper($payCode [0]) !== 'IV') {
                    $note = __(
                        'Payment reservation successful. Please use the hiP to check the payment.',
                        'woocommerce-heidelpay.'
                    );
                    $order->add_order_note($note, false);
                }

                $order->update_status('on-hold', __('Awaiting payment.', 'woocommerce-heidelpay')
                . ' ' . $note) . ' ';
            } else {
                $order->payment_complete();
            }

            echo $order->get_checkout_order_received_url();

            /* redirect customer to success page */
        } elseif (self::$response->isError()) {
            $error = self::$response->getError();
            $order->update_status('failed');

            echo apply_filters('woocommerce_get_cancel_order_url_raw', add_query_arg(array(
                'cancel_order' => 'true',
                'order' => $order->get_order_key(),
                'order_id' => $order->get_id(),
                '_wpnonce' => wp_create_nonce('woocommerce-cancel_order'),
                'errorCode' => $error['code'],
            ), $order->get_cancel_endpoint()));
        } elseif (self::$response->isPending()) {
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