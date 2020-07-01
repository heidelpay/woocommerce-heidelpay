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
                array('source' => 'heidelpay')
            );
            exit(); //error
        }

        $orderId = self::$response->getIdentification()->getTransactionId();
        $order = wc_get_order($orderId);


        $this->handleResult($post_data, $order);
    }

    /**
     * handle result post
     *
     * @param $post_data
     * @param WC_Order $order
     */
    public function handleResult($post_data, WC_Order $order)
    {
        $uid = self::$response->getIdentification()->getUniqueId();
        $sid = self::$response->getIdentification()->getShortId();

        if (self::$response->isSuccess() && !self::$response->isPending()) {
            $payCode = explode('.', strtoupper($post_data['PAYMENT_CODE']));
            $note = '';

            $availableGateways = WC_Payment_Gateways::instance()->get_available_payment_gateways();

            $paymentMethodId = $order->get_payment_method();
            if (empty($availableGateways[$paymentMethodId])) {
                return;
            }
            /** @var WC_Payment_Gateways $paymethod */
            $paymethod = $availableGateways[$paymentMethodId];

            // If registration, do a debit on registration afterwards
            if ($payCode[1] === 'RG' || $payCode[1] === 'CF') {
                $order->add_meta_data('heidelpay-Registration', $uid);
                /** @var WC_Heidelpay_Payment_Gateway $paymethod */
                $paymethod->prepareRequest($order);
                $paymethod->payMethod->getRequest()->getFrontend()->setEnabled('FALSE');
                $paymethod->payMethod->getRequest()->getIdentification()->setReferenceid($uid);
                // Use the Response of the debitOnRegiststration in order to set the correct paymentInfo
                $noGuiResponse = $paymethod->performNoGuiRequest($order, $uid);
                if ($noGuiResponse !== null) {
                    self::$response = $noGuiResponse;
                }
            }

            $paymethod->setPaymentInfo($order, self::$response);

            // If no money has been payed yet.
            if ($payCode[0] !== 'IV' && $payCode[1] === 'PA') {
                // If not Prepayment and Invoice payment can be captured manually
                if ($payCode [0] !== 'PP') {
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
                $order->add_meta_data('heidelpay-UniqueID', $uid);
                $order->add_meta_data('heidelpay-ShortID', $sid);
                $order->payment_complete($sid);
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
}
