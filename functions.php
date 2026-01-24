<?php
/**
 * Prevent direct access
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Theme Name: Unico
 * Theme URI: https://example.com/unico-theme
 * Author: Your Name
 * Author URI: https://example.com
 * Description: Voucher Booking System Theme
 * Version: 1.0
 * Text Domain: unico
 */

if (!defined('UNICO_DEV_MODE')) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        define('UNICO_DEV_MODE', true);
    } else {
        define('UNICO_DEV_MODE', false);
    }
}

if (!defined('UNICO_SOFT_LOCK_ENABLED')) {
    define('UNICO_SOFT_LOCK_ENABLED', true);
}
if (!defined('UNICO_SOFT_LOCK_THRESHOLD')) {
    define('UNICO_SOFT_LOCK_THRESHOLD', 5);
}
if (!defined('UNICO_SOFT_LOCK_MINUTES')) {
    define('UNICO_SOFT_LOCK_MINUTES', 15);
}

// Include Mail Settings
require_once get_template_directory() . '/includes/class-unico-mail-settings.php';

function unico_get_required_pages() {
    return array(
        'home' => array('title' => 'Home', 'template' => 'front-page.php'),
        'student-dashboard' => array('title' => 'Student Dashboard', 'template' => 'page-student-dashboard.php'),
        'customer-dashboard' => array('title' => 'Customer Dashboard', 'template' => 'page-customer-dashboard.php'),
        'agent-dashboard' => array('title' => 'Agent Dashboard', 'template' => 'page-agent-dashboard.php'),
        'reseller-dashboard' => array('title' => 'Reseller Dashboard', 'template' => 'page-reseller-dashboard.php'),
        'support-dashboard' => array('title' => 'Support Dashboard', 'template' => 'page-support-dashboard.php'),
        'finance-dashboard' => array('title' => 'Finance Dashboard', 'template' => 'page-finance-dashboard.php'),
        'management-dashboard' => array('title' => 'Management Dashboard', 'template' => 'page-management-dashboard.php'),
        'student-application-form' => array('title' => 'Student Application Form', 'template' => 'page-student-application-form.php'),
        'agent-application-form' => array('title' => 'Agent Application Form', 'template' => 'page-agent-application-form.php'),
        'study-abroad' => array('title' => 'Study Abroad', 'template' => 'page-study-abroad.php'),
        'support' => array('title' => 'Support', 'template' => 'page-support.php'),
        'login' => array('title' => 'Login', 'template' => 'page-login.php'),
        'register' => array('title' => 'Register', 'template' => 'page-register.php'),
        'forgot-password' => array('title' => 'Forgot Password', 'template' => 'page-forgot-password.php'),
        'reset-password' => array('title' => 'Reset Password', 'template' => 'page-reset-password.php'),
        'about-us' => array('title' => 'About Us', 'template' => 'page-about-us.php')
    );
}

function unico_sync_required_pages() {
    $required_pages = unico_get_required_pages();
    foreach ($required_pages as $slug => $page_data) {
        $page = get_page_by_path($slug);
        if (!$page) {
            $existing_by_template = get_pages(array(
                'meta_key' => '_wp_page_template',
                'meta_value' => $page_data['template'],
                'post_type' => 'page',
                'post_status' => 'any'
            ));
            if (!empty($existing_by_template)) {
                $page = $existing_by_template[0];
            }
        }
        if ($page) {
            $update = array('ID' => $page->ID);
            if ($page->post_title !== $page_data['title']) {
                $update['post_title'] = $page_data['title'];
            }
            if ($page->post_name !== $slug) {
                $update['post_name'] = $slug;
            }
            if (count($update) > 1) {
                wp_update_post($update);
            }
            update_post_meta($page->ID, '_wp_page_template', $page_data['template']);
        } else {
            $new_page = array(
                'post_title' => $page_data['title'],
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'page_template' => $page_data['template']
            );
            $page_id = wp_insert_post($new_page);
            if ($page_id && !is_wp_error($page_id)) {
                update_post_meta($page_id, '_wp_page_template', $page_data['template']);
            }
        }
    }
}

function unico_get_home_page_id() {
    $page = get_page_by_path('home');
    if ($page) {
        return (int) $page->ID;
    }
    $front_id = (int) get_option('page_on_front');
    if ($front_id > 0) {
        return $front_id;
    }
    $pages = get_pages(array('number' => 1));
    if (!empty($pages)) {
        return (int) $pages[0]->ID;
    }
    return 0;
}

