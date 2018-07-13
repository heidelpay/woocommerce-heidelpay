<?php
/**
 * iDeal
 *
 * WooCommerce payment gateway for iDeal
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

/**
 * IDeal
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\IDealPaymentMethod;

class WC_Gateway_HP_IDL extends WC_Heidelpay_Payment_Gateway
{
    /**
     * @var $payMethod IDealPaymentMethod
     */
    public $payMethod;
    /** @var array Array of locales */
    public $locale;

    public function setPayMethod()
    {
        $this->payMethod = new IDealPaymentMethod();
        $this->id = 'hp_idl';
        $this->name = __('iDeal', 'woocommerce-heidelpay');
        $this->has_fields = true;
        $this->bookingAction = 'authorize';
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
    }

    /**
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function payment_fields()
    {
        // declare text
        $accountHolderLabel = __('Account Holder', 'woocommerce-heidelpay');
        $bankNameLabel = __('Bank', 'woocommerce-heidelpay');

        // Performe Authorize request to get paymethod config
        $this->setAuthentification();
        $this->setAsync();
        $this->setCriterions();

        $this->payMethod->authorize();

        $brands = (array) $this->payMethod->getResponse()->getConfig()->getBrands();

        $accountHolder = wc()->customer->get_billing_first_name(). ' ' . wc()->customer->get_last_name();

        if (!empty($brands)) {
            echo '<div>';
            echo '<label for="accountholder">' . $accountHolderLabel . ':</label>';
            echo '<input type="text" id="accountholder" name="accountholder" class="form-row-wide validate-required" value="' . $accountHolder . '"> ';
            echo '<br/>';
            echo '<label for="bankname">' . $accountHolderLabel . ':</label>';
            echo '<select name ="bankname" id="bankname" class="form-row-wide validate-required">';
            echo '<option selected disabled>' . $bankNameLabel . '</option>';
            foreach ($brands as $value => $brandName) {
                echo '<option value="' . $value . '"> '.$brandName.' </option>';
            }
            echo '</select>';
            echo '</div>';
        } else {
            wc_print_notice($this->getErrorMessage(), 'error');
        }

    }

    /**
     * @return false Returns false if the handling failed
     */
    protected function handleFormPost()
    {
        parent::handleFormPost();

        if (!empty($_POST['bankname'])) {
            $this->payMethod->getRequest()->getAccount()->setBankName(htmlspecialchars($_POST['bankname']));
        }
    }


    public function checkoutValidation()
    {
        $isValid = parent::checkoutValidation();

        // If gateway is not active no validation is necessary.
        if($this->isGatewayActive() === false) {
            return true;
        }

        if(empty($_POST['bankname'])) {
            wc_add_notice(
                __('Payment error: ', 'woocommerce-heidelpay') . __('Chose a bank please.', 'woocommerce-heidelpay'),
                'error'
            );
            $isValid = false;
        }

        return $isValid;
    }

    public function setAvailability($available_gateways)
    {
        $available = true;

        $isCustomer = wc()->customer !== null;
        if ($isCustomer) {
            if (wc()->customer->get_billing_country() !== 'NL') {
                $available = false;
            }
        } else {
            $available = false;
        }

        if (!$available) {
            unset($available_gateways[$this->id]);
        }

        return $available_gateways;
    }

}
