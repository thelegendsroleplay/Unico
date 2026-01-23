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

// Include Bank Accounts Management
require_once get_template_directory() . '/includes/class-bank-accounts.php';

// Initialize Bank Accounts System
add_action('init', function() {
    if (class_exists('Unico_Bank_Accounts')) {
        Unico_Bank_Accounts::get_instance();
    }
}, 5);



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
        'vouchers' => array('title' => 'Vouchers', 'template' => 'page-vouchers.php'),
        'student-application-form' => array('title' => 'Student Application Form', 'template' => 'page-student-application-form.php'),
        'agent-application-form' => array('title' => 'Agent Application Form', 'template' => 'page-agent-application-form.php'),
        'study-abroad' => array('title' => 'Study Abroad', 'template' => 'page-study-abroad.php'),
        'support' => array('title' => 'Support', 'template' => 'page-support.php'),
        'login' => array('title' => 'Login', 'template' => 'page-login.php'),
        'register' => array('title' => 'Register', 'template' => 'page-register.php'),
        'forgot-password' => array('title' => 'Forgot Password', 'template' => 'page-forgot-password.php'),
        'reset-password' => array('title' => 'Reset Password', 'template' => 'page-reset-password.php'),
        'checkout' => array('title' => 'Checkout', 'template' => 'page-checkout.php'),
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

/**
 * Format price with currency symbol
 */
function unico_format_price($amount, $currency = 'USD') {
    $symbol = '$';
    if ($currency === 'GBP') $symbol = '£';
    if ($currency === 'EUR') $symbol = '€';
    
    return $symbol . number_format((float)$amount, 2);
}

function unico_get_voucher_catalog_definitions() {
    return [
        [
            'slug' => 'ielts-ukvi-academic',
            'name' => 'IELTS UKVI Academic',
            'exam_family' => 'IELTS',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'ielts-ukvi-general-training',
            'name' => 'IELTS UKVI General Training',
            'exam_family' => 'IELTS',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'ielts-academic',
            'name' => 'IELTS Academic',
            'exam_family' => 'IELTS',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'ielts-general-training',
            'name' => 'IELTS General Training',
            'exam_family' => 'IELTS',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'ielts-life-skills',
            'name' => 'IELTS Life Skills',
            'exam_family' => 'IELTS',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'pte-academic',
            'name' => 'PTE Academic',
            'exam_family' => 'PTE',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'pte-academic-ukvi',
            'name' => 'PTE Academic UKVI',
            'exam_family' => 'PTE',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'pte-core',
            'name' => 'PTE Core',
            'exam_family' => 'PTE',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'pte-home-a1-a2-b1',
            'name' => 'PTE Home A1, A2, B1',
            'exam_family' => 'PTE',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'languagecert-selt-academic-b1-c2-slrw',
            'name' => 'LanguageCert SELT Academic B1-C2 (SLRW)',
            'exam_family' => 'LanguageCert',
            'price' => 149,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'languagecert-selt-general-training-a1-c1-slrw',
            'name' => 'LanguageCert SELT General Training A1-C1 (SLRW)',
            'exam_family' => 'LanguageCert',
            'price' => 149,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'languagecert-selt-a1-a2-b1-sl',
            'name' => 'LanguageCert SELT A1, A2, B1 (SL)',
            'exam_family' => 'LanguageCert',
            'price' => 135,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'languagecert-academic',
            'name' => 'LanguageCert Academic',
            'exam_family' => 'LanguageCert',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'languagecert-general-training',
            'name' => 'LanguageCert General Training',
            'exam_family' => 'LanguageCert',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'languagecert-iesol-b2-lwr',
            'name' => 'LanguageCert International IESOL B2 LWR',
            'exam_family' => 'LanguageCert',
            'price' => 65,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'languagecert-iesol-b2-speaking',
            'name' => 'LanguageCert International IESOL B2 Speaking',
            'exam_family' => 'LanguageCert',
            'price' => 65,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'skills-for-english-selt-b1-c2-slrw',
            'name' => 'Skills for English SELT B1-C2 (SLRW)',
            'exam_family' => 'Skills for English',
            'price' => 155,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'skills-for-english-selt-a1-a2-b1-sl',
            'name' => 'Skills for English SELT A1, A2, B1 (SL)',
            'exam_family' => 'Skills for English',
            'price' => 125,
            'currency' => 'USD',
            'price_nature' => 'Country Wise',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'toefl-ibt',
            'name' => 'TOEFL iBT',
            'exam_family' => 'TOEFL',
            'price' => null,
            'currency' => null,
            'price_nature' => 'Country Wise',
            'stock_status' => 'outofstock'
        ],
        [
            'slug' => 'duolingo',
            'name' => 'Duolingo',
            'exam_family' => 'Duolingo',
            'price' => 60,
            'currency' => 'USD',
            'price_nature' => 'Global',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'oxford-ellt',
            'name' => 'Oxford ELLT',
            'exam_family' => 'Oxford ELLT',
            'price' => 85,
            'currency' => 'USD',
            'price_nature' => 'Global',
            'stock_status' => 'instock'
        ],
        [
            'slug' => 'password-skills-plus',
            'name' => 'Password Skills Plus',
            'exam_family' => 'Password Skills Plus',
            'price' => 85,
            'currency' => 'USD',
            'price_nature' => 'Global',
            'stock_status' => 'instock'
        ],
    ];
}

