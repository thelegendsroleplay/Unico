<?php
/**
 * Template Name: Agent Dashboard
 * 
 * The main dashboard for Agents to view their wallet, commissions, orders, and vouchers.
 */

// 1. Security & Access Control
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!is_user_logged_in()) {
    wp_redirect(wc_get_page_permalink('myaccount'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$roles = (array) $current_user->roles;

// Check permissions: Allow Administrators and Agents
$is_admin = in_array('administrator', $roles) || in_array('shop_manager', $roles);
$is_agent = in_array('unico_agent', $roles) || in_array('unico_reseller', $roles);

if (!$is_admin && !$is_agent) {
    // Fallback capability check
    if (class_exists('Unico_User_Roles') && Unico_User_Roles::user_can('access_agent_dashboard')) {
        // Allowed via capability
    } else {
        wc_add_notice('You do not have permission to access the Agent Dashboard.', 'error');
        wp_redirect(home_url());
        exit;
    }
}

// 2. Data Retrieval
global $wpdb;

// Initialize Systems
$wallet_system = class_exists('Unico_Wallet') ? Unico_Wallet::get_instance() : null;
$pricing_system = class_exists('Unico_Pricing') ? Unico_Pricing::get_instance() : null;
$security_system = class_exists('Unico_Security') ? Unico_Security::get_instance() : null;

// A. Wallet Balance
$wallet_balance = 0.00;
if ($wallet_system) {
    $wallet_balance = $wallet_system->get_balance($user_id);
}

// B. Commission Stats
$commissions_table = $wpdb->prefix . 'unico_commissions';
$commission_stats = (object) ['total_paid' => 0, 'total_pending' => 0];

// Check if table exists before querying to avoid errors
if ($wpdb->get_var("SHOW TABLES LIKE '$commissions_table'") === $commissions_table) {
    $commission_query = $wpdb->prepare(
        "SELECT 
            SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as total_pending
        FROM $commissions_table 
        WHERE user_id = %d",
        $user_id
    );
    $result = $wpdb->get_row($commission_query);
    if ($result) {
        $commission_stats = $result;
    }
}

// C. Recent Orders
$agent_orders = [];
if (function_exists('wc_get_orders')) {
    $agent_orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
}

// D. My Vouchers
$vouchers_table = $wpdb->prefix . 'unico_vouchers';
$my_vouchers = [];
if ($wpdb->get_var("SHOW TABLES LIKE '$vouchers_table'") === $vouchers_table) {
    $my_vouchers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $vouchers_table WHERE assigned_to = %d ORDER BY created_at DESC LIMIT 5",
        $user_id
    ));
}

// E. Pricing Rules
$pricing_rules = [];
if ($pricing_system) {
    // Get rules for the user's specific role
    foreach ($roles as $role) {
        $role_rules = $pricing_system->get_rules_by_role($role);
        if (!empty($role_rules)) {
            $pricing_rules = array_merge($pricing_rules, $role_rules);
        }
    }
}

get_header();
?>

<div class="unico-agent-dashboard">
    
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="user-welcome">
            <div class="avatar-circle">
                <?php echo get_avatar($user_id, 64); ?>
            </div>
            <div class="welcome-text">
                <h1>Welcome back, <?php echo esc_html($current_user->display_name); ?>!</h1>
                <span class="user-role-badge"><?php echo esc_html(ucfirst($roles[0])); ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="<?php echo esc_url(home_url('/vouchers')); ?>" class="action-btn btn-primary">
                <span class="dashicons dashicons-cart"></span> Purchase Vouchers
            </a>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>" class="action-btn btn-secondary">
                <span class="dashicons dashicons-admin-users"></span> My Profile
            </a>
        </div>
    </header>

    <!-- Key Metrics Grid -->
    <section class="metrics-grid">
        <!-- Wallet Card -->
        <div class="metric-card wallet-card">
            <div class="metric-icon">
                <span class="dashicons dashicons-vault"></span>
            </div>
            <div class="metric-content">
                <h3>Wallet Balance</h3>
                <div class="metric-value"><?php echo wc_price($wallet_balance); ?></div>
                <p class="metric-sub">Available for use</p>
            </div>
        </div>

        <!-- Commission Paid -->
        <div class="metric-card income-card">
            <div class="metric-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="metric-content">
                <h3>Total Earnings</h3>
                <div class="metric-value"><?php echo wc_price($commission_stats->total_paid ? $commission_stats->total_paid : 0); ?></div>
                <p class="metric-sub">Paid commissions</p>
            </div>
        </div>

        <!-- Commission Pending -->
        <div class="metric-card pending-card">
            <div class="metric-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="metric-content">
                <h3>Pending Payout</h3>
                <div class="metric-value"><?php echo wc_price($commission_stats->total_pending ? $commission_stats->total_pending : 0); ?></div>
                <p class="metric-sub">Processing</p>
            </div>
        </div>

        <!-- Orders Count -->
        <div class="metric-card orders-card">
            <div class="metric-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="metric-content">
                <h3>Total Orders</h3>
                <div class="metric-value"><?php echo count($agent_orders); ?></div>
                <p class="metric-sub">Lifetime orders</p>
            </div>
        </div>
    </section>

    <!-- Main Content Area -->
    <div class="dashboard-content-layout">
        
        <!-- Left Column: Operational Data -->
        <div class="main-column">
            
            <!-- Recent Vouchers Panel -->
            <div class="content-panel">
                <div class="panel-header">
                    <h2><span class="dashicons dashicons-tickets-alt"></span> Recent Vouchers</h2>
                </div>
                <div class="panel-body">
                    <?php if (!empty($my_vouchers)) : ?>
                        <div class="table-responsive">
                            <table class="unico-table">
                                <thead>
                                    <tr>
                                        <th>Exam Name</th>
                                        <th>Voucher Code</th>
                                        <th>Status</th>
                                        <th>Expiry</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_vouchers as $voucher) : ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($voucher->exam_name); ?></strong></td>
                                            <td>
                                                <code class="voucher-code">
                                                    <?php 
                                                    echo ($security_system && !empty($voucher->voucher_code)) 
                                                        ? esc_html($security_system->decrypt_data($voucher->voucher_code)) 
                                                        : '••••••••'; 
                                                    ?>
                                                </code>
                                            </td>
                                            <td>
                                                <span class="status-pill status-<?php echo esc_attr(strtolower($voucher->voucher_status)); ?>">
                                                    <?php echo esc_html(ucfirst($voucher->voucher_status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $voucher->expiry_date ? date_i18n(get_option('date_format'), strtotime($voucher->expiry_date)) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="empty-state">
                            <p>No vouchers found. Purchase some to get started!</p>
                            <a href="<?php echo esc_url(home_url('/vouchers')); ?>" class="btn-text">Go to Shop &rarr;</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders Panel -->
            <div class="content-panel">
                <div class="panel-header">
                    <h2><span class="dashicons dashicons-list-view"></span> Recent Orders</h2>
                    <a href="<?php echo esc_url(wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount'))); ?>" class="view-all-link">View All</a>
                </div>
                <div class="panel-body">
                    <?php if (!empty($agent_orders)) : ?>
                        <div class="table-responsive">
                            <table class="unico-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agent_orders as $order) : ?>
                                        <tr>
                                            <td>#<?php echo $order->get_id(); ?></td>
                                            <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo esc_attr($order->get_status()); ?>">
                                                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="btn-sm btn-outline">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="empty-state">
                            <p>You haven't placed any orders yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Column: Info & Tools -->
        <div class="sidebar-column">
            
            <!-- Pricing Rules / Discounts -->
            <div class="content-panel highlight-panel">
                <div class="panel-header">
                    <h2><span class="dashicons dashicons-tag"></span> Your Discounts</h2>
                </div>
                <div class="panel-body">
                    <?php if (!empty($pricing_rules)) : ?>
                        <ul class="discount-list">
                            <?php foreach ($pricing_rules as $rule) : ?>
                                <li class="discount-item">
                                    <div class="discount-info">
                                        <strong><?php echo esc_html($rule->rule_name); ?></strong>
                                        <span>Qty: <?php echo esc_html($rule->min_quantity); ?><?php echo $rule->max_quantity ? ' - ' . esc_html($rule->max_quantity) : '+'; ?></span>
                                    </div>
                                    <div class="discount-badge">
                                        <?php echo ($rule->discount_type === 'percentage') ? esc_html($rule->discount_value) . '% OFF' : wc_price($rule->discount_value) . ' OFF'; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="text-muted">No special bulk discounts active for your account currently.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Support / Quick Links -->
            <div class="content-panel">
                <div class="panel-header">
                    <h2>Quick Links</h2>
                </div>
                <div class="panel-body">
                    <ul class="quick-links">
                        <li><a href="<?php echo esc_url(home_url('/agent-resources')); ?>">📚 Agent Resources</a></li>
                        <li><a href="<?php echo esc_url(home_url('/marketing-materials')); ?>">🖼️ Marketing Materials</a></li>
                        <li><a href="<?php echo esc_url(home_url('/contact')); ?>">📞 Contact Support</a></li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>

<style>
    /* Scoped Styles for Agent Dashboard */
    .unico-agent-dashboard {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #333;
        background-color: #f4f6f9;
        min-height: 100vh;
    }

    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        background: #fff;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .user-welcome {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .avatar-circle img {
        border-radius: 50%;
        border: 4px solid #f0f0f0;
    }
    .welcome-text h1 {
        margin: 0 0 5px 0;
        font-size: 24px;
        font-weight: 700;
        color: #2c3e50;
    }
    .user-role-badge {
        background: #e95134;
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .header-actions {
        display: flex;
        gap: 15px;
    }

    /* Buttons */
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-primary {
        background: #e95134;
        color: #fff;
    }
    .btn-primary:hover {
        background: #d04328;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(233, 81, 52, 0.3);
    }
    .btn-secondary {
        background: #fff;
        color: #555;
        border: 1px solid #ddd;
    }
    .btn-secondary:hover {
        background: #f9f9f9;
        color: #333;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 4px;
    }
    .btn-outline {
        border: 1px solid #ddd;
        color: #666;
        text-decoration: none;
    }
    .btn-outline:hover {
        border-color: #aaa;
        background: #f5f5f5;
    }

    /* Metrics Grid */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    .metric-card {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        transition: transform 0.2s;
        border-left: 5px solid transparent;
    }
    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    }
    .wallet-card { border-left-color: #27ae60; }
    .income-card { border-left-color: #2980b9; }
    .pending-card { border-left-color: #f39c12; }
    .orders-card { border-left-color: #8e44ad; }

    .metric-icon {
        width: 50px;
        height: 50px;
        background: #f8f9fa;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #7f8c8d;
    }
    .metric-content h3 {
        margin: 0;
        font-size: 14px;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .metric-value {
        font-size: 28px;
        font-weight: 700;
        color: #2c3e50;
        margin: 5px 0 0;
    }
    .metric-sub {
        margin: 0;
        font-size: 12px;
        color: #aaa;
    }

    /* Content Layout */
    .dashboard-content-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    
    /* Panels */
    .content-panel {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.02);
        margin-bottom: 30px;
        overflow: hidden;
    }
    .panel-header {
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .panel-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .panel-body {
        padding: 25px;
    }

    /* Tables */
    .table-responsive {
        overflow-x: auto;
    }
    .unico-table {
        width: 100%;
        border-collapse: collapse;
    }
    .unico-table th {
        text-align: left;
        padding: 12px 15px;
        font-size: 13px;
        color: #7f8c8d;
        text-transform: uppercase;
        border-bottom: 2px solid #f0f0f0;
    }
    .unico-table td {
        padding: 15px;
        border-bottom: 1px solid #f9f9f9;
        font-size: 14px;
        color: #444;
    }
    .unico-table tr:last-child td {
        border-bottom: none;
    }
    
    /* Status Pills */
    .status-pill {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-completed, .status-active, .status-paid { background: #d4edda; color: #155724; }
    .status-processing, .status-pending { background: #fff3cd; color: #856404; }
    .status-cancelled, .status-failed, .status-used { background: #f8d7da; color: #721c24; }
    
    .voucher-code {
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: monospace;
        letter-spacing: 1px;
    }

    /* Discount List */
    .discount-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .discount-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px dashed #eee;
    }
    .discount-item:last-child {
        border-bottom: none;
    }
    .discount-info strong {
        display: block;
        color: #2c3e50;
        margin-bottom: 4px;
    }
    .discount-info span {
        font-size: 12px;
        color: #888;
    }
    .discount-badge {
        background: #fde8e4;
        color: #e95134;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 13px;
    }

    /* Quick Links */
    .quick-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .quick-links li {
        margin-bottom: 12px;
    }
    .quick-links a {
        display: block;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 6px;
        text-decoration: none;
        color: #555;
        font-weight: 500;
        transition: background 0.2s;
    }
    .quick-links a:hover {
        background: #eee;
        color: #333;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 20px;
        color: #999;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .dashboard-content-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        .user-welcome {
            flex-direction: column;
        }
        .header-actions {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php get_footer(); ?>
