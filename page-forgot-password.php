<?php
/**
 * Template Name: Forgot Password
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php bloginfo('name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle">U</div>

        <h1 class="auth-title">RESET <span>ACCESS</span></h1>
        <p class="auth-subtitle">Password Recovery Protocol</p>

        <?php if (isset($_GET['reset_sent'])): ?>
            <div class="auth-message msg-success">
                <strong>Email Sent!</strong><br>
                Check your inbox for password reset instructions.
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="auth-message msg-error">
                <?php
                if ($_GET['error'] === 'invalid_email') {
                    echo 'Email address not found.';
                } else {
                    echo 'An error occurred. Please try again.';
                }
                ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-stack">
                <div class="input-group">
                    <label class="input-label">Email Address</label>
                    <input type="email" name="user_email" class="form-control" placeholder="your@email.com" required>
                </div>

                <div style="font-size: 12px; color: #64748B; line-height: 1.5; background: #F1F5F9; padding: 10px; border-radius: 8px;">
                    <strong>Note:</strong> We'll send you a secure link to reset your password. The link expires in 1 hour.
                </div>

                <?php wp_nonce_field('forgot_password_action', 'forgot_password_nonce'); ?>

                <button type="submit" name="reset_password_submit" class="btn-primary">Send Reset Link</button>
            </div>
        </form>

        <div class="footer-nav">
            Remember your password? <a href="<?php echo home_url('/login'); ?>">Back to Login</a>
        </div>
    </div>
</div>

<?php
// Handle form submission
if (isset($_POST['reset_password_submit']) && wp_verify_nonce($_POST['forgot_password_nonce'], 'forgot_password_action')) {
    $email = sanitize_email($_POST['user_email']);

    if (!is_email($email)) {
        wp_redirect(add_query_arg('error', 'invalid_email', home_url('/forgot-password')));
        exit;
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        wp_redirect(add_query_arg('error', 'invalid_email', home_url('/forgot-password')));
        exit;
    }

    // Generate reset token
    $reset_token = wp_generate_password(64, false);
    $expires = time() + 3600; // 1 hour

    // Store token
    update_user_meta($user->ID, 'password_reset_token', $reset_token);
    update_user_meta($user->ID, 'password_reset_expires', $expires);

    // Send email
    $reset_url = add_query_arg([
        'action' => 'reset_password',
        'token' => $reset_token,
        'user_id' => $user->ID
    ], home_url('/reset-password'));

    $subject = 'Password Reset Request - ' . get_bloginfo('name');
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <h2 style='color: #103e54;'>Password Reset Request</h2>
        <p>You requested to reset your password. Click the button below to set a new password:</p>
        <p style='margin: 30px 0;'>
            <a href='{$reset_url}' style='background-color: #e84e33; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                Reset Password
            </a>
        </p>
        <p style='color: #666; font-size: 14px;'>This link will expire in 1 hour.</p>
        <p style='color: #666; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
        <p style='color: #999; font-size: 12px;'>© " . date('Y') . " " . get_bloginfo('name') . "</p>
    </body>
    </html>
    ";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($user->user_email, $subject, $message, $headers);

    wp_redirect(add_query_arg('reset_sent', '1', home_url('/forgot-password')));
    exit;
}
?>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