function unico_get_voucher_exam_options() {
    $options = [];
    $products = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'vouchers',
            ],
        ],
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $product_id = get_the_ID();
            $exam_key = get_post_meta($product_id, 'exam_name', true);
            if (!$exam_key) {
                $exam_key = get_the_title();
            }
            if (!$exam_key) {
                continue;
            }
            $label = get_the_title();
            $options[$exam_key] = $label;
        }
        wp_reset_postdata();
    }

    asort($options);

    $result = [];
    foreach ($options as $exam_key => $label) {
        $result[] = [
            'value' => $exam_key,
            'label' => $label,
        ];
    }

    return $result;
}

function unico_sync_voucher_products() {
    if (!is_admin()) {
        return;
    }
    
    $catalog = unico_get_voucher_catalog_definitions();
    $term = get_term_by('slug', 'vouchers', 'product_cat');
    if ($term && !is_wp_error($term)) {
        $term_id = (int) $term->term_id;
    } else {
        $inserted = wp_insert_term('Vouchers', 'product_cat', ['slug' => 'vouchers']);
        $term_id = is_wp_error($inserted) ? 0 : (int) $inserted['term_id'];
    }
    foreach ($catalog as $item) {
        $slug = $item['slug'];
        $name = $item['name'];
        $exam_family = $item['exam_family'];
        $price = $item['price'];
        $currency = $item['currency'];
        $price_nature = $item['price_nature'];
        $stock_status = $item['stock_status'];
        $product_post = get_page_by_path($slug, OBJECT, 'product');
        if ($product_post) {
            $product_id = $product_post->ID;
        } else {
            $product_id = wp_insert_post([
                'post_title' => $name,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'product',
                'post_author' => 1
            ]);
            if (is_wp_error($product_id) || !$product_id) {
                continue;
            }
        }
        
        // Update meta data
        if ($price !== null) {
            update_post_meta($product_id, '_regular_price', $price);
            update_post_meta($product_id, '_price', $price);
        }
        
        if (!empty($term_id)) {
            wp_set_object_terms($product_id, [$term_id], 'product_cat', true);
        }
        update_post_meta($product_id, 'exam_name', $exam_family);
        if ($currency) {
            update_post_meta($product_id, 'price_currency', $currency);
        }
        if ($price_nature) {
            update_post_meta($product_id, 'price_nature', $price_nature);
        }
        update_post_meta($product_id, 'is_voucher', 'yes');
    }
}

add_action('admin_init', 'unico_sync_voucher_products');

function unico_cart_has_voucher_items() {
    if (class_exists('Unico_Cart')) {
        return Unico_Cart::get_instance()->cart_has_voucher_items();
    }
    return false;
}

