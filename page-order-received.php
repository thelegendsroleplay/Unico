<?php
/**
 * Template Name: Order Received (Thank You)
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    wp_redirect(home_url('/'));
    exit;
}

$order = new Unico_Order($order_id);

// Verify order belongs to current user
if ($order->get_user_id() != get_current_user_id()) {
    wp_redirect(home_url('/'));
    exit;
}

get_header();
?>

<style>
.order-received-container {
    max-width: 800px;
    margin: 60px auto;
    padding: 20px;
}

.order-success-icon {
    text-align: center;
    margin-bottom: 30px;
}

.order-success-icon svg {
    width: 80px;
    height: 80px;
    stroke: #28a745;
    stroke-width: 2;
}

.order-received-header {
    text-align: center;
    margin-bottom: 40px;
}

.order-received-header h1 {
    color: #28a745;
    margin-bottom: 10px;
}

.order-details-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
}

.order-details-row {
    display: flex;
    justify-content: space-between;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.order-details-row:last-child {
    border-bottom: none;
}

.order-details-label {
    font-weight: 600;
    color: #666;
}

.order-details-value {
    color: #333;
}

.order-items {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.order-items h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #ddd;
}

.order-item:last-child {
    border-bottom: none;
}

.order-total {
    font-size: 24px;
    font-weight: bold;
    color: #103e54;
    text-align: right;
    padding: 20px 0;
}

.order-status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
}

.status-pending-verification {
    background: #fff3cd;
    color: #856404;
}

.order-next-steps {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 20px;
    margin: 30px 0;
    border-radius: 4px;
}

.order-next-steps h3 {
    margin-top: 0;
    color: #007bff;
}

.order-next-steps ul {
    margin: 15px 0;
    padding-left: 20px;
}

.order-next-steps li {
    margin: 8px 0;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn {
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #103e54;
    color: #fff;
}

.btn-secondary {
    background: #fff;
    color: #103e54;
    border: 2px solid #103e54;
}

.btn:hover {
    opacity: 0.9;
}
</style>

<div class="order-received-container">
    <div class="order-success-icon">
        <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M8 12l2 2 4-4"></path>
        </svg>
    </div>

    <div class="order-received-header">
        <h1>Thank You for Your Order!</h1>
        <p>Your order has been received and is being processed.</p>
    </div>

    <div class="order-details-card">
        <h2>Order Details</h2>

        <div class="order-details-row">
            <span class="order-details-label">Order Number:</span>
            <span class="order-details-value">#<?php echo esc_html($order->get_order_number()); ?></span>
        </div>

        <div class="order-details-row">
            <span class="order-details-label">Date:</span>
            <span class="order-details-value"><?php echo date('F j, Y', strtotime($order->get_date_created())); ?></span>
        </div>

        <div class="order-details-row">
            <span class="order-details-label">Email:</span>
            <span class="order-details-value"><?php echo esc_html($order->get_customer_email()); ?></span>
        </div>

        <div class="order-details-row">
            <span class="order-details-label">Payment Method:</span>
            <span class="order-details-value">Bank Transfer</span>
        </div>

        <div class="order-details-row">
            <span class="order-details-label">Status:</span>
            <span class="order-details-value">
                <span class="order-status-badge status-pending-verification">
                    Pending Verification
                </span>
            </span>
        </div>

        <div class="order-details-row">
            <span class="order-details-label">Payment Reference:</span>
            <span class="order-details-value"><?php echo esc_html($order->get_payment_reference()); ?></span>
        </div>

        <div class="order-items">
            <h3>Order Items</h3>
            <?php foreach ($order->get_items() as $item): ?>
                <div class="order-item">
                    <div>
                        <strong><?php echo esc_html($item['product_name']); ?></strong>
                        <br>
                        <small>Exam: <?php echo esc_html($item['exam_name']); ?></small>
                    </div>
                    <div>
                        <?php echo esc_html($item['quantity']); ?> × $<?php echo number_format($item['unit_price'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-total">
            Total: <?php echo $order->get_formatted_total(); ?>
        </div>
    </div>

    <div class="order-next-steps">
        <h3>What Happens Next?</h3>
        <ul>
            <li><strong>Payment Verification:</strong> Our team will verify your payment receipt within 24 hours.</li>
            <li><strong>Order Processing:</strong> Once verified, your order will be processed immediately.</li>
            <li><strong>Voucher Delivery:</strong> Your voucher(s) will be delivered to your email: <strong><?php echo esc_html($order->get_customer_email()); ?></strong></li>
            <li><strong>Email Notification:</strong> You'll receive a confirmation email with your voucher code(s).</li>
        </ul>
        <p><strong>Need help?</strong> Contact our support team 24/7.</p>
    </div>

    <div class="button-group">
        <a href="<?php echo home_url('/'); ?>" class="btn btn-primary">Continue Shopping</a>
        <a href="<?php echo home_url('/support'); ?>" class="btn btn-secondary">Contact Support</a>
    </div>
</div>

<?php get_footer(); ?>
