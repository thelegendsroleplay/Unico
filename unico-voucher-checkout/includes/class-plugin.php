<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Plugin {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-checkout-page.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-ajax-order.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-bank-manager.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-voucher-generator.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-admin-verification.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-gateway-bank-transfer.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/class-emails.php';

		add_action('init', [$this, 'register_assets']);

		add_action('init', [$this, 'register_order_status']);
		add_filter('wc_order_statuses', [$this, 'add_order_status_to_list']);
		add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);

		add_filter('woocommerce_product_add_to_cart_url', [$this, 'override_add_to_cart_url'], 10, 2);
		add_filter('woocommerce_add_to_cart_form_action', [$this, 'override_form_action'], 10, 2);
		add_filter('woocommerce_product_supports', [$this, 'disable_ajax_add_to_cart_for_vouchers'], 10, 3);

		add_action('wp_loaded', [$this, 'intercept_add_to_cart_request'], 0);

		Unico_VC_Checkout_Page::instance();
		Unico_VC_Ajax_Order::instance();
		Unico_VC_Bank_Manager::instance();
		Unico_VC_Admin_Verification::instance();
		Unico_VC_Emails::instance();
	}

	public static function activate() {
		if (!class_exists('WooCommerce')) {
			wp_die('WooCommerce is required for Unico Voucher Checkout.');
		}

		self::ensure_pages();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private static function ensure_pages() {
		$checkout_page_id = self::ensure_page_with_shortcode('checkout', 'Checkout', '[unico_voucher_checkout]');
		update_option('unico_vc_checkout_page_id', (int) $checkout_page_id);

		$thankyou_page_id = self::ensure_page_with_shortcode('voucher-order-received', 'Voucher Order Received', '[unico_voucher_thankyou]');
		update_option('unico_vc_thankyou_page_id', (int) $thankyou_page_id);
	}

	private static function ensure_page_with_shortcode($slug, $title, $shortcode) {
		$existing = get_page_by_path($slug);
		if ($existing && $existing->ID) {
			if (strpos((string) $existing->post_content, $shortcode) === false) {
				wp_update_post([
					'ID' => $existing->ID,
					'post_content' => $shortcode,
				]);
			}
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post([
			'post_title' => $title,
			'post_name' => $slug,
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => $shortcode,
		]);

		return is_wp_error($page_id) ? 0 : (int) $page_id;
	}

	public static function checkout_url(array $args = []) {
		$page_id = (int) get_option('unico_vc_checkout_page_id');
		$url = $page_id ? get_permalink($page_id) : home_url('/checkout/');
		return !empty($args) ? add_query_arg($args, $url) : $url;
	}

	public static function thankyou_url(array $args = []) {
		$page_id = (int) get_option('unico_vc_thankyou_page_id');
		$url = $page_id ? get_permalink($page_id) : home_url('/voucher-order-received/');
		return !empty($args) ? add_query_arg($args, $url) : $url;
	}

	public function register_assets() {
		wp_register_style('unico-vc-checkout', UNICO_VC_PLUGIN_URL . 'assets/css/checkout.css', [], UNICO_VC_VERSION);
		wp_register_script('unico-vc-checkout', UNICO_VC_PLUGIN_URL . 'assets/js/checkout.js', ['jquery'], UNICO_VC_VERSION, true);
	}

	public function register_gateway($gateways) {
		$gateways[] = 'Unico_VC_Gateway_Bank_Transfer';
		return $gateways;
	}

	public function register_order_status() {
		register_post_status('wc-unico-verify', [
			'label' => 'Payment Verification',
			'public' => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Payment Verification <span class="count">(%s)</span>', 'Payment Verification <span class="count">(%s)</span>'),
		]);
	}

	public function add_order_status_to_list($statuses) {
		$updated = [];
		foreach ($statuses as $key => $label) {
			$updated[$key] = $label;
			if ($key === 'wc-on-hold') {
				$updated['wc-unico-verify'] = 'Payment Verification';
			}
		}
		if (!isset($updated['wc-unico-verify'])) {
			$updated['wc-unico-verify'] = 'Payment Verification';
		}
		return $updated;
	}

	private function is_voucher_product($product_id) {
		return $product_id && has_term('voucher', 'product_cat', $product_id);
	}

	public function override_add_to_cart_url($url, $product) {
		if (!$product || !method_exists($product, 'get_id')) {
			return $url;
		}
		$product_id = (int) $product->get_id();
		if ($this->is_voucher_product($product_id)) {
			return self::checkout_url(['product_id' => $product_id]);
		}
		return $url;
	}

	public function override_form_action($action, $product) {
		if ($product && method_exists($product, 'get_id')) {
			$product_id = (int) $product->get_id();
			if ($this->is_voucher_product($product_id)) {
				return self::checkout_url(['product_id' => $product_id]);
			}
		}
		return $action;
	}

	public function disable_ajax_add_to_cart_for_vouchers($supports, $feature, $product) {
		if ($feature !== 'ajax_add_to_cart') {
			return $supports;
		}
		if ($product && method_exists($product, 'get_id') && $this->is_voucher_product((int) $product->get_id())) {
			return false;
		}
		return $supports;
	}

	public function intercept_add_to_cart_request() {
		$product_id = 0;
		if (isset($_REQUEST['add-to-cart'])) {
			$product_id = absint($_REQUEST['add-to-cart']);
		} elseif (isset($_REQUEST['product_id']) && isset($_REQUEST['quantity']) && isset($_REQUEST['add-to-cart'])) {
			$product_id = absint($_REQUEST['product_id']);
		}

		if ($product_id && $this->is_voucher_product($product_id)) {
			wp_safe_redirect(self::checkout_url(['product_id' => $product_id]));
			exit;
		}
	}
}
