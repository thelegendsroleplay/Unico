<?php
/**
 * Security & Fraud Prevention System
 * Handles email verification, IP logging, duplicate detection, and risk scoring
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Security {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('user_register', [$this, 'on_user_register'], 10, 1);
        add_action('wp_login', [$this, 'on_user_login'], 10, 2);
        add_action('wp_login_failed', [$this, 'on_wp_login_failed'], 10, 1);
        add_filter('wp_authenticate_user', [$this, 'enforce_soft_lock'], 5, 2);
    }

    /**
     * Encrypt data
     */
    public function encrypt_data($data) {
        $method = "AES-256-CBC";
        $key = substr(hash('sha256', wp_salt()), 0, 32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt data
     */
    public function decrypt_data($data) {
        $method = "AES-256-CBC";
        $key = substr(hash('sha256', wp_salt()), 0, 32);
        
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        $encrypted_data = $parts[0];
        $iv = $parts[1];
        
        return openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
    }

    /**
     * Generate secure verification token
     */
    public function generate_verification_token($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_email_verification';

        // Generate unique token
        $token = wp_generate_password(64, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $ip_address = $this->get_user_ip();

        // Delete old tokens for this user
        $wpdb->delete($table, ['user_id' => $user_id]);

        // Insert new token
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql')
        ]);

        return $token;
    }

    /**
     * Send verification email
     */
    public function send_verification_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $token = $this->generate_verification_token($user_id);
        $verification_url = add_query_arg([
            'action' => 'verify_email',
            'token' => $token,
            'user_id' => $user_id
        ], home_url('/email-verification'));

        $subject = 'Verify Your Email - ' . get_bloginfo('name');
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #4a4a4a;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #103e54;'>Welcome to " . get_bloginfo('name') . "!</h2>
                <p>Thank you for registering. Please verify your email address to activate your account.</p>
                <p style='margin: 30px 0;'>
                    <a href='{$verification_url}' style='background-color: #e95134; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Verify Email Address
                    </a>
                </p>
                <p style='color: #666; font-size: 14px;'>This link will expire in 24 hours.</p>
                <p style='color: #666; font-size: 14px;'>If you didn't create this account, please ignore this email.</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                <p style='color: #999; font-size: 12px;'>© " . date('Y') . " " . get_bloginfo('name') . ". All rights reserved.</p>
            </div>
        </body>
        </html>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Verify email token
     */
    public function verify_email_token($token, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_email_verification';

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s AND user_id = %d AND verified_at IS NULL",
            $token, $user_id
        ));

        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid verification token.'];
        }

        // Check if token expired
        if (strtotime($verification->expires_at) < time()) {
            return ['success' => false, 'message' => 'Verification token has expired.'];
        }

        // Mark as verified
        $wpdb->update($table, [
            'verified_at' => current_time('mysql')
        ], ['id' => $verification->id]);

        // Update user meta
        update_user_meta($user_id, 'email_verified', 1);
        update_user_meta($user_id, 'email_verified_at', current_time('mysql'));

        // Log activity
        $this->log_activity($user_id, 'email_verified', 'Email address verified successfully');

        return ['success' => true, 'message' => 'Email verified successfully!'];
    }

    /**
     * Check if user's email is verified
     */
    public function is_email_verified($user_id) {
        return (bool) get_user_meta($user_id, 'email_verified', true);
    }

    /**
     * Get user's IP address
     */
    public function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Get country from IP address
     */
    public function get_country_from_ip($ip = null) {
        if (!$ip) {
            $ip = $this->get_user_ip();
        }

        // Use ip-api.com for free geolocation (100 requests/minute)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}");

        if (is_wp_error($response)) {
            return 'UNKNOWN';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['countryCode']) ? $data['countryCode'] : 'UNKNOWN';
    }

    /**
     * Send OTP for purchase verification
     */
    public function send_purchase_otp($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $otp = rand(100000, 999999);
        set_transient('unico_purchase_otp_' . $user_id, $otp, 10 * 60); // 10 minutes

        $subject = 'Purchase Verification Code - ' . get_bloginfo('name');
        $message = "Your verification code is: $otp\n\nThis code expires in 10 minutes.";
        
        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Verify Purchase OTP
     */
    public function verify_purchase_otp($user_id, $code) {
        $stored_otp = get_transient('unico_purchase_otp_' . $user_id);
        if ($stored_otp && $stored_otp == $code) {
            delete_transient('unico_purchase_otp_' . $user_id);
            // Use a short transient for the verified session (e.g., 30 mins)
            set_transient('unico_purchase_verified_' . $user_id, true, 30 * 60);
            return true;
        }
        return false;
    }
    
    /**
     * Check if purchase is verified
     */
    public function is_purchase_verified($user_id) {
        return (bool) get_transient('unico_purchase_verified_' . $user_id);
    }

    /**
     * Clear purchase verification
     */
    public function clear_purchase_verification($user_id) {
        delete_transient('unico_purchase_verified_' . $user_id);
    }

    /**
     * Log user activity
     */
    public function log_activity($user_id, $activity_type, $description, $metadata = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_activity_logs';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'activity_description' => $description,
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Check for duplicate users
     */
    public function check_duplicate_users($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $duplicates = [];
        $current_ip = $this->get_user_ip();

        // Check by email domain
        $email_domain = substr(strrchr($user->user_email, "@"), 1);
        $same_domain_users = get_users([
            'search' => "*@{$email_domain}",
            'search_columns' => ['user_email'],
            'exclude' => [$user_id]
        ]);
        if (count($same_domain_users) > 0) {
            $duplicates['email_domain'] = count($same_domain_users);
        }

        // Check by IP address
        $logs_table = $wpdb->prefix . 'unico_activity_logs';
        $ip_matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $logs_table WHERE ip_address = %s AND user_id != %d",
            $current_ip, $user_id
        ));
        if ($ip_matches > 0) {
            $duplicates['ip_address'] = $ip_matches;
        }

        // Check by phone (if exists)
        $phone = get_user_meta($user_id, 'billing_phone', true);
        if ($phone) {
            $phone_matches = get_users([
                'meta_key' => 'billing_phone',
                'meta_value' => $phone,
                'exclude' => [$user_id]
            ]);
            if (count($phone_matches) > 0) {
                $duplicates['phone'] = count($phone_matches);
            }
        }

        return $duplicates;
    }

    /**
     * Calculate risk score for user
     */
    public function calculate_risk_score($user_id) {
        $risk_score = 0;
        $checks = [];

        // Check 1: Email verification status
        if (!$this->is_email_verified($user_id)) {
            $risk_score += 20;
            $checks['email_not_verified'] = 20;
        }

        // Check 2: Duplicate detection
        $duplicates = $this->check_duplicate_users($user_id);
        if (!empty($duplicates)) {
            $dup_score = count($duplicates) * 15;
            $risk_score += $dup_score;
            $checks['duplicates_found'] = $dup_score;
        }

        // Check 3: New account (less than 24 hours old)
        $user = get_userdata($user_id);
        $account_age_hours = (time() - strtotime($user->user_registered)) / 3600;
        if ($account_age_hours < 24) {
            $risk_score += 10;
            $checks['new_account'] = 10;
        }

        // Check 4: Suspicious country
        $country = $this->get_country_from_ip();
        $high_risk_countries = ['CN', 'RU', 'NG', 'VN']; // Example list
        if (in_array($country, $high_risk_countries)) {
            $risk_score += 25;
            $checks['high_risk_country'] = 25;
        }

        // Check 5: Multiple failed login attempts
        $failed_logins = get_user_meta($user_id, 'failed_login_attempts', true);
        if ($failed_logins && $failed_logins > 3) {
            $risk_score += 15;
            $checks['failed_logins'] = 15;
        }

        // Save risk score
        $this->save_security_check($user_id, 'risk_assessment', $risk_score, $checks, $duplicates);

        return [
            'risk_score' => $risk_score,
            'risk_level' => $this->get_risk_level($risk_score),
            'checks' => $checks
        ];
    }

    /**
     * Get risk level from score
     */
    private function get_risk_level($score) {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    /**
     * Save security check result
     */
    public function save_security_check($user_id, $check_type, $risk_score, $metadata = [], $duplicates = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_security_checks';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'check_type' => $check_type,
            'check_status' => $this->get_risk_level($risk_score),
            'risk_score' => $risk_score,
            'ip_address' => $this->get_user_ip(),
            'country_code' => $this->get_country_from_ip(),
            'email_verified' => $this->is_email_verified($user_id),
            'phone_verified' => 0, // Will be updated when WhatsApp OTP is added
            'duplicate_detected' => !empty($duplicates),
            'duplicate_matches' => json_encode($duplicates),
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * On user registration handler
     */
    public function on_user_register($user_id) {
        // Send verification email
        $this->send_verification_email($user_id);

        // Log registration
        $this->log_activity($user_id, 'user_registered', 'New user registered', [
            'ip' => $this->get_user_ip(),
            'country' => $this->get_country_from_ip()
        ]);

        // Calculate initial risk score
        $this->calculate_risk_score($user_id);
    }

    /**
     * On user login handler
     */
    public function on_user_login($user_login, $user) {
        $this->log_activity($user->ID, 'user_login', 'User logged in', [
            'ip' => $this->get_user_ip(),
            'country' => $this->get_country_from_ip()
        ]);

        delete_user_meta($user->ID, 'failed_login_attempts');
        delete_user_meta($user->ID, 'unico_soft_lock_until');
    }

    public function on_wp_login_failed($username) {
        $username = sanitize_text_field($username);
        $user = get_user_by('login', $username);
        if (!$user && is_email($username)) {
            $user = get_user_by('email', $username);
        }
        if ($user) {
            $attempts = (int) get_user_meta($user->ID, 'failed_login_attempts', true);
            $attempts++;
            update_user_meta($user->ID, 'failed_login_attempts', $attempts);
            if (defined('UNICO_SOFT_LOCK_ENABLED') && UNICO_SOFT_LOCK_ENABLED) {
                $threshold = defined('UNICO_SOFT_LOCK_THRESHOLD') ? (int) UNICO_SOFT_LOCK_THRESHOLD : 5;
                $duration = defined('UNICO_SOFT_LOCK_MINUTES') ? (int) UNICO_SOFT_LOCK_MINUTES : 15;
                if ($attempts >= $threshold && !$this->is_soft_locked($user->ID)) {
                    $until = current_time('timestamp') + ($duration * 60);
                    update_user_meta($user->ID, 'unico_soft_lock_until', $until);
                    $this->log_activity($user->ID, 'login_soft_locked', 'User soft locked after failed logins', [
                        'attempts' => $attempts,
                        'lock_until' => $until
                    ]);
                }
            }
            $this->log_activity($user->ID, 'login_failed', 'Failed login attempt', [
                'username' => $username,
                'attempts' => $attempts
            ]);
        } else {
            $this->log_activity(0, 'login_failed', 'Failed login attempt', [
                'username' => $username
            ]);
        }
    }

    public function is_soft_locked($user_id) {
        $until = (int) get_user_meta($user_id, 'unico_soft_lock_until', true);
        if (!$until) {
            return false;
        }
        $now = current_time('timestamp');
        if ($until <= $now) {
            delete_user_meta($user_id, 'unico_soft_lock_until');
            return false;
        }
        return true;
    }

    public function enforce_soft_lock($user, $password) {
        if (!$user instanceof WP_User) {
            return $user;
        }
        if (defined('UNICO_SOFT_LOCK_ENABLED') && !UNICO_SOFT_LOCK_ENABLED) {
            return $user;
        }
        if ($this->is_soft_locked($user->ID)) {
            $message = 'Your account is temporarily locked due to multiple failed login attempts. Please contact support.';
            return new WP_Error('unico_soft_locked', $message);
        }
        return $user;
    }

    public function should_block_order($user_id) {
        $risk_data = $this->calculate_risk_score($user_id);

        // Block if risk score is high (70+)
        if ($risk_data['risk_score'] >= 70) {
            return [
                'blocked' => true,
                'reason' => 'High risk score detected. Please contact support.'
            ];
        }

        // Require email verification for medium risk (40+)
        if ($risk_data['risk_score'] >= 40 && !$this->is_email_verified($user_id)) {
            return [
                'blocked' => true,
                'reason' => 'Please verify your email address before making purchases.'
            ];
        }

        return ['blocked' => false];
    }
}
