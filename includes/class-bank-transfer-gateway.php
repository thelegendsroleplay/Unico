<?php
/**
 * Custom Bank Transfer Payment Gateway
 *
 * @package Unico
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Bank_Transfer_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'unico_bank_transfer';
        $this->has_fields = false;
        $this->method_title = 'Bank Transfer (Unico)';
        $this->method_description = 'Accept bank transfer payments with receipt upload and manual verification.';
        $this->enabled = 'yes';

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title', 'Bank Transfer');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);

        // AJAX receipt upload
        add_action('wp_ajax_unico_upload_receipt', [$this, 'ajax_upload_receipt']);
        add_action('wp_ajax_nopriv_unico_upload_receipt', [$this, 'ajax_upload_receipt']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Bank Transfer Payment',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Bank Transfer',
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Upload your payment receipt after transferring to our bank account.',
            ],
            'instructions' => [
                'title' => 'Instructions',
                'type' => 'textarea',
                'default' => 'Your order is pending payment verification.',
            ],
        ];
    }

    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        if (function_exists('unico_cart_has_voucher_items') && unico_cart_has_voucher_items()) {
            return true;
        }
        return parent::is_available();
    }

    /**
     * AJAX: upload receipt BEFORE checkout
     */
    public function ajax_upload_receipt() {
        error_log('Unico AJAX: Upload receipt called');

        if (empty($_FILES['voucher_payment_receipt'])) {
            error_log('Unico AJAX: No file received');
            wp_send_json_error('No file received');
        }

        // Ensure WooCommerce is initialized
        if (!function_exists('WC') || !WC()) {
            error_log('Unico AJAX: WooCommerce not initialized');
            wp_send_json_error('WooCommerce not available');
        }

        // Initialize WooCommerce session if not already initialized
        if (!WC()->session || !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
            error_log('Unico AJAX: WC session initialized');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['voucher_payment_receipt'], [
            'test_form' => false,
        ]);

        if (isset($upload['error'])) {
            error_log('Unico AJAX: Upload error - ' . $upload['error']);
            wp_send_json_error($upload['error']);
        }

        error_log('Unico AJAX: File uploaded successfully to ' . $upload['file']);

        // Store in WooCommerce session
        WC()->session->set('unico_receipt_upload', $upload);

        // Verify it was saved
        $verify = WC()->session->get('unico_receipt_upload');
        error_log('Unico AJAX: Session data saved and verified: ' . print_r($verify, true));

        wp_send_json_success([
            'url' => $upload['url'],
            'file' => basename($upload['file']),
            'session_verified' => !empty($verify)
        ]);
    }

    /**
     * Process payment (NO FILE UPLOAD HERE)
     */
    public function process_payment($order_id) {
        global $woocommerce;

        $order = wc_get_order($order_id);

        // Get uploaded receipt from session
        $upload = WC()->session->get('unico_receipt_upload');

        if (!$upload || empty($upload['url']) || empty($upload['file'])) {
            wc_add_notice('Payment receipt is required.', 'error');
            return ['result' => 'failure'];
        }

        // Order status
        $order->update_status('pending-verification', 'Awaiting payment receipt verification');

        // Save receipt meta
        $order->update_meta_data('_voucher_payment_mode', 'bank_transfer');
        $order->update_meta_data('_voucher_verification_status', 'pending');
        $order->update_meta_data('_payment_receipt_uploaded', 'yes');
        $order->update_meta_data('_payment_receipt_url', $upload['url']);
        $order->update_meta_data('_payment_receipt_path', $upload['file']);
        $order->save();

        // Clear session
        WC()->session->__unset('unico_receipt_upload');

        wc_reduce_stock_levels($order_id);
        $woocommerce->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop($this->instructions));
        }
    }

    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if (
            !$sent_to_admin &&
            $this->id === $order->get_payment_method() &&
            $order->has_status('pending-verification')
        ) {
            echo wp_kses_post(wpautop($this->instructions));
        }
    }
}
