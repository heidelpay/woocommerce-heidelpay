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

    public function init($rawPayload, $secret)
    {
        if (empty(self::$push)) {
            self::$push = new Push($rawPayload);
        }
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

    public function handlePush($response)
    {
        //TODO: HashSecurityCheck
        //TODO: Amount mit OrderAmount abgleichen
        //TODO: Push in Datenbank schreiben?

        $orderID = $response->getIdentification()->getTransactionId();
        $order = wc_get_order($orderID);

        $order->update_status('processing', 'Bezahlung ist eingegangen');
    }
}
