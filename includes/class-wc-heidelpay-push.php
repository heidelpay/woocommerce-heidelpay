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
    public static $push;

    public function init($rawPayload)
    {
        if (empty(self::$push)) {
            self::$push = new Push($rawPayload);
        }

        $this->handlePush();
    }

    public function handlePush()
    {
        wc_get_logger()->log(WC_Log_Levels::DEBUG, 'PaUSH');
    }
}
