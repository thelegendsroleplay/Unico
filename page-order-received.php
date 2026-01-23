<?php
/**
 * Template Name: Order Received (Thank You)
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
$order_id = $order_key ? wc_get_order_id_by_order_key($order_key) : 0;
$order = $order_id ? wc_get_order($order_id) : null;

if (!$order) {
    wp_redirect(home_url('/'));
    exit;
}

if ((int) $order->get_user_id() !== get_current_user_id()) {
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

.status-under-review {
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
}

.btn-primary {
    background: #f97316;
    color: #fff;
}

.btn-secondary {
    background: #6b7280;
    color: #fff;
}
</style>

<div class="order-received-container">
    <div class="order-success-icon">
        <svg fill="none" viewBox="0 0 24 24">
            <path d="M20 6L9 17L4 12" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

    <div class="order-received-header">
        <h1>Order Received</h1>
        <p>Thank you! Your order has been created and is under review.</p>
    </div>

    <div class="order-details-card">
        <div class="order-details-row">
            <span class="order-details-label">Order Number</span>
            <span class="order-details-value">#<?php echo esc_html($order->get_order_number()); ?></span>
        </div>
        <div class="order-details-row">
            <span class="order-details-label">Date</span>
            <span class="order-details-value"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></span>
        </div>
        <div class="order-details-row">
            <span class="order-details-label">Total</span>
            <span class="order-details-value"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
        </div>
        <div class="order-details-row">
            <span class="order-details-label">Status</span>
            <span class="order-details-value">
                <span class="order-status-badge status-<?php echo esc_attr($order->get_status()); ?>">
                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                </span>
            </span>
        </div>
    </div>

    <div class="order-items">
        <h3>Order Items</h3>
        <?php foreach ($order->get_items() as $item) : ?>
            <div class="order-item">
                <span><?php echo esc_html($item->get_name()); ?></span>
                <span><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span>
            </div>
        <?php endforeach; ?>
        <div class="order-total">
            <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
        </div>
    </div>

    <div class="order-next-steps">
        <h3>Next Steps</h3>
        <ul>
            <li>Your payment proof has been submitted for review.</li>
            <li>Our team will verify your bank transfer.</li>
            <li>You will receive your voucher details after approval.</li>
        </ul>
    </div>

    <div class="button-group">
        <a class="btn btn-primary" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">View My Orders</a>
        <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/support')); ?>">Contact Support</a>
    </div>
</div>

<?php
get_footer();
?>
