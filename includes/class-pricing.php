<?php
/**
 * Bulk Pricing & Discount System
 * Handles dynamic pricing for agents, resellers, and bulk purchases
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Pricing {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Hook into WooCommerce pricing
        add_filter('woocommerce_product_get_price', [$this, 'apply_dynamic_pricing'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'apply_dynamic_pricing'], 99, 2);
        add_filter('woocommerce_cart_item_price', [$this, 'display_cart_item_price'], 10, 3);
    }

    /**
     * Create pricing rule
     */
    public function create_rule($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        $defaults = [
            'rule_name' => '',
            'user_role' => '',
            'product_category' => null,
            'min_quantity' => 1,
            'max_quantity' => null,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'is_active' => 1
        ];

        $data = wp_parse_args($data, $defaults);

        $inserted = $wpdb->insert($table, [
            'rule_name' => sanitize_text_field($data['rule_name']),
            'user_role' => sanitize_text_field($data['user_role']),
            'product_category' => $data['product_category'],
            'min_quantity' => intval($data['min_quantity']),
            'max_quantity' => $data['max_quantity'] ? intval($data['max_quantity']) : null,
            'discount_type' => sanitize_text_field($data['discount_type']),
            'discount_value' => floatval($data['discount_value']),
            'is_active' => intval($data['is_active']),
            'created_at' => current_time('mysql')
        ]);

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get applicable pricing rules for user
     */
    public function get_applicable_rules($user_id, $product_id, $quantity) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $user_roles = $user->roles;
        $product = wc_get_product($product_id);
        $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);

        $rules = [];

        foreach ($user_roles as $role) {
            $role_rules = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table
                WHERE user_role = %s
                AND is_active = 1
                AND min_quantity <= %d
                AND (max_quantity IS NULL OR max_quantity >= %d)
                ORDER BY discount_value DESC",
                $role, $quantity, $quantity
            ));

            foreach ($role_rules as $rule) {
                // Check if rule applies to product category
                if ($rule->product_category === null || in_array($rule->product_category, $product_categories)) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Calculate discounted price
     */
    public function calculate_discounted_price($original_price, $discount_type, $discount_value) {
        if ($discount_type === 'percentage') {
            $discount_amount = ($original_price * $discount_value) / 100;
            return $original_price - $discount_amount;
        } else {
            // Fixed discount
            return max(0, $original_price - $discount_value);
        }
    }

    /**
     * Apply dynamic pricing to products
     */
    public function apply_dynamic_pricing($price, $product) {
        if (!is_user_logged_in()) {
            return $price;
        }

        $user_id = get_current_user_id();
        $quantity = 1;

        $product_id = $product->get_id();

        $roles = (array) wp_get_current_user()->roles;
        $is_agent = in_array('unico_agent', $roles, true) || in_array('unico_reseller', $roles, true);

        $voucher_terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $is_voucher_product = in_array('vouchers', $voucher_terms, true) || $product->get_meta('is_voucher') === 'yes';

        if ($is_agent && $is_voucher_product) {
            $agent_price_meta = get_post_meta($product_id, '_voucher_agent_price', true);
            if ($agent_price_meta !== '') {
                $agent_price = (float) $agent_price_meta;
                if ($agent_price >= 0) {
                    return $agent_price;
                }
            }
        }

        // If in cart, get actual quantity
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] === $product_id) {
                    $quantity = $cart_item['quantity'];
                    break;
                }
            }
        }

        $rules = $this->get_applicable_rules($user_id, $product->get_id(), $quantity);

        if (empty($rules)) {
            return $price;
        }

        // Apply the best discount (highest discount value)
        $best_rule = $rules[0];
        $discounted_price = $this->calculate_discounted_price(
            $price,
            $best_rule->discount_type,
            $best_rule->discount_value
        );

        return $discounted_price;
    }

    /**
     * Display cart item price with discount info
     */
    public function display_cart_item_price($price_html, $cart_item, $cart_item_key) {
        if (!is_user_logged_in()) {
            return $price_html;
        }

        $user_id = get_current_user_id();
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];

        $rules = $this->get_applicable_rules($user_id, $product->get_id(), $quantity);

        if (!empty($rules)) {
            $rule = $rules[0];
            $discount_text = $rule->discount_type === 'percentage'
                ? $rule->discount_value . '% off'
                : wc_price($rule->discount_value) . ' off';

            $price_html .= '<br><small style="color: #e95134;">(' . $discount_text . ' applied)</small>';
        }

        return $price_html;
    }

    /**
     * Get all pricing rules
     */
    public function get_all_rules() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Get rules by role
     */
    public function get_rules_by_role($role) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_role = %s AND is_active = 1 ORDER BY min_quantity ASC",
            $role
        ));
    }

    /**
     * Delete rule
     */
    public function delete_rule($rule_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        return $wpdb->delete($table, ['id' => $rule_id]);
    }

    /**
     * Toggle rule status
     */
    public function toggle_rule_status($rule_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_pricing_rules';

        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $rule_id
        ));

        if (!$rule) {
            return false;
        }

        $new_status = $rule->is_active ? 0 : 1;

        return $wpdb->update($table, [
            'is_active' => $new_status
        ], ['id' => $rule_id]);
    }

    /**
     * Create default pricing rules
     */
    public function create_default_rules() {
        // Agent pricing rules
        $this->create_rule([
            'rule_name' => 'Agent - 5-10 vouchers',
            'user_role' => 'unico_agent',
            'min_quantity' => 5,
            'max_quantity' => 10,
            'discount_type' => 'percentage',
            'discount_value' => 10
        ]);

        $this->create_rule([
            'rule_name' => 'Agent - 11-25 vouchers',
            'user_role' => 'unico_agent',
            'min_quantity' => 11,
            'max_quantity' => 25,
            'discount_type' => 'percentage',
            'discount_value' => 15
        ]);

        $this->create_rule([
            'rule_name' => 'Agent - 26+ vouchers',
            'user_role' => 'unico_agent',
            'min_quantity' => 26,
            'max_quantity' => null,
            'discount_type' => 'percentage',
            'discount_value' => 20
        ]);

        // Reseller pricing rules
        $this->create_rule([
            'rule_name' => 'Reseller - 10-50 vouchers',
            'user_role' => 'unico_reseller',
            'min_quantity' => 10,
            'max_quantity' => 50,
            'discount_type' => 'percentage',
            'discount_value' => 15
        ]);

        $this->create_rule([
            'rule_name' => 'Reseller - 51-100 vouchers',
            'user_role' => 'unico_reseller',
            'min_quantity' => 51,
            'max_quantity' => 100,
            'discount_type' => 'percentage',
            'discount_value' => 20
        ]);

        $this->create_rule([
            'rule_name' => 'Reseller - 101+ vouchers',
            'user_role' => 'unico_reseller',
            'min_quantity' => 101,
            'max_quantity' => null,
            'discount_type' => 'percentage',
            'discount_value' => 25
        ]);

        return true;
    }
}
