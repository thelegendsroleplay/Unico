<?php
/**
 * Custom Cart Handlers
 * Handles add-to-cart and cart management actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Cart_Handlers {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Handle add-to-cart actions
        add_action('template_redirect', [$this, 'handle_add_to_cart']);

        // AJAX add to cart
        add_action('wp_ajax_unico_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_unico_add_to_cart', [$this, 'ajax_add_to_cart']);

        // AJAX remove from cart
        add_action('wp_ajax_unico_remove_from_cart', [$this, 'ajax_remove_from_cart']);
        add_action('wp_ajax_nopriv_unico_remove_from_cart', [$this, 'ajax_remove_from_cart']);

        // Add to cart redirect filter
        add_filter('unico_add_to_cart_redirect', [$this, 'add_to_cart_redirect'], 10, 2);
    }

    /**
     * Handle add-to-cart form submission
     */
    public function handle_add_to_cart() {
        $product_id = 0;
        
        if (isset($_REQUEST['unico_add_to_cart'])) {
            $product_id = intval($_REQUEST['unico_add_to_cart']);
        } elseif (isset($_REQUEST['add-to-cart'])) {
            $product_id = intval($_REQUEST['add-to-cart']);
        }
        
        if (!$product_id || !isset($_REQUEST['unico_add_to_cart_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['unico_add_to_cart_nonce'], 'unico_add_to_cart')) {
            return;
        }

        $quantity = isset($_REQUEST['quantity']) ? intval($_REQUEST['quantity']) : 1;

        // Check if user is logged in
        if (!is_user_logged_in()) {
            // Redirect to login
            wp_redirect(add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), home_url('/login')));
            exit;
        }

        // Validate product exists
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            wp_redirect(home_url('/vouchers'));
            exit;
        }

        // Check if it's a voucher product
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $is_voucher = in_array('vouchers', $categories);

        if (!$is_voucher) {
            wp_redirect(home_url('/vouchers'));
            exit;
        }

        // Add to cart
        $cart = Unico_Cart::get_instance();
        $cart_item_key = $cart->add_to_cart($product_id, $quantity);

        if ($cart_item_key) {
            // Log activity
            if (class_exists('Unico_Security')) {
                $security = Unico_Security::get_instance();
                $security->log_activity(get_current_user_id(), 'product_added_to_cart', "Product #{$product_id} added to cart", [
                    'product_id' => $product_id,
                    'quantity' => $quantity
                ]);
            }

            // Redirect
            $redirect_url = apply_filters('unico_add_to_cart_redirect', home_url('/checkout'), $product_id);
            wp_redirect($redirect_url);
            exit;
        } else {
            wp_redirect(home_url('/vouchers'));
            exit;
        }
    }

    /**
     * Add to cart redirect
     */
    public function add_to_cart_redirect($url, $product_id) {
        // Always redirect to checkout for vouchers
        return home_url('/checkout');
    }

    /**
     * AJAX: Add to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('unico_cart', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => 'Please log in to add items to cart',
                'redirect' => home_url('/login')
            ]);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if ($product_id < 1) {
            wp_send_json_error(['message' => 'Invalid product']);
        }

        $cart = Unico_Cart::get_instance();
        $cart_item_key = $cart->add_to_cart($product_id, $quantity);

        if ($cart_item_key) {
            wp_send_json_success([
                'message' => 'Product added to cart',
                'cart_count' => $cart->get_cart_item_count(),
                'cart_total' => $cart->get_total('display'),
                'redirect' => home_url('/checkout')
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add product to cart']);
        }
    }

    /**
     * AJAX: Remove from cart
     */
    public function ajax_remove_from_cart() {
        check_ajax_referer('unico_cart', 'nonce');

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

        if (empty($cart_item_key)) {
            wp_send_json_error(['message' => 'Invalid cart item']);
        }

        $cart = Unico_Cart::get_instance();
        $removed = $cart->remove_cart_item($cart_item_key);

        if ($removed) {
            wp_send_json_success([
                'message' => 'Item removed from cart',
                'cart_count' => $cart->get_cart_item_count(),
                'cart_total' => $cart->get_total('display')
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to remove item']);
        }
    }
}
