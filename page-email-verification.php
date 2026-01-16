<?php
/**
 * Template Name: Email Verification
 */

wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

$verification_result = null;

// Handle verification
if (isset($_GET['action']) && $_GET['action'] === 'verify_email' && isset($_GET['token']) && isset($_GET['user_id'])) {
    $security = Unico_Security::get_instance();
    $verification_result = $security->verify_email_token($_GET['token'], intval($_GET['user_id']));
}

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php bloginfo('name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle">U</div>

        <?php if ($verification_result): ?>
            <?php if ($verification_result['success']): ?>
                <h1 class="auth-title">EMAIL <span>VERIFIED</span></h1>
                <p class="auth-subtitle">Identity Confirmed</p>

                <div class="auth-message msg-success">
                    <div style="font-size: 48px; margin-bottom: 15px;">✓</div>
                    <strong>Email Verified Successfully!</strong><br>
                    Your email address has been confirmed. You can now access all features and make purchases.
                </div>

                <?php if (is_user_logged_in()): ?>
                    <div class="footer-nav">
                        <a href="<?php echo Unico_User_Roles::get_dashboard_url(get_current_user_id()); ?>">Go to Dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="footer-nav">
                        <a href="<?php echo home_url('/login'); ?>">Login to Your Account</a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <h1 class="auth-title">VERIFICATION <span>FAILED</span></h1>
                <p class="auth-subtitle">Invalid Link</p>

                <div class="auth-message msg-error">
                    <div style="font-size: 48px; margin-bottom: 15px;">✕</div>
                    <strong>Verification Failed</strong><br>
                    <?php echo esc_html($verification_result['message']); ?>
                </div>

                <div class="footer-nav">
                    <a href="<?php echo home_url('/support'); ?>">Contact Support</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h1 class="auth-title">VERIFY <span>EMAIL</span></h1>
            <p class="auth-subtitle">Account Verification</p>

            <div style="padding: 30px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">📧</div>
                <p style="color: #6c757d; margin-bottom: 20px;">
                    Please check your email for a verification link.<br>
                    Click the link in the email to verify your account.
                </p>
                <p style="font-size: 14px; color: #999;">
                    Didn't receive the email? Check your spam folder or contact support.
                </p>
            </div>

            <div class="footer-nav">
                <a href="<?php echo home_url('/support'); ?>">Contact Support</a> |
                <a href="<?php echo home_url('/login'); ?>">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
