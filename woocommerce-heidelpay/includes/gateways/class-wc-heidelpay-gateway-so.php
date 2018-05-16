<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Sofort
 */
require_once ( dirname(__DIR__) . '../../vendor/autoload.php');
use Heidelpay\PhpPaymentApi\PaymentMethods\SofortPaymentMethod;

class WC_Gateway_HP_SO extends WC_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

	public $payMethod;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

        $this->payMethod = new SofortPaymentMethod();

		$this->id                 = 'hp_so';
		//$this->icon               = apply_filters( 'hp_so_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'HP_SO', 'woocommerce-heidelpay' );
		$this->method_description = __( 'heidelpay sofort', 'woocommerce-heidelpay' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        //callback handler for response
        add_action( 'woocommerce_api_' . $this->id, array( $this, 'callback_handler' ) );
		/*add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		add_action( 'woocommerce_thankyou_hp_so', array( $this, 'thankyou_page' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );*/
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-heidelpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Sofort', 'woocommerce-heidelpay' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-heidelpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'Sofort', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'Insert payment data for Sofort', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-heidelpay' ),
				'default'     => 'The following acount will be billed:',
				'desc_tip'    => true,
			),
            'security_sender' => array(
                'title'       => __( 'Security Sender', 'woocommerce-heidelpay' ),
                'type' => 'text',
                'id' => 'hp_so_security_sender',
                'description' => 'Security Sender',
                'default' => '31HA07BC8142C5A171745D00AD63D182'
            ),
            'user_login' => array(
                'title'       => __( 'User Login', 'woocommerce-heidelpay' ),
                'type' => 'text',
                'id' => 'hp_so_user_login',
                'description' => 'User Login',
                'default' => '31ha07bc8142c5a171744e5aef11ffd3'
            ),
            'user_password' => array(
                'title'       => __( 'User Password', 'woocommerce-heidelpay' ),
                'type' => 'text',
                'id' => 'hp_so_user_password',
                'description' => 'User Password',
                'default' => '93167DE7'
            ),
            'transaction_channel' => array(
                'title'       => __( 'Transaction Channel', 'woocommerce-heidelpay' ),
                'type' => 'text',
                'id' => 'hp_so_transaction_channel',
                'description' => 'Transaction Channel',
                'default' => '31HA07BC8142C5A171749CDAA43365D2'
            ),
            'sandbox' => array(
                'title'   => __( 'Sandbox', 'woocommerce-heidelpay' ),
                'type'    => 'checkbox',
                'id' => 'hp_so_sandbox',
                'label'   => __( 'Enable sandbox mode', 'woocommerce-heidelpay' ),
                'default' => 'yes'
            ),
		);
	}

    public function admin_options() {
        ?>
        <h2><?php _e('heidelpay DD','woocommerce'); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

	//payment form
    public function payment_fields() {
        /*echo '<div>';

        echo
            'Holder:<input type="text" name="ACCOUNT.HOLDER" value="" /><br/>
            IBan:<input type="text" name="ACCOUNT.IBAN" value="" /><br/>'
        ;

        echo '</div>';*/
    }

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

        /**
         * Set up your authentification data for Heidepay api
         */
        $this->payMethod->getRequest()->authentification(
                $this->settings['security_sender'],  // SecuritySender
                $this->settings['user_login'],  // UserLogin
                $this->settings['user_password'],  // UserPassword
                $this->settings['transaction_channel'],  // TransactionChannel
                $this->settings['sandbox']  // Enable sandbox mode
            //$this->get_option('hp_so_security_sender'),
            //$this->get_option('hp_so_user_login'),
            //$this->get_option('hp_so_user_password'),
            //$this->get_option('hp_so_transaction_channel'),
            //$this->get_option('hp_so_sandbox')
        );
        /**
         * Set up asynchronous request parameters
         */
        $this->payMethod->getRequest()->async(
            'EN', // Language code for the Frame
            wc_get_page_id('shop') . 'wc-api' . strtolower(get_class($this)) //
            /*HEIDELPAY_PHP_PAYMENT_API_URL .
            HEIDELPAY_PHP_PAYMENT_API_FOLDER .
            'HeidelpayResponse.php'  // Response url from your application*/
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
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getRequest(),1));
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->settings['security_sender'],1));

        if($this->payMethod->getResponse()->isSuccess()) {
            return [
                    'result' => 'success',
                'redicect' => $this->payMethod->getResponse()->getPaymentFormUrl()
            ];
        } else {
            return [
                'result' => 'success',
                'redirect' => 'https://www.google.de/'
            ];
        }
	}

	/*
	 * callback handler for response and push
	 */
    public function callback_handler() {

        $response = new WC_Heidelpay_Response();

        //echoes response URL
        $response->init($_POST);
    }
}
