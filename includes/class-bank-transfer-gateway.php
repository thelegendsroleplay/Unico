<?php
/**
 * Custom Bank Transfer Payment Gateway
 *
 * Handles bank transfer payments with receipt upload and manual verification
 *
 * @package Unico
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unico Bank Transfer Payment Gateway Class
 */
class Unico_Bank_Transfer_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'unico_bank_transfer';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'Bank Transfer (Unico)';
        $this->method_description = 'Accept bank transfer payments with receipt upload and manual verification.';

        // Enable by default for voucher orders
        $this->enabled = 'yes';

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Override enabled status from settings if set
        if ($this->get_option('enabled')) {
            $this->enabled = $this->get_option('enabled');
        }

        // Get settings
        $this->title = $this->get_option('title', 'Bank Transfer');
        $this->description = $this->get_option('description', 'Upload your payment receipt after transferring to our bank account.');
        $this->instructions = $this->get_option('instructions', 'Your order is pending payment verification.');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Bank Transfer Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'Payment method title shown to customers during checkout.',
                'default' => 'Bank Transfer',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Payment method description shown to customers during checkout.',
                'default' => 'Upload your payment receipt after transferring to our bank account.',
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => 'Instructions',
                'type' => 'textarea',
                'description' => 'Instructions shown on the thank you page and in emails.',
                'default' => 'Your order is pending payment verification. We will process your order once the payment receipt is verified.',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        // Always available if enabled
        if ($this->enabled !== 'yes') {
            return false;
        }

        // Always available for voucher orders
        if (function_exists('unico_cart_has_voucher_items') && unico_cart_has_voucher_items()) {
            return true;
        }

        return parent::is_available();
    }

    /**
     * Process Payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        global $woocommerce;

        $order = wc_get_order($order_id);

        error_log("Unico Bank Transfer: Processing payment for order #{$order_id}");

        // Handle file upload
        $upload_result = $this->handle_receipt_upload($order_id);

        if (is_wp_error($upload_result)) {
            wc_add_notice($upload_result->get_error_message(), 'error');
            error_log("Unico Bank Transfer: Upload failed - " . $upload_result->get_error_message());
            return array(
                'result' => 'failure',
                'messages' => $upload_result->get_error_message()
            );
        }

        // Mark order as pending verification
        $order->update_status('pending-verification', __('Awaiting payment receipt verification.', 'unico'));

        // Store payment mode metadata
        $order->update_meta_data('_voucher_payment_mode', 'bank_transfer');
        $order->update_meta_data('_voucher_verification_status', 'pending');
        $order->update_meta_data('_payment_receipt_uploaded', 'yes');
        $order->update_meta_data('_payment_receipt_url', $upload_result['url']);
        $order->update_meta_data('_payment_receipt_path', $upload_result['file']);
        $order->save();

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        $woocommerce->cart->empty_cart();

        error_log("Unico Bank Transfer: Order #{$order_id} created successfully - Status: pending-verification");

        // Return success and redirect to thank you page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    /**
     * Handle Receipt File Upload
     *
     * @param int $order_id
     * @return array|WP_Error
     */
    private function handle_receipt_upload($order_id) {
        error_log("Unico Bank Transfer: Handling file upload for order #{$order_id}");
        error_log("Unico Bank Transfer: FILES array: " . print_r($_FILES, true));

        // Check if file exists
        if (!isset($_FILES['voucher_payment_receipt']) || empty($_FILES['voucher_payment_receipt']['name'])) {
            return new WP_Error('no_file', 'Payment receipt is required. Please upload your bank transfer receipt.');
        }

        $file = $_FILES['voucher_payment_receipt'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum form size.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
            );
            $error_message = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Unknown upload error.';
            return new WP_Error('upload_error', $error_message);
        }

        // Validate file type using wp_check_filetype
        $file_info = wp_check_filetype($file['name']);
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        error_log("Unico Bank Transfer: File extension: " . $file_info['ext']);
        error_log("Unico Bank Transfer: File MIME type: " . $file['type']);

        if (!$file_info['ext'] || !in_array(strtolower($file_info['ext']), $allowed_extensions)) {
            return new WP_Error('invalid_type', 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.');
        }

        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds 5MB limit.');
        }

        // Upload file using WordPress functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Override upload handling to bypass form checks
        $upload_overrides = array(
            'test_form' => false,
            'test_type' => false,  // Skip MIME type test since we validated it
            'test_size' => true,
        );

        error_log("Unico Bank Transfer: Calling wp_handle_upload with overrides: " . print_r($upload_overrides, true));
        error_log("Unico Bank Transfer: File tmp_name: " . $file['tmp_name']);
        error_log("Unico Bank Transfer: File exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));

        // Attempt upload
        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        error_log("Unico Bank Transfer: Upload result: " . print_r($uploaded_file, true));

        if (isset($uploaded_file['error'])) {
            error_log("Unico Bank Transfer: Upload error - " . $uploaded_file['error']);

            // If standard upload fails, try manual upload
            if (file_exists($file['tmp_name'])) {
                error_log("Unico Bank Transfer: Attempting manual file upload...");
                return $this->manual_file_upload($file, $order_id);
            }

            return new WP_Error('upload_failed', 'File upload failed: ' . $uploaded_file['error']);
        }

        if (!isset($uploaded_file['url']) || !isset($uploaded_file['file'])) {
            error_log("Unico Bank Transfer: Missing URL or file path in upload result");
            return new WP_Error('upload_failed', 'File upload completed but missing file information.');
        }

        error_log("Unico Bank Transfer: File uploaded successfully - " . $uploaded_file['url']);

        return $uploaded_file;
    }

    /**
     * Manual File Upload (Fallback Method)
     *
     * @param array $file The $_FILES array element
     * @param int $order_id The order ID
     * @return array|WP_Error
     */
    private function manual_file_upload($file, $order_id) {
        error_log("Unico Bank Transfer: Starting manual file upload for order #{$order_id}");

        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();

        // Create custom subdirectory for payment receipts
        $custom_dir = $upload_dir['basedir'] . '/payment-receipts';
        $custom_url = $upload_dir['baseurl'] . '/payment-receipts';

        // Create directory if it doesn't exist
        if (!file_exists($custom_dir)) {
            wp_mkdir_p($custom_dir);
            error_log("Unico Bank Transfer: Created directory: " . $custom_dir);
        }

        // Generate unique filename
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        $filename = 'receipt_order_' . $order_id . '_' . time() . '.' . $extension;
        $target_path = $custom_dir . '/' . $filename;
        $target_url = $custom_url . '/' . $filename;

        error_log("Unico Bank Transfer: Target path: " . $target_path);

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            error_log("Unico Bank Transfer: Manual upload successful - " . $target_url);

            // Set proper permissions
            chmod($target_path, 0644);

            return array(
                'file' => $target_path,
                'url' => $target_url,
                'type' => $file['type']
            );
        } else {
            error_log("Unico Bank Transfer: Manual upload failed - move_uploaded_file returned false");
            error_log("Unico Bank Transfer: Source exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
            error_log("Unico Bank Transfer: Target dir writable: " . (is_writable($custom_dir) ? 'YES' : 'NO'));
            return new WP_Error('manual_upload_failed', 'Failed to move uploaded file to destination.');
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }

        $order = wc_get_order($order_id);
        $receipt_url = $order->get_meta('_payment_receipt_url');

        if ($receipt_url) {
            echo '<div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 4px;">';
            echo '<p style="margin: 0 0 10px;"><strong>✅ Payment Receipt Uploaded</strong></p>';
            echo '<p style="margin: 0; font-size: 14px; color: #666;">Your payment receipt has been uploaded successfully and is awaiting verification by our team.</p>';
            echo '</div>';
        }
    }

    /**
     * Add content to the WC emails.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('pending-verification')) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
}
