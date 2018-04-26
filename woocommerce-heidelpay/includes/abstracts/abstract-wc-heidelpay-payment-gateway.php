<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WC_Heidelpay_Payment_Gateway extends WC_Payment_Gateway {
	const META_NAME_FEE = 'Heidelpay Fee';
	const META_NAME_NET = 'Net Revenue From Heidelpay';

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

		return parent::is_available();
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 */
	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}

	/**
	 * Remove admin notice.
	 */
	public function remove_admin_notice() {
		if ( did_action( 'woocommerce_update_options' ) ) {
			remove_action( 'admin_notices', array( $this, 'check_environment' ) );
		}
	}

	/**
	 * Validates that the order meets the minimum order amount
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < WC_Heidelpay_Helper::get_minimum_amount() ) {
			/* translators: 1) dollar amount */
			throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-heidelpay' ), wc_price( WC_Heidelpay_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Builds the return URL from redirects.
	 */
	public function get_heidelpay_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			if ( empty( $id ) ) {
				$id = uniqid();
			}

			$order_id = WC_Heidelpay_Helper::is_pre_30() ? $order->id : $order->get_id();

			$args = array(
				'utm_nooverride' => '1',
				'order_id'       => $order_id,
			);

			return esc_url_raw( add_query_arg( $args, $this->get_return_url( $order ) ) );
		}

		return esc_url_raw( add_query_arg( array( 'utm_nooverride' => '1' ), $this->get_return_url() ) );
	}

	/**
	 * Generate the request for the payment.
	 */
	public function generate_payment_request( $order, $source ) {
		$settings                          = get_option( 'woocommerce_heidelpay_settings', array() );
		$statement_descriptor              = ! empty( $settings['statement_descriptor'] ) ? str_replace( "'", '', $settings['statement_descriptor'] ) : '';
		$capture                           = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? true : false;
		$post_data                         = array();
		$post_data['currency']             = strtolower( $order->get_currency() );
		$post_data['amount']               = WC_Heidelpay_Helper::get_heidelpay_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description']          = sprintf( __( '%1$s - Order %2$s', 'woocommerce-heidelpay' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$billing_email      = $order->get_billing_email();
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_heidelpay_send_heidelpay_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		$post_data['expand[]'] = 'balance_transaction';

		$metadata = array(
			__( 'customer_name', 'woocommerce-heidelpay' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'customer_email', 'woocommerce-heidelpay' ) => sanitize_email( $billing_email ),
			'order_id' => $order->get_id(),
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
		 */
		return apply_filters( 'wc_heidelpay_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Store extra meta data for an order from a Heidelpay Response.
	 */
	public function process_response( $response, $order ) {
		WC_Heidelpay_Logger::log( 'Processing response: ' . print_r( $response, true ) );

		$order->get_id();

		// Store charge data
		$order->update_meta_data( '_heidelpay_charge_captured', $response->captured ? 'yes' : 'no' );

		if ( $response->captured ) {
			/**
			 * Charge can be captured but in a pending state. Payment methods
			 * that are asynchronous may take couple days to clear. Webhook will
			 * take care of the status changes.
			 */
			if ( 'pending' === $response->status ) {
				if ( ! wc_string_to_bool( get_post_meta( $order_id, '_order_stock_reduced', true ) ) ) {
					wc_reduce_stock_levels( $order_id );
				}

				$order->set_transaction_id( $response->id );
				/* translators: transaction id */
				$order->update_status( 'on-hold', sprintf( __( 'Heidelpay charge awaiting payment: %s.', 'woocommerce-heidelpay' ), $response->id ) );
			}

			if ( 'succeeded' === $response->status ) {
				$order->payment_complete( $response->id );

				/* translators: transaction id */
				$message = sprintf( __( 'Heidelpay charge complete (Charge ID: %s)', 'woocommerce-heidelpay' ), $response->id );
				$order->add_order_note( $message );
			}

			if ( 'failed' === $response->status ) {
				$error_msg = __( 'Payment processing failed. Please retry.', 'woocommerce-heidelpay' );
				$order->add_order_note( $error_msg );
				throw new Exception( $error_msg );
			}
		} else {
			$order->set_transaction_id( $response->id );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				wc_reduce_stock_levels( $order_id );
			}

			/* translators: transaction id */
			$order->update_status( 'on-hold', sprintf( __( 'Heidelpay charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-heidelpay' ), $response->id ) );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'wc_heidelpay_process_response', $response, $order );

		return $response;
	}

	/**
	 * Sends the failed order email to admin.
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Get owner details.
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		$details = array();

		$details['name']                   = $billing_first_name . ' ' . $billing_last_name;
		$details['email']                  = $order->get_billing_email();

		$phone                             = $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone']              = $phone;
		}

		$details['address']['line1']       = $order->get_billing_address_1();
		$details['address']['line2']       = $order->get_billing_address_2();
		$details['address']['state']       = $order->get_billing_state();
		$details['address']['city']        = $order->get_billing_city();
		$details['address']['postal_code'] = $order->get_billing_postcode();
		$details['address']['country']     = $order->get_billing_country();

		return (object) apply_filters( 'wc_heidelpay_owner_details', $details, $order );
	}
}