function unico_get_required_plugins() {
    return array();
}

function unico_dev_sync_required_pages() {
    if (!UNICO_DEV_MODE) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    unico_sync_required_pages();
}
add_action('init', 'unico_dev_sync_required_pages');

/* --------------------------------------------------
 * LOAD VOUCHER BOOKING SYSTEM
 * -------------------------------------------------- */
require_once get_template_directory() . '/includes/class-init.php';
require_once get_template_directory() . '/includes/class-smtp-settings.php'; // Load SMTP Settings

// Run Product Seeder (Temporary)
require_once get_template_directory() . '/includes/seeder.php';

require_once get_template_directory() . '/includes/unico-vc-bootstrap.php';
 
 

function unico_template_override($template) {
    if (is_page()) {
        $pages = unico_get_required_pages();
        $current = get_queried_object();
        if ($current && isset($current->post_name) && isset($pages[$current->post_name])) {
            $file = $pages[$current->post_name]['template'];
            $path = get_template_directory() . '/' . $file;
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    return $template;
}
add_filter('template_include', 'unico_template_override');

function unico_force_dashboard_templates() {
    if (is_admin()) {
        return;
    }
    $request_path = trim(parse_url(add_query_arg(array()), PHP_URL_PATH), '/');
    
    $routes = [
        'study-abroad'             => 'page-study-abroad.php',
        'student-application-form' => 'page-student-application-form.php',
        'agent-application-form'   => 'page-agent-application-form.php',
        'about-us'                 => 'page-about-us.php',
        'management-dashboard'     => 'page-management-dashboard.php',
        'agent-dashboard'          => 'page-agent-dashboard.php',
        'student-dashboard'        => 'page-student-dashboard.php',
        'customer-dashboard'       => 'page-customer-dashboard.php',
        'reseller-dashboard'       => 'page-reseller-dashboard.php',
        'support-dashboard'        => 'page-support-dashboard.php',
        'finance-dashboard'        => 'page-finance-dashboard.php',
        'login'                    => 'page-login.php',
        'register'                 => 'page-register.php',
        'support'                  => 'page-support.php',
    ];

    if (array_key_exists($request_path, $routes)) {
        $template = get_template_directory() . '/' . $routes[$request_path];
        if (file_exists($template)) {
            // Set basic query vars to prevent 404s if WP thinks page doesn't exist
            global $wp_query;
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            status_header(200);
            
            include $template;
            exit;
        }
    }
}
add_action('template_redirect', 'unico_force_dashboard_templates', 0);

/* --------------------------------------------------
 * PAGE MANAGEMENT
 * -------------------------------------------------- */
// Run on theme activation and admin pages
add_action('after_switch_theme', 'unico_check_pages');
add_action('admin_init', 'unico_check_pages');

function unico_check_pages() {
    $current = current_filter();
    if ($current === 'after_switch_theme') {
        unico_sync_required_pages();
        $home_id = unico_get_home_page_id();
        if ($home_id > 0) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $home_id);
        }
    } elseif ($current === 'admin_init') {
        unico_sync_required_pages();
    }
}

