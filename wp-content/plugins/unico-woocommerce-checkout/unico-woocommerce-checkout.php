<?php
/**
 * Plugin Name: Unico WooCommerce Checkout
 * Plugin URI: https://unico.com
 * Description: Custom WooCommerce checkout with bank transfer, transaction ID, payment proof upload, and voucher generation
 * Version: 1.0.0
 * Author: Unico
 * Author URI: https://unico.com
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('UNICO_WC_VERSION', '1.0.0');
define('UNICO_WC_PLUGIN_FILE', __FILE__);
define('UNICO_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UNICO_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>Unico WooCommerce Checkout</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

/**
 * Main Plugin Class
 */
class Unico_WC_Checkout {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-order-status.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-bank-accounts.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-custom-payment-gateway.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-checkout-fields.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-admin-orders.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-voucher-generator.php';
        require_once UNICO_WC_PLUGIN_DIR . 'includes/class-emails.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'), 11);

        // Register payment gateway
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize order status
        Unico_Order_Status::instance();

        // Initialize bank accounts
        Unico_Bank_Accounts::instance();

        // Initialize checkout fields
        Unico_Checkout_Fields::instance();

        // Initialize admin orders
        Unico_Admin_Orders::instance();

        // Initialize voucher generator
        Unico_Voucher_Generator::instance();

        // Initialize emails
        Unico_Emails::instance();
    }

    /**
     * Register payment gateway
     */
    public function register_gateway($gateways) {
        $gateways[] = 'Unico_Bank_Transfer_Gateway';
        return $gateways;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_checkout() || is_wc_endpoint_url('order-received')) {
            wp_enqueue_style('unico-checkout', UNICO_WC_PLUGIN_URL . 'assets/css/checkout.css', array(), UNICO_WC_VERSION);
            wp_enqueue_script('unico-checkout', UNICO_WC_PLUGIN_URL . 'assets/js/checkout.js', array('jquery'), UNICO_WC_VERSION, true);

            wp_localize_script('unico-checkout', 'unicoCheckout', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('unico_checkout_nonce'),
            ));
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook === 'woocommerce_page_wc-settings' ||
            $hook === 'post.php' ||
            $hook === 'edit.php') {
            wp_enqueue_style('unico-admin', UNICO_WC_PLUGIN_URL . 'assets/css/admin.css', array(), UNICO_WC_VERSION);
            wp_enqueue_script('unico-admin', UNICO_WC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), UNICO_WC_VERSION, true);

            wp_localize_script('unico-admin', 'unicoAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('unico_admin_nonce'),
            ));
        }
    }
}

/**
 * Initialize plugin
 */
function unico_wc_checkout() {
    return Unico_WC_Checkout::instance();
}

// Start the plugin
unico_wc_checkout();
