<?php
/**
 * Main Initialization Class
 * Bootstraps the entire voucher booking system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Init {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Load all class files
        $this->load_classes();

        // Initialize system
        add_action('after_setup_theme', [$this, 'setup_theme']);
        add_action('init', [$this, 'init_system']);

        // Activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    /**
     * Load all class files
     */
    private function load_classes() {
        $classes = [
            'class-database.php',
            'class-user-roles.php',
            'class-security.php',
            'class-voucher-system.php',
            'class-wallet.php',
            'class-pricing.php',
            'class-application-form.php',
            // Custom payment system (WooCommerce replacement)
            'class-cart.php',
            'class-order.php',
            'class-checkout.php',
            'class-cart-handlers.php',
        ];

        foreach ($classes as $class) {
            $file = get_template_directory() . '/includes/' . $class;
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Setup theme support
     */
    public function setup_theme() {
        // Add WooCommerce support
        add_theme_support('woocommerce');
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');

        // Add title tag support
        add_theme_support('title-tag');

        // Add post thumbnails
        add_theme_support('post-thumbnails');
    }

    /**
     * Initialize system
     */
    public function init_system() {
        // Initialize instances
        $database = Unico_Database::get_instance();
        $user_roles = Unico_User_Roles::get_instance();
        $security = Unico_Security::get_instance();
        $voucher_system = Unico_Voucher_System::get_instance();
        $wallet = Unico_Wallet::get_instance();
        $pricing = Unico_Pricing::get_instance();
        $application_form = Unico_Application_Form::get_instance();

        // Initialize custom payment system
        $cart = Unico_Cart::get_instance();
        $checkout = Unico_Checkout::get_instance();
        $cart_handlers = Unico_Cart_Handlers::get_instance();

        $db_version = get_option('unico_db_version');
        if (!$db_version || version_compare($db_version, '1.3.0', '<')) {
            $database->create_tables();
            $user_roles->create_roles();
            $pricing->create_default_rules();
            update_option('unico_db_version', '1.3.0');
        }

        // Add AJAX handlers for custom cart
        add_action('wp_ajax_unico_update_cart_quantity_custom', [$this, 'ajax_update_cart_quantity']);
        add_action('wp_ajax_nopriv_unico_update_cart_quantity_custom', [$this, 'ajax_update_cart_quantity']);

        // Register custom post types
        $this->register_post_types();

        // Add custom endpoints
        $this->add_custom_endpoints();

        // Handle form submissions
        $this->handle_forms();
    }

    /**
     * Register custom post types for CMS
     */
    public function register_post_types() {
        // Country Guides
        register_post_type('country_guide', [
            'labels' => [
                'name' => 'Country Guides',
                'singular_name' => 'Country Guide',
                'add_new' => 'Add New Guide',
                'add_new_item' => 'Add New Country Guide',
                'edit_item' => 'Edit Country Guide'
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-admin-site',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'study-abroad']
        ]);

        // University Profiles
        register_post_type('university', [
            'labels' => [
                'name' => 'Universities',
                'singular_name' => 'University',
                'add_new' => 'Add New University',
                'add_new_item' => 'Add New University',
                'edit_item' => 'Edit University'
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-building',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'universities']
        ]);

        // Lead Capture Forms
        register_post_type('counselling_lead', [
            'labels' => [
                'name' => 'Counselling Leads',
                'singular_name' => 'Lead'
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-email',
            'supports' => ['title'],
            'capabilities' => [
                'create_posts' => false
            ],
            'map_meta_cap' => true
        ]);
    }

    /**
     * Add custom rewrite endpoints
     */
    public function add_custom_endpoints() {
        add_rewrite_endpoint('customer-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('agent-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('reseller-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('support-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('finance-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('management-dashboard', EP_ROOT | EP_PAGES);

        flush_rewrite_rules();
    }

    /**
     * Handle custom form submissions
     */
    public function handle_forms() {
        // Email verification handler
        add_action('template_redirect', function() {
            if (isset($_GET['action']) && $_GET['action'] === 'verify_email' && isset($_GET['token']) && isset($_GET['user_id'])) {
                $security = Unico_Security::get_instance();
                $result = $security->verify_email_token($_GET['token'], intval($_GET['user_id']));

                if ($result['success']) {
                    wp_redirect(add_query_arg('verified', '1', home_url('/customer-dashboard')));
                } else {
                    wp_redirect(add_query_arg('verification_error', urlencode($result['message']), home_url('/login')));
                }
                exit;
            }
        });

        // Counselling form handler
        add_action('init', function() {
            if (isset($_POST['submit_counselling_form']) && wp_verify_nonce($_POST['counselling_nonce'], 'counselling_form')) {
                $lead_data = [
                    'name' => sanitize_text_field($_POST['full_name']),
                    'email' => sanitize_email($_POST['email']),
                    'phone' => sanitize_text_field($_POST['phone']),
                    'country_interest' => sanitize_text_field($_POST['country']),
                    'message' => sanitize_textarea_field($_POST['message'])
                ];

                // Create lead post
                $lead_id = wp_insert_post([
                    'post_type' => 'counselling_lead',
                    'post_title' => $lead_data['name'] . ' - ' . $lead_data['email'],
                    'post_status' => 'publish',
                    'meta_input' => $lead_data
                ]);

                if ($lead_id) {
                    // Send notification to admin
                    $admin_email = get_option('admin_email');
                    wp_mail(
                        $admin_email,
                        'New Counselling Lead',
                        sprintf("New lead from %s\nEmail: %s\nPhone: %s\nInterested in: %s\n\nMessage: %s",
                            $lead_data['name'],
                            $lead_data['email'],
                            $lead_data['phone'],
                            $lead_data['country_interest'],
                            $lead_data['message']
                        )
                    );

                    wp_redirect(add_query_arg('counselling_submitted', '1', home_url()));
                    exit;
                }
            }
        });
    }

    /**
     * AJAX: Update cart quantity (custom payment system)
     */
    public function ajax_update_cart_quantity() {
        check_ajax_referer('unico_update_cart', 'nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if ($product_id < 1 || $quantity < 1) {
            wp_send_json_error(['message' => 'Invalid product ID or quantity']);
        }

        $cart = Unico_Cart::get_instance();

        // Find the cart item and update quantity
        $cart_item_key = $cart->find_product_in_cart($product_id);

        if ($cart_item_key) {
            $cart->set_quantity($cart_item_key, $quantity);
            wp_send_json_success([
                'message' => 'Cart updated successfully',
                'quantity' => $quantity,
                'total' => $cart->get_total('edit')
            ]);
        } else {
            wp_send_json_error(['message' => 'Product not found in cart']);
        }
    }

    /**
     * System activation
     */
    public function activate() {
        $database = Unico_Database::get_instance();
        $database->create_tables();

        $user_roles = Unico_User_Roles::get_instance();
        $user_roles->create_roles();

        $pricing = Unico_Pricing::get_instance();
        $pricing->create_default_rules();

        flush_rewrite_rules();
    }

    /**
     * System deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the system
Unico_Init::get_instance();
