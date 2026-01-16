<?php
/**
 * Template Name: Management Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!in_array('unico_management', $current_user->roles) && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get overview data
global $wpdb;

$voucher_system = Unico_Voucher_System::get_instance();
$voucher_stats = $voucher_system->get_voucher_stats();

// Get user counts
$customer_count = count(get_users(['role' => 'unico_customer']));
$agent_count = count(get_users(['role' => 'unico_agent']));
$reseller_count = count(get_users(['role' => 'unico_reseller']));

// Get order stats (last 30 days)
$orders_30days = wc_get_orders([
    'date_created' => date('Y-m-d', strtotime('-30 days')) . '...' . date('Y-m-d'),
    'limit' => -1
]);

$revenue_30days = array_sum(array_map(function($order) {
    return $order->get_total();
}, $orders_30days));

// Get ticket stats
$tickets_table = $wpdb->prefix . 'unico_support_tickets';
$open_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'open'");
$total_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table");

// Get security stats
$security_table = $wpdb->prefix . 'unico_security_checks';
$high_risk_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $security_table WHERE risk_score >= 70");

get_header();
?>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa !important; }
    .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 20px; }
    .dashboard-header { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
    .header-title h1 { font-size: 28px; margin-bottom: 5px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .stat-icon { font-size: 32px; margin-bottom: 10px; }
    .stat-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
    .stat-value { font-size: 32px; font-weight: 700; color: #17a2b8; }
    .section-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 30px; }
    .section-header { background: #17a2b8; color: white; padding: 20px 25px; font-size: 18px; font-weight: 600; }
    .section-body { padding: 25px; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .info-item { padding: 20px; background: #f8f9fa; border-radius: 8px; }
    .info-item h4 { margin-bottom: 10px; color: #17a2b8; }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="header-title">
            <h1>Management Dashboard</h1>
            <p>System overview and key metrics</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-label">Orders (30 Days)</div>
            <div class="stat-value"><?php echo count($orders_30days); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-label">Revenue (30 Days)</div>
            <div class="stat-value"><?php echo wc_price($revenue_30days); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎫</div>
            <div class="stat-label">Available Vouchers</div>
            <div class="stat-value"><?php echo $voucher_stats['available']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Delivered Vouchers</div>
            <div class="stat-value"><?php echo $voucher_stats['delivered']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-label">Total Customers</div>
            <div class="stat-value"><?php echo $customer_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-label">Agents + Resellers</div>
            <div class="stat-value"><?php echo $agent_count + $reseller_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎫</div>
            <div class="stat-label">Open Tickets</div>
            <div class="stat-value"><?php echo $open_tickets; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div class="stat-label">High Risk Users</div>
            <div class="stat-value" style="color: #dc3545;"><?php echo $high_risk_users; ?></div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">Voucher Inventory Status</div>
        <div class="section-body">
            <div class="info-grid">
                <div class="info-item">
                    <h4>Total Vouchers</h4>
                    <p style="font-size: 24px; font-weight: 700;"><?php echo $voucher_stats['total']; ?></p>
                </div>
                <div class="info-item">
                    <h4>Available</h4>
                    <p style="font-size: 24px; font-weight: 700; color: #28a745;"><?php echo $voucher_stats['available']; ?></p>
                </div>
                <div class="info-item">
                    <h4>Assigned</h4>
                    <p style="font-size: 24px; font-weight: 700; color: #ffc107;"><?php echo $voucher_stats['assigned']; ?></p>
                </div>
                <div class="info-item">
                    <h4>Delivered</h4>
                    <p style="font-size: 24px; font-weight: 700; color: #17a2b8;"><?php echo $voucher_stats['delivered']; ?></p>
                </div>
                <div class="info-item">
                    <h4>Expired</h4>
                    <p style="font-size: 24px; font-weight: 700; color: #dc3545;"><?php echo $voucher_stats['expired']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">User Distribution</div>
        <div class="section-body">
            <div class="info-grid">
                <div class="info-item">
                    <h4>Customers</h4>
                    <p style="font-size: 24px; font-weight: 700;"><?php echo $customer_count; ?></p>
                </div>
                <div class="info-item">
                    <h4>Agents</h4>
                    <p style="font-size: 24px; font-weight: 700;"><?php echo $agent_count; ?></p>
                </div>
                <div class="info-item">
                    <h4>Resellers</h4>
                    <p style="font-size: 24px; font-weight: 700;"><?php echo $reseller_count; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
