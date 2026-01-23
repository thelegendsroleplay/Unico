<?php
/**
 * Admin Order Management Interface
 * Provides admin UI for viewing and managing custom orders
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Admin_Orders {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX handlers
        add_action('wp_ajax_unico_approve_payment', [$this, 'ajax_approve_payment']);
        add_action('wp_ajax_unico_reject_payment', [$this, 'ajax_reject_payment']);
        add_action('wp_ajax_unico_update_order_status', [$this, 'ajax_update_order_status']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Unico Orders',
            'Orders',
            'manage_options',
            'unico-orders',
            [$this, 'render_orders_page'],
            'dashicons-cart',
            26
        );

        add_submenu_page(
            'unico-orders',
            'All Orders',
            'All Orders',
            'manage_options',
            'unico-orders',
            [$this, 'render_orders_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'unico-orders') === false) {
            return;
        }

        wp_enqueue_style('unico-admin-orders', get_template_directory_uri() . '/assets/css/admin-orders.css', [], '1.0');
        wp_enqueue_script('unico-admin-orders', get_template_directory_uri() . '/assets/js/admin-orders.js', ['jquery'], '1.0', true);

        wp_localize_script('unico-admin-orders', 'unicoAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('unico_admin_orders')
        ]);
    }

    /**
     * Render orders page
     */
    public function render_orders_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if ($action === 'view' && $order_id) {
            $this->render_order_detail($order_id);
        } else {
            $this->render_orders_list();
        }
    }

    /**
     * Render orders list
     */
    private function render_orders_list() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        $args = ['limit' => 50];
        if ($status_filter) {
            $args['status'] = $status_filter;
        }

        $orders = Unico_Order::get_orders($args);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Orders</h1>

            <ul class="subsubsub">
                <li><a href="?page=unico-orders" <?php echo !$status_filter ? 'class="current"' : ''; ?>>All</a> |</li>
                <li><a href="?page=unico-orders&status=pending-verification" <?php echo $status_filter === 'pending-verification' ? 'class="current"' : ''; ?>>Pending Verification</a> |</li>
                <li><a href="?page=unico-orders&status=processing" <?php echo $status_filter === 'processing' ? 'class="current"' : ''; ?>>Processing</a> |</li>
                <li><a href="?page=unico-orders&status=completed" <?php echo $status_filter === 'completed' ? 'class="current"' : ''; ?>>Completed</a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">No orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="?page=unico-orders&action=view&order_id=<?php echo $order->get_id(); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo date('Y/m/d H:i', strtotime($order->get_date_created())); ?></td>
                                <td>
                                    <?php echo esc_html($order->get_customer_name()); ?><br>
                                    <small><?php echo esc_html($order->get_customer_email()); ?></small>
                                </td>
                                <td><?php echo count($order->get_items()); ?> item(s)</td>
                                <td><?php echo $order->get_formatted_total(); ?></td>
                                <td>
                                    <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                        <?php echo esc_html(ucwords(str_replace('-', ' ', $order->get_status()))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-status status-<?php echo esc_attr($order->get_payment_status()); ?>">
                                        <?php echo esc_html(ucwords($order->get_payment_status())); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=unico-orders&action=view&order_id=<?php echo $order->get_id(); ?>" class="button button-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
        .order-status, .payment-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending-verification {
            background: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-pending {
            background: #e2e3e5;
            color: #383d41;
        }
        </style>
        <?php
    }

    /**
     * Render order detail page
     */
    private function render_order_detail($order_id) {
        $order = new Unico_Order($order_id);

        if (!$order->get_id()) {
            echo '<div class="wrap"><h1>Order not found</h1></div>';
            return;
        }

        $order_data = $order->get_data();
        $items = $order->get_items();
        $notes = $order->get_notes();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Order #<?php echo esc_html($order->get_order_number()); ?></h1>
            <a href="?page=unico-orders" class="page-title-action">← Back to Orders</a>

            <div style="margin-top: 20px;">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

                    <!-- Main Column -->
                    <div>
                        <!-- Order Items -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Order Items</span></h2>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Exam</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($item['product_name']); ?></strong></td>
                                                <td><?php echo esc_html($item['exam_name']); ?></td>
                                                <td><?php echo esc_html($item['quantity']); ?></td>
                                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>$<?php echo number_format($item['total'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="4" style="text-align: right;">Subtotal:</th>
                                            <th>$<?php echo number_format($order->get_subtotal(), 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" style="text-align: right;">Tax:</th>
                                            <th>$<?php echo number_format($order_data['tax'], 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" style="text-align: right;"><strong>Total:</strong></th>
                                            <th><strong><?php echo $order->get_formatted_total(); ?></strong></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Payment Receipt -->
                        <?php if ($order_data['payment_receipt_url']): ?>
                        <div class="postbox">
                            <h2 class="hndle"><span>Payment Receipt</span></h2>
                            <div class="inside">
                                <p><strong>Payment Reference:</strong> <?php echo esc_html($order->get_payment_reference()); ?></p>
                                <p>
                                    <a href="<?php echo esc_url($order_data['payment_receipt_url']); ?>" target="_blank" class="button">
                                        View Receipt →
                                    </a>
                                </p>
                                <div style="margin-top: 15px;">
                                    <img src="<?php echo esc_url($order_data['payment_receipt_url']); ?>"
                                         alt="Payment Receipt"
                                         style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                </div>

                                <?php if ($order_data['verification_status'] === 'pending'): ?>
                                <div style="margin-top: 20px;">
                                    <button type="button"
                                            class="button button-primary button-large"
                                            onclick="unicoApprovePayment(<?php echo $order->get_id(); ?>)">
                                        ✓ Approve Payment
                                    </button>
                                    <button type="button"
                                            class="button button-large"
                                            onclick="unicoRejectPayment(<?php echo $order->get_id(); ?>)"
                                            style="margin-left: 10px;">
                                        ✗ Reject Payment
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Order Notes -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Order Notes</span></h2>
                            <div class="inside">
                                <?php if (empty($notes)): ?>
                                    <p>No notes yet.</p>
                                <?php else: ?>
                                    <ul style="list-style: none; padding: 0;">
                                        <?php foreach ($notes as $note): ?>
                                            <li style="padding: 10px; border-bottom: 1px solid #eee;">
                                                <div><?php echo esc_html($note['note']); ?></div>
                                                <small style="color: #666;">
                                                    <?php echo date('M j, Y H:i', strtotime($note['added_at'])); ?>
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Column -->
                    <div>
                        <!-- Order Details -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Order Details</span></h2>
                            <div class="inside">
                                <p><strong>Order Number:</strong><br>#<?php echo esc_html($order->get_order_number()); ?></p>
                                <p><strong>Date:</strong><br><?php echo date('F j, Y H:i', strtotime($order->get_date_created())); ?></p>
                                <p><strong>Status:</strong><br>
                                    <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                        <?php echo esc_html(ucwords(str_replace('-', ' ', $order->get_status()))); ?>
                                    </span>
                                </p>
                                <p><strong>Payment Status:</strong><br>
                                    <span class="payment-status status-<?php echo esc_attr($order->get_payment_status()); ?>">
                                        <?php echo esc_html(ucwords($order->get_payment_status())); ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Customer Details -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Customer Details</span></h2>
                            <div class="inside">
                                <p><strong>Name:</strong><br><?php echo esc_html($order->get_customer_name()); ?></p>
                                <p><strong>Email:</strong><br><?php echo esc_html($order->get_customer_email()); ?></p>
                                <?php if ($order_data['customer_phone']): ?>
                                <p><strong>Phone:</strong><br><?php echo esc_html($order_data['customer_phone']); ?></p>
                                <?php endif; ?>
                                <p><strong>User ID:</strong><br>#<?php echo esc_html($order->get_user_id()); ?></p>
                            </div>
                        </div>

                        <!-- Payment Details -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Payment Details</span></h2>
                            <div class="inside">
                                <p><strong>Method:</strong><br>Bank Transfer</p>
                                <p><strong>Reference:</strong><br><?php echo esc_html($order->get_payment_reference()); ?></p>
                                <?php if ($order_data['verification_status']): ?>
                                <p><strong>Verification:</strong><br>
                                    <span class="order-status status-<?php echo esc_attr($order_data['verification_status']); ?>">
                                        <?php echo esc_html(ucwords($order_data['verification_status'])); ?>
                                    </span>
                                </p>
                                <?php endif; ?>
                                <?php if ($order_data['bank_details']): ?>
                                    <?php $bank_details = json_decode($order_data['bank_details'], true); ?>
                                    <p><strong>Bank:</strong><br><?php echo esc_html($bank_details['bank_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Account:</strong><br><?php echo esc_html($bank_details['account_number'] ?? 'N/A'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Voucher Delivery -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Voucher Delivery</span></h2>
                            <div class="inside">
                                <p><strong>Status:</strong><br>
                                    <?php if ($order_data['vouchers_delivered']): ?>
                                        <span style="color: #155724;">✓ Delivered</span><br>
                                        <small>
                                            <?php echo date('M j, Y H:i', strtotime($order_data['vouchers_delivered_at'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: #856404;">⏳ Pending</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function unicoApprovePayment(orderId) {
            if (!confirm('Are you sure you want to approve this payment?')) {
                return;
            }

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unico_approve_payment',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('unico_admin_orders'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✓ Payment approved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to approve payment. Please try again.');
                }
            });
        }

        function unicoRejectPayment(orderId) {
            var reason = prompt('Please enter reason for rejection:');
            if (!reason) {
                return;
            }

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unico_reject_payment',
                    order_id: orderId,
                    reason: reason,
                    nonce: '<?php echo wp_create_nonce('unico_admin_orders'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✓ Payment rejected.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to reject payment. Please try again.');
                }
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX: Approve payment
     */
    public function ajax_approve_payment() {
        check_ajax_referer('unico_admin_orders', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = new Unico_Order($order_id);

        if (!$order->get_id()) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        // Update verification status
        $order->update_meta('_voucher_verification_status', 'approved');
        $order->update_meta('_verified_by', get_current_user_id());
        $order->update_meta('_verified_at', current_time('mysql'));

        // Update order status to processing
        $order->update_status('processing', 'Payment receipt approved and verified by admin.');
        $order->update_payment_status('completed');

        // Add order note
        $order->add_note('Payment receipt verified and approved. Order now processing.');

        // Trigger voucher delivery
        do_action('unico_payment_approved', $order_id, $order);

        wp_send_json_success(['message' => 'Payment approved successfully']);
    }

    /**
     * AJAX: Reject payment
     */
    public function ajax_reject_payment() {
        check_ajax_referer('unico_admin_orders', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = new Unico_Order($order_id);

        if (!$order->get_id()) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        // Update verification status
        $order->update_meta('_voucher_verification_status', 'rejected');
        $order->update_meta('_rejection_reason', $reason);
        $order->update_meta('_verified_by', get_current_user_id());
        $order->update_meta('_verified_at', current_time('mysql'));

        // Update order status to failed
        $order->update_status('failed', 'Payment receipt rejected: ' . $reason);
        $order->update_payment_status('failed');

        // Add order note
        $order->add_note('Payment receipt rejected. Reason: ' . $reason);

        wp_send_json_success(['message' => 'Payment rejected']);
    }
}
