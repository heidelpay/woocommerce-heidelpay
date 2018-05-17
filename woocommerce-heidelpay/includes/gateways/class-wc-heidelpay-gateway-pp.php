<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Prepayment
 */
use Heidelpay\PhpPaymentApi\PaymentMethods\PrepaymentPaymentMethod;

class WC_Gateway_HP_PP extends WC_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

        $this->PrePayment = new PrepaymentPaymentMethod();

		$this->id                 = 'hp_pp';
		//$this->icon               = apply_filters( 'hp_pp_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'HP_PP', 'woocommerce-heidelpay' );
		$this->method_description = __( 'heidelpay direct debit', 'woocommerce-heidelpay' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		// HP_PP account fields shown on the thanks page and in emails
		$this->account_details = get_option( 'woocommerce_hp_pp_accounts',
			array(
				array(
					'account_name'   => $this->get_option( 'account_name' ),
					'account_number' => $this->get_option( 'account_number' ),
					'sort_code'      => $this->get_option( 'sort_code' ),
					'bank_name'      => $this->get_option( 'bank_name' ),
					'iban'           => $this->get_option( 'iban' ),
					'bic'            => $this->get_option( 'bic' ),
				),
			)
		);

		/*// Actions
		app_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		app_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		app_action( 'woocommerce_thankyou_hp_pp', array( $this, 'thankyou_page' ) );

		// Customer Emails
		app_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );*/
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-heidelpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable prepayment', 'woocommerce-heidelpay' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-heidelpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'prepayment', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'Insert payment data for prepayment', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be apped to the thank you page and emails.', 'woocommerce-heidelpay' ),
				'default'     => 'Pay to the following account:',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type'        => 'account_details',
			),
		);

	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id
	 */
	public function thankyou_page( $order_id ) {

		if ( $this->instructions ) {
			echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
		}
		$this->bank_details( $order_id );

	}

	/**
	 * App content to the WC emails.
	 *
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'hp_pp' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
			$this->bank_details( $order->get_id() );
		}

	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', __( 'Awaiting HP_PP payment', 'woocommerce-heidelpay' ) );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		// Remove cart
		WC()->cart->empty_cart();

        $this->PrePayment->getRequest()->authentification(
            '31HA07BC8142C5A171745D00AD63D182',  // SecuritySender
            '31ha07bc8142c5a171744e5aef11ffd3',  // UserLogin
            '93167DE7',                          // UserPassword
            '31HA07BC8142C5A171744F3D6D155865',  // TransactionChannel credit card without 3d secure
            true                                 // Enable sandbox mode
        );
        /**
         * Set up asynchronous request parameters
         */
        $this->PrePayment->getRequest()->async(
            'EN', // Language code for the Frame
            HEIDELPAY_PHP_PAYMENT_API_URL .
            HEIDELPAY_PHP_PAYMENT_API_FOLDER .
            'HeidelpayResponse.php'  // Response url from your application
        );

        // Return thankyou redirect
        /*return array(
            'result'    => 'success',
            'redirect'  => $this->get_return_url( $order ),
        );*/

        /**
         * Set up customer information required for risk checks
         */
        $this->PrePayment->getRequest()->customerAddress(
            'Heidel',                  // Given name
            'Berger-Payment',           // Family name
            null,                     // Company Name
            '12344',                   // Customer id of your application
            'Vagerowstr. 18',          // Billing address street
            'DE-BW',                   // Billing address state
            '69115',                   // Billing address post code
            'Heidelberg',              // Billing address city
            'DE',                      // Billing address country code
            'support@heidelpay.com'     // Customer mail address
        );

        /**
         * Set up basket or transaction information
         */
        $this->PrePayment->getRequest()->basketData(
            '2843294932', // Reference Id of your application
            23.12,                         // Amount of this request
            'EUR',                         // Currency code of this request
            '39542395235ßfsokkspreipsr'    // A secret passphrase from your application
        );

        /**
         * Set necessary parameters for Heidelpay payment and send the request
         */
        $this->PrePayment->authorize();
	}
}
