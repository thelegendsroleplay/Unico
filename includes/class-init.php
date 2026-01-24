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
        add_action('after_switch_theme', [$this, 'reset_and_create_pages']);
        add_action('init', [$this, 'init_system']);

        // Activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    /**
     * Reset and create required pages on theme activation
     */
    public function reset_and_create_pages() {
        // 1. Define required pages and their templates
        $pages_to_create = [
            'Home' => [
                'slug' => 'home',
                'template' => 'front-page.php',
                'content' => ''
            ],
            'Login' => [
                'slug' => 'login',
                'template' => 'page-login.php',
                'content' => '[unico_login_form]'
            ],
            'Register' => [
                'slug' => 'register',
                'template' => 'page-register.php',
                'content' => '[unico_register_form]'
            ],
            'Forgot Password' => [
                'slug' => 'forgot-password',
                'template' => 'page-forgot-password.php',
                'content' => '[unico_forgot_password]'
            ],
            'Reset Password' => [
                'slug' => 'reset-password',
                'template' => 'page-reset-password.php',
                'content' => '[unico_reset_password]'
            ],
            'Student Dashboard' => [
                'slug' => 'student-dashboard',
                'template' => 'page-student-dashboard.php',
                'content' => ''
            ],
            'Agent Dashboard' => [
                'slug' => 'agent-dashboard',
                'template' => 'page-agent-dashboard.php',
                'content' => ''
            ],
            'Reseller Dashboard' => [
                'slug' => 'reseller-dashboard',
                'template' => 'page-reseller-dashboard.php',
                'content' => ''
            ],
            'Management Dashboard' => [
                'slug' => 'management-dashboard',
                'template' => 'page-management-dashboard.php',
                'content' => ''
            ],
            'Finance Dashboard' => [
                'slug' => 'finance-dashboard',
                'template' => 'page-finance-dashboard.php',
                'content' => ''
            ],
            'Customer Dashboard' => [
                'slug' => 'customer-dashboard',
                'template' => 'page-customer-dashboard.php',
                'content' => ''
            ],
            'Support' => [
                'slug' => 'support',
                'template' => 'page-support.php',
                'content' => ''
            ],
            'Support Dashboard' => [
                'slug' => 'support-dashboard',
                'template' => 'page-support-dashboard.php',
                'content' => ''
            ],
            'About Us' => [
                'slug' => 'about-us',
                'template' => 'page-about-us.php',
                'content' => ''
            ],
            'Study Abroad' => [
                'slug' => 'study-abroad',
                'template' => 'page-study-abroad.php',
                'content' => ''
            ],
            'Student Application' => [
                'slug' => 'student-application',
                'template' => 'page-student-application-form.php',
                'content' => ''
            ],
            'Agent Application' => [
                'slug' => 'agent-application',
                'template' => 'page-agent-application-form.php',
                'content' => ''
            ],
            'Terms and Conditions' => [
                'slug' => 'terms-conditions',
                'template' => '',
                'content' => 'Terms and conditions content goes here.'
            ],
            'Privacy Policy' => [
                'slug' => 'privacy-policy',
                'template' => '',
                'content' => 'Privacy policy content goes here.'
            ]
        ];

        // 2. Create or update pages
        foreach ($pages_to_create as $title => $data) {
            $existing_page = get_page_by_path($data['slug']);
            if ($existing_page) {
                $updates = [];
                if (!empty($data['template'])) {
                    update_post_meta($existing_page->ID, '_wp_page_template', $data['template']);
                }
                if (empty(trim($existing_page->post_content)) && !empty($data['content'])) {
                    $updates['ID'] = $existing_page->ID;
                    $updates['post_content'] = $data['content'];
                }
                if (!empty($updates)) {
                    wp_update_post($updates);
                }

                $page_id = $existing_page->ID;
            } else {
                $page_id = wp_insert_post([
                    'post_title' => $title,
                    'post_name' => $data['slug'],
                    'post_content' => $data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1
                ]);

                if ($page_id && !is_wp_error($page_id)) {
                    if (!empty($data['template'])) {
                        update_post_meta($page_id, '_wp_page_template', $data['template']);
                    }
                }
            }

            // Set homepage
            if ($page_id && !is_wp_error($page_id) && $data['slug'] === 'home') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $page_id);
            }
        }
        
        // Flush rules to ensure permalinks work for new pages
        flush_rewrite_rules();
    }

    /**
     * Load all class files
     */
    private function load_classes() {
        $classes = [
            'class-database.php',
            'class-user-roles.php',
            'class-security.php',
            'class-application-form.php',
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
        $application_form = Unico_Application_Form::get_instance();

        $db_version = get_option('unico_db_version');
        if (!$db_version || version_compare($db_version, '1.3.0', '<')) {
            $database->create_tables();
            $user_roles->create_roles();
            update_option('unico_db_version', '1.3.0');
        }

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
     * System activation
     */
    public function activate() {
        $database = Unico_Database::get_instance();
        $database->create_tables();

        $user_roles = Unico_User_Roles::get_instance();
        $user_roles->create_roles();

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
