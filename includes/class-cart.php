<?php
/**
 * Custom Shopping Cart System
 * Replaces WooCommerce cart with session-based implementation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Cart {

    private static $instance = null;
    private $cart_session_key = 'unico_cart';
    private $cart_contents = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->load_cart();
    }

    /**
     * Load cart from session
     */
    private function load_cart() {
        if (!session_id()) {
            session_start();
        }

        $this->cart_contents = isset($_SESSION[$this->cart_session_key])
            ? $_SESSION[$this->cart_session_key]
            : [];
    }

    /**
     * Save cart to session
     */
    private function save_cart() {
        if (!session_id()) {
            session_start();
        }

        $_SESSION[$this->cart_session_key] = $this->cart_contents;
    }

    /**
     * Add product to cart
     */
    public function add_to_cart($product_id, $quantity = 1, $meta = []) {
        $product_id = intval($product_id);
        $quantity = intval($quantity);

        if ($quantity < 1) {
            return false;
        }

        // Check if product exists (using WordPress post system)
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            return false;
        }

        // Generate cart item key
        $cart_item_key = $this->generate_cart_id($product_id, $meta);

        // Check if item already in cart
        if (isset($this->cart_contents[$cart_item_key])) {
            $this->cart_contents[$cart_item_key]['quantity'] += $quantity;
        } else {
            // Get product details
            $product_name = get_the_title($product_id);
            $product_price = $this->get_product_price($product_id);
            $exam_name = get_post_meta($product_id, 'exam_name', true);

            $this->cart_contents[$cart_item_key] = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'quantity' => $quantity,
                'price' => $product_price,
                'exam_name' => $exam_name,
                'meta' => $meta,
                'added_at' => current_time('timestamp')
            ];
        }

        $this->save_cart();

        do_action('unico_cart_item_added', $cart_item_key, $product_id, $quantity, $this);

        return $cart_item_key;
    }

    /**
     * Get product price
     */
    private function get_product_price($product_id) {
        // Check for custom pricing first
        if (class_exists('Unico_Pricing')) {
            $pricing = Unico_Pricing::get_instance();
            // This will use the existing pricing system
        }

        // Get base price from post meta
        $price = get_post_meta($product_id, '_price', true);
        if (!$price) {
            $price = get_post_meta($product_id, '_regular_price', true);
        }

        // Apply role-based pricing if user is logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $roles = $user->roles;

            if (in_array('unico_agent', $roles) || in_array('unico_reseller', $roles)) {
                $agent_price = get_post_meta($product_id, '_voucher_agent_price', true);
                if ($agent_price !== '') {
                    $price = floatval($agent_price);
                }
            }
        }

        return floatval($price);
    }

    /**
     * Remove item from cart
     */
    public function remove_cart_item($cart_item_key) {
        if (isset($this->cart_contents[$cart_item_key])) {
            unset($this->cart_contents[$cart_item_key]);
            $this->save_cart();

            do_action('unico_cart_item_removed', $cart_item_key, $this);

            return true;
        }

        return false;
    }

    /**
     * Update cart item quantity
     */
    public function set_quantity($cart_item_key, $quantity) {
        $quantity = intval($quantity);

        if ($quantity <= 0) {
            return $this->remove_cart_item($cart_item_key);
        }

        if (isset($this->cart_contents[$cart_item_key])) {
            $old_quantity = $this->cart_contents[$cart_item_key]['quantity'];
            $this->cart_contents[$cart_item_key]['quantity'] = $quantity;
            $this->save_cart();

            do_action('unico_cart_item_quantity_updated', $cart_item_key, $quantity, $old_quantity, $this);

            return true;
        }

        return false;
    }

    /**
     * Get cart contents
     */
    public function get_cart() {
        return $this->cart_contents;
    }

    /**
     * Get cart item count
     */
    public function get_cart_item_count() {
        $count = 0;
        foreach ($this->cart_contents as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Check if cart is empty
     */
    public function is_empty() {
        return empty($this->cart_contents);
    }

    /**
     * Get cart subtotal
     */
    public function get_cart_subtotal() {
        $subtotal = 0;

        foreach ($this->cart_contents as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        return $subtotal;
    }

    /**
     * Get cart total
     */
    public function get_cart_total() {
        $subtotal = $this->get_cart_subtotal();
        $tax = $this->get_cart_tax();

        return $subtotal + $tax;
    }

    /**
     * Get cart tax
     */
    public function get_cart_tax() {
        // For now, no tax calculation
        // Can be implemented based on requirements
        return 0;
    }

    /**
     * Get formatted cart total
     */
    public function get_total($format = 'edit') {
        $total = $this->get_cart_total();

        if ($format === 'display') {
            return '$' . number_format($total, 2);
        }

        return $total;
    }

    /**
     * Calculate cart totals (compatibility method)
     */
    public function calculate_totals() {
        // This method exists for compatibility
        // Totals are calculated on-the-fly in get_cart_total()
        $this->save_cart();
        return true;
    }

    /**
     * Empty cart
     */
    public function empty_cart() {
        $this->cart_contents = [];
        $this->save_cart();

        do_action('unico_cart_emptied', $this);

        return true;
    }

    /**
     * Generate cart item ID
     */
    private function generate_cart_id($product_id, $meta = []) {
        $meta_hash = md5(json_encode($meta));
        return md5($product_id . $meta_hash);
    }

    /**
     * Get cart item by product ID
     */
    public function find_product_in_cart($product_id) {
        foreach ($this->cart_contents as $key => $item) {
            if ($item['product_id'] == $product_id) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Check if cart contains voucher items
     */
    public function cart_has_voucher_items() {
        foreach ($this->cart_contents as $item) {
            $product_id = $item['product_id'];
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            if (in_array('vouchers', $categories)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get voucher cart quantity
     */
    public function get_voucher_cart_quantity() {
        $qty = 0;

        foreach ($this->cart_contents as $item) {
            $product_id = $item['product_id'];
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

            if (in_array('vouchers', $categories)) {
                $qty += $item['quantity'];
            }
        }

        return $qty;
    }

    /**
     * Get cart contents count (number of different products)
     */
    public function get_cart_contents_count() {
        return count($this->cart_contents);
    }

    /**
     * Destroy cart session
     */
    public function destroy() {
        $this->empty_cart();
        if (isset($_SESSION[$this->cart_session_key])) {
            unset($_SESSION[$this->cart_session_key]);
        }
    }
}

/**
 * Global function to get cart instance (replaces WC()->cart)
 */
function UNICO() {
    return (object) [
        'cart' => Unico_Cart::get_instance()
    ];
}
