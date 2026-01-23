<?php
/**
 * Template Name: Student Dashboard
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Fetch recent orders
$student_orders = [];
if (class_exists('Unico_Order')) {
    $student_orders = Unico_Order::get_orders([
        'user_id' => $user_id,
        'limit' => 5,
        'orderby' => 'created_at',
        'order' => 'DESC'
    ]);
}

// Check if user can access student dashboard
// We check for 'access_student_dashboard' capability
// If the role doesn't exist yet, admins can still access
if (class_exists('Unico_User_Roles') && !Unico_User_Roles::user_can('access_student_dashboard') && !current_user_can('administrator')) {
    wp_die(
        '<h1>Access Denied</h1><p>You do not have permission to access the Student Dashboard.</p><p><a href="' . home_url() . '">Return to Home</a></p>',
        'Access Denied',
        array('response' => 403)
    );
}

// Get security instance
$security = class_exists('Unico_Security') ? Unico_Security::get_instance() : null;
$is_verified = $security ? $security->is_email_verified($user_id) : true;

get_header();
?>

<style>
    /* Dashboard specific styles */
    body { background: #f8f9fa !important; color: #4a4a4a; }
    
    .dashboard-container { 
        max-width: 1600px; 
        margin: 0 auto; 
        padding: 40px 20px; 
        min-height: 100vh; 
        position: relative;
        z-index: 10;
        display: block !important; 
        background: #f8f9fa;
    }
    
    .dashboard-header { 
        background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%); 
        color: white; 
        padding: 30px; 
        border-radius: 12px; 
        margin-bottom: 30px; 
        box-shadow: 0 4px 20px rgba(44, 62, 80, 0.3); 
        margin-top: 20px;
    }
    
    .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
    .header-title h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; color: white; }
    .header-title p { opacity: 0.9; font-size: 14px; }
    
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .dashboard-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); transition: transform 0.2s; }
    .dashboard-card:hover { transform: translateY(-5px); }
    .card-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #2c3e50; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; }
    
    .info-item { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
    .info-label { color: #7f8c8d; }
    .info-value { font-weight: 600; color: #2c3e50; }
    
    .action-btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; transition: background 0.2s; }
    .action-btn:hover { background: #2980b9; color: white; }
    
    .verification-banner { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; }
    .verification-content h3 { font-size: 16px; margin-bottom: 5px; color: #856404; }
    .verification-content p { font-size: 14px; color: #856404; margin: 0; }
    
    @media (max-width: 768px) {
        .dashboard-container { padding: 20px 15px; }
        .dashboard-header { padding: 20px; }
        .header-title h1 { font-size: 24px; }
    }
    
    /* Orders Table */
    .section-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
    .section-header { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; }
    .orders-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    .orders-table th { text-align: left; padding: 12px; background: #f8f9fa; color: #7f8c8d; font-size: 13px; font-weight: 600; }
    .orders-table td { padding: 15px 12px; border-bottom: 1px solid #eee; color: #444; font-size: 14px; }
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-processing { background: #fff3cd; color: #856404; }
    .status-cancelled, .status-failed { background: #f8d7da; color: #721c24; }
    .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
    .empty-state-icon { font-size: 40px; margin-bottom: 15px; }
</style>

<div class="dashboard-container">
    
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Student Dashboard</h1>
                <p>Welcome back, <?php echo esc_html($current_user->display_name); ?></p>
            </div>
            <div class="header-actions">
                <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-size: 13px;">
                    Student ID: <?php echo esc_html($user_id); ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (!$is_verified): ?>
    <div class="verification-banner">
        <div class="verification-content">
            <h3>Email Verification Required</h3>
            <p>Please verify your email address to access all student features.</p>
        </div>
        <a href="<?php echo home_url('/email-verification'); ?>" class="action-btn" style="background: #ffc107; color: #000;">Verify Now</a>
    </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Profile Card -->
        <div class="dashboard-card">
            <h3 class="card-title">My Profile</h3>
            <div class="info-item">
                <span class="info-label">Name</span>
                <span class="info-value"><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo esc_html($current_user->user_email); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value"><?php echo $is_verified ? '<span style="color:green">Active</span>' : '<span style="color:orange">Pending Verification</span>'; ?></span>
            </div>
            <div style="margin-top: 20px;">
                <a href="<?php echo home_url('/profile'); ?>" class="action-btn">Edit Profile</a>
            </div>
        </div>

        <!-- Applications Card -->
        <div class="dashboard-card">
            <h3 class="card-title">My Applications</h3>
            <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 20px;">Track your university applications and status.</p>
            
            <?php
            global $wpdb;
            $submissions_table = $wpdb->prefix . 'unico_form_submissions';
            $user_email_search = '%' . $wpdb->esc_like($current_user->user_email) . '%';
            
            $my_applications = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $submissions_table WHERE user_id = %d OR form_data LIKE %s ORDER BY created_at DESC",
                $user_id,
                $user_email_search
            ));
            
            $active_count = 0;
            if ($my_applications) {
                foreach ($my_applications as $app) {
                    if (!in_array($app->status, ['rejected', 'cancelled'])) {
                        $active_count++;
                    }
                }
            }
            ?>
            <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                <span style="display: block; font-size: 24px; margin-bottom: 5px;"><?php echo intval($active_count); ?></span>
                <span style="font-size: 13px; color: #7f8c8d;">Active Applications</span>
            </div>
            
            <?php if (!empty($my_applications)): ?>
                <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                    <?php foreach (array_slice($my_applications, 0, 3) as $app): 
                        $status_color = '#3498db'; // Default/In Review
                        if ($app->status === 'approved') $status_color = '#2ecc71';
                        elseif ($app->status === 'rejected') $status_color = '#e74c3c';
                        elseif ($app->status === 'submitted') $status_color = '#f39c12';
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px;">
                            <div>
                                <div style="font-weight: 600;"><?php echo esc_html($app->submission_number); ?></div>
                                <div style="color: #95a5a6; font-size: 11px;"><?php echo date_i18n('M j, Y', strtotime($app->created_at)); ?></div>
                            </div>
                            <span style="background: <?php echo $status_color; ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; text-transform: uppercase;">
                                <?php echo esc_html(str_replace('_', ' ', $app->status)); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <a href="<?php echo home_url('/student-application-form'); ?>" class="action-btn" style="width: 100%; text-align: center;">New Application</a>
        </div>

        <!-- Resources Card -->
        <div class="dashboard-card">
            <h3 class="card-title">Student Resources</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo home_url('/study-abroad'); ?>" style="text-decoration: none; color: #3498db; display: flex; align-items: center; font-size: 14px;">
                        <span style="margin-right: 10px;">📚</span> Study Abroad Guide
                    </a>
                </li>
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo home_url('/vouchers'); ?>" style="text-decoration: none; color: #3498db; display: flex; align-items: center; font-size: 14px;">
                        <span style="margin-right: 10px;">🎟️</span> Exam Vouchers
                    </a>
                </li>
                <li>
                    <a href="<?php echo home_url('/support'); ?>" style="text-decoration: none; color: #3498db; display: flex; align-items: center; font-size: 14px;">
                        <span style="margin-right: 10px;">❓</span> Student Support
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="section-card">
        <div class="section-header">Recent Orders</div>
        <div class="section-body">
            <?php if (!empty($student_orders)): ?>
            <div style="overflow-x: auto;">
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
                        <?php foreach ($student_orders as $order): 
                            $date_created = $order->get_date_created();
                            $date_formatted = $date_created ? date('M j, Y', strtotime($date_created)) : '-';
                        ?>
                        <tr>
                            <td><strong><?php echo $order->get_order_number(); ?></strong></td>
                            <td><?php echo $date_formatted; ?></td>
                            <td><?php echo $order->get_formatted_total(); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($order->get_status()); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('-', ' ', $order->get_status()))); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo home_url('/view-order?id=' . $order->get_id()); ?>" class="action-btn" style="padding: 5px 10px; font-size: 12px;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders yet.</p>
                <a href="<?php echo home_url('/vouchers'); ?>" class="action-btn" style="margin-top: 15px;">Browse Vouchers</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php get_footer(); ?>
