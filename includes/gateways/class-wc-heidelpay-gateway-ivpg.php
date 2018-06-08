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

        $this->form_fields['title']['default'] = sprintf(__('%s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['description']['default'] = sprintf(__('Insert payment data for %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['instructions']['default'] = __('please send the money to IBAN BIC ', 'woocommerce-heidelpay');
        $this->form_fields['enabled']['label'] = sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC81856CAD6D8E05CDDE7E2AC8';

        $this->form_fields['min'] = array(
            'title' => __('Minimum Value', 'woocommerce-heidelpay'),
            'type' => 'text',
            'default' => 100,
            'desc_tip' => true,

        );

        $this->form_fields['max'] = array(
            'title' => __('Maxmimum Value', 'woocommerce-heidelpay'),
            'type' => 'text',
            'default' => 1000,
            'desc_tip' => true,
        );
    }

    public function setAvailability($available_gateways)
    {
        $security = true;
        if (!empty(wc()->customer->get_billing_company())) {
            $security = false;
        }

        if (wc()->customer->get_billing_country() !== 'DE') {
            $security = false;
        }

        if (wc()->customer->get_billing_address_1() !== wc()->customer->get_shipping_address_1() ||
            wc()->customer->get_billing_address_2() !== wc()->customer->get_shipping_address_2() ||
            wc()->customer->get_billing_city() !== wc()->customer->get_shipping_city() ||
            wc()->customer->get_billing_postcode() !== wc()->customer->get_shipping_postcode()
        ) {
            $security = false;
        }

        if (wc()->cart->get_totals()['total'] > $this->get_option('max') ||
            wc()->cart->get_totals()['total'] < $this->get_option('min')) {
            $security = false;
        }

        if (!$security) {
            unset($available_gateways[$this->id]);
        }
        return $available_gateways;
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
        $this->name = __('Secured Invoice', 'woocommerce-heidelpay');
        $this->has_fields = true;
        $this->bookingAction = 'authorize';
    }

    public function checkoutValidation()
    {
        parent::checkoutValidation();

        if($this->isGatewayActive() === false) {
            return true;
        }

        // TODO: Do validation here
    }

    protected function handleFormPost()
    {
        parent::handleFormPost();

        // TODO check array keys set
        $this->payMethod->getRequest()->b2cSecured($_POST['salutation'], $_POST['birthdate']);
    }
}
