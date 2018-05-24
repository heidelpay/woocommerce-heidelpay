<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rechnugskauf gesichert
 */
require_once(WC_HEIDELPAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php');

use Heidelpay\PhpPaymentApi\PaymentMethods\InvoiceB2CSecuredPaymentMethod;

class WC_Gateway_HP_IVPG extends WC_Heidelpay_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

    public function __construct()
    {
        parent::__construct();

        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['title']['default'] = __($this->name, 'woocommerce-heidelpay');
        $this->form_fields['description']['default'] = __('Insert payment data for ' . $this->name, 'woocommerce-heidelpay');
        $this->form_fields['instructions']['default'] = __('please send the money to IBAN BIC ', 'woocommerce-heidelpay');
        $this->form_fields['enabled']['label'] = __('Enable ' . $this->name, 'woocommerce-heidelpay');
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC81856CAD6D8E05CDDE7E2AC8';
    }

    public function payment_fields()
    {
        $salutationText = __('Salutation', 'woocommerce-heidelpay');
        $salutationMText = __('Mr', 'woocommerce-heidelpay');
        $salutationWText = __('Mrs', 'woocommerce-heidelpay');
        $birthdateText = __('Birthdate', 'woocommerce-heidelpay');

        echo '<div>';

        echo
            '<label for="salutation">' . $salutationText . ':</label>' .
            '<select name="salutation" id="salutation">' .
            '<option selected disabled>' . $salutationText . '</option>' .
            '<option value="' . $salutationMText . '">' . $salutationMText . '</option>' .
            '<option value="' . $salutationWText . '">' . $salutationWText . '</option>' .
            '</select>' .
            '<br/>' .
            '<label for="date">' . $birthdateText . ':</label>' .
            '<input type="date" name="birthdate" id="date" value="" />' .
            '<br/>';

        echo '</div>';
    }

    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }

    /**
     * Set the id and PaymenMethod
     */
    protected function setPayMethod()
    {
        $this->payMethod = new InvoiceB2CSecuredPaymentMethod();
        $this->id = 'hp_ivpg';
        $this->name = 'Secured Invoice';
        $this->has_fields = true;
    }

    /**
     * Send payment request
     * @return mixed
     */
    protected function performRequest($order_id)
    {
        $this->payMethod->getRequest()->b2cSecured($_POST['salutation'], $_POST['birthdate']);

        /**
         * Set necessary parameters for Heidelpay payment Frame and send a registration request
         */
        try {
            $this->payMethod->authorize();
        } catch (Exception $e) {
            wc_get_logger()->log(WC_Log_Levels::DEBUG, print_r($e->getMessage(), 1));
            // TODO: redirect to errorpage
            wc_add_notice(
                __('Payment error: ', 'woocommerce-heidelpay') . $this->payMethod->getResponse()->getError()['message'],
                'error'
            );
            return null;
        }

        if ($this->payMethod->getResponse()->isSuccess()) {
            return [
                'result' => 'success',
                'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl(),
            ];
        }
        wc_add_notice(
            __('Payment error: ' . $this->payMethod->getResponse()->getError()['message'], 'woocommerce-heidelpay'),
            'error'
        );
        return null;
    }
}
