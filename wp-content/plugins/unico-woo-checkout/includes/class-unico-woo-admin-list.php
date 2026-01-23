<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Woo_Admin_List {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            'Under Review Orders',
            'Under Review Orders',
            'manage_woocommerce',
            'unico-woo-under-review',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You do not have permission to access this page.');
        }

        $orders = wc_get_orders([
            'limit' => 50,
            'status' => ['under-review', 'rejected'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        ?>
        <div class="wrap">
            <h1>Under Review Orders</h1>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)) : ?>
                        <tr>
                            <td colspan="5">No orders awaiting review.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($orders as $order) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                                <td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
                                <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                                <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                <td>
                                    <a class="button button-primary" href="<?php echo esc_url($this->build_action_url('approve', $order->get_id())); ?>">Approve</a>
                                    <a class="button" href="<?php echo esc_url($this->build_action_url('reject', $order->get_id())); ?>">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function build_action_url($action, $order_id) {
        $nonce = wp_create_nonce('unico_order_' . $action . '_' . $order_id);
        return add_query_arg([
            'action' => 'unico_order_' . $action,
            'order_id' => $order_id,
            'unico_order_nonce' => $nonce,
        ], admin_url('admin-post.php'));
    }
}
