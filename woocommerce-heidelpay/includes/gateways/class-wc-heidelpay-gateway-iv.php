<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Secured Invoice
 */
require_once dirname(__DIR__) . '../../vendor/autoload.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\InvoiceB2CSecuredPaymentMethod;

class WC_Gateway_HP_IV extends WC_Payment_Gateway
{

    /** @var array Array of locales */
    public $locale;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {

        $this->SecuredInvoice = new InvoiceB2CSecuredPaymentMethod();

        $this->id = 'hp_iv';
        //$this->icon               = apply_filters( 'hp_iv_icon', '' );
        $this->has_fields = true;
        $this->method_title = __('HP_IV', 'woocommerce-heidelpay');
        $this->method_description = __('heidelpay secured invoice', 'woocommerce-heidelpay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        /*add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
        add_action( 'woocommerce_thankyou_hp_dd', array( $this, 'thankyou_page' ) );

        // Customer Emails
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );*/
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
                'label' => __('Enable secured invoice', 'woocommerce-heidelpay'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-heidelpay'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-heidelpay'),
                'default' => __('secured invoice', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay'),
                'default' => __('Insert payment data for secured invoice', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce-heidelpay'),
                'default' => 'Please transfer to this Data',
                'desc_tip' => true,
            ),
            'security_sender' => array(
                'title' => __('Security Sender', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_iv_security_sender',
                'description' => 'Security Sender',
                'default' => '31HA07BC8142C5A171745D00AD63D182',
            ),
            'user_login' => array(
                'title' => __('User Login', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_iv_user_login',
                'description' => 'User Login',
                'default' => '31ha07bc8142c5a171744e5aef11ffd3',
            ),
            'user_password' => array(
                'title' => __('User Password', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_iv_user_password',
                'description' => 'User Password',
                'default' => '93167DE7',
            ),
            'transaction_channel' => array(
                'title' => __('Transaction Channel', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => 'hp_iv_transaction_channel',
                'description' => 'Transaction Channel',
                'default' => '31HA07BC81895ACFE22C154CBC521922',
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'id' => 'hp_iv_sandbox',
                'label' => __('Enable sandbox mode', 'woocommerce-heidelpay'),
                'default' => 'yes',
            ),
        );
    }

    public function admin_options()
    {
        ?>
        <h2><?php _e('heidelpay IV', 'woocommerce'); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

    //payment form
    public function payment_fields()
    {
        echo '<div>';

        echo
        'Holder:<input type="text" name="ACCOUNT.HOLDER" value="" /><br/>
            IBan:<input type="text" name="ACCOUNT.IBAN" value="" /><br/>';

        echo '</div>';
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting HP_IV payment', 'woocommerce-heidelpay'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        /**
         * Set up your authentification data for Heidepay api
         */
        $this->SecuredInvoice->getRequest()->authentification(
            $this->get_option('hp_dd_security_sender'),  // SecuritySender
            $this->get_option('hp_dd_user_login'),  // UserLogin
            $this->get_option('hp_dd_user_password'),                         // UserPassword
            $this->get_option('hp_dd_transaction_channel'),  // TransactionChannel
            $this->get_option('hp_dd_sandbox')                                 // Enable sandbox mode
        );
        /**
         * Set up asynchronous request parameters
         */
        $this->SecuredInvoice->getRequest()->async(
            'EN', // Language code for the Frame
            'EN', // Language code for the Frame
            HEIDELPAY_PHP_PAYMENT_API_URL .
            HEIDELPAY_PHP_PAYMENT_API_FOLDER .
            'HeidelpayResponse.php'  // Response url from your application
        );

        /**
         * Set up customer information required for risk checks
         */

        $this->SecuredInvoice->getRequest()->customerAddress(
            $order->get_billing_first_name,                  // Given name
            $order->get_billing_last_name,           // Family name
            $order->get_billing_company,                     // Company Name
            $order->get_customer_id,                   // Customer id of your application
            $order->get_billing_address_1 . $order->get_billing_address_2,          // Billing address street
            $order->get_billing_state,                   // Billing address state
            $order->get_billing_postcode,                   // Billing address post code
            $order->get_billing_city,              // Billing address city
            $order->get_billing_country,                      // Billing address country code
            $order->get_billing_email     // Customer mail address
        );

        /**
         * Set up basket or transaction information
         */
        $this->SecuredInvoice->getRequest()->basketData(
            $order_id, //order id
            WC()->cart->total,                         //cart amount
            'EUR',                         // Currency code of this request
            'secret'    // A secret passphrase from your application
        );

        /**
         * Set necessary parameters for Heidelpay payment Frame and send a authorize request
         */
        $this->SecuredInvoice->authorize();
    }
}
