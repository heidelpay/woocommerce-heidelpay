<?php
/**
 * Secured Invoice
 *
 * WooCommerce payment gateway for Secured Invoice
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

use Heidelpay\PhpPaymentApi\PaymentMethods\InvoiceB2CSecuredPaymentMethod;

class WC_Gateway_HP_IVPG extends WC_Heidelpay_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

    public function checkoutValidation()
    {
        // If gateway is not active no validation is necessary.
        if ($this->isGatewayActive() === false) {
            return true;
        }

        if (!$this->is18($_POST['birthdate']) || empty($_POST['birthdate'])) {
            wc_add_notice(
                __('You have to be at least 18 years old in order to use secured invoice', 'woocommerce-heidelpay'),
                'error'
            );
        }
        if (empty($_POST['salutation'])) {
            wc_add_notice(
                __('You have to enter your salutation', 'woocommerce-heidelpay'),
                'error'
            );
        }

        if (!empty(wc()->customer->get_billing_company())) {
            wc_add_notice(
                __('You are not allowed to use secured invoice with a company name', 'woocommerce-heidelpay'),
                'error'
            );
        }
    }

    private function is18($given)
    {
        $given = strtotime($given);
        $min = strtotime('+18 years', $given);
        if (time() < $min) {
            return false;
        }
        return true;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['title']['default'] = sprintf(__('%s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['description']['default'] = sprintf(
            __('Insert payment data for %s', 'woocommerce-heidelpay'),
            $this->name
        );
        $this->form_fields['instructions']['default'] = __(
            'please send the money to IBAN BIC ',
            'woocommerce-heidelpay'
        );
        $this->form_fields['enabled']['label'] = sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name);
        $this->form_fields['security_sender']['default'] = '31HA07BC8142C5A171745D00AD63D182';
        $this->form_fields['user_login']['default'] = '31ha07bc8142c5a171744e5aef11ffd3';
        $this->form_fields['user_password']['default'] = '93167DE7';
        $this->form_fields['transaction_channel']['default'] = '31HA07BC81856CAD6D8E05CDDE7E2AC8';

        $this->form_fields['send_payment_info'] = array(
            'title' => __('Payment information mail', 'woocommerce-heidelpay'),
            'type' => 'checkbox',
            'description' => __(
                'Add payment information to e-mail',
                'woocommerce-heidelpay'
            ),
            'default' => 'yes',
            'desc_tip' => true,
        );

        $this->form_fields['advanced'] = array(
            'title' => __('Advanced options', 'woocommerce-heidelpay'),
            'type' => 'title',
            'description' => ''
        );

        $this->form_fields['min'] = array(
            'title' => __('Minimum Amount', 'woocommerce-heidelpay'),
            'type' => 'text',
            'default' => 10,
            'desc_tip' => true,
        );

        $this->form_fields['max'] = array(
            'title' => __('Maxmimum Amount', 'woocommerce-heidelpay'),
            'type' => 'text',
            'default' => 1000,
            'desc_tip' => true,
        );

        $this->form_fields['availableCountries'] = array(
            'title' => __('Available Countries for secured invoice', 'woocommerce-heidelpay'),
            'type' => 'title',
            'description' => __(
                'Here you can enable secured Invoice for specific countries. Be aware that this will not enable the ' .
                'country in your WooCommerce settings and you have to enable them seperately.',
                'woocommerce-heidelpay'
            )
        );

        $this->form_fields['availableDE'] = array(
            'title' => __('Enable Germany', 'woocommerce-heidelpay'),
            'type' => 'checkbox',
            'default' => 'yes',
        );

        $this->form_fields['availableAT'] = array(
            'title' => __('Enable Austria', 'woocommerce-heidelpay'),
            'type' => 'checkbox',
            'default' => 'no',
        );

        //Switzerland not working yet
        /*
        $this->form_fields['availableCH'] = array(
            'title' => __('Enable Switzerland', 'woocommerce-heidelpay'),
            'type' => 'checkbox',
            'default' => 'no',
        );
        */
    }

    /**
     * @param $available_gateways
     * @return mixed
     */
    public function setAvailability($available_gateways)
    {
        $security = true;
        $customer = wc()->customer;
        $cart = wc()->cart;

        if ($customer !== null && $cart !== null) {
            if (!empty($customer->get_billing_company())) {
                $security = false;
            }

            if (!in_array($customer->get_billing_country(), $this->getEnabledCountries(), true)) {
                $security = false;
            }

            if ($customer->get_billing_address_1() !== $customer->get_shipping_address_1() ||
                $customer->get_billing_address_2() !== $customer->get_shipping_address_2() ||
                $customer->get_billing_city() !== $customer->get_shipping_city() ||
                $customer->get_billing_postcode() !== $customer->get_shipping_postcode()
            ) {
                $security = false;
            }

            if ($cart->get_totals()['total'] > $this->get_option('max') ||
                $cart->get_totals()['total'] < $this->get_option('min')) {
                $security = false;
            }
        } else {
            $security = false;
        }

        if (!$security) {
            unset($available_gateways[$this->id]);
        }
        return $available_gateways;
    }

    /**
     * This function checks which countries are enabled for secured invoice
     * and returns an array containing these country short codes
     *
     * @return array
     */
    private function getEnabledCountries()
    {
        $availableCountries = array();

        if ($this->get_option('availableDE') === 'yes') {
            $availableCountries[] = 'DE';
        }
        if ($this->get_option('availableAT') === 'yes') {
            $availableCountries[] = 'AT';
        }
        if ($this->get_option('availableCH') === 'yes') {
            $availableCountries[] = 'CH';
        }

        return $availableCountries;
    }

    public function payment_fields()
    {
        $salutationText = __('Salutation', 'woocommerce-heidelpay');
        $salutationMText = __('Mr', 'woocommerce-heidelpay');
        $salutationWText = __('Mrs', 'woocommerce-heidelpay');
        $birthdateText = __('Birthdate', 'woocommerce-heidelpay');

        echo '<div>';

        echo
            '<label for="hp_salutation">' . $salutationText . ':</label>' .
            '<select name="salutation" id="hp_salutation" class="form-row-wide validate-required">' .
            '<option selected disabled>' . $salutationText . '</option>' .
            '<option value="' . $salutationMText . '">' . $salutationMText . '</option>' .
            '<option value="' . $salutationWText . '">' . $salutationWText . '</option>' .
            '</select>' .
            '<br/>' .
            '<label for="hp_date">' . $birthdateText . ':</label>' .
            '<input type="date" name="birthdate" id="hp_date" value="" class="form-row-wide validate-required"/>' .
            '<br/>';

        echo '</div>';
        echo '<script>
                var date_input = document.getElementById("hp_date");
                
                date_input.reportValidity = function() {
                    var inputDate = this.valueAsDate;
                    var currentDate = new Date();
                    if(new Date(currentDate-inputDate).getFullYear() - new Date(0).getFullYear() < 18){
                        return false;
                    }
                    return true;
                };
                
                date_input.onchange = function () {
                    if(!this.reportValidity() && jQuery("ul[class=woocommerce-error]")[0] == undefined){
                        jQuery("form[name=checkout]").prepend(\'' . $this->ErrorHtml() . '\');
                        jQuery("ul[class=woocommerce-error]")[0].scrollIntoView({behavior : "smooth"})
                    }else{
                        if(this.reportValidity() && jQuery("ul[class=woocommerce-error]")[0] !== undefined){
                            jQuery("ul[class=woocommerce-error]").remove();
                        }
                    }
                };
              </script>';
    }

    public function ErrorHtml()
    {
        $errorText = __(
            'You have to be at least 18 years old in order to use secured invoice',
            'woocommerce-heidelpay'
        );
        return '<ul class="woocommerce-error" role="alert">' .
            '<li>' . $errorText . '</li>' .
            '</ul>';
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

    protected function handleFormPost()
    {
        parent::handleFormPost();

        if (!empty($_POST['salutation']) && !empty($_POST['birthdate'])) {
            $this->payMethod->getRequest()->b2cSecured($_POST['salutation'], $_POST['birthdate']);
        }
    }
}
