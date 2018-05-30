<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

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

    public function process_payment($order_id)
    {
        return $this->performRequest($order_id);
    }

    protected function performRequest($order_id)
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

        $this->form_fields['bookingmode'] = $this->getBookingSelection();
    }

    /**
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    public function after_pay()
    {
        $order_id = wc_get_order_id_by_order_key($_GET['key']);
        $order = wc_get_order($order_id);

        if ($order->get_payment_method() === $this->id) {
            $this->getIFrame($order);
        }
    }

    /**
     * @param $order_id
     * @param $order
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\UndefinedTransactionModeException
     */
    protected function getIFrame( WC_Order $order)
    {
        wp_enqueue_script('heidelpay-iFrame');

        $this->setAuthentification();
        $this->setAsync();
        $this->setCustomer($order);
        $this->setBasket($order->get_id());

        $protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
        $host = $protocol . '://' . $_SERVER['SERVER_NAME'];
        $cssPath = WC_HEIDELPAY_PLUGIN_URL . '/assets/css/creditCardFrame.css';

        $bookingaction = $this->getBookingAction();

        $this->payMethod->$bookingaction(
            $host, // PaymentFrameOrigin - uri of your application like https://dev.heidelpay.com
            'FALSE',
            $cssPath
        );

        echo '<form method="post" class="formular" id="paymentFrameForm">';
        if ($this->payMethod->getResponse()->isSuccess()) {
            echo '<iframe id="paymentFrameIframe" src="'
                . $this->payMethod->getResponse()->getPaymentFormUrl()
                . '" frameborder="0" scrolling="no" style="height:250px;"></iframe><br />';
        } else {
            echo get_home_url() . '/wp-content/plugins/woocommerce-heidelpay/vendor/';
            echo '<pre>' . print_r($this->payMethod->getResponse()->getError(), 1) . '</pre>';
        }
        echo '<button type="submit">' . __('Pay Now', 'woocommerce-heidelpay') . '</button>';
        echo '</form>';
    }

    public function getBookingAction() {
        return $this->bookingModes[$this->get_option('bookingmode')];
    }
}
