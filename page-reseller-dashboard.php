<?php
/**
 * Template Name: Reseller Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user can access reseller dashboard
if (!Unico_User_Roles::user_can('access_reseller_dashboard') && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get instances
$pricing = Unico_Pricing::get_instance();
$voucher_system = Unico_Voucher_System::get_instance();

// Get reseller's orders
$reseller_orders = wc_get_orders([
    'customer_id' => $user_id,
    'limit' => 50,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get commission data
global $wpdb;
$commissions_table = $wpdb->prefix . 'unico_commissions';
$total_commission = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(commission_amount) FROM $commissions_table WHERE user_id = %d AND status = 'paid'",
    $user_id
));
$pending_commission = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(commission_amount) FROM $commissions_table WHERE user_id = %d AND status = 'pending'",
    $user_id
));

// Get pricing rules for reseller
$reseller_pricing_rules = $pricing->get_rules_by_role('unico_reseller');

// Get vouchers purchased
$vouchers_table = $wpdb->prefix . 'unico_vouchers';
$my_vouchers = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $vouchers_table WHERE assigned_to = %d AND voucher_status = 'delivered' ORDER BY delivered_at DESC",
    $user_id
));

// Get voucher stock levels (available vouchers in system)
$stock_stats = $voucher_system->get_voucher_stats();

// Get vouchers by exam type
$voucher_types = ['PTE', 'IELTS', 'TOEFL', 'Duolingo', 'GRE', 'GMAT'];
$stock_by_type = [];
foreach ($voucher_types as $type) {
    $available = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $vouchers_table WHERE exam_name = %s AND voucher_status = 'available'",
        $type
    ));
    $stock_by_type[$type] = $available;
}

// Calculate stats
$total_orders = count($reseller_orders);
$total_vouchers = count($my_vouchers);
$total_spent = array_sum(array_map(function($order) {
    return $order->get_total();
}, $reseller_orders));

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Dashboard - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #4a4a4a; }
        .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        .dashboard-header { background: linear-gradient(135deg, #103e54 0%, #1a5a7a 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(16, 62, 84, 0.3); }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .header-title h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .header-title p { opacity: 0.9; font-size: 14px; }
        .header-stats { display: flex; gap: 30px; }
        .header-stat { text-align: center; }
        .header-stat-label { font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
        .header-stat-value { font-size: 24px; font-weight: 700; margin-top: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12); }
        .stat-icon { font-size: 32px; margin-bottom: 10px; }
        .stat-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #103e54; }
        .section-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-bottom: 30px; }
        .section-header { background: #103e54; color: white; padding: 20px 25px; font-size: 18px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .section-body { padding: 25px; }
        .stock-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stock-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 4px solid #103e54; padding: 20px; border-radius: 8px; }
        .stock-exam-name { font-size: 18px; font-weight: 700; color: #103e54; margin-bottom: 10px; }
        .stock-count { font-size: 32px; font-weight: 700; color: #e95134; }
        .stock-label { font-size: 12px; color: #6c757d; text-transform: uppercase; margin-top: 5px; }
        .pricing-rules { display: grid; gap: 15px; }
        .pricing-rule { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 4px solid #e95134; padding: 20px; border-radius: 8px; }
        .rule-detail-value { font-size: 20px; font-weight: 700; color: #e95134; margin-top: 5px; }
        .rule-details { display: flex; gap: 30px; flex-wrap: wrap; }
        .rule-detail { display: flex; flex-direction: column; }
        .rule-detail-label { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; }
        .rule-detail-value { font-size: 20px; font-weight: 700; color: #e95134; margin-top: 5px; }
        .orders-table { width: 100%; border-collapse: collapse; }
        .orders-table th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; font-size: 14px; color: #6c757d; text-transform: uppercase; }
        .orders-table td { padding: 15px 12px; border-bottom: 1px solid #e9ecef; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-pending { background: #f8d7da; color: #721c24; }
        .status-available { background: #d4edda; color: #155724; }
        .status-delivered { background: #cfe2ff; color: #084298; }
        .voucher-code { font-family: 'Courier New', monospace; background: #f8f9fa; padding: 8px 12px; border-radius: 6px; font-weight: 600; display: inline-block; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #e95134; color: white; }
        .btn-primary:hover { background: #c43d2a; }
        .btn-outline { background: transparent; color: #194f68; border: 2px solid #194f68; }
        .btn-outline:hover { background: #194f68; color: white; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state-icon { font-size: 64px; opacity: 0.3; margin-bottom: 20px; }
        .info-banner { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; }
        .info-banner h4 { color: #0c5460; margin-bottom: 8px; }
        .low-stock { color: #dc3545; }
        .medium-stock { color: #ffc107; }
        .high-stock { color: #28a745; }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; }
            .header-stats { width: 100%; justify-content: space-around; }
            .orders-table { font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Welcome, <?php echo esc_html($current_user->display_name); ?></h1>
                <p>Reseller Dashboard - Premium bulk rates and stock management</p>
            </div>
            <div class="header-stats">
                <div class="header-stat">
                    <div class="header-stat-label">Total Commission</div>
                    <div class="header-stat-value"><?php echo wc_price($total_commission ?: 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <h4>🏢 Reseller Premium Access</h4>
        <p>As a Training Center/Reseller, you get the best bulk rates up to 25% off. View real-time stock levels and manage your voucher inventory.</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?php echo $total_orders; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎫</div>
            <div class="stat-label">Vouchers Purchased</div>
            <div class="stat-value"><?php echo $total_vouchers; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-label">Total Spent</div>
            <div class="stat-value"><?php echo wc_price($total_spent); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-label">Pending Commission</div>
            <div class="stat-value"><?php echo wc_price($pending_commission ?: 0); ?></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin-bottom: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo home_url('/shop'); ?>" class="btn btn-primary">Browse Vouchers</a>
        <a href="<?php echo home_url('/my-account'); ?>" class="btn btn-outline">My Account</a>
        <a href="<?php echo home_url('/support'); ?>" class="btn btn-outline">Contact Support</a>
    </div>

    <!-- Stock Levels -->
    <div class="section-card">
        <div class="section-header">
            <span>Current Stock Levels</span>
            <span style="font-size: 14px; opacity: 0.9;">Real-time voucher availability</span>
        </div>
        <div class="section-body">
            <div class="stock-grid">
                <?php foreach ($stock_by_type as $exam => $count): ?>
                <div class="stock-card">
                    <div class="stock-exam-name"><?php echo esc_html($exam); ?></div>
                    <div class="stock-count <?php
                        if ($count < 10) echo 'low-stock';
                        elseif ($count < 50) echo 'medium-stock';
                        else echo 'high-stock';
                    ?>">
                        <?php echo $count; ?>
                    </div>
                    <div class="stock-label">Available</div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; font-size: 14px;">
                <strong>Note:</strong> Stock levels are updated in real-time. Low stock (< 10) appears in red. Contact support for bulk orders exceeding available stock.
            </div>
        </div>
    </div>

    <!-- Bulk Pricing Rules -->
    <div class="section-card">
        <div class="section-header">
            <span>Your Bulk Pricing Discounts</span>
            <span style="font-size: 14px; opacity: 0.9;">Premium reseller rates</span>
        </div>
        <div class="section-body">
            <?php if (!empty($reseller_pricing_rules)): ?>
            <div class="pricing-rules">
                <?php foreach ($reseller_pricing_rules as $rule): ?>
                <div class="pricing-rule">
                    <div class="rule-title"><?php echo esc_html($rule->rule_name); ?></div>
                    <div class="rule-details">
                        <div class="rule-detail">
                            <span class="rule-detail-label">Quantity Range</span>
                            <span class="rule-detail-value">
                                <?php echo $rule->min_quantity; ?>
                                <?php echo $rule->max_quantity ? ' - ' . $rule->max_quantity : '+'; ?>
                            </span>
                        </div>
                        <div class="rule-detail">
                            <span class="rule-detail-label">Discount</span>
                            <span class="rule-detail-value">
                                <?php
                                if ($rule->discount_type === 'percentage') {
                                    echo $rule->discount_value . '%';
                                } else {
                                    echo wc_price($rule->discount_value);
                                }
                                ?>
                            </span>
                        </div>
                        <div class="rule-detail">
                            <span class="rule-detail-label">Status</span>
                            <span class="rule-detail-value" style="color: #28a745;">Active</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">💸</div>
                <h3>No Pricing Rules Configured</h3>
                <p>Contact support to set up your reseller pricing tiers.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="section-card">
        <div class="section-header">Recent Orders</div>
        <div class="section-body">
            <?php if (!empty($reseller_orders)): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($reseller_orders, 0, 10) as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order->get_id(); ?></strong></td>
                        <td><?php echo $order->get_date_created()->format('M j, Y'); ?></td>
                        <td><?php echo $order->get_item_count(); ?> items</td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td><strong style="color: #28a745;"><?php echo wc_price($order->get_discount_total()); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($order->get_status()); ?>">
                                <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo $order->get_view_order_url(); ?>" style="color: #e95134; font-weight: 600; text-decoration: none;">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No Orders Yet</h3>
                <p>Start purchasing vouchers to see your order history here.</p>
                <a href="<?php echo home_url('/shop'); ?>" class="btn btn-primary" style="margin-top: 20px;">Browse Vouchers</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Vouchers -->
    <div class="section-card">
        <div class="section-header">
            <span>My Voucher Inventory</span>
            <span style="font-size: 14px; opacity: 0.9;"><?php echo count($my_vouchers); ?> total vouchers</span>
        </div>
        <div class="section-body">
            <?php if (!empty($my_vouchers)): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Exam</th>
                        <th>Voucher Code</th>
                        <th>Status</th>
                        <th>Delivered</th>
                        <th>Expiry</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($my_vouchers, 0, 20) as $voucher): ?>
                    <tr>
                        <td><strong><?php echo esc_html($voucher->exam_name); ?></strong></td>
                        <td><span class="voucher-code"><?php echo esc_html($security->decrypt_data($voucher->voucher_code)); ?></span></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($voucher->voucher_status); ?>">
                                <?php echo esc_html(ucfirst($voucher->voucher_status)); ?>
                            </span>
                        </td>
                        <td><?php echo $voucher->delivered_at ? date('M j, Y', strtotime($voucher->delivered_at)) : 'N/A'; ?></td>
                        <td><?php echo $voucher->expiry_date ? date('M j, Y', strtotime($voucher->expiry_date)) : 'No Expiry'; ?></td>
                        <td>#<?php echo $voucher->order_id; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎫</div>
                <h3>No Vouchers Yet</h3>
                <p>Your purchased vouchers will appear here after delivery.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
