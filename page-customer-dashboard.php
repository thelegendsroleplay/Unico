<?php
/**
 * Template Name: Customer Dashboard
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user can access customer dashboard
if (!Unico_User_Roles::user_can('access_customer_dashboard') && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get security instance
$security = Unico_Security::get_instance();
$is_verified = $security->is_email_verified($user_id);

// Get recent orders
$customer_orders = wc_get_orders([
    'customer_id' => $user_id,
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get delivered vouchers
global $wpdb;
$vouchers_table = $wpdb->prefix . 'unico_vouchers';
$my_vouchers = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $vouchers_table WHERE assigned_to = %d AND voucher_status = 'delivered' ORDER BY delivered_at DESC LIMIT 10",
    $user_id
));

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8f9fa;
            color: #4a4a4a;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #103e54 0%, #1a5a7a 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(16, 62, 84, 0.3);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .header-title p {
            opacity: 0.9;
            font-size: 14px;
        }
        .verification-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .verification-banner strong {
            color: #856404;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #103e54;
        }
        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .section-header {
            background: #103e54;
            color: white;
            padding: 20px 25px;
            font-size: 18px;
            font-weight: 600;
        }
        .section-body {
            padding: 25px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #e95134;
            color: white;
        }
        .btn-primary:hover {
            background: #c43d2a;
        }
        .btn-outline {
            background: transparent;
            color: #103e54;
            border: 2px solid #103e54;
        }
        .btn-outline:hover {
            background: #103e54;
            color: white;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .orders-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
        }
        .orders-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-pending { background: #f8d7da; color: #721c24; }
        .voucher-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state-icon {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            .wallet-card {
                width: 100%;
                text-align: left;
            }
            .orders-table {
                font-size: 14px;
            }
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
                <p>Customer Dashboard</p>
            </div>
            <div>
                <a href="<?php echo home_url('/study-abroad'); ?>" class="btn btn-outline" style="color: white; border-color: white;">Book Counselling</a>
            </div>
        </div>
    </div>

    <!-- Email Verification Banner -->
    <?php if (!$is_verified): ?>
    <div class="verification-banner">
        <div>⚠️</div>
        <div>
            <strong>Email Verification Required</strong>
            <p style="margin: 5px 0 0 0; font-size: 14px;">Please check your email and verify your account to make purchases.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['verified']) && $_GET['verified'] === '1'): ?>
    <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px;">
        <strong style="color: #155724;">✓ Email Verified Successfully!</strong>
        <p style="margin: 5px 0 0 0; font-size: 14px; color: #155724;">You can now make purchases.</p>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?php echo count($customer_orders); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Vouchers Received</div>
            <div class="stat-value"><?php echo count($my_vouchers); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Account Status</div>
            <div class="stat-value" style="font-size: 20px;">
                <?php echo $is_verified ? '<span style="color: #28a745;">✓ Verified</span>' : '<span style="color: #ffc107;">Pending</span>'; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin-bottom: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo home_url('/shop'); ?>" class="btn btn-primary">Browse Vouchers</a>
        <a href="<?php echo home_url('/support'); ?>" class="btn btn-outline">Contact Support</a>
    </div>

    <!-- Recent Orders -->
    <div class="section-card">
        <div class="section-header">Recent Orders</div>
        <div class="section-body">
            <?php if (!empty($customer_orders)): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customer_orders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order->get_id(); ?></strong></td>
                        <td><?php echo $order->get_date_created()->format('M j, Y'); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
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
                <p>Start by browsing our available vouchers.</p>
                <a href="<?php echo home_url('/shop'); ?>" class="btn btn-primary" style="margin-top: 20px;">Browse Vouchers</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Vouchers -->
    <div class="section-card" style="margin-top: 30px;">
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
                <p>Your vouchers will appear here after purchase and delivery.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
