<?php
/**
 * Checkout Fields Handler
 * Additional checkout customizations and enhancements
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Checkout_Fields {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Enable file upload on checkout
        add_action('woocommerce_before_checkout_form', array($this, 'enable_checkout_file_upload'));

        // Add custom validation
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_checkout'), 10, 2);

        // Display payment details in admin order view
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_payment_details_in_admin'));

        // Display bank details in order emails
        add_action('woocommerce_email_after_order_table', array($this, 'display_bank_details_in_email'), 10, 4);
    }

    /**
     * Enable file upload support on checkout form
     */
    public function enable_checkout_file_upload() {
        echo '<script>
            jQuery(document).ready(function($) {
                $("form.checkout").attr("enctype", "multipart/form-data");
            });
        </script>';
    }

    /**
     * Additional checkout validation
     */
    public function validate_checkout($data, $errors) {
        // Additional validation can be added here if needed
        // The payment gateway already handles transaction_id and payment_proof validation
    }

    /**
     * Display payment details in admin order view
     */
    public function display_payment_details_in_admin($order) {
        if ($order->get_payment_method() !== 'unico_bank_transfer') {
            return;
        }

        $transaction_id = $order->get_meta('_transaction_id');
        $proof_url = $order->get_meta('_payment_proof_url');
        $bank_details = $order->get_meta('_bank_details');
        $verified = $order->get_meta('_payment_verified');

        if (!$transaction_id) {
            return;
        }

        echo '<div class="unico-payment-details" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;">';
        echo '<h3>' . __('Payment Details', 'unico-wc') . '</h3>';

        echo '<p><strong>' . __('Transaction ID:', 'unico-wc') . '</strong> ' . esc_html($transaction_id) . '</p>';

        if ($proof_url) {
            echo '<p><strong>' . __('Payment Proof:', 'unico-wc') . '</strong><br>';
            echo '<a href="' . esc_url($proof_url) . '" target="_blank">';
            echo '<img src="' . esc_url($proof_url) . '" style="max-width: 300px; height: auto; border: 1px solid #ddd; margin-top: 5px;">';
            echo '</a></p>';
        }

        if ($bank_details) {
            $bank = json_decode($bank_details, true);
            if ($bank) {
                echo '<p><strong>' . __('Bank Account Used:', 'unico-wc') . '</strong><br>';
                echo esc_html($bank['bank_name']) . ' - ' . esc_html($bank['account_number']);
                echo '</p>';
            }
        }

        $verified_status = ($verified === 'yes') ? __('Verified', 'unico-wc') : __('Not Verified', 'unico-wc');
        $verified_class = ($verified === 'yes') ? 'approved' : 'pending';

        echo '<p><strong>' . __('Verification Status:', 'unico-wc') . '</strong> ';
        echo '<span class="status-badge ' . esc_attr($verified_class) . '">' . esc_html($verified_status) . '</span>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Display bank details in order emails
     */
    public function display_bank_details_in_email($order, $sent_to_admin, $plain_text, $email) {
        if ($order->get_payment_method() !== 'unico_bank_transfer') {
            return;
        }

        $transaction_id = $order->get_meta('_transaction_id');
        $bank_details = $order->get_meta('_bank_details');

        if (!$transaction_id) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('PAYMENT DETAILS', 'unico-wc') . "\n";
            echo str_repeat('=', 50) . "\n";
            echo __('Transaction ID:', 'unico-wc') . ' ' . $transaction_id . "\n";

            if ($bank_details) {
                $bank = json_decode($bank_details, true);
                if ($bank) {
                    echo __('Bank:', 'unico-wc') . ' ' . $bank['bank_name'] . "\n";
                    echo __('Account:', 'unico-wc') . ' ' . $bank['account_number'] . "\n";
                }
            }
            echo "\n";
        } else {
            echo '<h2>' . __('Payment Details', 'unico-wc') . '</h2>';
            echo '<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;">';
            echo '<tr><th style="text-align:left; border: 1px solid #eee;">' . __('Transaction ID:', 'unico-wc') . '</th>';
            echo '<td style="text-align:left; border: 1px solid #eee;">' . esc_html($transaction_id) . '</td></tr>';

            if ($bank_details) {
                $bank = json_decode($bank_details, true);
                if ($bank) {
                    echo '<tr><th style="text-align:left; border: 1px solid #eee;">' . __('Bank:', 'unico-wc') . '</th>';
                    echo '<td style="text-align:left; border: 1px solid #eee;">' . esc_html($bank['bank_name']) . '</td></tr>';
                    echo '<tr><th style="text-align:left; border: 1px solid #eee;">' . __('Account:', 'unico-wc') . '</th>';
                    echo '<td style="text-align:left; border: 1px solid #eee;">' . esc_html($bank['account_number']) . '</td></tr>';
                }
            }

            echo '</table>';
        }
    }
}
