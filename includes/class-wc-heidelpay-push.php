<?php
/**
 * heidelpay push
 *
 * Handles a push notification
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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once(dirname(__DIR__) . '/vendor/autoload.php');

use Heidelpay\PhpPaymentApi\Push;

class WC_Heidelpay_Push
{
    /** @var Heidelpay\PhpPaymentApi\Push */
    public static $push;

    /**
     * @param $rawPayload
     * @param $secret
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\XmlResponseParserException
     */
    public function init($rawPayload, $secret)
    {
        if (empty(self::$push)) {
            self::$push = new Push($rawPayload);
        }
        /** @var Heidelpay\PhpPaymentApi\Response $response */
        $response = self::$push->getResponse();

        try {
            $response->verifySecurityHash($secret, $response->getIdentification()->getTransactionId());
        } catch (\Exception $e) {
            $callers = debug_backtrace();
            wc_get_logger()->log(WC_Log_Levels::NOTICE, print_r("Heidelpay - " .
                $callers [0] ['function'] . ": Invalid push hash from " .
                $_SERVER ['REMOTE_ADDR'] . ", suspecting manipulation", 1));
            exit(); //error
        }
        $this->handlePush($response);
    }

    /**
     * @param Heidelpay\PhpPaymentApi\Response $response
     */
    public function handlePush($response)
    {
        $orderID = $response->getIdentification()->getTransactionId();
        $order = wc_get_order($orderID);
        $payCode = explode('.', strtoupper($response->getPayment()->getCode()));

        wc_get_logger()->log(WC_Log_Levels::DEBUG, $order->get_status());

        if ($payCode[0] === 'IV') {
            if ($response->isSuccess()) {
                switch ($payCode[1]) {
                    case 'FI':
                        $order->update_status(
                            'on-hold',
                            'Order has been finalized'
                        );
                        break;
                    case 'PA':
                        $order->update_status(
                            'processing',
                            'Reservation done'
                        );
                        break;
                }
            }
        }

        if ($payCode[1] === 'CP' || $payCode[1] === 'RC' || $payCode[1] === 'DB') {
            if ($response->isSuccess()) {
                if ($order->get_total() === $response->getPresentation()->getAmount()) {
                    if ($payCode[0] === 'IV') {
                        $order->update_status(
                            'completed',
                            $this->getNote($response)
                        );
                    } else {
                        $order->update_status(
                            'processing',
                            $this->getNote($response)
                        );
                    }
                } else {
                    $order->add_order_note(
                        $this->getNote($response),
                        false
                    );
                }
            } elseif ($response->isError()) {
                if ($order->get_status() === 'pending') {
                    $order->update_status('failed');
                }
            }
        }
    }

    /**
     * @param Heidelpay\PhpPaymentApi\Response $response
     * @return string
     */
    private function getNote($response)
    {
        return sprintf(
            __('Payment of %s %s received. Heidelpay ShortID %s', 'woocommerce-heidelpay'),
            $response->getPresentation()->getAmount(),
            $response->getPresentation()->getCurrency(),
            $response->getIdentification()->getShortId()
        );
    }
}
