<?php
/**
 * Template Name: Reset Password
 */

if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard'));
    exit;
}

wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

// Validate token
$token_valid = false;
$user_id = 0;

if (isset($_GET['token']) && isset($_GET['user_id'])) {
    $token = sanitize_text_field($_GET['token']);
    $user_id = intval($_GET['user_id']);

    $stored_token = get_user_meta($user_id, 'password_reset_token', true);
    $expires = get_user_meta($user_id, 'password_reset_expires', true);

    if ($stored_token === $token && time() < $expires) {
        $token_valid = true;
    }
}

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php bloginfo('name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle">U</div>

        <h1 class="auth-title">NEW <span>PASSWORD</span></h1>
        <p class="auth-subtitle">Security Reset Protocol</p>

        <?php if (!$token_valid): ?>
            <div class="auth-message msg-error">
                <strong>Invalid or Expired Link</strong><br>
                This password reset link is invalid or has expired. Please request a new one.
            </div>
            <div class="footer-nav">
                <a href="<?php echo home_url('/forgot-password'); ?>">Request New Reset Link</a>
            </div>
        <?php else: ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="auth-message msg-success">
                    <strong>Password Reset Successful!</strong><br>
                    You can now login with your new password.
                </div>
                <div class="footer-nav">
                    <a href="<?php echo home_url('/login'); ?>">Go to Login</a>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="auth-message msg-error">
                    <?php
                    if ($_GET['error'] === 'password_mismatch') {
                        echo 'Passwords do not match.';
                    } elseif ($_GET['error'] === 'weak_password') {
                        echo 'Password must be at least 8 characters.';
                    } else {
                        echo 'An error occurred. Please try again.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($_GET['success'])): ?>
            <form method="post" action="">
                <div class="form-stack">
                    <div class="input-group">
                        <label class="input-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min. 8 characters" minlength="8" required>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" minlength="8" required>
                    </div>

                    <div style="font-size: 12px; color: #64748B; line-height: 1.5; background: #F1F5F9; padding: 10px; border-radius: 8px;">
                        <strong>Password Requirements:</strong>
                        <ul style="margin: 5px 0 0 20px;">
                            <li>Minimum 8 characters</li>
                            <li>Mix of letters and numbers recommended</li>
                        </ul>
                    </div>

                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr($_GET['token']); ?>">
                    <?php wp_nonce_field('reset_password_action', 'reset_password_nonce'); ?>

                    <button type="submit" name="reset_password_confirm" class="btn-primary">Reset Password</button>
                </div>
            </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php
// Handle password reset
if (isset($_POST['reset_password_confirm']) && wp_verify_nonce($_POST['reset_password_nonce'], 'reset_password_action')) {
    $token = sanitize_text_field($_POST['token']);
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate token again
    $stored_token = get_user_meta($user_id, 'password_reset_token', true);
    $expires = get_user_meta($user_id, 'password_reset_expires', true);

    if ($stored_token !== $token || time() >= $expires) {
        wp_redirect(home_url('/forgot-password'));
        exit;
    }

    // Validate passwords
    if ($new_password !== $confirm_password) {
        wp_redirect(add_query_arg(['error' => 'password_mismatch', 'token' => $token, 'user_id' => $user_id], home_url('/reset-password')));
        exit;
    }

    if (strlen($new_password) < 8) {
        wp_redirect(add_query_arg(['error' => 'weak_password', 'token' => $token, 'user_id' => $user_id], home_url('/reset-password')));
        exit;
    }

    // Update password
    wp_set_password($new_password, $user_id);

    // Delete reset token
    delete_user_meta($user_id, 'password_reset_token');
    delete_user_meta($user_id, 'password_reset_expires');

    // Log activity
    $security = Unico_Security::get_instance();
    $security->log_activity($user_id, 'password_reset', 'Password reset completed');

    wp_redirect(add_query_arg('success', '1', home_url('/reset-password')));
    exit;
}
?>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
