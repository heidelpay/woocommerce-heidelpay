<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * credit card
 */
require_once (WC_HEIDELPAY_PLUGIN_PATH.'/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\CreditCardPaymentMethod;

class WC_Gateway_HP_CC extends WC_Heidelpay_Payment_Gateway {

	/** @var array Array of locales */
	public $locale;

    public function setPayMethod()
    {
        $this->payMethod = new CreditCardPaymentMethod();
        $this->id                 = 'hp_cc';
        $this->name = 'Credit Card';
        $this->has_fields         = false;
        $this->method_description = __('heidelpay credit card', 'woocommerce-heidelpay');
    }

    public function payment_fields()
    {

        /*wp_register_script('heidelpay-iFrame',
            WC_HEIDELPAY_PLUGIN_URL . 'includes/js/creditCardFrame.js'
        );*/

        wp_enqueue_script('heidelpay-iFrame');

        $this->setAuthentification();
        $this->setAsync();

        $this->payMethod->registration(
            'http://qa.heidelpay.intern',
            // PaymentFrameOrigin - uri of your application like https://dev.heidelpay.com
            'FALSE'
        );

        echo '<form method="post" class="formular" id="paymentFrameForm">';
        if ($this->payMethod->getResponse()->isSuccess()) {
            echo '<iframe id="paymentFrameIframe" src="'
                . $this->payMethod->getResponse()->getPaymentFormUrl()
                . '" frameborder="0" scrolling="no" style="height:250px;"></iframe><br />';
        } else {
            echo get_home_url().'/wp-content/plugins/woocommerce-heidelpay/vendor/';
            echo '<pre>' . print_r($this->payMethod->getResponse()->getError(), 1) . '</pre>';
        }
        echo '</form>';
    }

    /**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

        parent::init_form_fields();

        $this->form_fields['description']['default'] = __('Insert payment data for '
            . $this->name, 'woocommerce-heidelpay');
        $this->form_fields['title']['default'] = __($this->name, 'woocommerce-heidelpay');
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC8142C5A171744F3D6D155865';
	}

    /**
     * Output for the order received page.
     *
     * @param int $order_id
     */
    public function thankyou_page($order_id) {

        if ($this->instructions) {
            echo wpautop(wptexturize(wp_kses_post($this->instructions)));
        }
        $this->bank_details($order_id);
    }

    /**
	 * Add content to the WC emails.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( ! $sent_to_admin && 'hp_cc' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
			$this->bank_details( $order->get_id() );
		}
	}

    protected function performRequest()
    {
        $logger = wc_get_logger();
        try {
            $this->payMethod->registration(
                'http://qa.heidelpay.intern',
                'FALSE'
            );
        } catch(\Exception $exception) {
            $logger->log(WC_Log_Levels::DEBUG, print_r('Paymethod not found',1));
            // TODO: redirect to errorpage
        }

        //logging and debug
        $logger->log(WC_Log_Levels::DEBUG, print_r($this->payMethod->getRequest(),1));
        mail('david.owusu@heidelpay.de', 'woo-request', print_r($this->payMethod->getResponse(),1));

        if($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl()
            ];
        }

        return [
            'result' => 'failed',
            'redirect' => 'https://www.google.de/'
        ];
    }



        /*
         * <html>
<head>
	<title>credit card debit example</title>
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
