<?php
/**
 * Student Application Form System
 * Dynamic form builder and submission management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Application_Form {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'handle_submission']);
        $this->create_tables();
    }

    /**
     * Create database tables for application forms
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table for form field configuration
        $table_fields = $wpdb->prefix . 'unico_form_fields';
        $sql_fields = "CREATE TABLE IF NOT EXISTS $table_fields (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            field_name varchar(255) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type varchar(50) NOT NULL,
            field_placeholder varchar(255) DEFAULT NULL,
            field_options longtext DEFAULT NULL,
            field_required tinyint(1) NOT NULL DEFAULT 1,
            field_order int(11) NOT NULL DEFAULT 0,
            section_name varchar(255) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY field_order (field_order),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Table for form submissions
        $table_submissions = $wpdb->prefix . 'unico_form_submissions';
        $sql_submissions = "CREATE TABLE IF NOT EXISTS $table_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_number varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            form_data longtext NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'submitted',
            assigned_to bigint(20) DEFAULT NULL,
            priority varchar(50) DEFAULT 'medium',
            notes longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY submission_number (submission_number),
            KEY user_id (user_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_fields);
        dbDelta($sql_submissions);

        // Add default fields if none exist
        $field_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields");
        if ($field_count == 0) {
            $this->create_default_fields();
        }
    }

    /**
     * Create default form fields
     */
    public function create_default_fields() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_fields';

        $default_fields = [
            // Personal Information Section
            ['field_name' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'field_placeholder' => 'John Doe', 'field_required' => 1, 'field_order' => 1, 'section_name' => 'Personal Information'],
            ['field_name' => 'email', 'field_label' => 'Email Address', 'field_type' => 'email', 'field_placeholder' => 'john@example.com', 'field_required' => 1, 'field_order' => 2, 'section_name' => 'Personal Information'],
            ['field_name' => 'phone', 'field_label' => 'Phone Number', 'field_type' => 'tel', 'field_placeholder' => '+1234567890', 'field_required' => 1, 'field_order' => 3, 'section_name' => 'Personal Information'],
            ['field_name' => 'date_of_birth', 'field_label' => 'Date of Birth', 'field_type' => 'date', 'field_placeholder' => '', 'field_required' => 1, 'field_order' => 4, 'section_name' => 'Personal Information'],
            ['field_name' => 'nationality', 'field_label' => 'Nationality', 'field_type' => 'text', 'field_placeholder' => 'USA', 'field_required' => 1, 'field_order' => 5, 'section_name' => 'Personal Information'],

            // Education Background Section
            ['field_name' => 'highest_qualification', 'field_label' => 'Highest Qualification', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['High School', 'Bachelor\'s Degree', 'Master\'s Degree', 'PhD', 'Other']), 'field_required' => 1, 'field_order' => 6, 'section_name' => 'Education Background'],
            ['field_name' => 'field_of_study', 'field_label' => 'Field of Study', 'field_type' => 'text', 'field_placeholder' => 'Computer Science', 'field_required' => 1, 'field_order' => 7, 'section_name' => 'Education Background'],
            ['field_name' => 'university_name', 'field_label' => 'Current/Previous University', 'field_type' => 'text', 'field_placeholder' => 'University Name', 'field_required' => 1, 'field_order' => 8, 'section_name' => 'Education Background'],

            // Study Abroad Plans Section
            ['field_name' => 'preferred_country', 'field_label' => 'Preferred Study Country', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['UK', 'USA', 'Canada', 'Australia', 'New Zealand', 'Germany', 'Ireland', 'Other']), 'field_required' => 1, 'field_order' => 9, 'section_name' => 'Study Abroad Plans'],
            ['field_name' => 'preferred_course', 'field_label' => 'Preferred Course/Program', 'field_type' => 'text', 'field_placeholder' => 'MBA, MSc Computer Science, etc.', 'field_required' => 1, 'field_order' => 10, 'section_name' => 'Study Abroad Plans'],
            ['field_name' => 'intake_year', 'field_label' => 'Preferred Intake Year', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['2026', '2027', '2028']), 'field_required' => 1, 'field_order' => 11, 'section_name' => 'Study Abroad Plans'],
            ['field_name' => 'budget', 'field_label' => 'Budget (Annual)', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['Under $20,000', '$20,000 - $40,000', '$40,000 - $60,000', 'Above $60,000']), 'field_required' => 1, 'field_order' => 12, 'section_name' => 'Study Abroad Plans'],

            // English Proficiency Section
            ['field_name' => 'english_test', 'field_label' => 'English Proficiency Test', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['Not Taken', 'IELTS', 'PTE', 'TOEFL', 'Duolingo', 'Other']), 'field_required' => 1, 'field_order' => 13, 'section_name' => 'English Proficiency'],
            ['field_name' => 'test_score', 'field_label' => 'Test Score (if taken)', 'field_type' => 'text', 'field_placeholder' => 'Overall score', 'field_required' => 0, 'field_order' => 14, 'section_name' => 'English Proficiency'],

            // Additional Information Section
            ['field_name' => 'work_experience', 'field_label' => 'Work Experience (Years)', 'field_type' => 'number', 'field_placeholder' => '0', 'field_required' => 0, 'field_order' => 15, 'section_name' => 'Additional Information'],
            ['field_name' => 'additional_info', 'field_label' => 'Additional Information / Questions', 'field_type' => 'textarea', 'field_placeholder' => 'Any specific requirements or questions...', 'field_required' => 0, 'field_order' => 16, 'section_name' => 'Additional Information'],
        ];

        foreach ($default_fields as $field) {
            $wpdb->insert($table, $field);
        }
    }

    /**
     * Get all active form fields
     */
    public function get_form_fields() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_fields';

        return $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY field_order ASC");
    }

    /**
     * Get fields grouped by section
     */
    public function get_fields_by_section() {
        $fields = $this->get_form_fields();
        $sections = [];

        foreach ($fields as $field) {
            $section = $field->section_name ?: 'Other';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $field;
        }

        return $sections;
    }

    /**
     * Handle form submission
     */
    public function handle_submission() {
        if (!isset($_POST['submit_application']) || !wp_verify_nonce($_POST['application_nonce'], 'submit_application')) {
            return;
        }

        // Collect form data
        $form_data = [];
        $fields = $this->get_form_fields();

        foreach ($fields as $field) {
            if (isset($_POST[$field->field_name])) {
                $form_data[$field->field_name] = sanitize_text_field($_POST[$field->field_name]);
            }
        }

        // Generate submission number
        $submission_number = 'APP-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false));

        // Get user IP
        $ip_address = $_SERVER['REMOTE_ADDR'];

        // Save submission
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';

        $inserted = $wpdb->insert($table, [
            'submission_number' => $submission_number,
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'form_data' => json_encode($form_data),
            'status' => 'submitted',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            // Send confirmation email
            if (isset($form_data['email'])) {
                $this->send_confirmation_email($form_data['email'], $submission_number);
            }

            // Log activity
            if (is_user_logged_in()) {
                $security = Unico_Security::get_instance();
                $security->log_activity(get_current_user_id(), 'application_submitted', "Application submitted: {$submission_number}");
            }

            // Redirect with success message
            wp_redirect(add_query_arg([
                'submission_success' => '1',
                'submission_number' => $submission_number
            ], home_url('/student-application-form')));
            exit;
        } else {
            wp_redirect(add_query_arg('submission_error', '1', home_url('/student-application-form')));
            exit;
        }
    }

    /**
     * Send confirmation email
     */
    private function send_confirmation_email($email, $submission_number) {
        $subject = 'Application Received - ' . get_bloginfo('name');
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2 style='color: #103e54;'>Application Received!</h2>
            <p>Thank you for submitting your study abroad application.</p>
            <p><strong>Your Application Number:</strong> <span style='font-size: 18px; color: #e84e33;'>{$submission_number}</span></p>
            <p>Our counselling team will review your application and contact you within 24-48 hours.</p>
            <p style='margin-top: 30px; color: #666;'>Best regards,<br>" . get_bloginfo('name') . " Team</p>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Get all submissions
     */
    public function get_submissions($status = 'all', $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';

        $where = "1=1";
        if ($status !== 'all') {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $limit");
    }

    /**
     * Get submission by ID
     */
    public function get_submission($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Update submission status
     */
    public function update_status($id, $status, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';

        $data = ['status' => $status];
        if ($notes) {
            $data['notes'] = $notes;
        }

        return $wpdb->update($table, $data, ['id' => $id]);
    }
}
