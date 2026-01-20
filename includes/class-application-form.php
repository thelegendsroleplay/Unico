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
            form_type varchar(50) NOT NULL DEFAULT 'student',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY field_order (field_order),
            KEY is_active (is_active),
            KEY form_type (form_type)
        ) $charset_collate;";

        // Table for form submissions
        $table_submissions = $wpdb->prefix . 'unico_form_submissions';
        $sql_submissions = "CREATE TABLE IF NOT EXISTS $table_submissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_number varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            form_type varchar(50) NOT NULL DEFAULT 'student',
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
            KEY form_type (form_type),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_fields);
        dbDelta($sql_submissions);

        // Manually check and add form_type column if dbDelta failed
        $row = $wpdb->get_results("SHOW COLUMNS FROM $table_fields LIKE 'form_type'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $table_fields ADD COLUMN form_type varchar(50) NOT NULL DEFAULT 'student' AFTER section_name");
            $wpdb->query("ALTER TABLE $table_fields ADD INDEX form_type (form_type)");
        }

        $row_sub = $wpdb->get_results("SHOW COLUMNS FROM $table_submissions LIKE 'form_type'");
        if (empty($row_sub)) {
            $wpdb->query("ALTER TABLE $table_submissions ADD COLUMN form_type varchar(50) NOT NULL DEFAULT 'student' AFTER user_id");
            $wpdb->query("ALTER TABLE $table_submissions ADD INDEX form_type (form_type)");
        }

        // Add default fields if none exist
        $field_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields WHERE form_type = 'student'");
        if ($field_count == 0) {
            $this->create_default_fields();
        }

        // Add agent fields if none exist
        $agent_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_fields WHERE form_type = 'agent'");
        if ($agent_count == 0) {
            $this->create_agent_fields();
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
            ['field_name' => 'full_name', 'field_label' => 'Full Name', 'field_type' => 'text', 'field_placeholder' => 'John Doe', 'field_required' => 1, 'field_order' => 1, 'section_name' => 'Personal Information', 'form_type' => 'student'],
            ['field_name' => 'email', 'field_label' => 'Email Address', 'field_type' => 'email', 'field_placeholder' => 'john@example.com', 'field_required' => 1, 'field_order' => 2, 'section_name' => 'Personal Information', 'form_type' => 'student'],
            ['field_name' => 'phone', 'field_label' => 'Phone Number', 'field_type' => 'tel', 'field_placeholder' => '+1234567890', 'field_required' => 1, 'field_order' => 3, 'section_name' => 'Personal Information', 'form_type' => 'student'],
            ['field_name' => 'date_of_birth', 'field_label' => 'Date of Birth', 'field_type' => 'date', 'field_placeholder' => '', 'field_required' => 1, 'field_order' => 4, 'section_name' => 'Personal Information', 'form_type' => 'student'],
            ['field_name' => 'nationality', 'field_label' => 'Nationality', 'field_type' => 'text', 'field_placeholder' => 'USA', 'field_required' => 1, 'field_order' => 5, 'section_name' => 'Personal Information', 'form_type' => 'student'],

            // Education Background Section
            ['field_name' => 'highest_qualification', 'field_label' => 'Highest Qualification', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['High School', 'Bachelor\'s Degree', 'Master\'s Degree', 'PhD', 'Other']), 'field_required' => 1, 'field_order' => 6, 'section_name' => 'Education Background', 'form_type' => 'student'],
            ['field_name' => 'field_of_study', 'field_label' => 'Field of Study', 'field_type' => 'text', 'field_placeholder' => 'Computer Science', 'field_required' => 1, 'field_order' => 7, 'section_name' => 'Education Background', 'form_type' => 'student'],
            ['field_name' => 'university_name', 'field_label' => 'Current/Previous University', 'field_type' => 'text', 'field_placeholder' => 'University Name', 'field_required' => 1, 'field_order' => 8, 'section_name' => 'Education Background', 'form_type' => 'student'],

            // Study Abroad Plans Section
            ['field_name' => 'preferred_country', 'field_label' => 'Preferred Study Country', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['UK', 'USA', 'Canada', 'Australia', 'New Zealand', 'Germany', 'Ireland', 'Other']), 'field_required' => 1, 'field_order' => 9, 'section_name' => 'Study Abroad Plans', 'form_type' => 'student'],
            ['field_name' => 'preferred_course', 'field_label' => 'Preferred Course/Program', 'field_type' => 'text', 'field_placeholder' => 'MBA, MSc Computer Science, etc.', 'field_required' => 1, 'field_order' => 10, 'section_name' => 'Study Abroad Plans', 'form_type' => 'student'],
            ['field_name' => 'intake_year', 'field_label' => 'Preferred Intake Year', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['2026', '2027', '2028']), 'field_required' => 1, 'field_order' => 11, 'section_name' => 'Study Abroad Plans', 'form_type' => 'student'],
            ['field_name' => 'budget', 'field_label' => 'Budget (Annual)', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['Under $20,000', '$20,000 - $40,000', '$40,000 - $60,000', 'Above $60,000']), 'field_required' => 1, 'field_order' => 12, 'section_name' => 'Study Abroad Plans', 'form_type' => 'student'],

            // English Proficiency Section
            ['field_name' => 'english_test', 'field_label' => 'English Proficiency Test', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['Not Taken', 'IELTS', 'PTE', 'TOEFL', 'Duolingo', 'Other']), 'field_required' => 1, 'field_order' => 13, 'section_name' => 'English Proficiency', 'form_type' => 'student'],
            ['field_name' => 'test_score', 'field_label' => 'Test Score (if taken)', 'field_type' => 'text', 'field_placeholder' => 'Overall score', 'field_required' => 0, 'field_order' => 14, 'section_name' => 'English Proficiency', 'form_type' => 'student'],

            // Additional Information Section
            ['field_name' => 'work_experience', 'field_label' => 'Work Experience (Years)', 'field_type' => 'number', 'field_placeholder' => '0', 'field_required' => 0, 'field_order' => 15, 'section_name' => 'Additional Information', 'form_type' => 'student'],
            ['field_name' => 'additional_info', 'field_label' => 'Additional Information / Questions', 'field_type' => 'textarea', 'field_placeholder' => 'Any specific requirements or questions...', 'field_required' => 0, 'field_order' => 16, 'section_name' => 'Additional Information', 'form_type' => 'student'],
        ];

        foreach ($default_fields as $field) {
            $wpdb->insert($table, $field);
        }
    }

    /**
     * Create agent form fields
     */
    public function create_agent_fields() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_fields';

        $agent_fields = [
            // Agency Information
            ['field_name' => 'agency_name', 'field_label' => 'Agency Name', 'field_type' => 'text', 'field_placeholder' => 'Your Agency Name', 'field_required' => 1, 'field_order' => 1, 'section_name' => 'Agency Information', 'form_type' => 'agent'],
            ['field_name' => 'registration_number', 'field_label' => 'Business Registration No.', 'field_type' => 'text', 'field_placeholder' => 'Registration/License Number', 'field_required' => 1, 'field_order' => 2, 'section_name' => 'Agency Information', 'form_type' => 'agent'],
            ['field_name' => 'website', 'field_label' => 'Website URL', 'field_type' => 'text', 'field_placeholder' => 'https://example.com', 'field_required' => 0, 'field_order' => 3, 'section_name' => 'Agency Information', 'form_type' => 'agent'],
            ['field_name' => 'office_address', 'field_label' => 'Office Address', 'field_type' => 'textarea', 'field_placeholder' => 'Full office address', 'field_required' => 1, 'field_order' => 4, 'section_name' => 'Agency Information', 'form_type' => 'agent'],

            // Contact Person
            ['field_name' => 'full_name', 'field_label' => 'Contact Person Name', 'field_type' => 'text', 'field_placeholder' => 'Full Name', 'field_required' => 1, 'field_order' => 5, 'section_name' => 'Contact Person', 'form_type' => 'agent'],
            ['field_name' => 'email', 'field_label' => 'Email Address', 'field_type' => 'email', 'field_placeholder' => 'contact@example.com', 'field_required' => 1, 'field_order' => 6, 'section_name' => 'Contact Person', 'form_type' => 'agent'],
            ['field_name' => 'phone', 'field_label' => 'Phone Number', 'field_type' => 'tel', 'field_placeholder' => '+1234567890', 'field_required' => 1, 'field_order' => 7, 'section_name' => 'Contact Person', 'form_type' => 'agent'],
            ['field_name' => 'job_title', 'field_label' => 'Job Title / Position', 'field_type' => 'text', 'field_placeholder' => 'e.g. Director, Manager', 'field_required' => 1, 'field_order' => 8, 'section_name' => 'Contact Person', 'form_type' => 'agent'],

            // Business Details
            ['field_name' => 'years_in_business', 'field_label' => 'Years in Business', 'field_type' => 'number', 'field_placeholder' => 'e.g. 5', 'field_required' => 1, 'field_order' => 9, 'section_name' => 'Business Details', 'form_type' => 'agent'],
            ['field_name' => 'student_volume', 'field_label' => 'Annual Student Volume', 'field_type' => 'select', 'field_placeholder' => '', 'field_options' => json_encode(['1-50', '51-200', '201-500', '500+']), 'field_required' => 1, 'field_order' => 10, 'section_name' => 'Business Details', 'form_type' => 'agent'],
            ['field_name' => 'key_destinations', 'field_label' => 'Key Destinations', 'field_type' => 'text', 'field_placeholder' => 'e.g. UK, USA, Canada', 'field_required' => 1, 'field_order' => 11, 'section_name' => 'Business Details', 'form_type' => 'agent'],
            ['field_name' => 'services_provided', 'field_label' => 'Services Provided', 'field_type' => 'textarea', 'field_placeholder' => 'Describe your services...', 'field_required' => 0, 'field_order' => 12, 'section_name' => 'Business Details', 'form_type' => 'agent'],
        ];

        foreach ($agent_fields as $field) {
            $wpdb->insert($table, $field);
        }
    }

    /**
     * Get all active form fields
     */
    public function get_form_fields($form_type = 'student') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_fields';

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE is_active = 1 AND form_type = %s ORDER BY field_order ASC", $form_type));
    }

    /**
     * Get fields grouped by section
     */
    public function get_fields_by_section($form_type = 'student') {
        $fields = $this->get_form_fields($form_type);
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

    public function handle_submission() {
        if (!isset($_POST['submit_application']) || !wp_verify_nonce($_POST['application_nonce'], 'submit_application')) {
            return;
        }

        $application_type = isset($_POST['application_type']) ? sanitize_text_field($_POST['application_type']) : 'student';

        $form_data = [];
        $fields = $this->get_form_fields($application_type);

        foreach ($fields as $field) {
            if (isset($_POST[$field->field_name])) {
                $form_data[$field->field_name] = sanitize_text_field($_POST[$field->field_name]);
            }
        }

        $form_data['application_type'] = $application_type;

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
            'form_type' => $application_type,
            'form_data' => json_encode($form_data),
            'status' => 'submitted',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            if (isset($form_data['email'])) {
                $this->send_confirmation_email($form_data['email'], $submission_number, $application_type);
            }

            $redirect_base = $application_type === 'agent'
                ? home_url('/agent-application-form')
                : home_url('/student-application-form');

            if (is_user_logged_in()) {
                $security = Unico_Security::get_instance();
                $security->log_activity(get_current_user_id(), 'application_submitted', "Application submitted: {$submission_number}");
            }

            wp_redirect(add_query_arg([
                'submission_success' => '1',
                'submission_number' => $submission_number
            ], $redirect_base));
            exit;
        } else {
            $redirect_base = $application_type === 'agent'
                ? home_url('/agent-application-form')
                : home_url('/student-application-form');
            wp_redirect(add_query_arg('submission_error', '1', $redirect_base));
            exit;
        }
    }

    private function send_confirmation_email($email, $submission_number, $application_type = 'student') {
        if ($application_type === 'agent') {
            $subject = 'Agent Application Received - ' . get_bloginfo('name');
            $intro_line = 'Thank you for submitting your agent application.';
            $body_line = 'Our partnerships team will review your details and contact you within 24-48 hours.';
        } else {
            $subject = 'Application Received - ' . get_bloginfo('name');
            $intro_line = 'Thank you for submitting your study abroad application.';
            $body_line = 'Our counselling team will review your application and contact you within 24-48 hours.';
        }

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2 style='color: #194f68;'>Application Received!</h2>
            <p>{$intro_line}</p>
            <p><strong>Your Application Number:</strong> <span style='font-size: 18px; color: #e95134;'>{$submission_number}</span></p>
            <p>{$body_line}</p>
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
