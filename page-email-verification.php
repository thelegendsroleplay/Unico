<?php
/**
 * Template Name: Email Verification
 */

wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

// Process verification token from email link
$verification_result = null;
if (isset($_GET['action']) && $_GET['action'] === 'verify_email' && isset($_GET['token']) && isset($_GET['user_id'])) {
    if (class_exists('Unico_Security')) {
        $security = Unico_Security::get_instance();
        $verification_result = $security->verify_email_token($_GET['token'], intval($_GET['user_id']));
    }
}

// Handle send/resend verification email
$send_result = null;
if (isset($_POST['send_verification']) && is_user_logged_in()) {
    if (wp_verify_nonce($_POST['verification_nonce'], 'send_verification_email')) {
        if (class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();
            $user_id = get_current_user_id();
            $send_result = $security->send_verification_email($user_id);
        }
    }
}

// Get redirect URL for after verification
$redirect_url = '';
if (isset($_GET['redirect'])) {
    $redirect = sanitize_text_field($_GET['redirect']);
    if ($redirect === 'checkout') {
        $redirect_url = wc_get_checkout_url();
    }
}

// Check current user verification status
$is_verified = false;
$user_email = '';
if (is_user_logged_in()) {
    if (class_exists('Unico_Security')) {
        $security = Unico_Security::get_instance();
        $user_id = get_current_user_id();
        $is_verified = $security->is_email_verified($user_id);
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
    }
}

get_header();
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle">U</div>

        <?php if ($verification_result): ?>
            <!-- Verification from email link completed -->
            <?php if ($verification_result['success']): ?>
                <h1 class="auth-title">EMAIL <span>VERIFIED</span></h1>
                <p class="auth-subtitle">Identity Confirmed</p>

                <div class="auth-message msg-success">
                    <div style="font-size: 48px; margin-bottom: 15px;">✓</div>
                    <strong>Email Verified Successfully!</strong><br>
                    Your email address has been confirmed. You can now access all features and make purchases.
                </div>

                <?php if (!empty($redirect_url)): ?>
                    <div class="footer-nav">
                        <a href="<?php echo esc_url($redirect_url); ?>" class="primary-link" style="display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px;">Continue to Checkout →</a>
                    </div>
                <?php else: ?>
                    <div class="footer-nav">
                        <a href="<?php echo home_url('/'); ?>">Go to Homepage</a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <h1 class="auth-title">VERIFICATION <span>FAILED</span></h1>
                <p class="auth-subtitle">Invalid or Expired Link</p>

                <div class="auth-message msg-error">
                    <div style="font-size: 48px; margin-bottom: 15px;">✕</div>
                    <strong>Verification Failed</strong><br>
                    <?php echo esc_html($verification_result['message']); ?>
                </div>

                <div class="footer-nav">
                    <a href="<?php echo home_url('/support'); ?>">Contact Support</a>
                </div>
            <?php endif; ?>

        <?php elseif ($is_verified): ?>
            <!-- User is already verified -->
            <h1 class="auth-title">ALREADY <span>VERIFIED</span></h1>
            <p class="auth-subtitle">Email Confirmed</p>

            <div class="auth-message msg-success">
                <div style="font-size: 48px; margin-bottom: 15px;">✓</div>
                <strong>Your email is verified!</strong><br>
                <?php echo esc_html($user_email); ?>
            </div>

            <?php if (!empty($redirect_url)): ?>
                <div class="footer-nav">
                    <a href="<?php echo esc_url($redirect_url); ?>" class="primary-link" style="display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px;">Continue to Checkout →</a>
                </div>
            <?php else: ?>
                <div class="footer-nav">
                    <a href="<?php echo home_url('/'); ?>">Go to Homepage</a>
                </div>
            <?php endif; ?>

        <?php elseif (is_user_logged_in()): ?>
            <!-- User needs to verify - show send email form -->
            <h1 class="auth-title">VERIFY <span>EMAIL</span></h1>
            <p class="auth-subtitle">Account Verification Required</p>

            <?php if ($send_result): ?>
                <?php if ($send_result['success']): ?>
                    <div class="auth-message msg-success">
                        <div style="font-size: 32px; margin-bottom: 10px;">📧</div>
                        <strong>Verification Email Sent!</strong><br>
                        Check your inbox at <strong><?php echo esc_html($user_email); ?></strong> and click the verification link.
                    </div>
                <?php else: ?>
                    <div class="auth-message msg-error">
                        <strong>Error:</strong> <?php echo esc_html($send_result['message']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="padding: 30px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">🔒</div>
                <p style="color: #6c757d; margin-bottom: 20px; font-size: 15px;">
                    <strong>Email verification is required to complete your purchase.</strong><br><br>
                    Your email: <strong><?php echo esc_html($user_email); ?></strong>
                </p>

                <form method="POST" style="margin-top: 30px;">
                    <?php wp_nonce_field('send_verification_email', 'verification_nonce'); ?>
                    <button type="submit" name="send_verification" class="auth-button" style="width: 100%; padding: 14px; background: #007bff; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-bottom: 15px;">
                        📧 Send Verification Email
                    </button>
                </form>

                <p style="font-size: 13px; color: #999; margin-top: 20px;">
                    Check your spam folder if you don't see the email within a few minutes.
                </p>
            </div>

            <div class="footer-nav">
                <a href="<?php echo home_url('/support'); ?>">Contact Support</a>
                <?php if (!empty($redirect_url)): ?>
                | <a href="<?php echo esc_url($redirect_url); ?>">Back to Checkout</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- User not logged in -->
            <h1 class="auth-title">LOGIN <span>REQUIRED</span></h1>
            <p class="auth-subtitle">Access Denied</p>

            <div class="auth-message msg-error">
                <div style="font-size: 48px; margin-bottom: 15px;">🔒</div>
                <strong>Please log in first</strong><br>
                You must be logged in to verify your email.
            </div>

            <div class="footer-nav">
                <a href="<?php echo home_url('/login'); ?>">Login</a> |
                <a href="<?php echo home_url('/register'); ?>">Register</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Debug info (only visible to admins)
if (current_user_can('manage_options')) {
    echo '<!-- Email Verification Page Debug:';
    echo ' is_verified=' . ($is_verified ? 'yes' : 'no');
    echo ', is_logged_in=' . (is_user_logged_in() ? 'yes' : 'no');
    echo ', user_email=' . esc_html($user_email);
    echo ', verification_result=' . ($verification_result ? 'set' : 'not set');
    echo ', class_exists=' . (class_exists('Unico_Security') ? 'yes' : 'no');
    echo ' -->';
}
?>

<?php get_footer(); ?>
