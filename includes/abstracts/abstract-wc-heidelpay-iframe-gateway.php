<?php
/**
 * Abstract iFrame gateway
 *
 * WooCommerce base class for iFrame based payment methods like Credit Card and Debit Card
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
    exit;
}

require_once WC_HEIDELPAY_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'abstracts' .
    DIRECTORY_SEPARATOR . 'abstract-wc-heidelpay-payment-gateway.php';

/**
 * Class WC_Heidelpay_IFrame_Gateway
 * Provide payment using an iFrame.
 */
abstract class WC_Heidelpay_IFrame_Gateway extends WC_Heidelpay_Payment_Gateway
{
    protected $bookingModes;

    public function __construct()
    {
        parent::__construct();
        add_action('after_woocommerce_pay', array($this, 'after_pay'));

        $this->bookingModes = array(
            'PA' => 'authorize',
            'DB' => 'debit'
        );
    }

    /**
     * @param int $order_id
     * @return array|mixed
     */
    public function process_payment($order_id)
    {
        return $this->toCheckoutPayment($order_id);
    }

    /**
     * Redirect to an extra Checkout page.
     * @param $order_id
     * @return array
     */
    public function toCheckoutPayment($order_id)
    {
        $order = wc_get_order($order_id);

        $order->update_status('pending', __('Awaiting payment', 'woocommerce-heidelpay'));
        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        ];
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
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171744F3D6D155865';

        $this->form_fields['description']['default'] = '';
        $this->form_fields['bookingmode'] = $this->getBookingSelection();
    }

    /**
     * register scripts and stylesheets for your payment gateway
     */
    public function enqueue_assets()
    {
        wp_register_script(
            'heidelpay-iFrame',
            WC_HEIDELPAY_PLUGIN_URL . '/assets/js/creditCardFrame.js',
            [],
            false,
            true
        );
    }

    /**
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function after_pay()
    {
        $order = $this->getOrderFromKey();

        if ($order !== null && $order->get_payment_method() === $this->id) {
            $this->performRequest($order);
        }
    }

    /**
     * @param $order
     * @param null $uid
     * @return mixed|void
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function performRequest($order, $uid = null)
    {
        echo $this->getIFrame($order);
    }

    /**
     * Build the Iframe and return the String
     * @param $order
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     * @return String
     */
    protected function getIFrame(WC_Order $order)
    {
        // Load script for payment cards
        wp_enqueue_script('heidelpay-iFrame');

        $this->prepareRequest($order);
        // build host url and css path
        $urlArray = explode('/', get_home_url());
        $host = $urlArray[0] . '//' . $urlArray[2];

        $cssPath = WC_HEIDELPAY_PLUGIN_URL . '/assets/css/creditCardFrame.css';

        $bookingAction = $this->getBookingAction();

        if (method_exists($this->payMethod, $bookingAction)) {
            if (class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order)) {
                $bookingAction = 'registration';
            }
            $this->payMethod->$bookingAction(
                $host,
                'FALSE',
                $cssPath
            );

            $iFrame = '';

            $response = $this->payMethod->getResponse();
            if ($response->isSuccess()) {
                $iFrame = '<form method="post" class="formular" id="paymentFrameForm">'
                . '<iframe id="paymentFrameIframe" src="'
                . $response->getPaymentFormUrl()
                . '" frameborder="0" scrolling="no" style="height:360px;"></iframe><br />'
                . '<button type="submit">' . __('Pay Now', 'woocommerce-heidelpay') . '</button>'
                . '</form>';
            } else {
                $paymentError = $response->getError();
                $this->paymentLog($paymentError);
                wc_print_notice($this->getErrorMessage(), 'error');
            }

            return $iFrame;
        }

        $this->addPaymentError($this->getErrorMessage());
        wc_print_notices();

        wc_get_logger()->error(
            htmlspecialchars(
                print_r(
                    $this->plugin_id . ' - ' . $this->id . __(
                        ' Error: Paymentmethod was not found: ',
                        'woocommerce-heidelpay'
                    ) . $bookingAction,
                    1
                )
            ),
            array('source' => 'heidelpay')
        );
        return null;
    }

    /**
     * @return mixed|string
     */
    public function getBookingAction()
    {
        if (!empty($this->bookingModes[$this->get_option('bookingmode')])) {
            return $this->bookingModes[$this->get_option('bookingmode')];
        }
        return $this->bookingAction;
    }
}
