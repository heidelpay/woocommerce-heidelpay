<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rechnugskauf gesichert
 */
require_once(dirname(__DIR__) . '../../vendor/autoload.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\InvoiceB2CSecuredPaymentMethod;

class WC_Gateway_HP_IVPG extends WC_Payment_Gateway
{

    /** @var array Array of locales */
    public $locale;

    public $payMethod;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->payMethod = new InvoiceB2CSecuredPaymentMethod();

        $this->id = 'hp_ivpg';
        //$this->icon               = apply_filters( 'hp_ivpg_icon', '' );
        $this->has_fields = false;
        $this->method_title = __('HP_IVPG', 'woocommerce-heidelpay');
        $this->method_description = __('heidelpay Invoice', 'woocommerce-heidelpay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'callback_handler'));
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'label' => __('Enable Invoice Secured', 'woocommerce-heidelpay'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-heidelpay'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-heidelpay'),
                'default' => __('Invoice', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay'),
                'default' => __('Insert payment data for Invoice', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce-heidelpay'),
                'default' => 'The following acount will be billed:',
                'desc_tip' => true,
            ),
            'security_sender' => array(
                'title' => __('Security Sender', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_ivpg_security_sender',
                'description' => 'Security Sender',
                'default' => '31HA07BC8142C5A171745D00AD63D182'
            ),
            'user_login' => array(
                'title' => __('User Login', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_ivpg_user_login',
                'description' => 'User Login',
                'default' => '31ha07bc8142c5a171744e5aef11ffd3'
            ),
            'user_password' => array(
                'title' => __('User Password', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_ivpg_user_password',
                'description' => 'User Password',
                'default' => '93167DE7'
            ),
            'transaction_channel' => array(
                'title' => __('Transaction Channel', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_ivpg_transaction_channel',
                'description' => 'Transaction Channel',
                'default' => '31HA07BC8181E8CCFDAD0419C1D13E84'
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'id' => 'hp_ivpg_sandbox',
                'label' => __('Enable sandbox mode', 'woocommerce-heidelpay'),
                'default' => 'yes'
            ),
        );
    }

    public function admin_options()
    {
        ?>
        <h2><?php _e('heidelpay IVPG', 'woocommerce'); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

    //payment form
    public function payment_fields() {
        echo '<div>';

        echo
        'Salutation:<select name="salutation">
<option>Herr</option>
<option>Frau</option>
</select><br/>
            Birthdate:<input type="date" name="birthdate" id="date" value="" /><br/>'
        ;

        echo '</div>';
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting HP_IVPG payment', 'woocommerce-heidelpay'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        wc()->cart->empty_cart();

        /**
         * Set up your authentification data for Heidepay api
         */
        $this->payMethod->getRequest()->authentification(
            $this->settings['security_sender'],  // SecuritySender
            $this->settings['user_login'],  // UserLogin
            $this->settings['user_password'],  // UserPassword
            $this->settings['transaction_channel'],  // TransactionChannel
            $this->settings['sandbox']  // Enable sandbox mode
        );
        /**
         * Set up asynchronous request parameters
         */
        $this->payMethod->getRequest()->async(
            'EN', // Language code for the Frame
            'https://www.google.de/'
        );

        /**
         * Set up customer information required for risk checks
         */
        $this->payMethod->getRequest()->customerAddress(
            $order->get_billing_first_name(),                  // Given name
            $order->get_billing_last_name(),           // Family name
            $order->get_billing_company(),                     // Company Name
            $order->get_customer_id(),                   // Customer id of your application
            $order->get_billing_address_1() . $order->get_billing_address_2(),          // Billing address street
            $order->get_billing_state(),                   // Billing address state
            $order->get_billing_postcode(),                   // Billing address post code
            $order->get_billing_city(),              // Billing address city
            $order->get_billing_country(),                      // Billing address country code
            $order->get_billing_email()     // Customer mail address
        );

        $this->payMethod->getRequest()->b2cSecured($_POST['salutation'], $_POST['birthdate']);

        /**
         * Set up basket or transaction information
         */
        $this->payMethod->getRequest()->basketData(
            $order_id, //order id
            $order->get_total(),                         //cart amount
            'EUR',                         // Currency code of this request
            'secret'    // A secret passphrase from your application
        );

        /**
         * Set necessary parameters for Heidelpay payment Frame and send a registration request
         */
        $this->payMethod->authorize();

        //logging and debug
        $logger = wc_get_logger();
        mail('florian.evertz@heidelpay.de', 'woo-response', print_r($this->payMethod->getResponse(), 1));
        mail('florian.evertz@heidelpay.de', 'woo-request', print_r($this->payMethod->getRequest(), 1));
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getRequest(), 1));
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->settings['security_sender'], 1));

        if ($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl()
            ];
        } else {
            wc_add_notice(
                __('Payment error: ', 'woothemes') . $this->payMethod->getResponse()->getError()['message'],
                'error'
            );
            return null;
        }
    }
}
