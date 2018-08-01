<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Heidelpay\MessageCodeMapper\MessageCodeMapper;

/**
 * Plugin Name: heidelpay WooCommerce
 * Plugin URI: https://dev.heidelpay.com
 * Description: heidelpay payment integration for WooCommerce
 * Version: 1.2.0
 * Author: heidelpay
 * Author URI: htts://www.heidelpay.com
 * Developer: heidelpay
 * Developer URI: https://dev.heidelpay.com
 * Text Domain: woocommerce-heidelpay
 * Domain Path: /languages
 *
 * Copyright: © 2018-present heidelpay GmbH
 * License: see LICENSE.txt
 */

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    /**
     * Required minimums and constants
     */
    define('WC_HEIDELPAY_VERSION', '1.2.0');
    define('WC_HEIDELPAY_MIN_PHP_VER', '5.6.0');
    define('WC_HEIDELPAY_MIN_WC_VER', '3.0.0');
    define('WC_HEIDELPAY_MAIN_FILE', __FILE__);
    define('WC_HEIDELPAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
    define('WC_HEIDELPAY_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

    if (!class_exists('WC_Heidelpay')) :

        class WC_Heidelpay
        {

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * @var Reference to logging class.
             */
            private static $log;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone()
            {
            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup()
            {
            }

            /**
             * Notices (array)
             * @var array
             */
            public $notices = array();

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            protected function __construct()
            {
                add_action('admin_init', array($this, 'check_environment'));
                add_action('admin_notices', array($this, 'admin_notices'), 15);
                add_action('plugins_loaded', array($this, 'init'));
                add_action('woocommerce_before_cart', array($this, 'errorNotice'));
            }

            public function errorNotice()
            {
                if (isset($_GET['errorCode'])) {
                    $mapper = new MessageCodeMapper(get_locale());
                    $message = $mapper->getMessage($_GET['errorCode']);
                    wc_print_notice($message, 'error');
                }
            }

            /**
             * Init the plugin after plugins_loaded so environment variables are set.
             */
            public function init()
            {
                // Don't hook anything else in the plugin if we're in an incompatible environment
                if (self::get_environment_warning()) {
                    return;
                }

                load_plugin_textdomain(
                    'woocommerce-heidelpay',
                    false,
                    plugin_basename(dirname(__FILE__)) . '/languages'
                );

                require_once dirname(__FILE__) . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-cc.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-dc.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-idl.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-dd.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-ivpg.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-so.php';
                require_once dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-va.php';
                require_once dirname(__FILE__) . '/includes/class-wc-heidelpay-response.php';
                require_once dirname(__FILE__) . '/includes/class-wc-heidelpay-push.php';

                add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
            }

            /**
             * Add the gateways to WooCommerce.
             * @param $methods
             * @return array
             */
            public function add_gateways($methods)
            {
                $methods[] = 'WC_Gateway_HP_CC';
                $methods[] = 'WC_Gateway_HP_DC';
                $methods[] = 'WC_Gateway_HP_IDL';
                $methods[] = 'WC_Gateway_HP_DD';
                $methods[] = 'WC_Gateway_HP_IVPG';
                $methods[] = 'WC_Gateway_HP_SO';
                $methods[] = 'WC_Gateway_HP_VA';

                return $methods;
            }

            /**
             * The backup sanity check, in case the plugin is activated in a weird way,
             * or the environment changes after activation. Also handles upgrade routines.
             */
            public function check_environment()
            {
                if (!defined('IFRAME_REQUEST') && (WC_HEIDELPAY_VERSION !== get_option('wc_heidelpay_version'))) {
                    $this->install();

                    do_action('woocommerce_heidelpay_updated');
                }

                $environment_warning = self::get_environment_warning();
            }

            /**
             * Allow this class and other classes to add slug keyed notices (to avoid duplication)
             * @param $slug
             * @param $class
             * @param $message
             */
            public function add_admin_notice($slug, $class, $message)
            {
                $this->notices[$slug] = array(
                    'class' => $class,
                    'message' => $message,
                );
            }

            /**
             * Updates the plugin version in db
             *
             * @since 1.0.0
             * @version 1.2.0
             * @return bool
             */
            private static function _update_plugin_version()
            {
                delete_option('wc_heidelpay_version');
                update_option('wc_heidelpay_version', WC_HEIDELPAY_VERSION);

                return true;
            }

            /**
             * Handles upgrade routines.
             *
             * @since 1.0.0
             * @version 1.2.0
             */
            public function install()
            {
                if (!defined('WC_HEIDELPAY_INSTALLING')) {
                    define('WC_HEIDELPAY_INSTALLING', true);
                }

                static::_update_plugin_version();
            }

            /**
             * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
             * found or false if the environment has no problems.
             */
            public static function get_environment_warning()
            {
                if (version_compare(phpversion(), WC_HEIDELPAY_MIN_PHP_VER, '<')) {
                    $message = __('minimal PHP version error', 'woocommerce-heidelpay');

                    return sprintf($message, WC_HEIDELPAY_MIN_PHP_VER, phpversion());
                }

                if (!defined('WC_VERSION')) {
                    return __('WooCommerce plugin not activated', 'woocommerce-heidelpay');
                }

                if (version_compare(WC_VERSION, WC_HEIDELPAY_MIN_WC_VER, '<')) {
                    $message = __('minimal WC version error', 'woocommerce-heidelpay');

                    return sprintf($message, WC_HEIDELPAY_MIN_WC_VER, WC_VERSION);
                }

                if (!function_exists('curl_init')) {
                    return __('cURL is not installed.', 'woocommerce-heidelpay');
                }

                return false;
            }

            /**
             * Get setting link.
             *
             * @since 1.0.0
             *
             * @return string Setting link
             */
            public function get_setting_link()
            {
                $use_id_as_section = function_exists('WC') ? version_compare(wc()->version, '2.6', '>=') : false;

                $section_slug = $use_id_as_section ? 'heidelpay' : strtolower('WC_Heidelpay');

                return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
            }

            /**
             * Display any notices
             */
            public function admin_notices()
            {
                foreach ((array)$this->notices as $notice_key => $notice) {
                    echo "<div class='" . esc_attr($notice['class']) . "'><p>";
                    echo wp_kses($notice['message'], array('a' => array('href' => array())));
                    echo '</p></div>';
                }
            }

            /**
             * logging
             * @param $message
             */
            public static function log($message)
            {
                if (empty(self::$log)) {
                    self::$log = new WC_Logger();
                }

                self::$log->add('heidelpay-woocommerce', $message);
            }
        }

        WC_Heidelpay::get_instance();

    endif;

}
