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

require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Heidelpay\PhpPaymentApi\Response;

class WC_Heidelpay_Response
{

    /**
     * @var Response
     */
    public static $response;

    /**
     * Setup for Response handling and security.
     * @param array $post_data
     * @param $secret
     */
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
            wc_get_logger()->notice(
                print_r("Heidelpay - " .
                    $callers [0] ['function'] . ": Invalid response hash from " .
                    $_SERVER ['REMOTE_ADDR'] . ", suspecting manipulation", 1),
                ['source' => 'heidelpay']
            );
            exit(); //error
        }

        $orderId = self::$response->getIdentification()->getTransactionId();
        $order = wc_get_order($orderId);


        $this->handleResult(self::$response, $order);
    }

    /**
     * handle result post
     *
     * @param Response $response
     * @param WC_Order $order
     */
    public function handleResult($response, WC_Order $order)
    {
        $uid = $response->getIdentification()->getUniqueId();
        $sid = $response->getIdentification()->getShortId();
        $payCode = explode('.', strtoupper($response->getPayment()->getCode()));
        list($transactionMethod, $transactionType) = $payCode;

        // Get Payment Method.
        $paymentGatewayList = WC_Payment_Gateways::instance()->payment_gateways();
        $paymentMethodId = $order->get_payment_method();
        /** @var WC_Heidelpay_Payment_Gateway $paymentMethod */
        $paymentMethod = !empty($paymentGatewayList[$paymentMethodId]) ? $paymentGatewayList[$paymentMethodId] : null;
        if (!$paymentMethod || !($paymentMethod instanceof WC_Heidelpay_Payment_Gateway)) {
            wc_get_logger()->notice(
                sprintf("Payment method is not valid or was not found: %s", htmlspecialchars($paymentMethodId)),
                ['source' => 'heidelpay']
            );
            return;
        }

        // If registration, do a debit on registration afterwards
        if (($transactionType === 'RG' || $transactionType === 'CF') && $response->isSuccess()) {
            $order->add_meta_data('heidelpay-Registration', $uid);
            $order->save_meta_data();
            /** @var WC_Heidelpay_Payment_Gateway $paymethod */
            $paymentMethod->prepareRequest($order);
            //$paymentMethod->payMethod->getRequest()->getFrontend()->setEnabled('FALSE');
            $paymentMethod->payMethod->getRequest()->getIdentification()->setReferenceid($uid);
            // Use the Response of the debitOnRegistration in order to set the correct paymentInfo
            $debitOnRegistrationResponse = $paymentMethod->performNoGuiRequest($order, $uid);
            if ($debitOnRegistrationResponse !== null) {
                $redirectUrl = $debitOnRegistrationResponse->getFrontend()->getRedirectUrl();
                if (!empty($redirectUrl)) {
                    echo $redirectUrl;
                    return;
                }
                $response = $debitOnRegistrationResponse;
            }
        }

        if ($response->isSuccess() && !$response->isPending()) {
            $note = '';

            $paymentMethod->setPaymentInfo($order, $response);
            $order->add_meta_data('heidelpay-UniqueID', $uid);
            $order->add_meta_data('heidelpay-ShortID', $sid);

            // If no money has been payed yet.
            if ($transactionType === 'PA') {
                if ($transactionMethod !== 'PP') {
                    $note = __(
                        'Payment reservation successful. Please use the hiP to check the payment.',
                        'woocommerce-heidelpay'
                    );
                    $order->add_order_note($note, false);
                }
                $order->update_status(
                    'on-hold',
                    __('Awaiting payment.', 'woocommerce-heidelpay') . ' ' . $note
                );
            } else {
                $order->payment_complete($sid);
            }
            /* redirect customer to success page */
            echo $order->get_checkout_order_received_url();
        } elseif ($response->isError()) {
            $error = $response->getError();
            $order->update_status('failed');

            echo apply_filters('woocommerce_get_cancel_order_url_raw', add_query_arg([
                'cancel_order' => 'true',
                'order' => $order->get_order_key(),
                'order_id' => $order->get_id(),
                '_wpnonce' => wp_create_nonce('woocommerce-cancel_order'),
                'errorCode' => $error['code'],
            ], $order->get_cancel_endpoint()));
        } elseif ($response->isPending()) {
            //empty cart
            wc()->cart->empty_cart();

            //show thank you page
            echo $order->get_checkout_order_received_url();
        }
    }
}