/* --------------------------------------------------
 * ASSETS
 * -------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        'unico-header',
        get_template_directory_uri() . '/assets/css/header.css',
        [],
        '3.2'
    );

    wp_enqueue_script(
        'unico-header-js',
        get_template_directory_uri() . '/assets/js/header.js',
        [],
        '1.0',
        true
    );

    wp_enqueue_style(
        'unico-home',
        get_template_directory_uri() . '/assets/css/home.css',
        [],
        '3.2'
    );

    wp_enqueue_style(
        'unico-footer',
        get_template_directory_uri() . '/assets/css/footer.css',
        [],
        '3.2'
    );

    // Dashboard styles
    if (is_page(['customer-dashboard', 'agent-dashboard', 'reseller-dashboard', 'support-dashboard', 'finance-dashboard', 'management-dashboard'])) {
        wp_enqueue_style(
            'unico-dashboard',
            get_template_directory_uri() . '/assets/css/dashboard.css',
            [],
            '1.0'
        );
    }

    $request_path = trim(parse_url(add_query_arg([]), PHP_URL_PATH), '/');

    if (is_page('management-dashboard') || $request_path === 'management-dashboard') {
        wp_enqueue_style(
            'unico-management-dashboard',
            get_template_directory_uri() . '/assets/css/management-dashboard.css',
            [],
            '1.0'
        );
        wp_enqueue_script(
            'unico-management-dashboard-js',
            get_template_directory_uri() . '/assets/js/management-dashboard.js',
            [],
            '1.0',
            true
        );
    }

    if (is_page('about-us')) {
        wp_enqueue_style(
            'unico-about',
            get_template_directory_uri() . '/assets/css/about.css',
            [],
            '1.0'
        );
    }

    // Vouchers Page Styles
    if (is_page('vouchers') || is_page_template('page-vouchers.php')) {
        wp_enqueue_style(
            'unico-vouchers',
            get_template_directory_uri() . '/assets/css/vouchers.css',
            ['unico-header', 'unico-footer'],
            '1.0'
        );
    }
});




/* --------------------------------------------------
 * CUSTOM LOGIN HANDLER
 * -------------------------------------------------- */
add_action('init', function () {

    // Only handle our login form
    if ( ! isset($_POST['unicou_login']) ) {
        return;
    }

    if (
        ! isset($_POST['unicou_login_nonce']) ||
        ! wp_verify_nonce($_POST['unicou_login_nonce'], 'unicou_login_action')
    ) {
        wp_die('Security check failed');
    }

    $creds = [
        'user_login'    => sanitize_user($_POST['user_email']),
        'user_password' => $_POST['user_password'],
        'remember'      => true,
    ];

    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        $code = $user->get_error_code();
        if ($code === 'unico_soft_locked') {
            wp_redirect(home_url('/support?login=locked'));
        } else {
            wp_redirect(home_url('/login?login=failed'));
        }
        exit;
    }

    $banned = get_user_meta($user->ID, 'unico_banned', true);
    if ($banned) {
        wp_logout();
        wp_redirect(home_url('/login?login=blocked'));
        exit;
    }

    if (in_array('administrator', $user->roles)) {
        wp_redirect(admin_url());
    } else {
        wp_redirect(home_url('/'));
    }
    exit;
});


/* --------------------------------------------------
 * CUSTOM REGISTER HANDLER
 * -------------------------------------------------- */
add_action('init', function () {

    if (!isset($_POST['unicou_register'])) {
        return;
    }

    wp_redirect(home_url('/student-application-form'));
    exit;
});


/* --------------------------------------------------
 * FORCE CUSTOM LOGIN PAGE (NO LOOPS)
 * -------------------------------------------------- */
// Tell WordPress to use custom login page (but allow wp-login.php for admins)
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    // If redirect is to admin area, keep default wp-login.php
    if ($redirect && strpos($redirect, 'wp-admin') !== false) {
        return $login_url;
    }
    // Otherwise use custom login page
    return home_url('/login');
}, 10, 3);


/* --------------------------------------------------
 * LOGIN REDIRECT (for wp-login.php)
 * -------------------------------------------------- */
add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {
    // If there's an error or no user, return default
    if (!isset($user->ID) || is_wp_error($user)) {
        return $redirect_to;
    }

    // Administrators go to wp-admin
    if (in_array('administrator', $user->roles)) {
        return admin_url();
    }

    // Other users go to the home page
    return home_url('/');
}, 10, 3);


/* --------------------------------------------------
 * LOGOUT REDIRECT
 * -------------------------------------------------- */
add_filter('logout_redirect', function () {
    return home_url('/login');
});


add_action('show_user_profile', function ($user) {
    $verified = get_user_meta($user->ID, 'email_verified', true);
    $verified_at = get_user_meta($user->ID, 'email_verified_at', true);
    ?>
    <h2>Email Verification</h2>
    <table class="form-table">
        <tr>
            <th><label for="unico_email_verified">Email Verified</label></th>
            <td>
                <label>
                    <input type="checkbox" name="unico_email_verified" id="unico_email_verified" value="1" <?php checked($verified, 1); ?> />
                    Mark this email as verified
                </label>
                <?php if ($verified_at): ?>
                    <p class="description">Verified at: <?php echo esc_html($verified_at); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
});

