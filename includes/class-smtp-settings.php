<?php
/**
 * Unico SMTP Settings
 * Adds a simple SMTP configuration page to WordPress Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_SMTP_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
        
        // Handle Test Email
        add_action('admin_post_unico_send_test_email', [$this, 'handle_test_email']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Unico SMTP', 
            'Unico SMTP', 
            'manage_options', 
            'unico-smtp-settings', 
            [$this, 'settings_page_html'], 
            'dashicons-email-alt', 
            99
        );
    }

    public function register_settings() {
        register_setting('unico_smtp_options', 'unico_smtp_host');
        register_setting('unico_smtp_options', 'unico_smtp_port');
        register_setting('unico_smtp_options', 'unico_smtp_encryption');
        register_setting('unico_smtp_options', 'unico_smtp_user');
        register_setting('unico_smtp_options', 'unico_smtp_pass');
        register_setting('unico_smtp_options', 'unico_smtp_from_email');
        register_setting('unico_smtp_options', 'unico_smtp_from_name');
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Unico SMTP Settings</h1>
            <p>Configure your SMTP server settings to ensure emails are delivered reliably.</p>
            
            <?php 
            if (isset($_GET['status']) && $_GET['status'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>Test email sent successfully!</p></div>';
            } elseif (isset($_GET['status']) && $_GET['status'] === 'error') {
                $error_msg = isset($_GET['message']) ? urldecode($_GET['message']) : 'Failed to send test email.';
                echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($error_msg) . '</p></div>';
            }
            ?>

            <form action="options.php" method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px;">
                <?php
                settings_fields('unico_smtp_options');
                do_settings_sections('unico_smtp_options');
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">SMTP Host</th>
                        <td><input type="text" name="unico_smtp_host" value="<?php echo esc_attr(get_option('unico_smtp_host')); ?>" class="regular-text" placeholder="smtp.hostinger.com"></td>
                    </tr>
                    <tr>
                        <th scope="row">SMTP Port</th>
                        <td><input type="text" name="unico_smtp_port" value="<?php echo esc_attr(get_option('unico_smtp_port')); ?>" class="regular-text" placeholder="465 or 587"></td>
                    </tr>
                    <tr>
                        <th scope="row">Encryption</th>
                        <td>
                            <select name="unico_smtp_encryption">
                                <option value="ssl" <?php selected(get_option('unico_smtp_encryption'), 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected(get_option('unico_smtp_encryption'), 'tls'); ?>>TLS</option>
                                <option value="none" <?php selected(get_option('unico_smtp_encryption'), 'none'); ?>>None</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">SMTP Username</th>
                        <td><input type="text" name="unico_smtp_user" value="<?php echo esc_attr(get_option('unico_smtp_user')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">SMTP Password</th>
                        <td><input type="password" name="unico_smtp_pass" value="<?php echo esc_attr(get_option('unico_smtp_pass')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">From Email</th>
                        <td><input type="email" name="unico_smtp_from_email" value="<?php echo esc_attr(get_option('unico_smtp_from_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">From Name</th>
                        <td><input type="text" name="unico_smtp_from_name" value="<?php echo esc_attr(get_option('unico_smtp_from_name')); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>

            <h2>Test Email Configuration</h2>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px;">
                <input type="hidden" name="action" value="unico_send_test_email">
                <?php wp_nonce_field('unico_test_email_nonce', 'test_email_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Send Test To</th>
                        <td><input type="email" name="test_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text" required></td>
                    </tr>
                </table>
                
                <?php submit_button('Send Test Email', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function configure_phpmailer($phpmailer) {
        $host = get_option('unico_smtp_host');
        $user = get_option('unico_smtp_user');
        $pass = get_option('unico_smtp_pass');

        if (!empty($host) && !empty($user) && !empty($pass)) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->SMTPAuth = true;
            $phpmailer->Port = get_option('unico_smtp_port', '465');
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
            $phpmailer->SMTPSecure = get_option('unico_smtp_encryption', 'ssl');
            
            // From Email
            $from_email = get_option('unico_smtp_from_email');
            $from_name = get_option('unico_smtp_from_name');
            
            if (!empty($from_email)) {
                $phpmailer->From = $from_email;
            }
            if (!empty($from_name)) {
                $phpmailer->FromName = $from_name;
            }
        }
    }

    public function handle_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('unico_test_email_nonce', 'test_email_nonce');

        $to = sanitize_email($_POST['test_email']);
        $subject = 'Unico SMTP Test Email';
        $message = 'This is a test email from Unico Theme to verify SMTP settings.';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            wp_redirect(admin_url('admin.php?page=unico-smtp-settings&status=success'));
        } else {
            // Capture error if possible (requires global $phpmailer or similar trick, but usually tough in standard wp_mail return)
            // But since we hooked into phpmailer_init, usually errors are logged or retrievable if we add an action on failure
            wp_redirect(admin_url('admin.php?page=unico-smtp-settings&status=error'));
        }
        exit;
    }
}

// Initialize
Unico_SMTP_Settings::get_instance();
