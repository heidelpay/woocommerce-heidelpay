<?php
/**
 * Debit Card
 *
 * WooCommerce payment gateway for Debit Card
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

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-iframe-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\DebitCardPaymentMethod;

class WC_Gateway_HP_DC extends WC_Heidelpay_IFrame_Gateway
{
    public function setPayMethod()
    {
        $this->payMethod = new DebitCardPaymentMethod();
        $this->id = 'hp_dc';
        $this->name = __('Debit Card', 'woocommerce-heidelpay');
    }
}
