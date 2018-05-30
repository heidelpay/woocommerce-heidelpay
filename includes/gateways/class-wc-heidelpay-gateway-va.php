<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PayPal
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\PayPalPaymentMethod;

class WC_Gateway_HP_VA extends WC_Heidelpay_Payment_Gateway
{

    /** @var array Array of locales */
    public $locale;
    protected $bookingModes;

    public function setPayMethod()
    {
        $this->payMethod = new PayPalPaymentMethod();
        $this->id = 'hp_va';
        $this->name = 'PayPal';

        $this->bookingModes = array(
            'PA' => 'authorize',
            'DB' => 'debit'
        );
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['security_sender']['default']        = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default']             = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default']          = '93167DE7';
        $this->form_fields['transaction_channel']['default']    = '31HA07BC8124365CA41D4BDA79CCCD22';

        $this->form_fields['bookingmode'] = $this->getBookingSelection();
    }

    protected function performRequest($order_id)
    {
        $bookingAction = $this->getBookingAction();

        try {
            $this->payMethod->$bookingAction();
        } catch (\Exception $exception) {
            wc_get_logger()->logger->log(WC_Log_Levels::DEBUG, print_r('Paymethod not found', 1));
        }

        if ($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl()
            ];
        }

        wc_add_notice(
            __('Payment error: ', 'woocommerce-heidelpay') . $this->payMethod->getResponse()->getError()['message'],
            'error'
        );

        return null;
    }

    public function getBookingAction() {
        return $this->bookingModes[$this->get_option('bookingmode')];
    }
}
