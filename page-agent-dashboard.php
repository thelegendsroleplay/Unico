<?php
/**
 * Template Name: Agent Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user can access agent dashboard
if (!Unico_User_Roles::user_can('access_agent_dashboard') && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get instances
$wallet = Unico_Wallet::get_instance();
$balance = $wallet->get_balance($user_id);
$pricing = Unico_Pricing::get_instance();
$security = Unico_Security::get_instance();

// Get agent's orders
$agent_orders = wc_get_orders([
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

// Get pricing rules for agent
$agent_pricing_rules = $pricing->get_rules_by_role('unico_agent');

// Get vouchers purchased
$vouchers_table = $wpdb->prefix . 'unico_vouchers';
$my_vouchers = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $vouchers_table WHERE assigned_to = %d AND voucher_status = 'delivered' ORDER BY delivered_at DESC LIMIT 20",
    $user_id
));

// Calculate stats
$total_orders = count($agent_orders);
$total_vouchers = count($my_vouchers);
$total_spent = array_sum(array_map(function($order) {
    return $order->get_total();
}, $agent_orders));

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #4a4a4a; }
        .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        .dashboard-header { background: linear-gradient(135deg, #e95134 0%, #c43d2a 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(233, 81, 52, 0.3); }
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
        .stat-value { font-size: 32px; font-weight: 700; color: #e95134; }
        .section-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-bottom: 30px; }
        .section-header { background: #103e54; color: white; padding: 20px 25px; font-size: 18px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .section-body { padding: 25px; }
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
        .status-paid { background: #d4edda; color: #155724; }
        .voucher-code { font-family: 'Courier New', monospace; background: #f8f9fa; padding: 8px 12px; border-radius: 6px; font-weight: 600; display: inline-block; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #e95134; color: white; }
        .btn-primary:hover { background: #c43d2a; }
        .btn-outline { background: transparent; color: #194f68; border: 2px solid #194f68; }
        .btn-outline:hover { background: #194f68; color: white; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state-icon { font-size: 64px; opacity: 0.3; margin-bottom: 20px; }
        .info-banner { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; }
        .info-banner h4 { color: #004085; margin-bottom: 8px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e9ecef; }
        .tab { padding: 12px 24px; cursor: pointer; font-weight: 600; color: #6c757d; transition: all 0.2s; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #e95134; border-bottom-color: #e95134; }
        .tab:hover { color: #e95134; }
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
                <p>Agent Dashboard - Access your bulk pricing and commission reports</p>
            </div>
            <div class="header-stats">
                <div class="header-stat">
                    <div class="header-stat-label">Wallet Balance</div>
                    <div class="header-stat-value"><?php echo wc_price($balance); ?></div>
                </div>
                <div class="header-stat">
                    <div class="header-stat-label">Total Commission</div>
                    <div class="header-stat-value"><?php echo wc_price($total_commission ?: 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <h4>🎯 Agent Benefits</h4>
        <p>As an agent, you get automatic discounts based on purchase quantity. The more you buy, the more you save!</p>
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

    <!-- Bulk Pricing Rules -->
    <div class="section-card">
        <div class="section-header">
            <span>Your Bulk Pricing Discounts</span>
            <span style="font-size: 14px; opacity: 0.9;">Automatic discounts applied at checkout</span>
        </div>
        <div class="section-body">
            <?php if (!empty($agent_pricing_rules)): ?>
            <div class="pricing-rules">
                <?php foreach ($agent_pricing_rules as $rule): ?>
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
                <p>Contact support to set up your agent pricing tiers.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="section-card">
        <div class="section-header">Recent Orders</div>
        <div class="section-body">
            <?php if (!empty($agent_orders)): ?>
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
                    <?php foreach (array_slice($agent_orders, 0, 10) as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order->get_id(); ?></strong></td>
                        <td><?php echo $order->get_date_created()->format('M j, Y'); ?></td>
                        <td><?php echo $order->get_item_count(); ?> items</td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td><?php echo wc_price($order->get_discount_total()); ?></td>
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

    <!-- Commission History -->
    <?php
    $commissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $commissions_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
        $user_id
    ));
    if (!empty($commissions)):
    ?>
    <div class="section-card">
        <div class="section-header">Commission History</div>
        <div class="section-body">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Order Total</th>
                        <th>Commission %</th>
                        <th>Commission Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $commission): ?>
                    <tr>
                        <td><strong>#<?php echo $commission->order_id; ?></strong></td>
                        <td><?php echo wc_price($commission->order_total); ?></td>
                        <td><?php echo number_format($commission->commission_percentage, 2); ?>%</td>
                        <td><strong><?php echo wc_price($commission->commission_amount); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($commission->status); ?>">
                                <?php echo esc_html(ucfirst($commission->status)); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($commission->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Vouchers -->
    <div class="section-card">
        <div class="section-header">My Vouchers</div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_vouchers as $voucher): ?>
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
