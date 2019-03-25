<?php
/**
 * PayPal
 *
 * WooCommerce payment gateway for PayPal
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
require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'traits' .
    DIRECTORY_SEPARATOR . 'trait-wc-heidelpay-subscription-gateway.php';

use Heidelpay\PhpPaymentApi\PaymentMethods\PayPalPaymentMethod;

class WC_Gateway_HP_VA extends WC_Heidelpay_Payment_Gateway
{
    use WC_Heidelpay_Subscription_Gateway;

    /** @var array Array of locales */
    public $locale;
    protected $bookingModes;

    /**
     * WC_Gateway_HP_VA constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->constructorAddon();
    }

    /**
     * set the id and payment method
     */
    public function setPayMethod()
    {
        $this->payMethod = new PayPalPaymentMethod();
        $this->id = 'hp_va';
        $this->name = 'PayPal';

        $this->bookingModes = array(
            'PA' => 'authorize',
            'DB' => 'debit'
        );
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initFormFieldsAddon();

        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8124365CA41D4BDA79CCCD22';

        $this->form_fields['bookingmode'] = $this->getBookingSelection();
    }

    /**
     * @return String get the transaction type to use for payment
     */
    public function getBookingAction()
    {
        return (string)$this->bookingModes[$this->get_option('bookingmode')];
    }

    public function setCriterions($orderID = null)
    {
        parent::setCriterions();

        $this->payMethod->getRequest()->getCriterion()->set('PAYPAL_PAYMENTREQUEST_0_INVNUM', $orderID);
    }
}
