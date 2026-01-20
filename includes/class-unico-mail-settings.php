<?php
/**
 * Unico Mail Settings
 * Handles SMTP configuration for the theme
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Mail_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('phpmailer_init', [$this, 'configure_smtp']);
    }

    public function add_settings_page() {
        add_menu_page(
            'Mail Server Settings',
            'Mail Settings',
            'manage_options',
            'unico-mail-settings',
            [$this, 'render_settings_page'],
            'dashicons-email',
            80
        );
    }

    public function register_settings() {
        register_setting('unico_mail_settings_group', 'unico_smtp_host');
        register_setting('unico_mail_settings_group', 'unico_smtp_port');
        register_setting('unico_mail_settings_group', 'unico_smtp_encryption');
        register_setting('unico_mail_settings_group', 'unico_smtp_user');
        register_setting('unico_mail_settings_group', 'unico_smtp_pass');
        register_setting('unico_mail_settings_group', 'unico_smtp_from_email');
        register_setting('unico_mail_settings_group', 'unico_smtp_from_name');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Mail Server Settings</h1>
            <p>Configure the SMTP server settings for sending emails.</p>
            <form method="post" action="options.php">
                <?php settings_fields('unico_mail_settings_group'); ?>
                <?php do_settings_sections('unico_mail_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">SMTP Host</th>
                        <td><input type="text" name="unico_smtp_host" value="<?php echo esc_attr(get_option('unico_smtp_host')); ?>" class="regular-text" placeholder="smtp.hostinger.com" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">SMTP Port</th>
                        <td><input type="text" name="unico_smtp_port" value="<?php echo esc_attr(get_option('unico_smtp_port')); ?>" class="regular-text" placeholder="465" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Encryption</th>
                        <td>
                            <select name="unico_smtp_encryption">
                                <option value="ssl" <?php selected(get_option('unico_smtp_encryption'), 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected(get_option('unico_smtp_encryption'), 'tls'); ?>>TLS</option>
                                <option value="none" <?php selected(get_option('unico_smtp_encryption'), 'none'); ?>>None</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">SMTP Username</th>
                        <td><input type="text" name="unico_smtp_user" value="<?php echo esc_attr(get_option('unico_smtp_user')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">SMTP Password</th>
                        <td><input type="password" name="unico_smtp_pass" value="<?php echo esc_attr(get_option('unico_smtp_pass')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">From Email</th>
                        <td><input type="email" name="unico_smtp_from_email" value="<?php echo esc_attr(get_option('unico_smtp_from_email')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">From Name</th>
                        <td><input type="text" name="unico_smtp_from_name" value="<?php echo esc_attr(get_option('unico_smtp_from_name')); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2>Test Email</h2>
            <form method="post" action="">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Send To</th>
                        <td><input type="email" name="test_email_recipient" class="regular-text" required /></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="send_test_email" id="send_test_email" class="button button-secondary" value="Send Test Email">
                </p>
                <?php wp_nonce_field('unico_test_email', 'unico_test_email_nonce'); ?>
            </form>
            <?php
            if (isset($_POST['send_test_email']) && check_admin_referer('unico_test_email', 'unico_test_email_nonce')) {
                $to = sanitize_email($_POST['test_email_recipient']);
                $subject = 'Test Email from Unico';
                $message = 'This is a test email to verify your SMTP settings.';
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                
                $sent = wp_mail($to, $subject, $message, $headers);
                
                if ($sent) {
                    echo '<div class="notice notice-success is-dismissible"><p>Test email sent successfully.</p></div>';
                } else {
                    global $phpmailer;
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to send test email.</p>';
                    if (isset($phpmailer->ErrorInfo)) {
                        echo '<p>Error Info: ' . esc_html($phpmailer->ErrorInfo) . '</p>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php
    }

    public function configure_smtp($phpmailer) {
        $host = get_option('unico_smtp_host');
        $port = get_option('unico_smtp_port');
        $encryption = get_option('unico_smtp_encryption');
        $user = get_option('unico_smtp_user');
        $pass = get_option('unico_smtp_pass');
        $from_email = get_option('unico_smtp_from_email');
        $from_name = get_option('unico_smtp_from_name');

        if ($host && $port && $user && $pass) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->SMTPAuth = true;
            $phpmailer->Port = $port;
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
            $phpmailer->SMTPSecure = ($encryption === 'none') ? '' : $encryption;
            
            // Bypass SSL verification if needed (common issue on some hosts)
            $phpmailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            if ($from_email) {
                $phpmailer->From = $from_email;
            }
            if ($from_name) {
                $phpmailer->FromName = $from_name;
            }
        }
    }
}

Unico_Mail_Settings::get_instance();
