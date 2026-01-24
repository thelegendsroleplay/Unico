<?php
/*
Plugin Name: Unico Voucher Checkout
Description: Custom voucher checkout UI using WooCommerce as backend only (no cart/checkout templates).
Version: 1.0.0
Author: Unico
Text Domain: unico-voucher-checkout
*/

if (!defined('ABSPATH')) {
	exit;
}

define('UNICO_VC_VERSION', '1.0.0');
define('UNICO_VC_PLUGIN_FILE', __FILE__);
define('UNICO_VC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UNICO_VC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once UNICO_VC_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['Unico_VC_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Unico_VC_Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
	Unico_VC_Plugin::instance();
});

