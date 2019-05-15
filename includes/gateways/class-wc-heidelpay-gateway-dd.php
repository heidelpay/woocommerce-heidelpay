<?php
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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-payment-gateway.php';
require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'traits' .
    DIRECTORY_SEPARATOR . 'trait-wc-heidelpay-subscription-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\DirectDebitPaymentMethod;

class WC_Gateway_HP_DD extends WC_Heidelpay_Payment_Gateway
{
    use WC_Heidelpay_Subscription_Gateway;

    /** @var array Array of locales */
    public $locale;

    /**
     * WC_Gateway_HP_DD constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->constructorAddon();
    }

    /**
     * @return bool|void
     */
    public function checkoutValidation()
    {
        // If gateway is not active no validation is necessary.
        if ($this->isGatewayActive() === false) {
            return true;
        }

        if (empty($_POST['accountholder'])) {
            wc_add_notice(
                __('You have to enter the account holder', 'woocommerce-heidelpay'),
                'error'
            );
        }

        if (empty($_POST['accountiban'])) {
            wc_add_notice(
                __('You have to enter the IBAN', 'woocommerce-heidelpay'),
                'error'
            );
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->initFormFieldsAddon();

        $this->form_fields['title']['default'] = sprintf(__('%s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['description']['default'] = sprintf(__('Insert payment data for %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['enabled']['label'] = sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171744F3D6D155865';
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
     * sets Payment Fields
     */
    public function payment_fields()
    {
        $accountHolderLabel = __('Account Holder', 'woocommerce-heidelpay');
        $accountIbanLabel = __('IBAN', 'woocommerce-heidelpay');

        $accountHolder = wc()->customer->get_billing_first_name() . ' ' . wc()->customer->get_billing_last_name();

        echo '<div>';
        parent::payment_fields();

        echo '<label for="accountholder">' . $accountHolderLabel . ':</label>';
        echo '<input type="text" class="form-row-wiede validate-required" id="accountholder" name="accountholder" value="' . $accountHolder . '"> ';
        echo '<br/>';

        echo '<label for="accountiban">' . $accountIbanLabel . ':</label>';
        echo '<input type="text" class="form-row-wiede validate-required" id="accountiban" name="accountiban" value=""> ';

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
     * @return false Returns false if the handling failed
     */
    protected function handleFormPost()
    {
        parent::handleFormPost();

        if (!empty($_POST['accountholder']) && !empty($_POST['accountiban'])) {
            $this->payMethod->getRequest()->getAccount()->setHolder(htmlspecialchars($_POST['accountholder']));
            $this->payMethod->getRequest()->getAccount()->setIban(htmlspecialchars($_POST['accountiban']));
        }
    }
}