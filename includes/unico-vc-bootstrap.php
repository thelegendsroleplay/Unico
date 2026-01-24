<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Unico_VC_Plugin')) {
    return;
}

define('UNICO_VC_VERSION', '1.0.0');
define('UNICO_VC_PLUGIN_FILE', get_template_directory() . '/unico-voucher-checkout/unico-voucher-checkout.php');
define('UNICO_VC_PLUGIN_DIR', trailingslashit(get_template_directory() . '/unico-voucher-checkout'));
define('UNICO_VC_PLUGIN_URL', trailingslashit(get_template_directory_uri() . '/unico-voucher-checkout'));

require_once UNICO_VC_PLUGIN_DIR . 'includes/class-plugin.php';

add_action('after_setup_theme', static function () {
    Unico_VC_Plugin::instance();
});

add_action('admin_menu', static function () {
    if (!class_exists('WooCommerce') || !current_user_can('manage_woocommerce')) {
        return;
    }
    add_submenu_page(
        'woocommerce',
        'Orders (Classic)',
        'Orders (Classic)',
        'manage_woocommerce',
        'unico-vc-orders-classic',
        static function () {
            wp_safe_redirect(admin_url('edit.php?post_type=shop_order'));
            exit;
        },
        1
    );
}, 20);

add_action('init', static function () {
    if (is_admin()) {
        return;
    }
    if (!function_exists('wc_get_product')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (wp_doing_cron()) {
        return;
    }

    Unico_VC_Plugin::activate();
}, 1);
