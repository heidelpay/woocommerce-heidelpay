<?php
/**
 * Prepayment
 *
 * WooCommerce payment gateway for Secured Invoice
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2018-present heidelpay GmbH. All rights reserved.
 *
 * @link  http://dev.heidelpay.com/
 *
 * @author  Kai Himken
 *
 * @package  woocommerce-heidelpay
 * @category WooCommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-payment-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\PrepaymentPaymentMethod;

class WC_Gateway_HP_PP extends WC_Heidelpay_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

    public function checkoutValidation()
    {
        // If gateway is not active no validation is necessary.
        if ($this->isGatewayActive() === false) {
            return true;
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
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
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171749A60D979B6E4';

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
     * Set the id and PaymenMethod
     */
    protected function setPayMethod()
    {
        $this->payMethod = new PrepaymentPaymentMethod();
        $this->id = 'hp_pp';
        $this->name = __('Prepayment', 'woocommerce-heidelpay');
        $this->has_fields = false;
        $this->bookingAction = 'authorize';
    }
}
