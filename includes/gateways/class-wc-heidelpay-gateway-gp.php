<?php
/**
 * GiroPay
 *
 * WooCommerce payment gateway for GiroPay
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

use Heidelpay\PhpPaymentApi\PaymentMethods\GiropayPaymentMethod;

class WC_Gateway_HP_GP extends WC_Heidelpay_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

	/**
	 * set the id and payment method
	 */
    public function setPayMethod()
    {
        $this->payMethod = new GiropayPaymentMethod();
        $this->id = 'hp_gp';
        $this->name = __('GiroPay', 'woocommerce-heidelpay');
        $this->has_fields = false;
        $this->bookingAction = 'authorize';
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171740166AF277E03';
    }

    /**
     * @return bool|void
     */
    public function checkoutValidation()
    {
        $isValid = parent::checkoutValidation();

        // If gateway is not active no validation is necessary.
        if ($this->isGatewayActive() === false) {
            return true;
        }
        return $isValid;
    }

    /**
     * @param $available_gateways
     * @return mixed
     */
    public function setAvailability($available_gateways)
    {
        $available = true;

        if (wc()->customer !== null) {
            if (wc()->customer->get_billing_country() !== 'DE') {
                $available = false;
            }
        } else {
            $available = false;
        }

        if (!$available) {
            unset($available_gateways[$this->id]);
        }

        return $available_gateways;
    }
}
