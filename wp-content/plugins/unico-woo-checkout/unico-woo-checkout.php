<?php
/**
 * Plugin Name: Unico Woo Checkout
 * Description: WooCommerce-only checkout flow with random bank accounts, manual payment proof, and admin review.
 * Version: 1.0.0
 * Author: Unico
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('UNICO_WOO_CHECKOUT_PATH')) {
    define('UNICO_WOO_CHECKOUT_PATH', plugin_dir_path(__FILE__));
}

if (!defined('UNICO_WOO_CHECKOUT_URL')) {
    define('UNICO_WOO_CHECKOUT_URL', plugin_dir_url(__FILE__));
}

require_once UNICO_WOO_CHECKOUT_PATH . 'includes/class-unico-woo-bank-accounts.php';
require_once UNICO_WOO_CHECKOUT_PATH . 'includes/class-unico-woo-statuses.php';
require_once UNICO_WOO_CHECKOUT_PATH . 'includes/class-unico-woo-checkout.php';
require_once UNICO_WOO_CHECKOUT_PATH . 'includes/class-unico-woo-admin.php';
require_once UNICO_WOO_CHECKOUT_PATH . 'includes/class-unico-woo-admin-list.php';
require_once UNICO_WOO_CHECKOUT_PATH . 'includes/functions-vouchers.php';

final class Unico_Woo_Checkout_Plugin {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function init() {
        Unico_Woo_Bank_Accounts::get_instance();
        Unico_Woo_Statuses::get_instance();
        Unico_Woo_Checkout::get_instance();
        Unico_Woo_Admin::get_instance();
        Unico_Woo_Admin_List::get_instance();
    }

    public function enqueue_assets() {
        if (function_exists('is_checkout') && is_checkout()) {
            wp_enqueue_style('unico-woo-checkout', UNICO_WOO_CHECKOUT_URL . 'assets/css/checkout.css', [], '1.0.0');
        }
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'unico-woo-under-review') !== false) {
            wp_enqueue_style('unico-woo-admin', UNICO_WOO_CHECKOUT_URL . 'assets/css/admin.css', [], '1.0.0');
            wp_enqueue_script('unico-woo-admin', UNICO_WOO_CHECKOUT_URL . 'assets/js/admin.js', ['jquery'], '1.0.0', true);
        }
    }
}

new Unico_Woo_Checkout_Plugin();
