<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * credit card
 */
require_once ( dirname(__DIR__) . '../../vendor/autoload.php');
use Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod;

class WC_Gateway_HP_CC extends WC_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

        $this->DirectDebit = new CreditCardPaymentMethod();

		$this->id                 = 'hp_dd';
		//$this->icon               = apply_filters( 'hp_dd_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'HP_CC', 'woocommerce-heidelpay' );
		$this->method_description = __( 'heidelpay credit card', 'woocommerce-heidelpay' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		/*// HP_DD account fields shown on the thanks page and in emails
		$this->account_details = get_option( 'woocommerce_hp_dd_accounts',
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
		);*/

		/*// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		add_action( 'woocommerce_thankyou_hp_dd', array( $this, 'thankyou_page' ) );

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
				'label'   => __( 'Enable credit card', 'woocommerce-heidelpay' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-heidelpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'credit card', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-heidelpay' ),
				'default'     => __( 'Insert payment data for direct debit', 'woocommerce-heidelpay' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce-heidelpay' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-heidelpay' ),
				'default'     => 'The following acount will be billed:',
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
	 * Add content to the WC emails.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'hp_dd' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
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
		$order->update_status( 'on-hold', __( 'Awaiting HP_CC payment', 'woocommerce-heidelpay' ) );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		// Remove cart
		WC()->cart->empty_cart();

        /**
         * Set up your authentification data for Heidepay api
         */
        $this->CreditCard->getRequest()->authentification(
            '31HA07BC8142C5A171745D00AD63D182',  // SecuritySender
            '31ha07bc8142c5a171744e5aef11ffd3',  // UserLogin
            '93167DE7',                          // UserPassword
            '31HA07BC8142C5A171744F3D6D155865',  // TransactionChannel credit card without 3d secure
            true                                 // Enable sandbox mode
        );
        /**
         * Set up asynchronous request parameters
         */
        $this->CreditCard->getRequest()->async(
            'EN', // Language code for the Frame
            HEIDELPAY_PHP_PAYMENT_API_URL .
            HEIDELPAY_PHP_PAYMENT_API_FOLDER .
            'HeidelpayResponse.php'  // Response url from your application
        );

        /**
         * Set up customer information required for risk checks
         */
        $this->CreditCard->getRequest()->customerAddress(
            'Heidel',                  // Given name
            'Berger-Payment',           // Family name,
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
        $this->CreditCard->getRequest()->basketData(
            '2843294932', // Reference Id of your application
            23.12,                         // Amount of this request
            'EUR',                         // Currency code of this request
            '39542395235ßfsokkspreipsr'    // A secret passphrase from your application
        );

        /**
         * Set necessary parameters for Heidelpay payment Frame and send a registration request
         */
        $this->CreditCard->debit(
            HEIDELPAY_PHP_PAYMENT_API_URL,                        // PaymentFrameOrigin - uri of your application like https://dev.heidelpay.com
            'FALSE',                                   // PreventAsyncRedirect - this will tell the payment weather it should redirect the customer or not
            HEIDELPAY_PHP_PAYMENT_API_URL .
            HEIDELPAY_PHP_PAYMENT_API_FOLDER   // CSSPath - css url to style the Heidelpay payment frame
        );

        /*
         * <html>
<head>
	<title>Debit card debit example</title>
</head>
<body>
<form method="post" class="formular" id="paymentFrameForm">
<?php
    if ($DebitCard->getResponse()->isSuccess()) {
        echo '<iframe id="paymentIframe" src="'.$DebitCard->getResponse()->getPaymentFormUrl().'" style="height:250px;"></iframe><br />';
    } else {
        echo '<pre>'. print_r($DebitCard->getResponse()->getError(), 1).'</pre>';
    }
 ?>
 <button type="submit">Submit data</button>
 </form>
 <script type="text/javascript" src="./js/creditCardFrame.js"></script>
 </body>
 </html>


         */
	}
}
