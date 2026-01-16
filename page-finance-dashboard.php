<?php
/**
 * Template Name: Finance Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!in_array('unico_finance', $current_user->roles) && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get financial data
global $wpdb;

// Get date range
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

// Get orders in date range
$orders = wc_get_orders([
    'date_created' => $start_date . '...' . $end_date,
    'limit' => -1,
    'status' => ['completed', 'processing']
]);

// Calculate totals
$total_revenue = 0;
$total_orders = count($orders);
$total_refunds = 0;

foreach ($orders as $order) {
    $total_revenue += $order->get_total();
    $total_refunds += $order->get_total_refunded();
}

$net_revenue = $total_revenue - $total_refunds;

// Commission data
$commissions_table = $wpdb->prefix . 'unico_commissions';
$total_commissions = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(commission_amount) FROM $commissions_table WHERE created_at BETWEEN %s AND %s",
    $start_date . ' 00:00:00',
    $end_date . ' 23:59:59'
));

$pending_commissions = $wpdb->get_var("SELECT SUM(commission_amount) FROM $commissions_table WHERE status = 'pending'");

// Wallet data
$wallets_table = $wpdb->prefix . 'unico_wallets';
$total_wallet_balance = $wpdb->get_var("SELECT SUM(balance) FROM $wallets_table");

get_header();
?>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa !important; }
    .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 20px; }
    .dashboard-header { background: linear-gradient(135deg, #28a745 0%, #20893a 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
    .header-title h1 { font-size: 28px; margin-bottom: 5px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .stat-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
    .stat-value { font-size: 32px; font-weight: 700; color: #28a745; }
    .section-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 30px; }
    .section-header { background: #28a745; color: white; padding: 20px 25px; font-size: 18px; font-weight: 600; }
    .section-body { padding: 25px; }
    .date-filter { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
    .date-filter input { padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; }
    .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; }
    .btn-primary { background: #28a745; color: white; }
    .orders-table { width: 100%; border-collapse: collapse; }
    .orders-table th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 14px; }
    .orders-table td { padding: 15px 12px; border-bottom: 1px solid #e9ecef; }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="header-title">
            <h1>Finance Dashboard</h1>
            <p>Revenue tracking and financial analytics</p>
        </div>
    </div>

    <form method="get" class="date-filter">
        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value"><?php echo wc_price($total_revenue); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Net Revenue</div>
            <div class="stat-value"><?php echo wc_price($net_revenue); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?php echo $total_orders; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Refunds</div>
            <div class="stat-value"><?php echo wc_price($total_refunds); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Commissions Paid</div>
            <div class="stat-value"><?php echo wc_price($total_commissions ?: 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Commissions</div>
            <div class="stat-value"><?php echo wc_price($pending_commissions ?: 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Wallet Balance</div>
            <div class="stat-value"><?php echo wc_price($total_wallet_balance ?: 0); ?></div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">Recent Orders</div>
        <div class="section-body">
            <?php if (!empty($orders)): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($orders, 0, 20) as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order->get_id(); ?></strong></td>
                        <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                        <td><?php echo $order->get_date_created()->format('M j, Y'); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td><?php echo $order->get_status(); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No orders in selected date range.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
