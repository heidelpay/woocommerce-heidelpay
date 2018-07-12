<?php
/**
 * Abstract heidelpay
 *
 * WooCommerce payment base gateway for heidelpay payment methods
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

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' .
    DIRECTORY_SEPARATOR . 'autoload.php';

use Heidelpay\MessageCodeMapper\MessageCodeMapper;
use Heidelpay\PhpPaymentApi\Response;

abstract class WC_Heidelpay_Payment_Gateway extends WC_Payment_Gateway
{

    public $payMethod;
    protected $name;
    protected $bookingAction;
    protected $messageMapper;

    public function __construct()
    {
        $this->has_fields = false;
        $this->bookingAction = 'debit';
        $this->messageMapper = new MessageCodeMapper(get_locale());

        $this->setPayMethod();
        $this->method_title = sprintf(__('%s', 'woocommerce-heidelpay'), strtoupper($this->id));
        $this->method_description = sprintf(__('heidelpay %s', 'woocommerce-heidelpay'), $this->name);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'callback_handler'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('woocommerce_after_checkout_validation', array($this, 'checkoutValidation'));

        // Filter
        add_filter('woocommerce_available_payment_gateways', array($this, 'setAvailability'));
    }

    /**
     * Set the id and PaymentMethod
     */
    abstract protected function setPayMethod();

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s', 'woocommerce-heidelpay'), $this->name),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-heidelpay'),
                'type' => 'text',
                'description' =>
                    __('This controls the title which the user sees during checkout.', 'woocommerce-heidelpay'),
                'default' => $this->name,
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __(
                    'Payment method description that the customer will see on your checkout.',
                    'woocommerce-heidelpay'
                ),
                'default' => sprintf(__('Insert payment data for %s', 'woocommerce-heidelpay'), $this->name),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce-heidelpay'),
                'type' => 'textarea',
                'description' => __(
                    'Instructions that will be added to the thank you page and emails.',
                    'woocommerce-heidelpay'
                ),
                'default' => __('The following account will be billed:', 'woocommerce-heidelpay'),
                'desc_tip' => true,
            ),
            'security_sender' => array(
                'title' => __('Security Sender', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_security_sender',
                'description' => 'Security Sender',
                'default' => ''
            ),
            'user_login' => array(
                'title' => __('User Login', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_user_login',
                'description' => 'User Login',
                'default' => ''
            ),
            'user_password' => array(
                'title' => __('User Password', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_user_password',
                'description' => 'User Password',
                'default' => '93167DE7'
            ),
            'transaction_channel' => array(
                'title' => __('Transaction Channel', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_transaction_channel',
                'description' => 'Transaction Channel',
                'default' => ''
            ),
            'secret' => array(
                'title' => __('Secret', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_secret',
                'description' => 'A secret passphrase from your application',
                'default' => 'secret',
                'desc_tip' => true
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'woocommerce-heidelpay'),
                'type' => 'checkbox',
                'id' => $this->id . '_sandbox',
                'label' => __('Enable sandbox mode', 'woocommerce-heidelpay'),
                'default' => 'yes'
            ),
        );
    }

    /**
     * Validate the customer input coming from checkout.
     * @return boolean
     */
    public function checkoutValidation()
    {
        //return true;
    }

    /**
     * Check whether this paymethod was selected based on
     * @return bool
     */
    public function isGatewayActive()
    {
        if (!empty($_POST['payment_method'])) {
            if ($_POST['payment_method'] === $this->id)
                return true;
        }

        return false;
    }

    /**
     * register scripts and stylesheets for your payment gateway
     */
    public function enqueue_assets()
    {
        wp_register_script('heidelpay-secured',
            WC_HEIDELPAY_PLUGIN_URL . '/assets/js/securedInvoice.js',
            [],
            false,
            true
        );

        wp_enqueue_script('heidelpay-secured');
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $this->prepareRequest($order);

        return $this->performRequest($order_id);
    }

    /**
     * @param $order WC_Order
     */
    public function prepareRequest(WC_Order $order)
    {
        $this->setAuthentification();
        $this->setAsync();
        $this->setCustomer($order);
        $this->setBasket($order->get_id());
        $this->setCriterions();
    }

    /**
     * Set up your authentification data for Heidepay api
     */
    protected function setAuthentification()
    {
        $isSandbox = false;
        if ($this->get_option('sandbox') === 'yes') {
            $isSandbox = true;
        }
        $this->payMethod->getRequest()->authentification(
            $this->get_option('security_sender'),
            $this->get_option('user_login'),
            $this->get_option('user_password'),
            $this->get_option('transaction_channel'),
            $isSandbox
        );
    }

    /**
     * Set up asynchronous request parameters
     */
    protected function setAsync()
    {
        $this->payMethod->getRequest()->async(
            $this->getLanguage(), // Language code for the Frame
            get_home_url() . '/wc-api/' . strtolower(get_class($this))
        );
    }

    public function getLanguage()
    {
        if (strpos(get_locale(), 'de_') !== false) {
            return 'de';
        }
        return 'en';
    }

    protected function setCustomer(WC_Order $order)
    {
        $this->payMethod->getRequest()->customerAddress(
            $order->get_billing_first_name(),                  // Given name
            $order->get_billing_last_name(),           // Family name
            $order->get_billing_company(),                     // Company Name
            $order->get_customer_id(),                   // Customer id of your application
            $order->get_billing_address_1() . $order->get_billing_address_2(),          // Billing address street
            $order->get_billing_state(),                   // Billing address state
            $order->get_billing_postcode(),                   // Billing address post code
            $order->get_billing_city(),              // Billing address city
            $order->get_billing_country(),                      // Billing address country code
            $order->get_billing_email()         // Customer mail address
        );
    }

    protected function setBasket($order_id)
    {
        $order = wc_get_order($order_id);
        $this->payMethod->getRequest()->basketData(
            $order_id, //order id
            $order->get_total(),                         //cart amount
            'EUR',                         // Currency code of this request
            $this->get_option('secret')    // A secret passphrase from your application
        );
    }

    /**
     * @global string $wp_version
     */
    protected function setCriterions()
    {
        global $wp_version;

        $shopType = 'WordPress: ' . $wp_version . ' - ' . 'WooCommerce: ' . wc()->version;
        $this->payMethod->getRequest()->getCriterion()->set('PUSH_URL', get_permalink(wc_get_page_id('shop')) . 'wc-api/' . strtolower(get_class($this))); //TODO insert URL
        $this->payMethod->getRequest()->getCriterion()->set('SHOP.TYPE', $shopType);
        $this->payMethod->getRequest()->getCriterion()->set(
            'SHOPMODULE.VERSION',
            'heidelpay gateway ' . WC_HEIDELPAY_VERSION
        );
    }

    /**
     * Send payment request.
     * Validation happens before this in the checkoutValidation() function.
     * @return mixed
     */
    protected function performRequest($order_id)
    {
        if (!empty($_POST)) {
            $this->handleFormPost($_POST);
        }

        if (!empty($this->bookingAction) AND method_exists($this->payMethod, $this->bookingAction)) {
            $action = $this->getbookingAction();
            try {
                $this->payMethod->$action();
            } catch (Exception $e) {
                wc_get_logger()->log(WC_Log_Levels::DEBUG, htmlspecialchars(print_r($e->getMessage(), 1)));

                $this->addPaymentError($this->getErrorMessage());

                return null;
            }

            if ($this->payMethod->getResponse()->isSuccess()) {
                return [
                    'result' => 'success',
                    'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl(),
                ];
            }

            $this->addPaymentError($this->getErrorMessage());
        } else {
            $this->addPaymentError($this->getErrorMessage());

            wc_get_logger()->log(
                WC_Log_Levels::ERROR,
                htmlspecialchars(
                    print_r(
                        $this->plugin_id . ' - ' . $this->id . __(' Error: Paymentmethod was not found: ', 'woocommerce-heidelpay') . $this->bookingAction,
                        1
                    )
                )
            );

            return null;
        }
    }

    /**
     * process the Form input from customer comimg from checkout.
     */
    protected function handleFormPost()
    {
    }

    /**
     * @return string
     */
    public function getBookingAction()
    {
        return $this->bookingAction;
    }

    /**
     * @param String $message
     */
    public function addPaymentError(String $message)
    {
        wc_add_notice(
            __('Payment error: ', 'woocommerce-heidelpay') . htmlspecialchars($message),
            'error'
        );
    }

    /**
     * Get the mapped Errormessage from Respone wich is html escaped.
     * If a response is given as a parameter that will determine the message. Otherwise the Response from the payMethod
     * is used. If none of them is given return the default message
     * @param Response|null $response
     * @return string
     */
    protected function getErrorMessage(Response $response = null)
    {
        if ($response !== null || $this->payMethod->getResponse() !== null) {
            if (!empty($response['code'])) {
                return htmlspecialchars($this->messageMapper->getMessage($response->getError()['code']));
            }
            if (!empty($this->payMethod->getResponse()->getError()['code'])) {
                return htmlspecialchars(
                    $this->messageMapper->getMessage($this->payMethod->getResponse()->getError()['code'])
                );
            }
        }

        return $this->messageMapper->getDefaultMessage();
    }

    public function admin_options()
    {
        echo '<h2>';
        printf(__('heidelpay %s', 'woocommerce'), strtoupper($this->id));
        echo '</h2>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * Action for woocommerce_api.
     * Handle the Response coming from payment system and return redirect url.
     */
    public function callback_handler()
    {
        if (array_key_exists('<?xml_version', $_POST)) {
            $push = new WC_Heidelpay_Push();
            $push->init(file_get_contents('php://input'), $this->get_option('secret'));
            exit;
        }
        if (!empty($_POST)) {
            $response = new WC_Heidelpay_Response();
            $response->init($_POST, $this->get_option('secret'));
        }
        exit();
    }

    /**
     * Filter function for the hook: woocommerce_available_payment_gateways
     * Can be used to set conditions vor availability of a paymethod.
     * @param $available_gateways
     * @return mixed
     */
    public function setAvailability($available_gateways)
    {
        return $available_gateways;
    }

    /**
     * @return array Containing the optionfield to select booking mode in the admin menue.
     */
    protected function getBookingSelection()
    {
        return array(
            'title' => __('Bookingmode', 'woocommerce-heidelpay'),
            'type' => 'select',
            'options' => array(
                'DB' => __('Debit', 'woocommerce-heidelpay'),
                'PA' => __('Authorization', 'woocommerce-heidelpay')
            ),
            'id' => $this->id . '_bookingmode',
            'label' => __('Choose a bookingmode', 'woocommerce-heidelpay'),
            'default' => 'DB'
        );
    }
}
