<?php
/**
 * Unico Custom Shop System
 *
 * Custom voucher ordering system (replaces WooCommerce)
 *
 * @package Unico
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Shop System Class
 */
class Unico_Custom_Shop {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Database setup
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('after_switch_theme', array($this, 'create_tables'));
        add_action('init', array($this, 'check_and_create_tables'), 1);

        // AJAX handlers
        add_action('wp_ajax_unico_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_unico_add_to_cart', array($this, 'ajax_add_to_cart'));

        add_action('wp_ajax_unico_upload_receipt', array($this, 'ajax_upload_receipt'));
        add_action('wp_ajax_nopriv_unico_upload_receipt', array($this, 'ajax_upload_receipt'));

        add_action('wp_ajax_unico_place_order', array($this, 'ajax_place_order'));
        add_action('wp_ajax_nopriv_unico_place_order', array($this, 'ajax_place_order'));
    }

    /**
     * Check if tables exist and create if needed
     */
    public function check_and_create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'unico_vouchers';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            $this->create_tables();
        }
    }

    /**
     * Create custom database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Voucher Products Table
        $sql_products = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}unico_vouchers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug varchar(200) NOT NULL,
            name varchar(255) NOT NULL,
            exam_family varchar(100) NOT NULL,
            price decimal(10,2) DEFAULT NULL,
            currency varchar(10) DEFAULT 'USD',
            price_nature varchar(50) DEFAULT 'Country Wise',
            stock_status varchar(20) DEFAULT 'instock',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY exam_family (exam_family),
            KEY stock_status (stock_status)
        ) $charset_collate;";

        // Orders Table
        $sql_orders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}unico_orders (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            voucher_id bigint(20) UNSIGNED NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(10,2) NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            payment_mode varchar(50) NOT NULL,
            payment_reference varchar(255) DEFAULT NULL,
            bank_id bigint(20) DEFAULT NULL,
            buyer_name varchar(255) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            verification_status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY user_id (user_id),
            KEY voucher_id (voucher_id),
            KEY status (status),
            KEY verification_status (verification_status)
        ) $charset_collate;";

        // Order Meta Table
        $sql_order_meta = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}unico_order_meta (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY order_id (order_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        // Receipts Table
        $sql_receipts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}unico_receipts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_size bigint(20) DEFAULT NULL,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            status varchar(50) DEFAULT 'pending',
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_products);
        dbDelta($sql_orders);
        dbDelta($sql_order_meta);
        dbDelta($sql_receipts);

        // Sync voucher products from catalog
        $this->sync_voucher_catalog();

        error_log('Unico Custom Shop: Database tables created/updated');
    }

    /**
     * Sync voucher catalog to database
     */
    private function sync_voucher_catalog() {
        global $wpdb;

        $catalog = unico_get_voucher_catalog_definitions();

        foreach ($catalog as $item) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}unico_vouchers WHERE slug = %s",
                $item['slug']
            ));

            if ($existing) {
                // Update existing
                $wpdb->update(
                    $wpdb->prefix . 'unico_vouchers',
                    array(
                        'name' => $item['name'],
                        'exam_family' => $item['exam_family'],
                        'price' => $item['price'],
                        'currency' => $item['currency'],
                        'price_nature' => $item['price_nature'],
                        'stock_status' => $item['stock_status']
                    ),
                    array('slug' => $item['slug']),
                    array('%s', '%s', '%f', '%s', '%s', '%s'),
                    array('%s')
                );
            } else {
                // Insert new
                $wpdb->insert(
                    $wpdb->prefix . 'unico_vouchers',
                    array(
                        'slug' => $item['slug'],
                        'name' => $item['name'],
                        'exam_family' => $item['exam_family'],
                        'price' => $item['price'],
                        'currency' => $item['currency'],
                        'price_nature' => $item['price_nature'],
                        'stock_status' => $item['stock_status']
                    ),
                    array('%s', '%s', '%s', '%f', '%s', '%s', '%s')
                );
            }
        }
    }

    /**
     * AJAX: Add to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('unico-checkout', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in to purchase vouchers');
        }

        $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if ($voucher_id < 1 || $quantity < 1) {
            wp_send_json_error('Invalid voucher or quantity');
        }

        // Get voucher details
        global $wpdb;
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_vouchers WHERE id = %d AND stock_status = 'instock'",
            $voucher_id
        ));

        if (!$voucher) {
            wp_send_json_error('Voucher not available');
        }

        // Store in session (simple cart)
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION['unico_cart'] = array(
            'voucher_id' => $voucher->id,
            'voucher_slug' => $voucher->slug,
            'voucher_name' => $voucher->name,
            'quantity' => $quantity,
            'unit_price' => $voucher->price,
            'currency' => $voucher->currency,
            'total' => $voucher->price * $quantity
        );

        wp_send_json_success(array(
            'message' => 'Added to cart',
            'cart' => $_SESSION['unico_cart']
        ));
    }

    /**
     * AJAX: Upload receipt
     */
    public function ajax_upload_receipt() {
        error_log('Unico Custom Shop: Receipt upload started');

        if (empty($_FILES['voucher_payment_receipt'])) {
            wp_send_json_error('No file received');
        }

        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['voucher_payment_receipt'], array(
            'test_form' => false,
            'mimes' => array(
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            )
        ));

        if (isset($upload['error'])) {
            error_log('Unico Custom Shop: Upload error - ' . $upload['error']);
            wp_send_json_error($upload['error']);
        }

        // Store in session
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION['unico_receipt'] = array(
            'file' => $upload['file'],
            'url' => $upload['url'],
            'name' => basename($upload['file']),
            'size' => filesize($upload['file']),
            'type' => $upload['type']
        );

        error_log('Unico Custom Shop: Receipt uploaded successfully to ' . $upload['file']);

        wp_send_json_success(array(
            'url' => $upload['url'],
            'file' => basename($upload['file']),
            'message' => 'Receipt uploaded successfully'
        ));
    }

    /**
     * AJAX: Place order
     */
    public function ajax_place_order() {
        check_ajax_referer('unico-checkout', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
        }

        $user_id = get_current_user_id();

        // Get form data
        $data = array(
            'buyer_name' => sanitize_text_field($_POST['buyer_name'] ?? ''),
            'buyer_email' => sanitize_email($_POST['buyer_email'] ?? ''),
            'payment_mode' => sanitize_text_field($_POST['payment_mode'] ?? ''),
            'payment_reference' => sanitize_text_field($_POST['payment_reference'] ?? ''),
            'bank_id' => intval($_POST['bank_id'] ?? 0),
            'terms_confirmed' => isset($_POST['terms_confirmed'])
        );

        // Validate
        if (empty($data['buyer_name']) || empty($data['buyer_email'])) {
            wp_send_json_error('Please provide buyer name and email');
        }

        if (!$data['terms_confirmed']) {
            wp_send_json_error('Please confirm terms and conditions');
        }

        // Get cart from session
        if (!isset($_SESSION)) {
            session_start();
        }

        if (empty($_SESSION['unico_cart'])) {
            wp_send_json_error('Cart is empty');
        }

        $cart = $_SESSION['unico_cart'];

        // Validate payment mode
        if ($data['payment_mode'] === 'bank_transfer') {
            if (empty($data['payment_reference'])) {
                wp_send_json_error('Transaction ID is required for bank transfer');
            }

            if (empty($_SESSION['unico_receipt'])) {
                wp_send_json_error('Payment receipt is required for bank transfer');
            }
        }

        // Create order
        global $wpdb;

        $order_number = 'UNO-' . strtoupper(substr(md5(uniqid()), 0, 10));

        $order_data = array(
            'order_number' => $order_number,
            'user_id' => $user_id,
            'voucher_id' => $cart['voucher_id'],
            'quantity' => $cart['quantity'],
            'unit_price' => $cart['unit_price'],
            'total_amount' => $cart['total'],
            'currency' => $cart['currency'],
            'payment_mode' => $data['payment_mode'],
            'payment_reference' => $data['payment_reference'],
            'bank_id' => $data['bank_id'],
            'buyer_name' => $data['buyer_name'],
            'buyer_email' => $data['buyer_email'],
            'status' => 'pending-verification',
            'verification_status' => 'pending'
        );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'unico_orders',
            $order_data,
            array('%s', '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            error_log('Unico Custom Shop: Order insert failed - ' . $wpdb->last_error);
            wp_send_json_error('Failed to create order. Please try again.');
        }

        $order_id = $wpdb->insert_id;

        // Save receipt if available
        if (!empty($_SESSION['unico_receipt'])) {
            $receipt = $_SESSION['unico_receipt'];

            $wpdb->insert(
                $wpdb->prefix . 'unico_receipts',
                array(
                    'order_id' => $order_id,
                    'file_path' => $receipt['file'],
                    'file_url' => $receipt['url'],
                    'file_name' => $receipt['name'],
                    'file_size' => $receipt['size'],
                    'uploaded_by' => $user_id,
                    'status' => 'pending'
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
            );
        }

        // Save bank details if available
        if ($data['bank_id'] > 0 && class_exists('Unico_Bank_Accounts')) {
            $bank_system = Unico_Bank_Accounts::get_instance();
            $bank = $bank_system->get_bank($data['bank_id']);

            if ($bank) {
                $this->update_order_meta($order_id, '_bank_name', $bank->bank_name);
                $this->update_order_meta($order_id, '_bank_account_holder', $bank->account_holder);
                $this->update_order_meta($order_id, '_bank_account_number', $bank->account_number);
            }
        }

        // Clear cart and receipt from session
        unset($_SESSION['unico_cart']);
        unset($_SESSION['unico_receipt']);

        // Send confirmation email
        $this->send_order_confirmation_email($order_id);

        // Send admin notification
        $this->send_admin_notification_email($order_id);

        error_log('Unico Custom Shop: Order created successfully - #' . $order_number);

        wp_send_json_success(array(
            'order_id' => $order_id,
            'order_number' => $order_number,
            'message' => 'Order placed successfully!',
            'redirect' => home_url('/order-confirmation?order=' . $order_number)
        ));
    }

    /**
     * Update order meta
     */
    public function update_order_meta($order_id, $meta_key, $meta_value) {
        global $wpdb;

        // Check if meta exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->prefix}unico_order_meta WHERE order_id = %d AND meta_key = %s",
            $order_id,
            $meta_key
        ));

        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'unico_order_meta',
                array('meta_value' => $meta_value),
                array('order_id' => $order_id, 'meta_key' => $meta_key),
                array('%s'),
                array('%d', '%s')
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'unico_order_meta',
                array(
                    'order_id' => $order_id,
                    'meta_key' => $meta_key,
                    'meta_value' => $meta_value
                ),
                array('%d', '%s', '%s')
            );
        }
    }

    /**
     * Get order meta
     */
    public function get_order_meta($order_id, $meta_key) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}unico_order_meta WHERE order_id = %d AND meta_key = %s",
            $order_id,
            $meta_key
        ));
    }

    /**
     * Send order confirmation email
     */
    private function send_order_confirmation_email($order_id) {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_orders WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            return false;
        }

        $to = $order->buyer_email;
        $subject = 'Order Confirmation - ' . $order->order_number;
        $message = sprintf(
            "Thank you for your order!\n\nOrder Number: %s\nTotal Amount: %s %s\nStatus: Pending Verification\n\nWe will verify your payment receipt and process your order shortly.\n\nThank you!",
            $order->order_number,
            $order->currency,
            number_format($order->total_amount, 2)
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Send admin notification email
     */
    private function send_admin_notification_email($order_id) {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_orders WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            return false;
        }

        $admin_email = get_option('admin_email');
        $subject = 'New Order Awaiting Verification - ' . $order->order_number;
        $message = sprintf(
            "New order received:\n\nOrder Number: %s\nBuyer: %s (%s)\nAmount: %s %s\nQuantity: %d\n\nPlease verify the payment receipt in the admin panel.",
            $order->order_number,
            $order->buyer_name,
            $order->buyer_email,
            $order->currency,
            number_format($order->total_amount, 2),
            $order->quantity
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Get all vouchers
     */
    public static function get_vouchers($args = array()) {
        global $wpdb;

        $defaults = array(
            'stock_status' => 'instock',
            'orderby' => 'name',
            'order' => 'ASC'
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$wpdb->prefix}unico_vouchers WHERE 1=1";

        if ($args['stock_status']) {
            $sql .= $wpdb->prepare(" AND stock_status = %s", $args['stock_status']);
        }

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        return $wpdb->get_results($sql);
    }

    /**
     * Get voucher by ID
     */
    public static function get_voucher($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_vouchers WHERE id = %d",
            $id
        ));
    }

    /**
     * Get voucher by slug
     */
    public static function get_voucher_by_slug($slug) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_vouchers WHERE slug = %s",
            $slug
        ));
    }

    /**
     * Get order by ID
     */
    public static function get_order($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_orders WHERE id = %d",
            $id
        ));
    }

    /**
     * Get order by order number
     */
    public static function get_order_by_number($order_number) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}unico_orders WHERE order_number = %s",
            $order_number
        ));
    }

    /**
     * Get cart from session
     */
    public static function get_cart() {
        if (!isset($_SESSION)) {
            session_start();
        }

        return $_SESSION['unico_cart'] ?? null;
    }
}

// Initialize
Unico_Custom_Shop::get_instance();
