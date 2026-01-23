<?php
/**
 * Custom Checkout System
 * Handles checkout process and order creation without WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Checkout {

    private static $instance = null;
    private $errors = [];
    private $notices = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Handle checkout form submission
        add_action('init', [$this, 'process_checkout']);

        // AJAX handlers
        add_action('wp_ajax_unico_upload_payment_receipt', [$this, 'ajax_upload_receipt']);
        add_action('wp_ajax_nopriv_unico_upload_payment_receipt', [$this, 'ajax_upload_receipt']);
    }

    /**
     * Process checkout form submission
     */
    public function process_checkout() {
        if (!isset($_POST['unico_checkout']) || !isset($_POST['unico_checkout_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['unico_checkout_nonce'], 'unico_checkout_action')) {
            $this->add_error('Security check failed. Please try again.');
            return;
        }

        // Validation
        if (!$this->validate_checkout()) {
            return;
        }

        // Create order
        $order_id = $this->create_order();

        if (!$order_id) {
            $this->add_error('Failed to create order. Please try again.');
            return;
        }

        // Clear cart
        $cart = Unico_Cart::get_instance();
        $cart->empty_cart();

        // Clear purchase verification
        if (is_user_logged_in() && class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();
            $security->clear_purchase_verification(get_current_user_id());
        }

        // Redirect to thank you page
        wp_redirect(home_url('/order-received/?order_id=' . $order_id));
        exit;
    }

    /**
     * Validate checkout fields
     */
    private function validate_checkout() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            $this->add_error('You must be logged in to complete checkout.');
            return false;
        }

        $cart = Unico_Cart::get_instance();

        // Check if cart is empty
        if ($cart->is_empty()) {
            $this->add_error('Your cart is empty.');
            return false;
        }

        // Check if cart has voucher items
        if (!$cart->cart_has_voucher_items()) {
            $this->add_error('Invalid cart contents.');
            return false;
        }

        // Verify purchase OTP
        $user_id = get_current_user_id();
        if ($user_id && class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();

            if (!$security->is_purchase_verified($user_id)) {
                $this->add_error('Purchase verification required. Please verify using the code sent to your email.');
                return false;
            }
        }

        // Validate required fields
        if (empty($_POST['voucher_buyer_full_name'])) {
            $this->add_error('Buyer full name is required.');
            return false;
        }

        if (empty($_POST['voucher_buyer_email']) || !is_email($_POST['voucher_buyer_email'])) {
            $this->add_error('Valid email address is required.');
            return false;
        }

        // Validate payment method
        if (empty($_POST['voucher_payment_mode'])) {
            $this->add_error('Payment method is required.');
            return false;
        }

        // Validate bank transfer fields
        if ($_POST['voucher_payment_mode'] === 'bank_transfer') {
            if (empty($_POST['voucher_payment_reference'])) {
                $this->add_error('Transaction ID is required for bank transfer.');
                return false;
            }

            // Check for receipt - first in session (AJAX upload), then in form upload
            if (!session_id()) {
                session_start();
            }

            $receipt_in_session = isset($_SESSION['unico_receipt_upload']) ? $_SESSION['unico_receipt_upload'] : null;
            $has_valid_receipt = false;

            // Check session first (AJAX uploaded receipt)
            if ($receipt_in_session && !empty($receipt_in_session['url']) && !empty($receipt_in_session['file'])) {
                if (file_exists($receipt_in_session['file'])) {
                    $has_valid_receipt = true;
                }
            }

            // If no session receipt, check for form-uploaded file
            if (!$has_valid_receipt && !empty($_FILES['voucher_payment_receipt']['name'])) {
                if ($_FILES['voucher_payment_receipt']['error'] === UPLOAD_ERR_OK) {
                    // Process form upload
                    require_once ABSPATH . 'wp-admin/includes/file.php';

                    $upload = wp_handle_upload($_FILES['voucher_payment_receipt'], [
                        'test_form' => false,
                        'mimes' => [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp',
                            'pdf' => 'application/pdf',
                        ],
                    ]);

                    if (!isset($upload['error'])) {
                        $_SESSION['unico_receipt_upload'] = $upload;
                        $has_valid_receipt = true;
                    } else {
                        $this->add_error('Error uploading receipt: ' . $upload['error']);
                        return false;
                    }
                }
            }

            if (!$has_valid_receipt) {
                $this->add_error('Payment receipt is required. Please upload your bank transfer receipt.');
                return false;
            }
        }

        // Validate terms confirmation
        if (empty($_POST['voucher_terms_confirmed'])) {
            $this->add_error('Please confirm accuracy and non-refundable terms before placing your order.');
            return false;
        }

        // Check quantity limits
        $qty = $cart->get_voucher_cart_quantity();
        if ($_POST['voucher_payment_mode'] === 'bank_transfer' && $qty > 10) {
            $this->add_error('Bank Transfer is limited to 10 units. Reduce quantity before placing the order.');
            return false;
        }

        return true;
    }

    /**
     * Create order from cart
     */
    private function create_order() {
        $cart = Unico_Cart::get_instance();
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Get form data
        $buyer_name = sanitize_text_field($_POST['voucher_buyer_full_name']);
        $buyer_email = sanitize_email($_POST['voucher_buyer_email']);
        $payment_mode = sanitize_text_field($_POST['voucher_payment_mode']);
        $payment_reference = isset($_POST['voucher_payment_reference']) ? sanitize_text_field($_POST['voucher_payment_reference']) : '';
        $selected_bank_id = isset($_POST['selected_bank_id']) ? intval($_POST['selected_bank_id']) : null;

        // Get receipt from session
        if (!session_id()) {
            session_start();
        }
        $receipt_in_session = isset($_SESSION['unico_receipt_upload']) ? $_SESSION['unico_receipt_upload'] : null;

        // Get bank details
        $bank_details = null;
        if ($selected_bank_id && class_exists('Unico_Bank_Accounts')) {
            $bank_system = Unico_Bank_Accounts::get_instance();
            $bank = $bank_system->get_bank($selected_bank_id);
            if ($bank) {
                $bank_details = [
                    'bank_name' => $bank->bank_name,
                    'account_holder' => $bank->account_holder,
                    'account_number' => $bank->account_number,
                    'ifsc_code' => $bank->ifsc_code ?? '',
                    'swift_code' => $bank->swift_code ?? '',
                    'branch_name' => $bank->branch_name ?? ''
                ];
            }
        }

        // Create order
        $order = Unico_Order::create([
            'user_id' => $user_id,
            'customer_email' => $buyer_email,
            'customer_name' => $buyer_name,
            'customer_phone' => get_user_meta($user_id, 'billing_phone', true) ?: '',
            'order_status' => 'pending-verification',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'subtotal' => $cart->get_cart_subtotal(),
            'tax' => $cart->get_cart_tax(),
            'total' => $cart->get_cart_total(),
            'currency' => 'USD',
            'payment_reference' => $payment_reference,
            'payment_receipt_url' => $receipt_in_session ? $receipt_in_session['url'] : null,
            'payment_receipt_path' => $receipt_in_session ? $receipt_in_session['file'] : null,
            'selected_bank_id' => $selected_bank_id,
            'bank_details' => $bank_details,
            'verification_status' => 'pending'
        ]);

        if (!$order) {
            return false;
        }

        // Add cart items to order
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $order->add_item([
                'product_id' => $cart_item['product_id'],
                'product_name' => $cart_item['product_name'],
                'product_type' => 'voucher',
                'exam_name' => $cart_item['exam_name'],
                'quantity' => $cart_item['quantity'],
                'unit_price' => $cart_item['price'],
                'subtotal' => $cart_item['price'] * $cart_item['quantity'],
                'total' => $cart_item['price'] * $cart_item['quantity'],
                'tax' => 0.00
            ]);
        }

        // Add order note
        $order->add_note('Order created via custom checkout. Pending payment verification.');

        // Clear receipt from session
        if (isset($_SESSION['unico_receipt_upload'])) {
            unset($_SESSION['unico_receipt_upload']);
        }

        // Send admin notification email
        $this->send_admin_notification($order);

        // Trigger action for other systems to hook into
        do_action('unico_order_created', $order->get_id(), $order);

        return $order->get_id();
    }

    /**
     * AJAX: Upload payment receipt
     */
    public function ajax_upload_receipt() {
        if (empty($_FILES['voucher_payment_receipt'])) {
            wp_send_json_error(['message' => 'No file received']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['voucher_payment_receipt'], [
            'test_form' => false,
            'mimes' => [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
            ],
        ]);

        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        // Store in session
        if (!session_id()) {
            session_start();
        }
        $_SESSION['unico_receipt_upload'] = $upload;

        wp_send_json_success([
            'url' => $upload['url'],
            'file' => $upload['file']
        ]);
    }

    /**
     * Send admin notification email
     */
    private function send_admin_notification($order) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] New Order Awaiting Payment Verification (#%s)', $site_name, $order->get_order_number());

        $receipt_url = $order->get_payment_receipt_url();

        $message = sprintf(
            "A new order is awaiting payment verification.\n\n" .
            "Order: #%s\n" .
            "Customer: %s\n" .
            "Email: %s\n" .
            "Total: %s\n\n" .
            "Payment Reference: %s\n" .
            "Payment Receipt: %s\n\n" .
            "Please review and verify the payment receipt in the admin panel:\n%s",
            $order->get_order_number(),
            $order->get_customer_name(),
            $order->get_customer_email(),
            $order->get_formatted_total(),
            $order->get_payment_reference(),
            $receipt_url ? $receipt_url : 'Not uploaded',
            admin_url('admin.php?page=unico-orders&action=view&order_id=' . $order->get_id())
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Add error message
     */
    public function add_error($message) {
        $this->errors[] = $message;
    }

    /**
     * Add notice message
     */
    public function add_notice($message) {
        $this->notices[] = $message;
    }

    /**
     * Get errors
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get notices
     */
    public function get_notices() {
        return $this->notices;
    }

    /**
     * Display errors
     */
    public function display_errors() {
        if (!empty($this->errors)) {
            echo '<div class="unico-checkout-errors">';
            foreach ($this->errors as $error) {
                echo '<div class="unico-error">' . esc_html($error) . '</div>';
            }
            echo '</div>';
        }
    }

    /**
     * Display notices
     */
    public function display_notices() {
        if (!empty($this->notices)) {
            echo '<div class="unico-checkout-notices">';
            foreach ($this->notices as $notice) {
                echo '<div class="unico-notice">' . esc_html($notice) . '</div>';
            }
            echo '</div>';
        }
    }
}
