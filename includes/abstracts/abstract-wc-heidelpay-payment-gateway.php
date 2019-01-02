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
    public $bookingAction;
    protected $name;
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
        add_action('woocommerce_api_push', array($this, 'pushHandler'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('woocommerce_after_checkout_validation', array($this, 'checkoutValidation'));
        add_action('woocommerce_email_before_order_table', array($this, 'emailInstructions'), 10, 3);

        // Filter
        add_filter('woocommerce_available_payment_gateways', array($this, 'setAvailability'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'addPayInfo'));
    }

    /**
     * Set the id and PaymentMethod
     */
    abstract protected function setPayMethod();

    /**
     * Initiates the form fields
     */
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
                'default' => '',
                'desc_tip' => true,
            ),
            'security_sender' => array(
                'title' => __('Security Sender', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_security_sender',
                'description' => 'Security Sender',
                'default' => '31HA07BC8142C5A171745D00AD63D182'
            ),
            'user_login' => array(
                'title' => __('User Login', 'woocommerce-heidelpay'),
                'type' => 'text',
                'id' => $this->id . '_user_login',
                'description' => 'User Login',
                'default' => '31ha07bc8142c5a171744e5aef11ffd3'
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
     * Handles an incoming Push Notification
     *
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\XmlResponseParserException
     */
    public function pushHandler()
    {
        if (array_key_exists('<?xml_version', $_POST)) {
            $push = new WC_Heidelpay_Push();
            $push->init(file_get_contents('php://input'), $this->get_option('secret'));
        }
        exit;
    }

    /**
     * Validate the customer input coming from checkout.
     * @return void
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
        return !empty($_POST['payment_method']) && $_POST['payment_method'] === $this->id;
    }

    /**
     * register scripts and stylesheets for your payment gateway
     */
    public function enqueue_assets()
    {
        wp_register_script(
            'heidelpay-secured',
            WC_HEIDELPAY_PLUGIN_URL . '/assets/js/securedInvoice.js',
            [],
            false,
            true
        );

        wp_enqueue_script('heidelpay-secured');
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array|mixed
     * @throws \Heidelpay\PhpPaymentApi\Exceptions\PaymentFormUrlException
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $this->prepareRequest($order);

        return $this->performRequest($order);
    }

    /**
     * @param $order WC_Order
     */
    public function prepareRequest(WC_Order $order)
    {
        $this->setAuthentification($order);
        $this->setAsync();
        $this->setCustomer($order);
        $this->setBasket($order->get_id());
        $this->setCriterions();
        $this->payMethod->getRequest()->getContact()->setIp(WC_Geolocation::get_ip_address());
    }

    /**
     * Set up your authentification data for Heidepay api
     * @param WC_order $order
     */
    protected function setAuthentification(WC_order $order = null)
    {
        $isSandbox = false;
        $channel = $this->get_option('transaction_channel');
        if ($this->get_option('sandbox') === 'yes') {
            $isSandbox = true;
        }
        if (class_exists('WC_Subscriptions_Order')) {
            if ($order !== null && (wcs_order_contains_renewal($order) || wcs_order_contains_subscription($order))) {
                $channel = $this->get_option('transaction_channel_subscription');
            }
        }
        $this->payMethod->getRequest()->authentification(
            $this->get_option('security_sender'),
            $this->get_option('user_login'),
            $this->get_option('user_password'),
            $channel,
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

    /**
     * Get the Language set in WordPress Settings
     *
     * @return string
     */
    public function getLanguage()
    {
        if (strpos(get_locale(), 'de_') !== false) {
            return 'de';
        }
        return 'en';
    }

    /**
     * @param WC_Order $order
     */
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

    /**
     * @param $order_id
     */
    protected function setBasket($order_id)
    {
        $order = wc_get_order($order_id);
        $this->payMethod->getRequest()->basketData(
            $order_id, //order id
            round($order->get_total(), 2),                         //cart amount
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
        $this->payMethod->getRequest()->getCriterion()->set('PUSH_URL', get_home_url() . '/wc-api/push');
        $this->payMethod->getRequest()->getCriterion()->set('SHOP.TYPE', $shopType);
        $this->payMethod->getRequest()->getCriterion()->set(
            'SHOPMODULE.VERSION',
            'heidelpay gateway ' . WC_HEIDELPAY_VERSION
        );
    }

    /**
     * Send payment request.
     * Validation happens before this in the checkoutValidation() function.
     *
     * @param $order
     * @param null $uid
     * @return mixed
     */
    public function performRequest($order, $uid = null)
    {
        if (!empty($_POST)) {
            try{
                $this->handleFormPost($_POST);
            } catch (\Exception $e) {
                wc_get_logger()->log(WC_Log_Levels::DEBUG, htmlspecialchars(print_r($e->getMessage(), 1)));
                return null;
            }
        }

        if (!empty($this->bookingAction) && method_exists($this->payMethod, $this->bookingAction)) {
            if (class_exists('WC_Subscriptions_Order') &&
                wcs_order_contains_subscription($order) &&
                empty($order->get_meta('heidelpay-Registration'))) {
                $action = 'registration';
            } else {
                $action = $this->getBookingAction();
            }
            try {
                $this->payMethod->$action($uid);
            } catch (Exception $e) {
                wc_get_logger()->log(WC_Log_Levels::DEBUG, htmlspecialchars(print_r($e->getMessage(), 1)));

                $this->addPaymentError($this->getErrorMessage());

                return null;
            }

            if ($this->payMethod->getResponse()->isSuccess()) {
                if ($this->payMethod->getResponse()->getFrontend()->getRedirectUrl() !== '' ||
                    !empty($this->payMethod->getResponse()->getFrontend()->getRedirectUrl())) {
                    return [
                        'result' => 'success',
                        'redirect' => $this->payMethod->getResponse()->getPaymentFormUrl(),
                    ];
                }
            }

            $this->paymentLog($this->payMethod->getResponse()->getError());
            $this->addPaymentError($this->getErrorMessage());
        } else {
            $this->addPaymentError($this->getErrorMessage());

            wc_get_logger()->log(
                WC_Log_Levels::ERROR,
                htmlspecialchars(
                    print_r(
                        $this->plugin_id . ' - ' . $this->id . __(
                            ' Error: Paymentmethod was not found: ',
                            'woocommerce-heidelpay'
                        ) . $this->bookingAction,
                        1
                    )
                )
            );

            return null;
        }
    }

    /**
     * process the Form input from customer coming from checkout.
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
    public function addPaymentError($message)
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

    /**
     * Funktion to log Events as a notice. It has a prefix to identify that the log entry is from heidelpay and which
     * function has created it.
     * @param  string|array $logData
     */
    protected function paymentLog($logData)
    {
        $callers = debug_backtrace();
        wc_get_logger()->log(WC_Log_Levels::NOTICE, print_r('heidelpay - ' .
            $callers [1] ['function'] . ': ' . print_r($logData, 1), 1));
    }

    /**
     * @param $order
     * @param $uid
     */
    public function performNoGuiRequest($order, $uid)
    {
        $this->performAfterRegistrationRequest($order, $uid);
    }

    /**
     * @param WC_Order $order
     * @param $uid
     */
    public function performAfterRegistrationRequest($order, $uid)
    {
        if (!empty($_POST)) {
            $this->handleFormPost($_POST);
        }
        if ($order->get_meta('heidelpay-Registration') !== '') {
            try {
                $this->payMethod->debitOnRegistration($uid);
            } catch (Exception $e) {
                wc_get_logger()->log(WC_Log_Levels::DEBUG, htmlspecialchars(print_r($e->getMessage(), 1)));

                $this->addPaymentError($this->getErrorMessage());
            }
            if ($this->payMethod->getResponse()->isError()) {
                $order->set_status('on-hold');
            }
        }
    }

    /**
     * echoes the admin options
     */
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
     * "woocommerce_thankyou_order_received_text" hook to display heidelpay-paymentInfo text on the successpage after
     * payment.
     * @param $orderReceivedText
     * @return string
     */
    public function addPayInfo($orderReceivedText)
    {
        /**
         * @var WC_Order $order
         */
        $order = $this->getOrderFromKey();

        if (!$order instanceof WC_Order || $order->get_payment_method() !== $this->id) {
            return $orderReceivedText;
        }

        $paymentInfo = $order->get_meta('heidelpay-paymentInfo');

        if (!empty($paymentInfo)) {
            $orderReceivedText .= '<p>' . $paymentInfo . '</p>';
        }

        return $orderReceivedText;
    }

    /**
     * Get the order using the Get parameter 'key'
     * @return bool|WC_Order|WC_Refund
     */
    public function getOrderFromKey()
    {
        if (isset($_GET['key'])) {
            $order_id = wc_get_order_id_by_order_key($_GET['key']);
            return wc_get_order($order_id);
        }

        return null;
    }

    /**
     * Hook - "woocommerce_email_before_order_table". Add heidelpay-paymentInfo text to "completed order" email.
     * @param WC_Order $order
     * @return null
     */
    public function emailInstructions(WC_Order $order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return null;
        }

        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }

        $status = $order->get_status();
        // defines the statuses when the mail should be send
        $mailingArray = array(
            'pending',
            'on-hold',
            'processing'
        );

        if ($this->get_option('send_payment_info') === 'yes') {
            if (in_array($status, $mailingArray)) {
                echo $order->get_meta('heidelpay-paymentInfo');
            }
        }
    }

    /**
     * @return array Containing the option field to select booking mode in the admin menu.
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
