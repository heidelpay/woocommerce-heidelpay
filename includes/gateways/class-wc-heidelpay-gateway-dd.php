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
        $this->payMethod->getRequest()->getAccount()->setHolder($_POST['holder']);
        $this->payMethod->getRequest()->getAccount()->setIban($_POST['iban']);

        /**
         * Set necessary parameters for Heidelpay payment Frame and send a registration request
         */
        try {
            $this->payMethod->debit();
        } catch (Exception $e) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, print_r($e->getMessage(), 1));
            // TODO: redirect to errorpage
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