<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Plugin Name: heidelpay WooCommerce
 * Plugin URI: https://dev.heidelpay.com
 * Description: heidelpay payment integration for WooCommerce
 * Version: 1.0.0
 * Author: heidelpay
 * Author URI: http://woocommerce.com/
 * Developer: heidelpay
 * Developer URI: https://dev.heidelpay.com
 * Text Domain: woocommerce-heidelpay
 * Domain Path: /languages
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    /**
     * Required minimums and constants
     */
    define( 'WC_HEIDELPAY_VERSION', '3.2.3' );
    define( 'WC_HEIDELPAY_MIN_PHP_VER', '5.6.0' );
    define( 'WC_HEIDELPAY_MIN_WC_VER', '2.5.0' );
    define( 'WC_HEIDELPAY_MAIN_FILE', __FILE__ );
    define( 'WC_HEIDELPAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
    define( 'WC_HEIDELPAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

    if ( ! class_exists( 'WC_Heidelpay' ) ) :

        class WC_Heidelpay {

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
            public static function get_instance() {
                if ( null === self::$instance ) {
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
            private function __clone() {}

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            private function __wakeup() {}

            /**
             * Flag to indicate whether or not we need to load code for / support subscriptions.
             *
             * @var bool
             */

            /**
             * Notices (array)
             * @var array
             */
            public $notices = array();

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            protected function __construct() {
                add_action( 'admin_init', array( $this, 'check_environment' ) );
                add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
                add_action( 'plugins_loaded', array( $this, 'init' ) );
            }

            /**
             * Init the plugin after plugins_loaded so environment variables are set.
             */
            public function init() {
                require_once( dirname( __FILE__ ) . '/includes/class-wc-heidelpay-logger.php' );

                // Don't hook anything else in the plugin if we're in an incompatible environment
                if ( self::get_environment_warning() ) {
                    return;
                }

                load_plugin_textdomain( 'woocommerce-heidelpay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

                require_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-heidelpay-payment-gateway.php' );
                require_once( dirname( __FILE__ ) . '/includes/class-wc-heidelpay.php' );
                require_once( dirname( __FILE__ ) . '/includes/gateways/class-wc-heidelpay-gateway-cc.php' );
                require_once( dirname( __FILE__ ) . '/includes/gateways/class-wc-heidelpay-gateway-dc.php' );
                require_once( dirname( __FILE__ ) . '/includes/gateways/class-wc-heidelpay-gateway-dd.php' );
                require_once( dirname( __FILE__ ) . '/includes/gateways/class-wc-heidelpay-gateway-pp.php' );
                require_once(dirname(__FILE__) . '/includes/gateways/class-wc-heidelpay-gateway-ivpg.php');
                require_once( dirname( __FILE__ ) . '/includes/gateways/class-wc-heidelpay-gateway-so.php' );
                require_once( dirname( __FILE__ ) . '/includes/class-wc-heidelpay-payment-request.php' );

                add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
                add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );
            }

            /**
             * Add the gateways to WooCommerce.
             */
            public function add_gateways( $methods ) {
                $methods[] = 'WC_Gateway_HP_CC';
                $methods[] = 'WC_Gateway_HP_DC';
                $methods[] = 'WC_Gateway_HP_DD';
                $methods[] = 'WC_Gateway_HP_PP';
                $methods[] = 'WC_Gateway_HP_IVPG';
                $methods[] = 'WC_Gateway_HP_SO';

                return $methods;
            }

            /**
             * Modifies the order of the gateways displayed in admin.
             */
            public function filter_gateway_order_admin( $sections ) {
                //unset( $sections['heidelpay'] );
                unset( $sections['hp_cc'] );
                unset( $sections['hp_dc'] );
                unset( $sections['hp_dd'] );
                unset( $sections['hp_so'] );
                unset( $sections['heidelpay_pp'] );
                unset( $sections['hp_ivpg'] );

                //$sections['heidelpay']            = 'heidelpay';
                $sections['hp_cc'] = __( 'heidelpay CC', 'woocommerce-heidelpay' );
                $sections['hp_dc'] = __( 'heidelpay DC', 'woocommerce-heidelpay' );
                $sections['hp_dd'] = __( 'heidelpay DD', 'woocommerce-heidelpay' );
                $sections['hp_pp'] = __( 'heidelpay PP', 'woocommerce-heidelpay' );
                $sections['hp_ivpg'] = __( 'heidelpay IVPG', 'woocommerce-heidelpay' );
                $sections['hp_so'] = __( 'heidelpay SO', 'woocommerce-heidelpay' );

                return $sections;
            }

            /**
             * The backup sanity check, in case the plugin is activated in a weird way,
             * or the environment changes after activation. Also handles upgrade routines.
             */
            public function check_environment() {
                if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_HEIDELPAY_VERSION !== get_option( 'wc_heidelpay_version' ) ) ) {
                    $this->install();

                    do_action( 'woocommerce_heidelpay_updated' );
                }

                $environment_warning = self::get_environment_warning();
            }

            /**
             * Allow this class and other classes to add slug keyed notices (to avoid duplication)
             */
            public function add_admin_notice( $slug, $class, $message ) {
                $this->notices[ $slug ] = array(
                    'class'   => $class,
                    'message' => $message,
                );
            }

            /**
             * Updates the plugin version in db
             *
             * @since 1.0.0
             * @version 1.0.0
             * @return bool
             */
            private static function _update_plugin_version() {
                delete_option( 'wc_heidelpay_version' );
                update_option( 'wc_heidelpay_version', WC_HEIDELPAY_VERSION );

                return true;
            }

            /**
             * Handles upgrade routines.
             *
             * @since 1.0.0
             * @version 1.0.0
             */
            public function install() {
                if ( ! defined( 'WC_HEIDELPAY_INSTALLING' ) ) {
                    define( 'WC_HEIDELPAY_INSTALLING', true );
                }

                $this->_update_plugin_version();
            }

            /**
             * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
             * found or false if the environment has no problems.
             */
            static function get_environment_warning() {
                if ( version_compare( phpversion(), WC_HEIDELPAY_MIN_PHP_VER, '<' ) ) {
                    $message = __( 'minimal PHP version error', 'woocommerce-heidelpay' );

                    return sprintf( $message, WC_HEIDELPAY_MIN_PHP_VER_MIN_PHP_VER, phpversion() );
                }

                if ( ! defined( 'WC_VERSION' ) ) {
                    return __( 'WooCommerce plugin not activated', 'woocommerce-heidelpay' );
                }

                if ( version_compare( WC_VERSION, WC_HEIDELPAY_MIN_WC_VER, '<' ) ) {
                    $message = __( 'minimal WC version error', 'woocommerce-heidelpay' );

                    return sprintf( $message, WC_HEIDELPAY_MIN_WC_VER, WC_VERSION );
                }

                if ( ! function_exists( 'curl_init' ) ) {
                    return __( 'cURL is not installed.', 'woocommerce-heidelpay' );
                }

                return false;
            }

            /**
             * Adds plugin action links
             *
             * @since 1.0.0
             */
            public function plugin_action_links( $links ) {
                $y_link = $this->get_setting_link();

                $plugin_links = array(
                    '<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-heidelpay' ) . '</a>',
                    '<a href="https://docs.woocommerce.com/document/heidelpay/">' . __( 'Docs', 'woocommerce-heidelpay' ) . '</a>',
                    '<a href="https://woocommerce.com/contact-us/">' . __( 'Support', 'woocommerce-heidelpay' ) . '</a>',
                );
                return array_merge( $plugin_links, $links );
            }

            /**
             * Get setting link.
             *
             * @since 1.0.0
             *
             * @return string Setting link
             */
            public function get_setting_link() {
                $use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

                $section_slug = $use_id_as_section ? 'heidelpay' : strtolower( 'WC_Heidelpay' );

                return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
            }

            /**
             * Display any notices
             */
            public function admin_notices() {
                foreach ( (array) $this->notices as $notice_key => $notice ) {
                    echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
                    echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
                    echo '</p></div>';
                }
            }

            /**
             * logging
             */
            public static function log( $message ) {
                if ( empty( self::$log ) ) {
                    self::$log = new WC_Logger();
                }

                self::$log->add( 'heidelpay-woocommerce', $message );
            }
        }

        WC_Heidelpay::get_instance();

    endif;

}