add_action('edit_user_profile', function ($user) {
    $verified = get_user_meta($user->ID, 'email_verified', true);
    $verified_at = get_user_meta($user->ID, 'email_verified_at', true);
    ?>
    <h2>Email Verification</h2>
    <table class="form-table">
        <tr>
            <th><label for="unico_email_verified">Email Verified</label></th>
            <td>
                <label>
                    <input type="checkbox" name="unico_email_verified" id="unico_email_verified" value="1" <?php checked($verified, 1); ?> />
                    Mark this email as verified
                </label>
                <?php if ($verified_at): ?>
                    <p class="description">Verified at: <?php echo esc_html($verified_at); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
});

add_action('personal_options_update', function ($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    $value = isset($_POST['unico_email_verified']) ? 1 : 0;
    update_user_meta($user_id, 'email_verified', $value);
    if ($value === 1 && !get_user_meta($user_id, 'email_verified_at', true)) {
        update_user_meta($user_id, 'email_verified_at', current_time('mysql'));
    }
});

add_action('edit_user_profile_update', function ($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    $value = isset($_POST['unico_email_verified']) ? 1 : 0;
    update_user_meta($user_id, 'email_verified', $value);
    if ($value === 1 && !get_user_meta($user_id, 'email_verified_at', true)) {
        update_user_meta($user_id, 'email_verified_at', current_time('mysql'));
    }
});

add_action('template_redirect', function () {
    if (!isset($_GET['unico_buy_now'])) {
        return;
    }
    $product_id = absint($_GET['unico_buy_now']);
    if ($product_id <= 0) {
        return;
    }
    wp_redirect(add_query_arg('product_id', $product_id, home_url('/checkout/')));
    exit;
}, 0);


/* --------------------------------------------------
 * OPTIONAL: PROTECT DASHBOARD PAGE
 * -------------------------------------------------- */
add_action('template_redirect', function () {

    if (is_page('dashboard') && ! is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }
});


/* --------------------------------------------------
 * PROTECT ROLE-BASED DASHBOARDS
 * Redirect to login if not logged in, otherwise allow access
 * -------------------------------------------------- */
