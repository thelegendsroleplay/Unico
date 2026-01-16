<?php
/**
 * Theme Functions
 * Unico Voucher Booking System
 */

/* --------------------------------------------------
 * LOAD VOUCHER BOOKING SYSTEM
 * -------------------------------------------------- */
require_once get_template_directory() . '/includes/class-init.php';

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

    wp_enqueue_script(
        'unico-footer-js',
        get_template_directory_uri() . '/assets/js/footer.js',
        [],
        '1.0',
        true
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
        wp_redirect(home_url('/login?login=failed'));
        exit;
    }

    // Administrators go to wp-admin, others to role-based dashboards
    if (in_array('administrator', $user->roles)) {
        wp_redirect(admin_url());
    } else {
        $dashboard_url = Unico_User_Roles::get_dashboard_url($user->ID);
        wp_redirect($dashboard_url);
    }
    exit;
});


/* --------------------------------------------------
 * CUSTOM REGISTER HANDLER
 * -------------------------------------------------- */
add_action('init', function () {

    // Only handle our register form
    if ( ! isset($_POST['unicou_register']) ) {
        return;
    }

    if (
        ! isset($_POST['unicou_register_nonce']) ||
        ! wp_verify_nonce($_POST['unicou_register_nonce'], 'unicou_register_action')
    ) {
        wp_die('Security check failed');
    }

    $email = sanitize_email($_POST['email']);
    $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';
    $user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : 'unico_customer';

    if ( ! is_email($email) || email_exists($email) ) {
        wp_redirect(home_url('/register?register=failed&error=email_exists'));
        exit;
    }

    // Create user with provided password or generate one
    $password = !empty($_POST['password']) ? $_POST['password'] : wp_generate_password(12, true);

    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        wp_redirect(home_url('/register?register=failed&error=creation_failed'));
        exit;
    }

    // Set user meta
    if (!empty($full_name)) {
        $name_parts = explode(' ', $full_name, 2);
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $name_parts[0],
            'last_name' => isset($name_parts[1]) ? $name_parts[1] : '',
            'display_name' => $full_name
        ]);
    }

    // Assign role
    $user = new WP_User($user_id);
    $user->set_role($user_type);

    // Additional meta for phone if provided
    if (isset($_POST['phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['phone']));
    }

    // The Unico_Security class will handle email verification automatically via user_register hook

    wp_redirect(home_url('/login?registered=true&verify_email=1'));
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

    // Other users go to their role-based dashboard
    return Unico_User_Roles::get_dashboard_url($user->ID);
}, 10, 3);


/* --------------------------------------------------
 * LOGOUT REDIRECT
 * -------------------------------------------------- */
add_filter('logout_redirect', function () {
    return home_url('/login');
});


/* --------------------------------------------------
 * OPTIONAL: PROTECT DASHBOARD PAGE
 * -------------------------------------------------- */
add_action('template_redirect', function () {

    if (is_page('dashboard') && ! is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }
});
