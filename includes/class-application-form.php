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
        add_action('init', [$this, 'handle_submission'], 5);
        add_action('init', [$this, 'handle_email_verification'], 5);

        // AJAX handlers for email verification
        add_action('wp_ajax_nopriv_send_verification_otp', [$this, 'ajax_send_verification_otp']);
        add_action('wp_ajax_send_verification_otp', [$this, 'ajax_send_verification_otp']);
        add_action('wp_ajax_nopriv_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_verify_otp', [$this, 'ajax_verify_otp']);

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

        // Table for management notification preferences
        $table_notifications = $wpdb->prefix . 'unico_notification_recipients';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            notification_type varchar(100) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY notification_type (notification_type),
            KEY is_active (is_active),
            UNIQUE KEY user_notification (user_id, notification_type)
        ) $charset_collate;";

        // Table for email verification
        $table_verification = $wpdb->prefix . 'unico_email_verification';
        $sql_verification = "CREATE TABLE IF NOT EXISTS $table_verification (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            token varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verified_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY token (token),
            KEY verified_at (verified_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_fields);
        dbDelta($sql_submissions);
        dbDelta($sql_notifications);
        dbDelta($sql_verification);

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

        // Check for existing user by email and phone
        $email = isset($form_data['email']) ? sanitize_email($form_data['email']) : '';
        $phone = isset($form_data['phone']) ? $form_data['phone'] : '';

        if (empty($email) || !is_email($email)) {
            $redirect_base = $application_type === 'agent'
                ? home_url('/agent-application-form')
                : home_url('/student-application-form');
            wp_redirect(add_query_arg([
                'submission_error' => '1',
                'error_message' => urlencode('Please provide a valid email address.')
            ], $redirect_base));
            exit;
        }

        // Check if email is verified
        if (!$this->is_email_verified_for_application($email)) {
            $redirect_base = $application_type === 'agent'
                ? home_url('/agent-application-form')
                : home_url('/student-application-form');
            wp_redirect(add_query_arg([
                'submission_error' => '1',
                'error_message' => urlencode('Please verify your email address before submitting the application.')
            ], $redirect_base));
            exit;
        }

        $validation_result = $this->validate_user_existence($email, $phone);

        if ($validation_result['exists']) {
            $redirect_base = $application_type === 'agent'
                ? home_url('/agent-application-form')
                : home_url('/student-application-form');
            wp_redirect(add_query_arg([
                'submission_error' => '1',
                'error_message' => urlencode($validation_result['message'])
            ], $redirect_base));
            exit;
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
            'form_type' => $application_type,
            'form_data' => json_encode($form_data),
            'status' => 'submitted',
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            // Send confirmation email to applicant
            if (isset($form_data['email'])) {
                $this->send_confirmation_email($form_data['email'], $submission_number, $application_type, $form_data);
            }

            // Send notification to management
            $this->send_management_notification($submission_number, $application_type, $form_data);

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

    /**
     * Validate if user already exists by email or phone
     */
    private function validate_user_existence($email, $phone) {
        global $wpdb;

        $result = [
            'exists' => false,
            'message' => ''
        ];

        // Check if user exists in WordPress users table
        if (!empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                $result['exists'] = true;
                $result['message'] = 'A user with this email address already exists. Please login or use a different email.';
                return $result;
            }
        }

        // Check if email or phone exists in previous submissions
        $table = $wpdb->prefix . 'unico_form_submissions';

        if (!empty($email)) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE form_data LIKE %s AND status != 'rejected'",
                '%"email":"' . $wpdb->esc_like($email) . '"%'
            ));

            if ($existing) {
                $result['exists'] = true;
                $result['message'] = 'An application with this email address already exists. If your application was rejected, the data should have been deleted and you can reapply.';
                return $result;
            }
        }

        if (!empty($phone)) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE form_data LIKE %s AND status != 'rejected'",
                '%"phone":"' . $wpdb->esc_like($phone) . '"%'
            ));

            if ($existing) {
                $result['exists'] = true;
                $result['message'] = 'An application with this phone number already exists. If your application was rejected, the data should have been deleted and you can reapply.';
                return $result;
            }
        }

        return $result;
    }

    /**
     * Send notification to management about new application
     */
    private function send_management_notification($submission_number, $application_type, $form_data) {
        global $wpdb;

        // Get all management users who opted in for new application notifications
        $table = $wpdb->prefix . 'unico_notification_recipients';
        $notification_type = 'new_application';

        $recipients = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM $table WHERE notification_type = %s AND is_active = 1",
            $notification_type
        ));

        // If no specific recipients, send to all management and admin users
        if (empty($recipients)) {
            $admin_users = get_users(['role__in' => ['administrator', 'management']]);
            $emails = array_map(function($user) {
                return $user->user_email;
            }, $admin_users);
        } else {
            $emails = [];
            foreach ($recipients as $recipient) {
                $user = get_userdata($recipient->user_id);
                if ($user) {
                    $emails[] = $user->user_email;
                }
            }
        }

        if (empty($emails)) {
            return;
        }

        $subject = 'New ' . ucfirst($application_type) . ' Application Submitted - ' . get_bloginfo('name');

        $applicant_name = isset($form_data['full_name']) ? $form_data['full_name'] : 'Unknown';
        $applicant_email = isset($form_data['email']) ? $form_data['email'] : 'Not provided';
        $applicant_phone = isset($form_data['phone']) ? $form_data['phone'] : 'Not provided';

        // Build application details
        $details_html = '';
        foreach ($form_data as $key => $value) {
            if ($key !== 'application_type') {
                $label = ucwords(str_replace('_', ' ', $key));
                $details_html .= "<tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>{$label}:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$value}</td></tr>";
            }
        }

        $dashboard_url = home_url('/management-dashboard');

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: linear-gradient(135deg, #194f68 0%, #103e54 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>New Application Received</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <p>A new {$application_type} application has been submitted and is awaiting review.</p>

                <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #194f68; margin-top: 0;'>Application Details</h3>
                    <p><strong>Application Number:</strong> <span style='font-size: 16px; color: #e95134;'>{$submission_number}</span></p>
                    <p><strong>Applicant Name:</strong> {$applicant_name}</p>
                    <p><strong>Email:</strong> {$applicant_email}</p>
                    <p><strong>Phone:</strong> {$applicant_phone}</p>
                </div>

                <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #194f68; margin-top: 0;'>Complete Application Data</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        {$details_html}
                    </table>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='{$dashboard_url}' style='background: #e95134; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Review Application
                    </a>
                </div>

                <p style='margin-top: 30px; color: #666; font-size: 14px;'>
                    This is an automated notification from " . get_bloginfo('name') . ".
                    You can manage your notification preferences in the Management Dashboard.
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        foreach ($emails as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
    }

    private function send_confirmation_email($email, $submission_number, $application_type = 'student', $form_data = []) {
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

    /**
     * Approve application - create user account and send credentials
     */
    public function approve_application($submission_id) {
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            return ['success' => false, 'message' => 'Application not found'];
        }

        $form_data = json_decode($submission->form_data, true);
        $email = isset($form_data['email']) ? $form_data['email'] : '';
        $full_name = isset($form_data['full_name']) ? $form_data['full_name'] : '';

        if (empty($email)) {
            return ['success' => false, 'message' => 'Email not found in application'];
        }

        // Check if user already exists
        if (get_user_by('email', $email)) {
            return ['success' => false, 'message' => 'User account already exists with this email'];
        }

        // Generate username from email
        $username = sanitize_user(current(explode('@', $email)));
        $base_username = $username;
        $counter = 1;

        // Ensure unique username
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Generate random password
        $password = wp_generate_password(12, true, false);

        // Create user account
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return ['success' => false, 'message' => 'Failed to create user: ' . $user_id->get_error_message()];
        }

        // Set user role based on application type
        $user = new WP_User($user_id);
        $role = $submission->form_type === 'agent' ? 'agent' : 'student';
        $user->set_role($role);

        // Update user meta
        if (!empty($full_name)) {
            $name_parts = explode(' ', $full_name, 2);
            update_user_meta($user_id, 'first_name', $name_parts[0]);
            if (isset($name_parts[1])) {
                update_user_meta($user_id, 'last_name', $name_parts[1]);
            }
        }

        // Update submission status
        $this->update_status($submission_id, 'approved', 'Application approved and user account created');

        // Link submission to user
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';
        $wpdb->update($table, ['user_id' => $user_id], ['id' => $submission_id]);

        // Send approval email with credentials
        $this->send_approval_email($email, $username, $password, $role, $submission->submission_number);

        return ['success' => true, 'message' => 'Application approved and user account created', 'user_id' => $user_id];
    }

    /**
     * Reject application - send rejection email and delete data
     */
    public function reject_application($submission_id, $rejection_reason = '') {
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            return ['success' => false, 'message' => 'Application not found'];
        }

        $form_data = json_decode($submission->form_data, true);
        $email = isset($form_data['email']) ? $form_data['email'] : '';

        // Send rejection email
        if (!empty($email)) {
            $this->send_rejection_email($email, $submission->submission_number, $submission->form_type, $rejection_reason);
        }

        // Delete the submission
        global $wpdb;
        $table = $wpdb->prefix . 'unico_form_submissions';
        $deleted = $wpdb->delete($table, ['id' => $submission_id]);

        if ($deleted) {
            return ['success' => true, 'message' => 'Application rejected and data deleted'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete application data'];
        }
    }

    /**
     * Send approval email with login credentials
     */
    private function send_approval_email($email, $username, $password, $role, $submission_number) {
        $subject = 'Application Approved - Welcome to ' . get_bloginfo('name');

        $role_name = ucfirst($role);
        $dashboard_url = $role === 'agent' ? home_url('/agent-dashboard') : home_url('/student-dashboard');
        $login_url = home_url('/login');

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: linear-gradient(135deg, #194f68 0%, #103e54 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>🎉 Application Approved!</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <p>Congratulations! Your application has been approved.</p>

                <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #194f68; margin-top: 0;'>Your Account Details</h3>
                    <p><strong>Application Number:</strong> <span style='color: #e95134;'>{$submission_number}</span></p>
                    <p><strong>Username:</strong> <span style='font-family: monospace; background: #f5f5f5; padding: 5px 10px; border-radius: 3px;'>{$username}</span></p>
                    <p><strong>Password:</strong> <span style='font-family: monospace; background: #f5f5f5; padding: 5px 10px; border-radius: 3px;'>{$password}</span></p>
                    <p><strong>Account Type:</strong> {$role_name}</p>
                </div>

                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Security Notice:</strong> Please change your password after first login for security.</p>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='{$login_url}' style='background: #e95134; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;'>
                        Login Now
                    </a>
                    <a href='{$dashboard_url}' style='background: #194f68; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Visit Dashboard
                    </a>
                </div>

                <p style='margin-top: 30px; color: #666;'>
                    Best regards,<br>
                    " . get_bloginfo('name') . " Team
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Send rejection email
     */
    private function send_rejection_email($email, $submission_number, $application_type, $rejection_reason = '') {
        $subject = 'Application Update - ' . get_bloginfo('name');

        $type_label = $application_type === 'agent' ? 'agent' : 'student';
        $reapply_url = $application_type === 'agent' ? home_url('/agent-application-form') : home_url('/student-application-form');

        $reason_html = '';
        if (!empty($rejection_reason)) {
            $reason_html = "
            <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #194f68; margin-top: 0;'>Reason</h3>
                <p>{$rejection_reason}</p>
            </div>
            ";
        }

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: linear-gradient(135deg, #194f68 0%, #103e54 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>Application Status Update</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <p>Thank you for your interest in " . get_bloginfo('name') . ".</p>

                <div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #194f68; margin-top: 0;'>Application Details</h3>
                    <p><strong>Application Number:</strong> <span style='color: #e95134;'>{$submission_number}</span></p>
                    <p><strong>Status:</strong> Not Approved</p>
                </div>

                {$reason_html}

                <p>Your application data has been removed from our system, which means you are welcome to submit a new application with updated information if you wish.</p>

                <div style='text-align: center; margin-top: 30px;'>
                    <a href='{$reapply_url}' style='background: #194f68; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Submit New Application
                    </a>
                </div>

                <p style='margin-top: 30px; color: #666;'>
                    Best regards,<br>
                    " . get_bloginfo('name') . " Team
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Add user to notification recipients
     */
    public function add_notification_recipient($user_id, $notification_type = 'new_application') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_notification_recipients';

        return $wpdb->insert($table, [
            'user_id' => $user_id,
            'notification_type' => $notification_type,
            'is_active' => 1
        ]);
    }

    /**
     * Remove user from notification recipients
     */
    public function remove_notification_recipient($user_id, $notification_type = 'new_application') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_notification_recipients';

        return $wpdb->delete($table, [
            'user_id' => $user_id,
            'notification_type' => $notification_type
        ]);
    }

    /**
     * Check if user is subscribed to notifications
     */
    public function is_notification_recipient($user_id, $notification_type = 'new_application') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_notification_recipients';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND notification_type = %s AND is_active = 1",
            $user_id,
            $notification_type
        ));

        return $result > 0;
    }

    /**
     * Get all notification recipients
     */
    public function get_notification_recipients($notification_type = 'new_application') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_notification_recipients';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE notification_type = %s AND is_active = 1",
            $notification_type
        ));
    }

    /**
     * Handle email verification for application forms
     */
    public function handle_email_verification() {
        // Handle OTP send request (AJAX)
        if (isset($_POST['action']) && $_POST['action'] === 'send_verification_otp' && isset($_POST['verify_email_nonce']) && wp_verify_nonce($_POST['verify_email_nonce'], 'verify_application_email')) {
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

            if (!empty($email) && is_email($email)) {
                // Check if email already exists
                if (get_user_by('email', $email)) {
                    wp_send_json_error(['message' => 'This email is already registered. Please login or use a different email.']);
                    exit;
                }

                // Check in submissions
                global $wpdb;
                $table = $wpdb->prefix . 'unico_form_submissions';
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE form_data LIKE %s AND status != 'rejected'",
                    '%"email":"' . $wpdb->esc_like($email) . '"%'
                ));

                if ($existing) {
                    wp_send_json_error(['message' => 'An application with this email already exists.']);
                    exit;
                }

                // Send OTP
                $sent = $this->send_application_verification_otp($email);

                if ($sent) {
                    wp_send_json_success(['message' => 'OTP sent to your email. Please check your inbox.']);
                } else {
                    wp_send_json_error(['message' => 'Failed to send OTP. Please try again.']);
                }
            } else {
                wp_send_json_error(['message' => 'Please enter a valid email address.']);
            }
            exit;
        }

        // Handle OTP verification (AJAX)
        if (isset($_POST['action']) && $_POST['action'] === 'verify_otp' && isset($_POST['verify_email_nonce']) && wp_verify_nonce($_POST['verify_email_nonce'], 'verify_application_email')) {
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';

            $verified = $this->verify_application_otp($email, $otp);

            if ($verified) {
                wp_send_json_success(['message' => 'Email verified successfully!']);
            } else {
                wp_send_json_error(['message' => 'Invalid or expired OTP. Please try again.']);
            }
            exit;
        }
    }

    /**
     * Send verification email for application
     */
    private function send_application_verification_email($email) {
        global $wpdb;

        // Generate verification token
        $token = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Store in email_verification table (reuse existing table)
        $table = $wpdb->prefix . 'unico_email_verification';

        // Delete any existing tokens for this email
        $wpdb->delete($table, ['email' => $email, 'verified_at' => null]);

        // Insert new token
        $inserted = $wpdb->insert($table, [
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ]);

        if (!$inserted) {
            return false;
        }

        // Send verification email
        $verification_url = add_query_arg([
            'action' => 'verify_application_email',
            'token' => $token,
            'email' => urlencode($email),
            'form_type' => 'student'
        ], home_url('/student-application-form'));

        $subject = 'Verify Your Email - ' . get_bloginfo('name');

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: linear-gradient(135deg, #194f68 0%, #103e54 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>Email Verification Required</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <p>Thank you for starting your application with " . get_bloginfo('name') . ".</p>

                <p>To proceed with your application submission, please verify your email address by clicking the button below:</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verification_url}' style='background: #e95134; color: #fff; padding: 15px 40px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                        Verify Email Address
                    </a>
                </div>

                <p style='color: #666; font-size: 14px;'>
                    Or copy and paste this link into your browser:<br>
                    <a href='{$verification_url}' style='color: #194f68; word-break: break-all;'>{$verification_url}</a>
                </p>

                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px;'>
                        <strong>Important:</strong> This verification link will expire in 24 hours.
                    </p>
                </div>

                <p style='margin-top: 30px; color: #666;'>
                    Best regards,<br>
                    " . get_bloginfo('name') . " Team
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Verify application email with token
     */
    private function verify_application_email($email, $token) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_email_verification';

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND token = %s AND verified_at IS NULL",
            $email,
            $token
        ));

        if (!$record) {
            return false;
        }

        // Check if expired
        if (strtotime($record->expires_at) < time()) {
            return false;
        }

        // Mark as verified
        $updated = $wpdb->update(
            $table,
            ['verified_at' => current_time('mysql')],
            ['id' => $record->id]
        );

        return $updated !== false;
    }

    /**
     * Check if email is verified for application
     */
    private function is_email_verified_for_application($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_email_verification';

        // Check if email has been verified within last 24 hours
        $verified = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
            WHERE email = %s
            AND verified_at IS NOT NULL
            AND verified_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $email
        ));

        return $verified > 0;
    }

    /**
     * Send OTP for email verification
     */
    private function send_application_verification_otp($email) {
        global $wpdb;

        // Generate 6-digit OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store in email_verification table
        $table = $wpdb->prefix . 'unico_email_verification';

        // Delete any existing unverified tokens for this email
        $wpdb->delete($table, ['email' => $email, 'verified_at' => null]);

        // Insert new OTP
        $inserted = $wpdb->insert($table, [
            'email' => $email,
            'token' => $otp,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ]);

        if (!$inserted) {
            error_log('OTP Insert Failed: ' . $wpdb->last_error);
            return ['error' => 'db_insert_failed', 'db_error' => $wpdb->last_error];
        }

        // Send OTP email
        $subject = 'Your Verification Code - ' . get_bloginfo('name');

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: linear-gradient(135deg, #194f68 0%, #103e54 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>Email Verification</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <p>Thank you for starting your application with " . get_bloginfo('name') . ".</p>

                <p>Your verification code is:</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background: #fff; border: 2px solid #194f68; padding: 20px; border-radius: 8px; display: inline-block;'>
                        <span style='font-size: 32px; font-weight: bold; color: #194f68; letter-spacing: 8px; font-family: monospace;'>{$otp}</span>
                    </div>
                </div>

                <p>Enter this code in the verification popup to continue with your application.</p>

                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px;'>
                        <strong>Important:</strong> This verification code will expire in 15 minutes.
                    </p>
                </div>

                <p style='margin-top: 30px; color: #666;'>
                    Best regards,<br>
                    " . get_bloginfo('name') . " Team
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $mail_sent = wp_mail($email, $subject, $message, $headers);

        if (!$mail_sent) {
            error_log('OTP Email Failed to: ' . $email);
        }

        // Return array with status and OTP
        return [
            'otp' => $otp,
            'mail_sent' => $mail_sent,
            'db_error' => $inserted ? '' : $wpdb->last_error
        ];
    }

    /**
     * Verify OTP for email verification
     * Returns:
     * 0: Success
     * 1: Record not found
     * 2: Expired
     */
    private function verify_application_otp($email, $otp) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_email_verification';

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND token = %s AND verified_at IS NULL",
            $email,
            $otp
        ));

        if (!$record) {
            // Check if it exists but is already verified
            $verified_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE email = %s AND token = %s AND verified_at IS NOT NULL",
                $email,
                $otp
            ));
            
            if ($verified_record) {
                // Already verified, treat as success (idempotent)
                return 0;
            }
            
            error_log("OTP Verify Failed: Record not found for Email: $email, OTP: $otp");
            return 1; // Not found
        }

        // Check if expired
        if (strtotime($record->expires_at) < time()) {
            error_log("OTP Verify Failed: Expired for Email: $email");
            return 2; // Expired
        }

        // Mark as verified
        $updated = $wpdb->update(
            $table,
            ['verified_at' => current_time('mysql')],
            ['id' => $record->id]
        );

        if ($updated === false) {
             error_log("OTP Verify Failed: Database update failed for Email: $email. DB Error: " . $wpdb->last_error);
        }

        return $updated !== false ? 0 : 3; // 3: Update failed
    }

    /**
     * AJAX handler for sending verification OTP
     */
    public function ajax_send_verification_otp() {
        check_ajax_referer('verify_application_email', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        if (!empty($email) && is_email($email)) {
            // Validate email and phone don't already exist
            $validation_result = $this->validate_user_existence($email, $phone);

            if ($validation_result['exists']) {
                wp_send_json_error(['message' => $validation_result['message']]);
                return;
            }

            // Send OTP
            $result = $this->send_application_verification_otp($email);

            if ($result && isset($result['otp'])) {
                $msg = 'Verification code sent to your email. Please check your inbox.';
                if (!$result['mail_sent']) {
                    // For development/debugging when mail fails
                    $msg = 'Verification code generated: ' . $result['otp'] . ' (Mail failed to send. Check server logs.)';
                }
                wp_send_json_success(['message' => $msg]);
            } else {
                $error_msg = 'Failed to generate verification code.';
                if (isset($result['error']) && $result['error'] === 'db_insert_failed') {
                     $error_msg .= ' Database Error: ' . $result['db_error'];
                }
                wp_send_json_error(['message' => $error_msg]);
            }
        } else {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }
    }

    /**
     * AJAX handler for verifying OTP
     */
    public function ajax_verify_otp() {
        check_ajax_referer('verify_application_email', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';

        if (empty($email) || empty($otp)) {
            wp_send_json_error(['message' => 'Email and verification code are required.']);
            return;
        }

        $status = $this->verify_application_otp($email, $otp);

        if ($status === 0) {
            wp_send_json_success(['message' => 'Email verified successfully!']);
        } elseif ($status === 2) {
            wp_send_json_error(['message' => 'Verification code has expired. Please request a new one.']);
        } elseif ($status === 3) {
            wp_send_json_error(['message' => 'Database update failed. Please try again.']);
        } else {
            // Debug info for localhost
            $debug = '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug = " (Email: $email, OTP: $otp)";
            }
            wp_send_json_error(['message' => 'Invalid verification code. Please check and try again.' . $debug]);
        }
    }
}
