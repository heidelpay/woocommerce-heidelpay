<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Heidelpay class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Heidelpay extends WC_Payment_Gateway_CC {

	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * Checkout enabled
	 *
	 * @var bool
	 */
	public $heidelpay_checkout;

	/**
	 * Checkout Locale
	 *
	 * @var string
	 */
	public $heidelpay_checkout_locale;

	/**
	 * Credit card image
	 *
	 * @var string
	 */
	public $heidelpay_checkout_image;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'heidelpay';
		$this->method_title         = __( 'Heidelpay', 'woocommerce-heidelpay' );
		$this->method_description   = sprintf( __( 'heidelpay payment' ));
		$this->has_fields           = true;
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility.
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders',
			'tokenization',
			'add_payment_method',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                   = $this->get_option( 'title' );
		$this->description             = $this->get_option( 'description' );
		$this->enabled                 = $this->get_option( 'enabled' );
		$this->testmode                = 'yes' === $this->get_option( 'testmode' );
		$this->capture                 = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->statement_descriptor    = $this->get_option( 'statement_descriptor', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$this->heidelpay_checkout         = 'yes' === $this->get_option( 'heidelpay_checkout' );
		$this->heidelpay_checkout_locale  = $this->get_option( 'heidelpay_checkout_locale' );
		$this->heidelpay_checkout_image   = $this->get_option( 'heidelpay_checkout_image', '' );
		$this->saved_cards             = 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key              = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key         = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		$this->logging                 = 'yes' === $this->get_option( 'logging' );

		if ( $this->heidelpay_checkout ) {
			$this->order_button_text = __( 'Continue to payment', 'woocommerce-heidelpay' );
		}

		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation "<a href="%s">Testing Heidelpay</a>" for more card numbers.', 'woocommerce-heidelpay' ), 'https://heidelpay.com/docs/testing' );
			$this->description  = trim( $this->description );
		}

		WC_Heidelpay_API::set_secret_key( $this->secret_key );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" ' . $style . ' />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" ' . $style . ' />';
		}

		if ( $this->bitcoin && $this->heidelpay_checkout ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( plugins_url( '/assets/images/bitcoin' . $ext, WC_HEIDELPAY_MAIN_FILE ) ) . '" alt="Bitcoin" width="24" ' . $style . ' />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get Heidelpay amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public function get_heidelpay_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}
		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies.
			case 'BIF' :
			case 'CLP' :
			case 'DJF' :
			case 'GNF' :
			case 'JPY' :
			case 'KMF' :
			case 'KRW' :
			case 'MGA' :
			case 'PYG' :
			case 'RWF' :
			case 'VND' :
			case 'VUV' :
			case 'XAF' :
			case 'XOF' :
			case 'XPF' :
				$total = absint( $total );
				break;
			default :
				$total = round( $total, 2 ) * 100; // In cents.
				break;
		}
		return $total;
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
		if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) ) ) {
			echo '<div class="error heidelpay-ssl-message"><p>' . sprintf( __( 'please activate SSL!', 'woocommerce-heidelpay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			if ( ! $this->secret_key || ! $this->publishable_key ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-heidelpay.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;
		$total                = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

		if ( $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ? $user_email : $user->user_email;
		} else {
			$user_email = '';
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Card', 'woocommerce-heidelpay' );
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="heidelpay-payment-data"
			data-panel-label="' . esc_attr( $pay_button_text ) . '"
			data-description=""
			data-email="' . esc_attr( $user_email ) . '"
			data-amount="' . esc_attr( $this->get_heidelpay_amount( $total ) ) . '"
			data-name="' . esc_attr( $this->statement_descriptor ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
			data-image="' . esc_attr( $this->heidelpay_checkout_image ) . '"
			data-bitcoin="' . esc_attr( $this->bitcoin ? 'true' : 'false' ) . '"
			data-locale="' . esc_attr( $this->heidelpay_checkout_locale ? $this->heidelpay_checkout_locale : 'en' ) . '"
			data-allow-remember-me="' . esc_attr( $this->saved_cards ? 'true' : 'false' ) . '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_heidelpay_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		if ( ! $this->heidelpay_checkout ) {
			$this->form();

			if ( apply_filters( 'wc_heidelpay_display_save_payment_method_checkbox', $display_tokenization ) ) {
				$this->save_payment_method_checkbox();
			}
		}

		echo '</div>';
	}

	/**
	 * Localize Heidelpay messages based on code
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public function get_localized_messages() {
		return apply_filters( 'wc_heidelpay_localized_messages', array(
			'invalid_number'        => __( 'The card number is not a valid credit card number.', 'woocommerce-heidelpay' ),
			'invalid_expiry_month'  => __( 'The card\'s expiration month is invalid.', 'woocommerce-heidelpay' ),
			'invalid_expiry_year'   => __( 'The card\'s expiration year is invalid.', 'woocommerce-heidelpay' ),
			'invalid_cvc'           => __( 'The card\'s security code is invalid.', 'woocommerce-heidelpay' ),
			'incorrect_number'      => __( 'The card number is incorrect.', 'woocommerce-heidelpay' ),
			'expired_card'          => __( 'The card has expired.', 'woocommerce-heidelpay' ),
			'incorrect_cvc'         => __( 'The card\'s security code is incorrect.', 'woocommerce-heidelpay' ),
			'incorrect_zip'         => __( 'The card\'s zip code failed validation.', 'woocommerce-heidelpay' ),
			'card_declined'         => __( 'The card was declined.', 'woocommerce-heidelpay' ),
			'missing'               => __( 'There is no card on a customer that is being charged.', 'woocommerce-heidelpay' ),
			'processing_error'      => __( 'An error occurred while processing the card.', 'woocommerce-heidelpay' ),
			'invalid_request_error' => __( 'Could not find payment information.', 'woocommerce-heidelpay' ),
		) );
	}

	/**
	 * Load admin scripts.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function admin_scripts() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'woocommerce_heidelpay_admin', plugins_url( 'assets/js/heidelpay-admin' . $suffix . '.js', WC_HEIDELPAY_MAIN_FILE ), array(), WC_HEIDELPAY_VERSION, true );

		$heidelpay_admin_params = array(
			'localized_messages' => array(
				'not_valid_live_key_msg' => __( 'This is not a valid live key. Live keys start with "sk_live_" and "pk_live_".', 'woocommerce-heidelpay' ),
				'not_valid_test_key_msg' => __( 'This is not a valid test key. Test keys start with "sk_test_" and "pk_test_".', 'woocommerce-heidelpay' ),
				're_verify_button_text'  => __( 'Re-verify Domain', 'woocommerce-heidelpay' ),
				'missing_secret_key'     => __( 'Missing Secret Key. Please set the secret key field above and re-try.', 'woocommerce-heidelpay' ),
			),
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => array(
			),
		);

		wp_localize_script( 'woocommerce_heidelpay_admin', 'wc_heidelpay_admin_params', apply_filters( 'wc_heidelpay_admin_params', $heidelpay_admin_params ) );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for heidelpay payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( $this->heidelpay_checkout ) {
			wp_enqueue_script( 'heidelpay_checkout', 'https://checkout.heidelpay.com/checkout.js', '', WC_HEIDELPAY_VERSION, true );
			wp_enqueue_script( 'woocommerce_heidelpay', plugins_url( 'assets/js/heidelpay-checkout' . $suffix . '.js', WC_HEIDELPAY_MAIN_FILE ), array( 'heidelpay_checkout' ), WC_HEIDELPAY_VERSION, true );
		} else {
			wp_enqueue_script( 'heidelpay', 'https://js.heidelpay.com/v2/', '', '2.0', true );
			wp_enqueue_script( 'woocommerce_heidelpay', plugins_url( 'assets/js/heidelpay' . $suffix . '.js', WC_HEIDELPAY_MAIN_FILE ), array( 'jquery-payment', 'heidelpay' ), WC_HEIDELPAY_VERSION, true );
		}

		$heidelpay_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-heidelpay' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-heidelpay' ),
		);

		// If we're on the pay page we need to pass heidelpay.js the address of the order.
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			$order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
			$order    = wc_get_order( $order_id );

			$heidelpay_params['billing_first_name'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
			$heidelpay_params['billing_last_name']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();
			$heidelpay_params['billing_address_1']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1();
			$heidelpay_params['billing_address_2']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2();
			$heidelpay_params['billing_state']      = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_state : $order->get_billing_state();
			$heidelpay_params['billing_city']       = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_city : $order->get_billing_city();
			$heidelpay_params['billing_postcode']   = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode();
			$heidelpay_params['billing_country']    = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country();
		}

		$heidelpay_params['heidelpay_checkout_require_billing_address'] = apply_filters( 'wc_heidelpay_checkout_require_billing_address', false ) ? 'yes' : 'no';

		// merge localized messages to be use in JS
		$heidelpay_params = array_merge( $heidelpay_params, $this->get_localized_messages() );

		wp_localize_script( 'woocommerce_heidelpay', 'wc_heidelpay_params', apply_filters( 'wc_heidelpay_params', $heidelpay_params ) );
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */
	protected function generate_payment_request( $order, $source ) {
		$post_data                = array();
		$post_data['currency']    = strtolower( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency() );
		$post_data['amount']      = $this->get_heidelpay_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-heidelpay' ), $this->statement_descriptor, $order->get_order_number() );
		$post_data['capture']     = $this->capture ? 'true' : 'false';

		$billing_email      = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();
		$billing_first_name = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_heidelpay_send_heidelpay_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		$post_data['expand[]']    = 'balance_transaction';

		$metadata = array(
			__( 'Customer Name', 'woocommerce-heidelpay' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'Customer Email', 'woocommerce-heidelpay' ) => sanitize_email( $billing_email ),
		);

		$post_data['metadata'] = apply_filters( 'wc_heidelpay_payment_metadata', $metadata, $order, $source );

		if ( $source->customer ) {
			$post_data['customer'] = $source->customer;
		}

		if ( $source->source ) {
			$post_data['source'] = $source->source;
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_heidelpay_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Get payment source. This can be a new token or existing card.
	 *
	 * @param string $user_id
	 * @param bool  $force_customer Should we force customer creation.
	 *
	 * @throws Exception When card was not added or for and invalid card.
	 * @return object
	 */
	protected function get_source( $user_id, $force_customer = false ) {
		$heidelpay_customer = new WC_Heidelpay_Customer( $user_id );
		$force_customer  = apply_filters( 'wc_heidelpay_force_customer_creation', $force_customer, $heidelpay_customer );
		$heidelpay_source   = false;
		$token_id        = false;

		// New CC info was entered and we have a new token to process
		if ( isset( $_POST['heidelpay_token'] ) ) {
			$heidelpay_token     = wc_clean( $_POST['heidelpay_token'] );
			$maybe_saved_card = isset( $_POST['wc-heidelpay-new-payment-method'] ) && ! empty( $_POST['wc-heidelpay-new-payment-method'] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_customer ) {
				$heidelpay_source = $heidelpay_customer->add_card( $heidelpay_token );

				if ( is_wp_error( $heidelpay_source ) ) {
					throw new Exception( $heidelpay_source->get_error_message() );
				}
			} else {
				// Not saving token, so don't define customer either.
				$heidelpay_source   = $heidelpay_token;
				$heidelpay_customer = false;
			}
		} elseif ( isset( $_POST['wc-heidelpay-payment-token'] ) && 'new' !== $_POST['wc-heidelpay-payment-token'] ) {
			// Use an existing token, and then process the payment

			$token_id = wc_clean( $_POST['wc-heidelpay-payment-token'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
				WC()->session->set( 'refresh_totals', true );
				throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-heidelpay' ) );
			}

			$heidelpay_source = $token->get_token();
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $heidelpay_customer ? $heidelpay_customer->get_id() : false,
			'source'   => $heidelpay_source,
		);
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order  = wc_get_order( $order_id );
			$source = $this->get_source( get_current_user_id(), $force_customer );

			if ( empty( $source->source ) && empty( $source->customer ) ) {
				$error_msg = __( 'Please enter your card details to make a payment.', 'woocommerce-heidelpay' );
				$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-heidelpay' );
				throw new Exception( $error_msg );
			}

			// Store source to order meta.
			$this->save_source( $order, $source );

			// Result from Heidelpay API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Heidelpay::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-heidelpay' ), wc_price( WC_Heidelpay::get_minimum_amount() / 100 ) ) );
				}

				$this->log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Make the request.
				$response = WC_Heidelpay_API::request( $this->generate_payment_request( $order, $source ) );

				if ( is_wp_error( $response ) ) {
					// Customer param wrong? The user may have been deleted on heidelpay's end. Remove customer_id. Can be retried without.
					if ( 'customer' === $response->get_error_code() && $retry ) {
						delete_user_meta( get_current_user_id(), '_heidelpay_customer_id' );
						return $this->process_payment( $order_id, false, $force_customer );
						// Source param wrong? The CARD may have been deleted on heidelpay's end. Remove token and show message.
					} elseif ( 'source' === $response->get_error_code() && $source->token_id ) {
						$token = WC_Payment_Tokens::get( $source->token_id );
						$token->delete();
						$message = __( 'This card is no longer available and has been removed.', 'woocommerce-heidelpay' );
						$order->add_order_note( $message );
						throw new Exception( $message );
					}

					$localized_messages = $this->get_localized_messages();

					$message = isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message();

					$order->add_order_note( $message );

					throw new Exception( $message );
				}

				// Process valid response.
				$this->process_response( $response, $order );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			do_action( 'wc_gateway_heidelpay_process_payment', $response, $order );

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			$this->log( sprintf( __( 'Error: %s', 'woocommerce-heidelpay' ), $e->getMessage() ) );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			do_action( 'wc_gateway_heidelpay_process_payment_error', $e, $order );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Save source to order.
	 *
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $source Source information.
	 */
	protected function save_source( $order, $source ) {
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		// Store source in the order.
		if ( $source->customer ) {
			version_compare( WC_VERSION, '3.0.0', '<' ) ? update_post_meta( $order_id, '_heidelpay_customer_id', $source->customer ) : $order->update_meta_data( '_heidelpay_customer_id', $source->customer );
		}
		if ( $source->source ) {
			version_compare( WC_VERSION, '3.0.0', '<' ) ? update_post_meta( $order_id, '_heidelpay_card_id', $source->source ) : $order->update_meta_data( '_heidelpay_card_id', $source->source );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
	}

	/**
	 * Store extra meta data for an order from a Heidelpay Response.
	 */
	public function process_response( $response, $order ) {
		$this->log( 'Processing response: ' . print_r( $response, true ) );

		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		// Store charge data
		update_post_meta( $order_id, '_heidelpay_charge_id', $response->id );
		update_post_meta( $order_id, '_heidelpay_charge_captured', $response->captured ? 'yes' : 'no' );

		// Store other data such as fees
		if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
			// Fees and Net needs to both come from Heidelpay to be accurate as the returned
			// values are in the local currency of the Heidelpay account, not from WC.
			$fee = ! empty( $response->balance_transaction->fee ) ? WC_Heidelpay::format_number( $response->balance_transaction, 'fee' ) : 0;
			$net = ! empty( $response->balance_transaction->net ) ? WC_Heidelpay::format_number( $response->balance_transaction, 'net' ) : 0;
			update_post_meta( $order_id, 'Heidelpay Fee', $fee );
			update_post_meta( $order_id, 'Net Revenue From Heidelpay', $net );
		}

		if ( $response->captured ) {
			$order->payment_complete( $response->id );

			$message = sprintf( __( 'Heidelpay charge complete (Charge ID: %s)', 'woocommerce-heidelpay' ), $response->id );
			$order->add_order_note( $message );
			$this->log( 'Success: ' . $message );

		} else {
			update_post_meta( $order_id, '_transaction_id', $response->id, true );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
			}

			$order->update_status( 'on-hold', sprintf( __( 'Heidelpay charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-heidelpay' ), $response->id ) );
			$this->log( "Successful auth: $response->id" );
		}

		do_action( 'wc_gateway_heidelpay_process_response', $response, $order );

		return $response;
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the Heidelpay API.
	 * @since 3.0.0
	 */
	public function add_payment_method() {
		if ( empty( $_POST['heidelpay_token'] ) || ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'woocommerce-heidelpay' ), 'error' );
			return;
		}

		$heidelpay_customer = new WC_Heidelpay_Customer( get_current_user_id() );
		$card            = $heidelpay_customer->add_card( wc_clean( $_POST['heidelpay_token'] ) );

		if ( is_wp_error( $card ) ) {
			$localized_messages = $this->get_localized_messages();
			$error_msg = __( 'There was a problem adding the card.', 'woocommerce-heidelpay' );

			// loop through the errors to find matching localized message
			foreach ( $card->errors as $error => $msg ) {
				if ( isset( $localized_messages[ $error ] ) ) {
					$error_msg = $localized_messages[ $error ];
				}
			}

			wc_add_notice( $error_msg, 'error' );
			return;
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	/**
	 * Refund a charge
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		$body = array();

		if ( ! is_null( $amount ) ) {
			$body['amount']	= $this->get_heidelpay_amount( $amount );
		}

		if ( $reason ) {
			$body['metadata'] = array(
				'reason'	=> $reason,
			);
		}

		$this->log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$response = WC_Heidelpay_API::request( $body, 'charges/' . $order->get_transaction_id() . '/refunds' );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Error: ' . $response->get_error_message() );
			return $response;
		} elseif ( ! empty( $response->id ) ) {
			$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-heidelpay' ), wc_price( $response->amount / 100 ), $response->id, $reason );
			$order->add_order_note( $refund_message );
			$this->log( 'Success: ' . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}

	/**
	 * Sends the failed order email to admin
	 *
	 * @version 3.1.0
	 * @since 3.1.0
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WC_Heidelpay::log( $message );
		}
	}
}
