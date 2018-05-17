<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_Heidelpay_Payment_Gateway extends WC_Payment_Gateway
{

    public $payMethod;
    protected $name;

    public function __construct()
    {
        $this->has_fields = false;

        $this->setPayMethod();
        $this->method_title = __(strtoupper($this->id), 'woocommerce-heidelpay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower( get_class($this) ), array( $this, 'callback_handler' ) );
    }

    /**
     * Set the id and PaymenMethod
     */
    abstract protected function setPayMethod();

    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'label' => __('Enable Sofort', 'woocommerce-heidelpay'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-heidelpay'),
                'type' => 'text',
                'description' =>
                    __('This controls the title which the user sees during checkout.', 'woocommerce-heidelpay'),
                'default' => __('', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay'),
                'default' => __('', 'woocommerce-heidelpay'),
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
                'id' => $this->id . '_security_sender',
                'description' => 'Security Sender',
                'default' => ''
            ),
            'user_login' => array(
                'title' => __('User Login', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_user_login',
                'description' => 'User Login',
                'default' => ''
            ),
            'user_password' => array(
                'title' => __('User Password', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_user_password',
                'description' => 'User Password',
                'default' => '93167DE7'
            ),
            'transaction_channel' => array(
                'title' => __('Transaction Channel', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_transaction_channel',
                'description' => 'Transaction Channel',
                'default' => ''
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'id' => $this->id . '_sandbox',
                'label' => __('Enable sandbox mode', 'woocommerce-heidelpay'),
                'default' => 'yes'
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting ' . strtoupper($this->id) . ' payment', 'woocommerce-heidelpay'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        //wc()->cart->empty_cart();

        $this->setAuthentification();
        $this->setAsync();
        $this->setCustomer($order);
        $this->setBasket($order_id);

        return $this->performRequest();
    }

    /**
     * Set up your authentification data for Heidepay api
     */
    protected function setAuthentification() {
        $this->payMethod->getRequest()->authentification(
            $this->get_option('security_sender'),
            $this->get_option('user_login'),
            $this->get_option('user_password'),
            $this->get_option('transaction_channel'),
            $this->get_option('sandbox')
        );
    }

    /**
     * Set up asynchronous request parameters
     */
    protected function setAsync() {
        $this->payMethod->getRequest()->async(
            'EN', // Language code for the Frame
            get_permalink( wc_get_page_id( 'shop' ) ) . 'wc-api/' . strtolower(get_class($this))
        );
    }

    protected function setCustomer($order) {
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
            $order->get_billing_email()         // Customer mail address
        );
    }

    protected function setBasket($order_id) {
        $order = wc_get_order($order_id);
        $this->payMethod->getRequest()->basketData(
            $order_id, //order id
            $order->get_total(),                         //cart amount
            'EUR',                         // Currency code of this request
            'secret'    // A secret passphrase from your application
        );
    }

    /**
     * Send payment request
     * @return mixed
     */
    abstract protected function performRequest();

    public function admin_options()
    {
        //$header = ;

        echo '<h2>';
        _e('heidelpay ' . strtoupper($this->id), 'woocommerce');
        echo '</h2>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function callback_handler()
    {
        $response = new WC_Heidelpay_Response();

        //echoes response URL
        $response->init($_POST, '');
    }
}
