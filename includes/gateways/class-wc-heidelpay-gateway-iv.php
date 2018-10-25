<?php

/**
 * Invoice
 *
 * WooCommerce payment gateway for Invoice
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

if (!defined('ABSPATH')){
    exit; // Exit if accessed directly
}

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-payment-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\InvoicePaymentMethod;

class WC_Heidelpay_HP_IV extends WC_Heidelpay_Payment_Gateway
{

    /**
     * Set the id and PaymentMethod
     */
    protected function setPayMethod()
    {
        // TODO: Implement setPayMethod() method.
    }
}
