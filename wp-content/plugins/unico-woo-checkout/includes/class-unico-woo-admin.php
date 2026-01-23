<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Woo_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('woocommerce_order_actions', [$this, 'add_order_actions']);
        add_action('woocommerce_order_action_unico_approve', [$this, 'handle_approve_action']);
        add_action('woocommerce_order_action_unico_reject', [$this, 'handle_reject_action']);
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        add_action('admin_post_unico_order_approve', [$this, 'handle_admin_post_approve']);
        add_action('admin_post_unico_order_reject', [$this, 'handle_admin_post_reject']);
    }

    public function add_order_actions($actions) {
        $actions['unico_approve'] = 'Approve Payment (Unico)';
        $actions['unico_reject'] = 'Reject Payment (Unico)';
        return $actions;
    }

    public function handle_approve_action($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        $this->approve_order($order);
    }

    public function handle_reject_action($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        $this->reject_order($order);
    }

    public function add_order_meta_box() {
        add_meta_box(
            'unico_order_review',
            'Bank Transfer Review',
            [$this, 'render_meta_box'],
            'shop_order',
            'side',
            'high'
        );
    }

    public function render_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            echo '<p>Order not found.</p>';
            return;
        }

        $transaction_id = $order->get_meta('_unico_transaction_id');
        $proof_id = $order->get_meta('_unico_payment_proof_id');
        $proof_url = $proof_id ? wp_get_attachment_url($proof_id) : '';

        echo '<p><strong>Transaction ID:</strong> ' . esc_html($transaction_id ?: '—') . '</p>';
        if ($proof_url) {
            echo '<p><a href="' . esc_url($proof_url) . '" target="_blank">View Payment Proof</a></p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('unico_order_approve_' . $post->ID, 'unico_order_nonce');
        echo '<input type="hidden" name="action" value="unico_order_approve">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr($post->ID) . '">';
        echo '<p><button type="submit" class="button button-primary">Approve</button></p>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('unico_order_reject_' . $post->ID, 'unico_order_nonce');
        echo '<input type="hidden" name="action" value="unico_order_reject">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr($post->ID) . '">';
        echo '<p><button type="submit" class="button">Reject</button></p>';
        echo '</form>';
    }

    public function handle_admin_post_approve() {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id || !isset($_POST['unico_order_nonce']) || !wp_verify_nonce($_POST['unico_order_nonce'], 'unico_order_approve_' . $order_id)) {
            wp_die('Invalid request.');
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $this->approve_order($order);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        exit;
    }

    public function handle_admin_post_reject() {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id || !isset($_POST['unico_order_nonce']) || !wp_verify_nonce($_POST['unico_order_nonce'], 'unico_order_reject_' . $order_id)) {
            wp_die('Invalid request.');
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $this->reject_order($order);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        exit;
    }

    private function approve_order(WC_Order $order) {
        $order->update_status('completed', 'Payment approved by admin.');
        if (function_exists('deliver_vouchers')) {
            deliver_vouchers($order->get_id());
        }

        $customer_email = $order->get_billing_email();
        if ($customer_email) {
            wp_mail(
                $customer_email,
                'Your vouchers are ready',
                'Your payment has been approved and your voucher delivery is being processed.'
            );
        }
    }

    private function reject_order(WC_Order $order) {
        $order->update_status('rejected', 'Payment rejected by admin.');
    }
}
