<?php
/**
 * Custom Order System
 * Replaces WooCommerce orders with custom implementation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Order {

    private $id;
    private $data = [];
    private $items = [];
    private $meta = [];

    /**
     * Constructor - Load order by ID or create new
     */
    public function __construct($order_id = 0) {
        if ($order_id > 0) {
            $this->load($order_id);
        }
    }

    /**
     * Load order from database
     */
    private function load($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $order_id
        ), ARRAY_A);

        if ($order) {
            $this->id = $order['id'];
            $this->data = $order;
            $this->load_items();
            $this->load_meta();
        }
    }

    /**
     * Load order items
     */
    private function load_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_order_items';

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d",
            $this->id
        ), ARRAY_A);

        foreach ($items as $item) {
            if (!empty($item['meta_data'])) {
                $item['meta_data'] = maybe_unserialize($item['meta_data']);
            }
            $this->items[] = $item;
        }
    }

    /**
     * Load order meta
     */
    private function load_meta() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_order_meta';

        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM $table WHERE order_id = %d",
            $this->id
        ), ARRAY_A);

        foreach ($meta as $row) {
            $this->meta[$row['meta_key']] = maybe_unserialize($row['meta_value']);
        }
    }

    /**
     * Create new order
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $defaults = [
            'order_number' => self::generate_order_number(),
            'user_id' => get_current_user_id(),
            'customer_email' => '',
            'customer_name' => '',
            'customer_phone' => '',
            'order_status' => 'pending',
            'payment_method' => '',
            'payment_status' => 'pending',
            'subtotal' => 0.00,
            'tax' => 0.00,
            'total' => 0.00,
            'currency' => 'USD',
            'customer_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        $order_data = wp_parse_args($data, $defaults);

        // Bank details as JSON
        if (isset($order_data['bank_details']) && is_array($order_data['bank_details'])) {
            $order_data['bank_details'] = json_encode($order_data['bank_details']);
        }

        $inserted = $wpdb->insert($table, [
            'order_number' => $order_data['order_number'],
            'user_id' => $order_data['user_id'],
            'customer_email' => sanitize_email($order_data['customer_email']),
            'customer_name' => sanitize_text_field($order_data['customer_name']),
            'customer_phone' => sanitize_text_field($order_data['customer_phone']),
            'order_status' => sanitize_text_field($order_data['order_status']),
            'payment_method' => sanitize_text_field($order_data['payment_method']),
            'payment_status' => sanitize_text_field($order_data['payment_status']),
            'subtotal' => floatval($order_data['subtotal']),
            'tax' => floatval($order_data['tax']),
            'total' => floatval($order_data['total']),
            'currency' => sanitize_text_field($order_data['currency']),
            'payment_reference' => isset($order_data['payment_reference']) ? sanitize_text_field($order_data['payment_reference']) : null,
            'payment_receipt_url' => isset($order_data['payment_receipt_url']) ? esc_url_raw($order_data['payment_receipt_url']) : null,
            'payment_receipt_path' => isset($order_data['payment_receipt_path']) ? sanitize_text_field($order_data['payment_receipt_path']) : null,
            'selected_bank_id' => isset($order_data['selected_bank_id']) ? intval($order_data['selected_bank_id']) : null,
            'bank_details' => isset($order_data['bank_details']) ? $order_data['bank_details'] : null,
            'verification_status' => isset($order_data['verification_status']) ? sanitize_text_field($order_data['verification_status']) : null,
            'customer_ip' => $order_data['customer_ip'],
            'user_agent' => $order_data['user_agent'],
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            $order_id = $wpdb->insert_id;

            // Log activity
            if (class_exists('Unico_Security')) {
                $security = Unico_Security::get_instance();
                $security->log_activity($order_data['user_id'], 'order_created', "Order #{$order_data['order_number']} created", [
                    'order_id' => $order_id,
                    'total' => $order_data['total']
                ]);
            }

            return new self($order_id);
        }

        return false;
    }

    /**
     * Generate unique order number
     */
    private static function generate_order_number() {
        return 'ORD-' . strtoupper(uniqid());
    }

    /**
     * Add item to order
     */
    public function add_item($item_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_order_items';

        $defaults = [
            'product_id' => null,
            'product_name' => '',
            'product_type' => 'voucher',
            'exam_name' => '',
            'quantity' => 1,
            'unit_price' => 0.00,
            'subtotal' => 0.00,
            'total' => 0.00,
            'tax' => 0.00,
            'meta_data' => []
        ];

        $item = wp_parse_args($item_data, $defaults);

        // Serialize meta_data
        if (is_array($item['meta_data'])) {
            $item['meta_data'] = serialize($item['meta_data']);
        }

        $inserted = $wpdb->insert($table, [
            'order_id' => $this->id,
            'product_id' => $item['product_id'],
            'product_name' => sanitize_text_field($item['product_name']),
            'product_type' => sanitize_text_field($item['product_type']),
            'exam_name' => sanitize_text_field($item['exam_name']),
            'quantity' => intval($item['quantity']),
            'unit_price' => floatval($item['unit_price']),
            'subtotal' => floatval($item['subtotal']),
            'total' => floatval($item['total']),
            'tax' => floatval($item['tax']),
            'meta_data' => $item['meta_data'],
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            $this->load_items(); // Reload items
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update order status
     */
    public function update_status($new_status, $note = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $old_status = $this->data['order_status'];

        $updated = $wpdb->update($table, [
            'order_status' => sanitize_text_field($new_status),
            'updated_at' => current_time('mysql')
        ], ['id' => $this->id]);

        if ($updated) {
            $this->data['order_status'] = $new_status;

            // Add note
            if ($note) {
                $this->add_note($note);
            }

            // Log activity
            if (class_exists('Unico_Security')) {
                $security = Unico_Security::get_instance();
                $security->log_activity($this->data['user_id'], 'order_status_changed', "Order #{$this->data['order_number']} status: {$old_status} → {$new_status}", [
                    'order_id' => $this->id,
                    'old_status' => $old_status,
                    'new_status' => $new_status
                ]);
            }

            // Trigger actions based on status
            do_action('unico_order_status_changed', $this->id, $old_status, $new_status, $this);
            do_action('unico_order_status_' . $new_status, $this->id, $this);
        }

        return $updated;
    }

    /**
     * Update payment status
     */
    public function update_payment_status($status) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $updated = $wpdb->update($table, [
            'payment_status' => sanitize_text_field($status),
            'updated_at' => current_time('mysql')
        ], ['id' => $this->id]);

        if ($updated) {
            $this->data['payment_status'] = $status;
        }

        return $updated;
    }

    /**
     * Add order note
     */
    public function add_note($note) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $current_notes = $this->data['order_notes'] ? maybe_unserialize($this->data['order_notes']) : [];

        if (!is_array($current_notes)) {
            $current_notes = [];
        }

        $current_notes[] = [
            'note' => sanitize_textarea_field($note),
            'added_at' => current_time('mysql'),
            'added_by' => get_current_user_id()
        ];

        $wpdb->update($table, [
            'order_notes' => serialize($current_notes)
        ], ['id' => $this->id]);

        $this->data['order_notes'] = serialize($current_notes);

        return true;
    }

    /**
     * Get order notes
     */
    public function get_notes() {
        if (!isset($this->data['order_notes'])) {
            return [];
        }

        $notes = maybe_unserialize($this->data['order_notes']);
        return is_array($notes) ? $notes : [];
    }

    /**
     * Update meta
     */
    public function update_meta($key, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_order_meta';

        // Check if meta exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_id = %d AND meta_key = %s",
            $this->id, $key
        ));

        $value_serialized = maybe_serialize($value);

        if ($exists) {
            $wpdb->update($table, [
                'meta_value' => $value_serialized
            ], [
                'order_id' => $this->id,
                'meta_key' => $key
            ]);
        } else {
            $wpdb->insert($table, [
                'order_id' => $this->id,
                'meta_key' => $key,
                'meta_value' => $value_serialized,
                'created_at' => current_time('mysql')
            ]);
        }

        $this->meta[$key] = $value;

        return true;
    }

    /**
     * Get meta
     */
    public function get_meta($key, $default = '') {
        if (!$this->id) {
            return $default;
        }

        return isset($this->meta[$key]) ? $this->meta[$key] : $default;
    }

    /**
     * Get order ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get order number
     */
    public function get_order_number() {
        return $this->data['order_number'] ?? '';
    }

    /**
     * Get customer email
     */
    public function get_customer_email() {
        return $this->data['customer_email'] ?? '';
    }

    /**
     * Get customer name
     */
    public function get_customer_name() {
        return $this->data['customer_name'] ?? '';
    }

    /**
     * Get user ID
     */
    public function get_user_id() {
        return $this->data['user_id'] ?? 0;
    }

    /**
     * Get order status
     */
    public function get_status() {
        return $this->data['order_status'] ?? 'pending';
    }

    /**
     * Get payment method
     */
    public function get_payment_method() {
        return $this->data['payment_method'] ?? '';
    }

    /**
     * Get payment status
     */
    public function get_payment_status() {
        return $this->data['payment_status'] ?? 'pending';
    }

    /**
     * Get total
     */
    public function get_total() {
        return floatval($this->data['total'] ?? 0);
    }

    /**
     * Get subtotal
     */
    public function get_subtotal() {
        return floatval($this->data['subtotal'] ?? 0);
    }

    /**
     * Get currency
     */
    public function get_currency() {
        return $this->data['currency'] ?? 'USD';
    }

    /**
     * Get formatted total
     */
    public function get_formatted_total() {
        $total = $this->get_total();
        $currency = $this->get_currency();

        $symbol = '$';
        if ($currency === 'GBP') {
            $symbol = '£';
        } elseif ($currency === 'EUR') {
            $symbol = '€';
        }

        return $symbol . number_format($total, 2);
    }

    /**
     * Get items
     */
    public function get_items() {
        return $this->items;
    }

    /**
     * Get created date
     */
    public function get_date_created() {
        return $this->data['created_at'] ?? '';
    }

    /**
     * Get payment reference
     */
    public function get_payment_reference() {
        return $this->data['payment_reference'] ?? '';
    }

    /**
     * Get payment receipt URL
     */
    public function get_payment_receipt_url() {
        return $this->data['payment_receipt_url'] ?? '';
    }

    /**
     * Get all order data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Save order
     */
    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        if (!$this->id) {
            return false;
        }

        $wpdb->update($table, [
            'updated_at' => current_time('mysql')
        ], ['id' => $this->id]);

        return true;
    }

    /**
     * Delete order
     */
    public function delete() {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        // Delete items
        $wpdb->delete($wpdb->prefix . 'unico_order_items', ['order_id' => $this->id]);

        // Delete meta
        $wpdb->delete($wpdb->prefix . 'unico_order_meta', ['order_id' => $this->id]);

        // Delete order
        $wpdb->delete($wpdb->prefix . 'unico_orders', ['id' => $this->id]);

        return true;
    }

    /**
     * Get all orders (static method)
     */
    public static function get_orders($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_orders';

        $defaults = [
            'user_id' => null,
            'status' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $where_values = [];

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['status']) {
            $where[] = 'order_status = %s';
            $where_values[] = $args['status'];
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "SELECT * FROM $table $where_sql ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        $orders = [];
        foreach ($results as $order_data) {
            $order = new self();
            $order->id = $order_data['id'];
            $order->data = $order_data;
            $order->load_items();
            $order->load_meta();
            $orders[] = $order;
        }

        return $orders;
    }
}
