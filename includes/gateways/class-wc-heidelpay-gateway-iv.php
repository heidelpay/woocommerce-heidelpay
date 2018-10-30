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

class WC_Gateway_HP_IV extends WC_Heidelpay_Payment_Gateway
{
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['title']['default'] = sprintf(__('%s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['description']['default'] = sprintf(
            __('Insert payment data for %s', 'woocommerce-heidelpay'),
            $this->name
        );
        $this->form_fields['instructions']['default'] = __(
            'please send the money to IBAN BIC ',
            'woocommerce-heidelpay'
        );
        $this->form_fields['enabled']['label'] = sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['security_sender']['default'] = '31HA07BC8181E8CCFDAD64E8A4B3B766';
        $this->form_fields['user_login']['default'] = '31ha07bc8181e8ccfdad73fd513d2a53';
        $this->form_fields['user_password']['default'] = '4B2D4BE3';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8181E8CCFDAD0419C1D13E84';

        $this->form_fields['send_payment_info'] = array(
            'title' => __('Payment information mail', 'woocommerce-heidelpay'),
            'type' => 'checkbox',
            'description' => __(
                'Add payment information to e-mail',
                'woocommerce-heidelpay'
            ),
            'default' => 'yes',
            'desc_tip' => true,
        );
    }

    /**
     * Set the id and PaymentMethod
     */
    protected function setPayMethod()
    {
        $this->payMethod = new InvoicePaymentMethod();
        $this->id = 'hp_iv';
        $this->name = __('Invoice', 'woocommerce-heidelpay');
        $this->has_fields = false;
        $this->bookingAction = 'authorize';
    }
}
