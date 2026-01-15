<?php
/**
 * Theme Functions
 */

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

    wp_redirect(home_url('/dashboard'));
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

    if ( ! is_email($email) || email_exists($email) ) {
        wp_redirect(home_url('/register?register=failed'));
        exit;
    }

    $password = wp_generate_password(12, true);

    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        wp_redirect(home_url('/register?register=failed'));
        exit;
    }

    wp_new_user_notification($user_id, null, 'both');

    wp_redirect(home_url('/login?registered=true'));
    exit;
});


/* --------------------------------------------------
 * FORCE CUSTOM LOGIN PAGE (NO LOOPS)
 * -------------------------------------------------- */
// Tell WordPress to use custom login page
add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    return home_url('/login');
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