add_action('template_redirect', function () {
    $dashboard_pages = array(
        'customer-dashboard',
        'agent-dashboard',
        'reseller-dashboard',
        'support-dashboard',
        'finance-dashboard',
        'management-dashboard',
        'student-dashboard'
    );

    // Check if current page is a dashboard
    $current_page = get_queried_object();
    $is_dashboard = false;

    if ($current_page && isset($current_page->post_name)) {
        $is_dashboard = in_array($current_page->post_name, $dashboard_pages);
    }

    if (!$is_dashboard) {
        // Also check by request path for forced templates
        $request_path = trim(parse_url(add_query_arg([]), PHP_URL_PATH), '/');
        $is_dashboard = in_array($request_path, $dashboard_pages);
    }

    // If it's a dashboard page and user is not logged in, redirect to login
    if ($is_dashboard && !is_user_logged_in()) {
        wp_redirect(home_url('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
        exit;
    }
});

function unico_get_destination_page_url($key)
{
    $map = array(
        'uk' => array(
            'slug' => 'study-in-uk',
            'title' => 'The United Kingdom',
        ),
        'australia' => array(
            'slug' => 'study-in-australia',
            'title' => 'Australia',
        ),
        'usa' => array(
            'slug' => 'study-in-usa',
            'title' => 'USA',
        ),
        'canada' => array(
            'slug' => 'study-in-canada',
            'title' => 'Canada',
        ),
        'new-zealand' => array(
            'slug' => 'study-in-new-zealand',
            'title' => 'New Zealand',
        ),
        'ireland' => array(
            'slug' => 'study-in-ireland',
            'title' => 'Ireland',
        ),
        'germany' => array(
            'slug' => 'study-in-germany',
            'title' => 'Germany',
        ),
        'sweden' => array(
            'slug' => 'study-in-sweden',
            'title' => 'Sweden',
        ),
        'finland' => array(
            'slug' => 'study-in-finland',
            'title' => 'Finland',
        ),
        'cyprus' => array(
            'slug' => 'study-in-cyprus',
            'title' => 'Cyprus',
        ),
        'dubai' => array(
            'slug' => 'study-in-dubai',
            'title' => 'Dubai',
        ),
        'malaysia' => array(
            'slug' => 'study-in-malaysia',
            'title' => 'Malaysia',
        ),
        'turkey' => array(
            'slug' => 'study-in-turkey',
            'title' => 'Turkey',
        ),
        'europe' => array(
            'slug' => 'study-in-europe',
            'title' => 'Europe',
        ),
        'italy' => array(
            'slug' => 'study-in-italy',
            'title' => 'Italy',
        ),
    );

    if (!isset($map[$key])) {
        return home_url('/');
    }

    $config = $map[$key];

    if (!empty($config['slug'])) {
        $page = get_page_by_path($config['slug']);
        if ($page) {
            return get_permalink($page->ID);
        }
    }

    if (!empty($config['title'])) {
        $page = get_page_by_title($config['title']);
        if ($page) {
            return get_permalink($page->ID);
        }
    }

    return home_url('/');
}

function unico_get_ticket_details_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }

    $current_user = wp_get_current_user();
    $user_id = $current_user ? (int) $current_user->ID : 0;

    if (!class_exists('Unico_User_Roles')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    if (!Unico_User_Roles::user_can('access_support_dashboard') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $ticket_id = isset($_GET['ticket_id']) ? (int) $_GET['ticket_id'] : 0;
    if (!$ticket_id && isset($_POST['ticket_id'])) {
        $ticket_id = (int) $_POST['ticket_id'];
    }

    if ($ticket_id <= 0) {
        wp_send_json_error(['message' => 'Invalid ticket'], 400);
    }

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'unico_support_tickets';
    $replies_table = $wpdb->prefix . 'unico_ticket_replies';

    $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tickets_table} WHERE id = %d", $ticket_id));

    if (!$ticket) {
        wp_send_json_error(['message' => 'Ticket not found'], 404);
    }

    $customer = get_userdata($ticket->user_id);
    $customer_name = $customer ? $customer->display_name : 'Unknown';
    $customer_email = $customer ? $customer->user_email : '';

    $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$replies_table} WHERE ticket_id = %d ORDER BY created_at ASC", $ticket_id));

    if (class_exists('Unico_Security') && $user_id) {
        $security = Unico_Security::get_instance();
        $security->log_activity($user_id, 'ticket_viewed', 'Support ticket viewed', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket->ticket_number
        ]);
    }

    ob_start();
    ?>
    <div class="ticket-details-wrapper">
        <div class="ticket-details-header">
            <div>
                <h3><?php echo esc_html($ticket->subject); ?></h3>
                <p>Ticket #<?php echo esc_html($ticket->ticket_number); ?></p>
            </div>
            <div>
                <span><?php echo esc_html(ucfirst($ticket->priority)); ?></span>
                <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $ticket->status))); ?></span>
            </div>
        </div>
        <div class="ticket-details-meta">
            <p><strong>Customer:</strong> <?php echo esc_html($customer_name); ?></p>
            <?php if ($customer_email): ?>
                <p><strong>Email:</strong> <?php echo esc_html($customer_email); ?></p>
            <?php endif; ?>
            <p><strong>Created:</strong> <?php echo esc_html(date('M j, Y H:i', strtotime($ticket->created_at))); ?></p>
            <?php if ($ticket->last_reply_at): ?>
                <p><strong>Last Reply:</strong> <?php echo esc_html(date('M j, Y H:i', strtotime($ticket->last_reply_at))); ?></p>
            <?php endif; ?>
        </div>
        <div class="ticket-details-body">
            <h4>Original Message</h4>
            <div>
                <?php echo nl2br(esc_html($ticket->message)); ?>
            </div>
        </div>
        <?php if (!empty($replies)): ?>
            <div class="ticket-details-replies">
                <h4>Replies</h4>
                <?php foreach ($replies as $reply): ?>
                    <div class="ticket-reply">
                        <div class="ticket-reply-meta">
                            <?php
                            $reply_user = get_userdata($reply->user_id);
                            $reply_name = $reply_user ? $reply_user->display_name : 'Staff';
                            ?>
                            <span><?php echo esc_html($reply_name); ?></span>
                            <span><?php echo esc_html(date('M j, Y H:i', strtotime($reply->created_at))); ?></span>
                        </div>
                        <div class="ticket-reply-message">
                            <?php echo nl2br(esc_html($reply->message)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_get_ticket_details', 'unico_get_ticket_details_ajax');
