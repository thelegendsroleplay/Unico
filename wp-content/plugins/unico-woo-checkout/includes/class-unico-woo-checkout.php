<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Woo_Checkout {
    private static $instance = null;
    private $max_upload_bytes = 5242880;
    private $allowed_mime_types = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/pdf',
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('woocommerce_available_payment_gateways', [$this, 'limit_gateways']);
        add_filter('woocommerce_bacs_accounts', [$this, 'hide_default_bacs_accounts']);
        add_filter('woocommerce_checkout_form_tag', [$this, 'add_multipart_to_checkout_form']);
        add_action('woocommerce_before_checkout_form', [$this, 'ensure_selected_bank']);
        add_action('woocommerce_review_order_before_payment', [$this, 'render_bank_details']);
        add_action('woocommerce_after_order_notes', [$this, 'render_custom_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_custom_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_custom_fields'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'mark_order_under_review'], 10, 3);
        add_action('woocommerce_thankyou', [$this, 'render_thankyou_bank_details'], 10, 1);
        add_action('woocommerce_view_order', [$this, 'render_view_order_bank_details'], 10, 1);
        add_action('woocommerce_order_details_after_order_table', [$this, 'render_rejected_support_button']);
    }

    public function limit_gateways($gateways) {
        if (is_admin()) {
            return $gateways;
        }

        if (isset($gateways['bacs'])) {
            return ['bacs' => $gateways['bacs']];
        }

        return $gateways;
    }

    public function hide_default_bacs_accounts($accounts) {
        return [];
    }

    public function add_multipart_to_checkout_form($tag) {
        if (false === strpos($tag, 'enctype')) {
            $tag = str_replace('<form', '<form enctype="multipart/form-data"', $tag);
        }
        return $tag;
    }

    public function ensure_selected_bank() {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $selected_id = WC()->session->get('unico_selected_bank_id');
        if ($selected_id) {
            return;
        }

        $bank_accounts = Unico_Woo_Bank_Accounts::get_instance();
        $random_id = $bank_accounts->get_random_account_id();
        if ($random_id) {
            WC()->session->set('unico_selected_bank_id', $random_id);
        } else {
            wc_add_notice('No bank accounts are available for bank transfer. Please contact support.', 'error');
        }
    }

    public function render_bank_details() {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $selected_id = WC()->session->get('unico_selected_bank_id');
        if (!$selected_id) {
            return;
        }

        $bank_accounts = Unico_Woo_Bank_Accounts::get_instance();
        $snapshot = $bank_accounts->get_account_snapshot($selected_id);
        if (empty($snapshot)) {
            return;
        }

        wc_get_template(
            'checkout-bank-details.php',
            ['bank' => $snapshot],
            '',
            UNICO_WOO_CHECKOUT_PATH . 'templates/'
        );
    }

    public function render_custom_fields($checkout) {
        echo '<div class="unico-bank-transfer-fields">';
        echo '<h3>Bank Transfer Details</h3>';

        woocommerce_form_field(
            'unico_transaction_id',
            [
                'type' => 'text',
                'class' => ['form-row-wide'],
                'label' => 'Transaction ID',
                'required' => true,
            ],
            $checkout->get_value('unico_transaction_id')
        );

        echo '<p class="form-row form-row-wide">';
        echo '<label for="unico_payment_proof">Upload Payment Proof <abbr class="required" title="required">*</abbr></label>';
        echo '<input type="file" name="unico_payment_proof" id="unico_payment_proof" accept=".jpg,.jpeg,.png,.pdf" required />';
        echo '</p>';
        echo '</div>';
    }

    public function validate_custom_fields() {
        if (empty($_POST['unico_transaction_id'])) {
            wc_add_notice('Please enter your transaction ID.', 'error');
        }

        if (function_exists('WC') && WC()->session && !WC()->session->get('unico_selected_bank_id')) {
            wc_add_notice('No bank account is available at the moment. Please contact support.', 'error');
        }

        if (empty($_FILES['unico_payment_proof']) || empty($_FILES['unico_payment_proof']['name'])) {
            wc_add_notice('Please upload your payment proof.', 'error');
            return;
        }

        $file = $_FILES['unico_payment_proof'];

        if (!empty($file['error'])) {
            wc_add_notice('There was an issue uploading your payment proof. Please try again.', 'error');
            return;
        }

        if ($file['size'] > $this->max_upload_bytes) {
            wc_add_notice('Payment proof must be under 5MB.', 'error');
        }

        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $mime_type = isset($file_type['type']) ? $file_type['type'] : '';

        if (!in_array($mime_type, $this->allowed_mime_types, true)) {
            wc_add_notice('Payment proof must be a JPG, PNG, or PDF file.', 'error');
        }
    }

    public function save_custom_fields($order_id, $data) {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        if (isset($_POST['unico_transaction_id'])) {
            update_post_meta($order_id, '_unico_transaction_id', sanitize_text_field(wp_unslash($_POST['unico_transaction_id'])));
        }

        $selected_id = WC()->session->get('unico_selected_bank_id');
        if ($selected_id) {
            $bank_accounts = Unico_Woo_Bank_Accounts::get_instance();
            $snapshot = $bank_accounts->get_account_snapshot($selected_id);
            update_post_meta($order_id, '_unico_bank_account_id', $selected_id);
            update_post_meta($order_id, '_unico_bank_account_snapshot', $snapshot);
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        if (!empty($_FILES['unico_payment_proof']['name'])) {
            $attachment_id = media_handle_upload('unico_payment_proof', $order_id);
            if (!is_wp_error($attachment_id)) {
                update_post_meta($order_id, '_unico_payment_proof_id', $attachment_id);
            }
        }
    }

    public function mark_order_under_review($order_id, $posted_data, $order) {
        if (!$order) {
            return;
        }

        $order->update_status('under-review', 'Bank transfer submitted. Waiting for manual review.');

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('unico_selected_bank_id', null);
        }
    }

    public function render_thankyou_bank_details($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $snapshot = $order->get_meta('_unico_bank_account_snapshot');
        if (!$snapshot) {
            return;
        }

        wc_get_template(
            'checkout-bank-details.php',
            ['bank' => $snapshot, 'context' => 'thankyou'],
            '',
            UNICO_WOO_CHECKOUT_PATH . 'templates/'
        );
    }

    public function render_view_order_bank_details($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $snapshot = $order->get_meta('_unico_bank_account_snapshot');
        if (!$snapshot) {
            return;
        }

        wc_get_template(
            'checkout-bank-details.php',
            ['bank' => $snapshot, 'context' => 'view-order'],
            '',
            UNICO_WOO_CHECKOUT_PATH . 'templates/'
        );
    }

    public function render_rejected_support_button($order) {
        if (!$order instanceof WC_Order) {
            return;
        }

        if ('rejected' !== $order->get_status()) {
            return;
        }

        $support_url = home_url('/support');
        echo '<p class="unico-support-ticket">';
        echo '<a class="button" href="' . esc_url($support_url) . '">Open Support Ticket</a>';
        echo '</p>';
    }
}
