<?php
/**
 * Admin Order Management
 * Approve/Reject payment actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Admin_Orders {

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
        // Add order actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_approve_payment', array($this, 'approve_payment'));
        add_action('woocommerce_order_action_reject_payment', array($this, 'reject_payment'));

        // Add bulk actions
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);

        // Add admin list columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_columns'), 20, 2);

        // Add meta box for quick approve/reject
        add_action('add_meta_boxes', array($this, 'add_payment_verification_metabox'));

        // AJAX handlers
        add_action('wp_ajax_unico_quick_approve', array($this, 'ajax_quick_approve'));
        add_action('wp_ajax_unico_quick_reject', array($this, 'ajax_quick_reject'));

        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }

    /**
     * Add order actions dropdown
     */
    public function add_order_actions($actions) {
        global $theorder;

        if ($theorder && $theorder->get_payment_method() === 'unico_bank_transfer') {
            $verified = $theorder->get_meta('_payment_verified');

            if ($verified !== 'yes') {
                $actions['approve_payment'] = __('Approve Payment', 'unico-wc');
                $actions['reject_payment'] = __('Reject Payment', 'unico-wc');
            }
        }

        return $actions;
    }

    /**
     * Approve payment action
     */
    public function approve_payment($order) {
        if ($order->get_payment_method() !== 'unico_bank_transfer') {
            return;
        }

        // Mark as verified
        $order->update_meta_data('_payment_verified', 'yes');
        $order->add_order_note(__('Payment approved by admin.', 'unico-wc'), false, true);

        // Update order status to processing
        $order->update_status('processing', __('Payment verified and approved.', 'unico-wc'));

        // Generate and deliver vouchers
        $voucher_generator = Unico_Voucher_Generator::instance();
        $voucher_generator->generate_and_deliver_vouchers($order->get_id());

        $order->save();

        // Trigger action hook
        do_action('unico_payment_approved', $order->get_id(), $order);
    }

    /**
     * Reject payment action
     */
    public function reject_payment($order) {
        if ($order->get_payment_method() !== 'unico_bank_transfer') {
            return;
        }

        // Mark as not verified
        $order->update_meta_data('_payment_verified', 'no');
        $order->update_meta_data('_payment_rejected', 'yes');
        $order->add_order_note(__('Payment rejected by admin.', 'unico-wc'), false, true);

        // Update order status to rejected
        $order->update_status('rejected', __('Payment verification failed.', 'unico-wc'));

        $order->save();

        // Trigger action hook
        do_action('unico_payment_rejected', $order->get_id(), $order);
    }

    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['approve_payments'] = __('Approve Payments', 'unico-wc');
        $actions['reject_payments'] = __('Reject Payments', 'unico-wc');
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'approve_payments' && $action !== 'reject_payments') {
            return $redirect_to;
        }

        $processed = 0;

        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);

            if (!$order || $order->get_payment_method() !== 'unico_bank_transfer') {
                continue;
            }

            if ($action === 'approve_payments') {
                $this->approve_payment($order);
            } else {
                $this->reject_payment($order);
            }

            $processed++;
        }

        $redirect_to = add_query_arg(array(
            'bulk_action' => $action,
            'processed' => $processed,
        ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Add custom columns to orders list
     */
    public function add_order_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'order_status') {
                $new_columns['payment_verification'] = __('Payment Verified', 'unico-wc');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom columns
     */
    public function render_order_columns($column, $post_id) {
        if ($column === 'payment_verification') {
            $order = wc_get_order($post_id);

            if ($order && $order->get_payment_method() === 'unico_bank_transfer') {
                $verified = $order->get_meta('_payment_verified');
                $rejected = $order->get_meta('_payment_rejected');

                if ($verified === 'yes') {
                    echo '<span class="unico-verified" style="color: green;">✓ ' . __('Verified', 'unico-wc') . '</span>';
                } elseif ($rejected === 'yes') {
                    echo '<span class="unico-rejected" style="color: red;">✗ ' . __('Rejected', 'unico-wc') . '</span>';
                } else {
                    echo '<span class="unico-pending" style="color: orange;">⏳ ' . __('Pending', 'unico-wc') . '</span>';
                }
            } else {
                echo '—';
            }
        }
    }

    /**
     * Add payment verification meta box
     */
    public function add_payment_verification_metabox() {
        add_meta_box(
            'unico_payment_verification',
            __('Payment Verification', 'unico-wc'),
            array($this, 'render_payment_verification_metabox'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Render payment verification meta box
     */
    public function render_payment_verification_metabox($post) {
        $order = wc_get_order($post->ID);

        if (!$order || $order->get_payment_method() !== 'unico_bank_transfer') {
            echo '<p>' . __('This order does not use bank transfer payment.', 'unico-wc') . '</p>';
            return;
        }

        $verified = $order->get_meta('_payment_verified');
        $rejected = $order->get_meta('_payment_rejected');
        $transaction_id = $order->get_meta('_transaction_id');
        $proof_url = $order->get_meta('_payment_proof_url');

        echo '<div class="unico-verification-box">';

        if ($transaction_id) {
            echo '<p><strong>' . __('Transaction ID:', 'unico-wc') . '</strong><br>' . esc_html($transaction_id) . '</p>';
        }

        if ($proof_url) {
            echo '<p><strong>' . __('Payment Proof:', 'unico-wc') . '</strong><br>';
            echo '<a href="' . esc_url($proof_url) . '" target="_blank">';
            echo '<img src="' . esc_url($proof_url) . '" style="max-width: 100%; height: auto; border: 1px solid #ddd;">';
            echo '</a></p>';
        }

        echo '<p><strong>' . __('Status:', 'unico-wc') . '</strong> ';
        if ($verified === 'yes') {
            echo '<span style="color: green;">' . __('Verified', 'unico-wc') . '</span>';
        } elseif ($rejected === 'yes') {
            echo '<span style="color: red;">' . __('Rejected', 'unico-wc') . '</span>';
        } else {
            echo '<span style="color: orange;">' . __('Pending Verification', 'unico-wc') . '</span>';
        }
        echo '</p>';

        if ($verified !== 'yes' && $rejected !== 'yes') {
            echo '<p>';
            echo '<button type="button" class="button button-primary unico-quick-approve" data-order-id="' . esc_attr($order->get_id()) . '">';
            echo __('✓ Approve Payment', 'unico-wc');
            echo '</button>';
            echo '</p>';

            echo '<p>';
            echo '<button type="button" class="button unico-quick-reject" data-order-id="' . esc_attr($order->get_id()) . '">';
            echo __('✗ Reject Payment', 'unico-wc');
            echo '</button>';
            echo '</p>';
        }

        echo '</div>';

        wp_nonce_field('unico_quick_action', 'unico_quick_action_nonce');
    }

    /**
     * AJAX quick approve
     */
    public function ajax_quick_approve() {
        check_ajax_referer('unico_admin_nonce', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Permission denied');
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found');
        }

        $this->approve_payment($order);

        wp_send_json_success('Payment approved');
    }

    /**
     * AJAX quick reject
     */
    public function ajax_quick_reject() {
        check_ajax_referer('unico_admin_nonce', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Permission denied');
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found');
        }

        $this->reject_payment($order);

        wp_send_json_success('Payment rejected');
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (isset($_GET['bulk_action']) && isset($_GET['processed'])) {
            $action = sanitize_text_field($_GET['bulk_action']);
            $processed = intval($_GET['processed']);

            if ($action === 'approve_payments') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(__('%d payment(s) approved successfully.', 'unico-wc'), $processed) . '</p>';
                echo '</div>';
            } elseif ($action === 'reject_payments') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(__('%d payment(s) rejected successfully.', 'unico-wc'), $processed) . '</p>';
                echo '</div>';
            }
        }
    }
}
