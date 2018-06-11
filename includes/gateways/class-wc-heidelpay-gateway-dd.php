<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Direct Debit
 *
 * WooCommerce payment gateway for Direct Debit
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
require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\DirectDebitPaymentMethod;

class WC_Gateway_HP_DD extends WC_Heidelpay_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['title']['default'] = sprintf(__('%s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['description']['default'] = sprintf(__('Insert payment data for %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['enabled']['label'] = sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171744F3D6D155865';

        $this->form_fields['advanced'] = array(
            'title' => __('Advanced options', 'woocommerce-heidelpay'),
            'type' => 'title',
            'description' => ''
        );

        $this->form_fields['min'] = array(
            'title' => __('Minimum Amount', 'woocommerce-heidelpay'),
            'type' => 'text',
            'default' => 1,
            'desc_tip' => true,
        );
    }

    public function payment_fields()
    {
        $accountHolder = __('Holder:', 'woocommerce-heidelpay');
        $accountIban = __('IBAN:', 'woocommerce-heidelpay');

        echo '<div>';

        echo $accountHolder . '<input type="text" name="holder" value="" /><br/>' .
-            $accountIban . '<input type="text" name="iban" value="" /><br/>';

        echo '</div>';
    }

    //payment form
    /**
     * Set the id and PaymenMethod
     */
    protected function setPayMethod()
    {
        $this->payMethod = new DirectDebitPaymentMethod();
        $this->id = 'hp_dd';
        $this->has_fields = true;
        $this->name = __('Direct Debit', 'woocommerce-heidelpay');
    }

    /**
     * Send payment request
     * @return mixed
     */
    protected function performRequest($order_id)
    {
        if (isset($_POST['holder']) && isset($_POST['iban'])) {
            $this->payMethod->getRequest()->getAccount()->setHolder(htmlspecialchars($_POST['holder']));
            $this->payMethod->getRequest()->getAccount()->setIban(htmlspecialchars($_POST['iban']));
        } else {
            wc_add_notice(
                __('Payment error: ', 'woocommerce-heidelpay') . 'Not all fields set',
                'error'
            );
            return null;
        }


        /**
         * Set necessary parameters for Heidelpay payment Frame and send a registration request
         */
        try {
            $this->payMethod->debit();
        } catch (Exception $e) {
            wc_add_notice(
                __('Payment error: ', 'woocommerce-heidelpay') . $this->payMethod->getResponse()->getError()['message'],
                'error'
            );
            return null;
        }

        if ($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl(),
            ];
        }
        wc_add_notice(
            __('Payment error: ' . $this->payMethod->getResponse()->getError()['message'], 'woocommerce-heidelpay'),
            'error'
        );
        return null;
    }
}