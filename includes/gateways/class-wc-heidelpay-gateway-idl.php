<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * IDeal
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\IDealPaymentMethod;

class WC_Gateway_HP_IDL extends WC_Heidelpay_Payment_Gateway
{
    /**
     * @var $payMethod IDealPaymentMethod
     */
    public $payMethod;
    /** @var array Array of locales */
    public $locale;
    protected $bookingModes;

    public function setPayMethod()
    {
        $this->payMethod = new IDealPaymentMethod();
        $this->id = 'hp_idl';
        $this->name = __('iDeal', 'woocommerce-heidelpay');
        $this->has_fields = true;

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
        $this->form_fields['transaction_channel']['default']    = '31HA07BC8142C5A171744B56E61281E5';

        $this->form_fields['bookingmode'] = $this->getBookingSelection();
    }

    /**
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function payment_fields()
    {
        $countries = new WC_Countries();

        $billingCountry = $countries->get_address_fields( $countries->get_base_country(),'billing_');

        // declare text
        $accountHolderLabel = __('Account Holder', 'woocommerce-heidelpay');
        $bankNameLabel = __('Bank', 'woocommerce-heidelpay');
        // Performe Authorize request to get paymethod config
        $this->setAuthentification();
        $this->setAsync();
        $this->payMethod->authorize();

        $brands = (array) $this->payMethod->getResponse()->getConfig()->getBrands();

        echo '<div>';
        echo '<label for="accountholder">' . $accountHolderLabel . ':</label>';
        echo '<input type="text" id="accountholder" name="accountholder"/>';
        echo '<br/>';
        echo '<label for="bankname">' . $accountHolderLabel . ':</label>';
        echo '<select name ="bankname" id="bankname">';
        echo '<option selected disabled>' . $bankNameLabel . '</option>';
        foreach ($brands as $value => $brandName) {
            echo '<option value="' . $value . '"> '.$brandName.' </option>';
        }
        echo '</select>';
        echo '</div>';
    }

    protected function performRequest($order_id)
    {
        $this->payMethod->getRequest()->getAccount()->setBankName($_POST['bankname']);

        try {
            $this->payMethod->authorize();
        } catch (\Exception $exception) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, print_r('Paymethod not found', 1));
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

    public function setAvailability($available_gateways)
    {
        $available = true;

        if (wc()->customer->get_billing_country() !== 'NL') {
            $available = false;
        }

        if (!$available) {
            unset($available_gateways[$this->id]);
        }

        return $available_gateways;
    }

    public function getBookingAction() {
        return $this->bookingModes[$this->get_option('bookingmode')];
    }
}