// Add AJAX handlers for Purchase Verification
add_action('wp_ajax_unico_send_purchase_otp', function() {
    check_ajax_referer('unico_purchase_verification', 'nonce');
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in']);
    }
    
    if (class_exists('Unico_Security')) {
        $security = Unico_Security::get_instance();
        $result = $security->send_purchase_otp($user_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if ($result) {
            wp_send_json_success(['message' => 'Verification code sent to your email']);
        }

        wp_send_json_error(['message' => 'Failed to send verification code']);
    }
    wp_send_json_error(['message' => 'Security system not available']);
});

add_action('wp_ajax_unico_verify_purchase_otp', function() {
    check_ajax_referer('unico_purchase_verification', 'nonce');
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'User not logged in']);
    }
    
    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    if (empty($code)) {
        wp_send_json_error(['message' => 'Please enter the verification code']);
    }
    
    if (class_exists('Unico_Security')) {
        $security = Unico_Security::get_instance();
        $result = $security->verify_purchase_otp($user_id, $code);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if ($result) {
            wp_send_json_success(['message' => 'Identity verified successfully']);
        }

        wp_send_json_error(['message' => 'Invalid or expired verification code']);
    }
    wp_send_json_error(['message' => 'Security system not available']);
});

// Add AJAX handler for updating cart quantity
add_action('wp_ajax_unico_update_cart_quantity', function() {
    check_ajax_referer('unico_update_cart', 'nonce');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    if ($product_id < 1 || $quantity < 1) {
        wp_send_json_error(['message' => 'Invalid product ID or quantity']);
    }

    if (!class_exists('Unico_Cart')) {
        wp_send_json_error(['message' => 'Cart system not available']);
    }

    $cart = Unico_Cart::get_instance();
    
    // Find the cart item key by product ID
    $cart_item_key = $cart->find_product_in_cart($product_id);
    
    if ($cart_item_key) {
        $cart->set_quantity($cart_item_key, $quantity);
        
        wp_send_json_success([
            'message' => 'Cart updated successfully',
            'quantity' => $quantity,
            'total' => $cart->get_total('display')
        ]);
    } else {
        wp_send_json_error(['message' => 'Product not found in cart']);
    }
});

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
        'vouchers'                 => 'page-vouchers.php',
        'checkout'                 => 'page-checkout.php',
        'order-received'           => 'page-order-received.php',
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

    if (is_page('checkout')) {
        wp_enqueue_style(
            'unico-checkout',
            get_template_directory_uri() . '/assets/css/checkout.css',
            [],
            '1.0'
        );
        wp_enqueue_script(
            'unico-checkout-js',
            get_template_directory_uri() . '/assets/js/checkout.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('unico-checkout-js', 'unicoCheckout', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('unico-checkout'),
            'nonce_verification' => wp_create_nonce('unico_purchase_verification'),
            'nonce_update_cart' => wp_create_nonce('unico_update_cart')
        ]);
    }

    if (is_page('about-us')) {
        wp_enqueue_style(
            'unico-about',
            get_template_directory_uri() . '/assets/css/about.css',
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

/**
 * Send Admin Notification When Order Requires Verification
 */
add_action('unico_order_status_pending-verification', function($order_id, $order = null) {
    if (!$order && class_exists('Unico_Order')) {
        $order = new Unico_Order($order_id);
    }

    if (!$order || !method_exists($order, 'get_id') || !$order->get_id()) {
        return;
    }

    // Send admin email notification
    $admin_email = get_option('admin_email');
    $receipt_url = $order->get_payment_receipt_url();

    $subject = sprintf('[%s] New Order Awaiting Payment Verification (#%s)', get_bloginfo('name'), $order->get_order_number());

    $message = sprintf(
        "A new order is awaiting payment verification.\n\n" .
        "Order: #%s\n" .
        "Customer: %s\n" .
        "Email: %s\n" .
        "Total: %s\n\n" .
        "Payment Receipt: %s\n\n" .
        "Please review and approve the payment receipt in the Management Dashboard.",
        $order->get_order_number(),
        $order->get_customer_name(),
        $order->get_customer_email(),
        $order->get_formatted_total(),
        $receipt_url ? $receipt_url : 'Not uploaded'
    );

    wp_mail($admin_email, $subject, $message);

    error_log("Unico: Sent admin notification for order #{$order_id} pending verification");
}, 10, 2);
