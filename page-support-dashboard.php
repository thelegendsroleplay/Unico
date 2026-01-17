<?php
/**
 * Template Name: Support Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user can access support dashboard
if (!Unico_User_Roles::user_can('access_support_dashboard') && !current_user_can('administrator')) {
    wp_redirect(Unico_User_Roles::get_dashboard_url($user_id));
    exit;
}

// Get ticket data
global $wpdb;
$tickets_table = $wpdb->prefix . 'unico_support_tickets';
$replies_table = $wpdb->prefix . 'unico_ticket_replies';

// Handle ticket actions
if (isset($_POST['action']) && wp_verify_nonce($_POST['support_nonce'], 'support_action')) {
    if ($_POST['action'] === 'update_status' && isset($_POST['ticket_id'])) {
        $ticket_id = intval($_POST['ticket_id']);
        $new_status = sanitize_text_field($_POST['status']);

        $wpdb->update($tickets_table, [
            'status' => $new_status,
            'updated_at' => current_time('mysql')
        ], ['id' => $ticket_id]);
    }

    if ($_POST['action'] === 'add_reply' && isset($_POST['ticket_id'])) {
        $ticket_id = intval($_POST['ticket_id']);
        $message = sanitize_textarea_field($_POST['message']);

        $wpdb->insert($replies_table, [
            'ticket_id' => $ticket_id,
            'user_id' => $user_id,
            'message' => $message,
            'is_staff_reply' => 1,
            'created_at' => current_time('mysql')
        ]);

        // Update ticket last reply time
        $wpdb->update($tickets_table, [
            'last_reply_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ], ['id' => $ticket_id]);

        // Send notification to customer
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tickets_table WHERE id = %d", $ticket_id));
        if ($ticket) {
            $customer = get_userdata($ticket->user_id);
            if ($customer) {
                wp_mail(
                    $customer->user_email,
                    'New Reply to Your Support Ticket #' . $ticket->ticket_number,
                    "You have received a new reply to your support ticket.\n\nTicket: {$ticket->subject}\n\nPlease login to view the reply."
                );
            }
        }
    }
}

// Get filter status
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$filter_priority = isset($_GET['priority']) ? sanitize_text_field($_GET['priority']) : 'all';

// Build query
$where = "1=1";
if ($filter_status !== 'all') {
    $where .= $wpdb->prepare(" AND status = %s", $filter_status);
}
if ($filter_priority !== 'all') {
    $where .= $wpdb->prepare(" AND priority = %s", $filter_priority);
}

// Get tickets
$tickets = $wpdb->get_results("SELECT * FROM $tickets_table WHERE $where ORDER BY created_at DESC LIMIT 50");

// Get statistics
$total_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table");
$open_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'open'");
$in_progress_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'in_progress'");
$closed_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'closed'");
$my_assigned = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tickets_table WHERE assigned_to = %d AND status != 'closed'", $user_id));

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Dashboard - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #4a4a4a; }
        .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        .dashboard-header { background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(111, 66, 193, 0.3); }
        .header-title h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .header-title p { opacity: 0.9; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12); }
        .stat-icon { font-size: 32px; margin-bottom: 10px; }
        .stat-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #6f42c1; }
        .section-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-bottom: 30px; }
        .section-header { background: #6f42c1; color: white; padding: 20px 25px; font-size: 18px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .section-body { padding: 25px; }
        .filters { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 10px 20px; border-radius: 8px; border: 2px solid #e9ecef; background: white; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .filter-btn:hover { border-color: #6f42c1; color: #6f42c1; }
        .filter-btn.active { background: #6f42c1; color: white; border-color: #6f42c1; }
        .tickets-table { width: 100%; border-collapse: collapse; }
        .tickets-table th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; font-size: 14px; color: #6c757d; text-transform: uppercase; }
        .tickets-table td { padding: 15px 12px; border-bottom: 1px solid #e9ecef; }
        .ticket-row { cursor: pointer; transition: background 0.2s; }
        .ticket-row:hover { background: #f8f9fa; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-open { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #cfe2ff; color: #084298; }
        .status-closed { background: #d4edda; color: #155724; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-low { background: #d1ecf1; color: #0c5460; }
        .ticket-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; }
        .ticket-modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 12px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; margin: 20px; }
        .modal-header { background: #6f42c1; color: white; padding: 20px 25px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; }
        .close-modal { background: none; border: none; color: white; font-size: 28px; cursor: pointer; }
        .ticket-details { margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .ticket-replies { margin-top: 20px; }
        .reply { padding: 15px; margin-bottom: 15px; border-radius: 8px; }
        .reply.staff { background: #e7f3ff; border-left: 4px solid #0066cc; }
        .reply.customer { background: #f8f9fa; border-left: 4px solid #6c757d; }
        .reply-meta { font-size: 12px; color: #6c757d; margin-bottom: 8px; }
        .reply-message { line-height: 1.6; }
        .reply-form textarea { width: 100%; min-height: 100px; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-family: inherit; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #6f42c1; color: white; }
        .btn-primary:hover { background: #563d7c; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state-icon { font-size: 64px; opacity: 0.3; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-title">
            <h1>Welcome, <?php echo esc_html($current_user->display_name); ?></h1>
            <p>Support Dashboard - Manage customer tickets and inquiries</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-label">Total Tickets</div>
            <div class="stat-value"><?php echo $total_tickets; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔓</div>
            <div class="stat-label">Open</div>
            <div class="stat-value"><?php echo $open_tickets; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-label">In Progress</div>
            <div class="stat-value"><?php echo $in_progress_tickets; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Closed</div>
            <div class="stat-value"><?php echo $closed_tickets; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-label">Assigned to Me</div>
            <div class="stat-value"><?php echo $my_assigned; ?></div>
        </div>
    </div>

    <!-- Tickets Section -->
    <div class="section-card">
        <div class="section-header">
            <span>Support Tickets</span>
        </div>
        <div class="section-body">

            <!-- Filters -->
            <div class="filters">
                <a href="?status=all" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?status=open" class="filter-btn <?php echo $filter_status === 'open' ? 'active' : ''; ?>">Open</a>
                <a href="?status=in_progress" class="filter-btn <?php echo $filter_status === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                <a href="?status=closed" class="filter-btn <?php echo $filter_status === 'closed' ? 'active' : ''; ?>">Closed</a>
            </div>

            <?php if (!empty($tickets)): ?>
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Reply</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket):
                        $customer = get_userdata($ticket->user_id);
                        $customer_name = $customer ? $customer->display_name : 'Unknown';
                    ?>
                    <tr class="ticket-row" onclick="openTicket(<?php echo $ticket->id; ?>)">
                        <td><strong><?php echo esc_html($ticket->ticket_number); ?></strong></td>
                        <td><?php echo esc_html($customer_name); ?></td>
                        <td><?php echo esc_html($ticket->subject); ?></td>
                        <td>
                            <span class="status-badge priority-<?php echo esc_attr($ticket->priority); ?>">
                                <?php echo esc_html(ucfirst($ticket->priority)); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($ticket->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $ticket->status))); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($ticket->created_at)); ?></td>
                        <td><?php echo $ticket->last_reply_at ? date('M j, Y', strtotime($ticket->last_reply_at)) : 'No replies'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎫</div>
                <h3>No Tickets Found</h3>
                <p>All tickets matching your filter will appear here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Ticket Modal -->
<div class="ticket-modal" id="ticketModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ticket Details</h2>
            <button class="close-modal" onclick="closeTicket()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="empty-state">
                <p>Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
function openTicket(ticketId) {
    const modal = document.getElementById('ticketModal');
    const modalBody = document.getElementById('modalBody');

    modal.classList.add('active');

    // Fetch ticket details via AJAX
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_ticket_details&ticket_id=' + ticketId)
        .then(response => response.json())
        .then(data => {
            modalBody.innerHTML = data.html;
        });
}

function closeTicket() {
    document.getElementById('ticketModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('ticketModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTicket();
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
