<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Direct debit
 *
 * @class       WC_Gateway_HP_PP
 * @extends     WC_Payment_Gateway
 * @version     1.0
 * @package     WooCommerce/Classes/Payment
 * @author      heidelpay
 */
class WC_Gateway_HP_PP extends WC_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

	    require_once('../classes/class-wc-heidelpay-pp.php');

		$this->id                 = 'hp_pp';
		//$this->icon               = apply_filters( 'hp_pp_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'HP_PP', 'woocommerce' );
		$this->method_description = __( 'heidelpay direct debit', 'woocommerce' );

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

		// Actions
		app_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		app_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
		app_action( 'woocommerce_thankyou_hp_pp', array( $this, 'thankyou_page' ) );

		// Customer Emails
		app_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable prepayment', 'woocommerce' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'prepayment', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Insert payment data for prepayment', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be apped to the thank you page and emails.', 'woocommerce' ),
				'default'     => 'Pay to the following account:',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type'        => 'account_details',
			),
		);

	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_html() {

		ob_start();

		$country 	= WC()->countries->get_base_country();
		$locale		= $this->get_country_locale();

		// Get sortcode label in the $locale array and use appropriate one
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );

		?>

        if ($Prepayment->getResponse()->isSuccess()) {
        echo '<a href="'.$Prepayment->getResponse()->getPaymentFormUrl().'">place prepayment</a>';
        } else {
        echo '<pre>'. print_r($Prepayment->getResponse()->getError(), 1).'</pre>';
        }

		<?php
		return ob_get_clean();

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
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id
	 */
	private function bank_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order
		$order 		= wc_get_order( $order_id );

		// Get the order country and country $locale
		$country 	= $order->get_billing_country();
		$locale		= $this->get_country_locale();

		// Get sortcode label in the $locale array and use appropriate one
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );

		$hp_pp_accounts = apply_filters( 'woocommerce_hp_pp_accounts', $this->account_details );

		if ( ! empty( $hp_pp_accounts ) ) {
			$account_html = '';
			$has_details  = false;

			foreach ( $hp_pp_accounts as $hp_pp_account ) {
				$hp_pp_account = (object) $hp_pp_account;

				if ( $hp_pp_account->account_name ) {
					$account_html .= '<h3 class="wc-hp_pp-bank-details-account-name">' . wp_kses_post( wp_unslash( $hp_pp_account->account_name ) ) . ':</h3>' . PHP_EOL;
				}

				$account_html .= '<ul class="wc-hp_pp-bank-details order_details hp_pp_details">' . PHP_EOL;

				// HP_PP account fields shown on the thanks page and in emails
				$account_fields = apply_filters( 'woocommerce_hp_pp_account_fields', array(
					'bank_name' => array(
						'label' => __( 'Bank', 'woocommerce' ),
						'value' => $hp_pp_account->bank_name,
					),
					'account_number' => array(
						'label' => __( 'Account number', 'woocommerce' ),
						'value' => $hp_pp_account->account_number,
					),
					'sort_code'     => array(
						'label' => $sortcode,
						'value' => $hp_pp_account->sort_code,
					),
					'iban'          => array(
						'label' => __( 'IBAN', 'woocommerce' ),
						'value' => $hp_pp_account->iban,
					),
					'bic'           => array(
						'label' => __( 'BIC', 'woocommerce' ),
						'value' => $hp_pp_account->bic,
					),
				), $order_id );

				foreach ( $account_fields as $field_key => $field ) {
					if ( ! empty( $field['value'] ) ) {
						$account_html .= '<li class="' . esc_attr( $field_key ) . '">' . wp_kses_post( $field['label'] ) . ': <strong>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</strong></li>' . PHP_EOL;
						$has_details   = true;
					}
				}

				$account_html .= '</ul>';
			}

			if ( $has_details ) {
				echo '<section class="woocommerce-hp_pp-bank-details"><h2 class="wc-hp_pp-bank-details-heading">' . __( 'Our bank details', 'woocommerce' ) . '</h2>' . PHP_EOL . $account_html . '</section>';
			}
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
		$order->update_status( 'on-hold', __( 'Awaiting HP_PP payment', 'woocommerce' ) );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'    => 'success',
			'redirect'  => $this->get_return_url( $order ),
		);

	}
}
