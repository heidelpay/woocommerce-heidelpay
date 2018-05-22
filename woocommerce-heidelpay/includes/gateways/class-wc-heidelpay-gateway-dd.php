<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Direct debit
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

        $this->form_fields['description']['default'] = __('Insert payment data for'
            . $this->name, 'woocommerce-heidelpay');
        $this->form_fields['title']['default'] = __($this->name, 'woocommerce-heidelpay');
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171744F3D6D155865';
    }

    public function payment_fields()
    {
        echo '<div>';

        echo 'Holder:<input type="text" name="ACCOUNT.HOLDER" value="" /><br/>
            IBan:<input type="text" name="ACCOUNT.IBAN" value="" /><br/>';

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
        $this->name = 'Direct Debit';
        $this->method_title = __('HP_DD', 'woocommerce-heidelpay');
        $this->method_description = __('heidelpay direct debit', 'woocommerce-heidelpay');
    }

    /**
     * Send payment request
     * @return mixed
     */
    protected function performRequest($order_id)
    {
        $logger = wc_get_logger();
        try {
            $this->payMethod->debit();
        } catch (\Exception $exception) {
            $logger->log(WC_Log_Levels::DEBUG, print_r('Paymethod not found', 1));
            // TODO: redirect to errorpage
        }

        //logging and debug
        $logger = wc_get_logger();
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getRequest(), 1));
        $logger->log(
            WC_Log_Levels::DEBUG,
            print_r(get_permalink(wc_get_page_id('shop')) . 'wc-api' . strtolower(get_class($this)), 1)
        );

        if ($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl()
            ];
        }

        wc_add_notice(
            __('Payment error: ', 'woothemes') . $this->payMethod->getResponse()->getError()['message'],
            'error'
        );

        return null;
    }
}
