<?php get_header(); ?>
<?php
/**
 * Template Name: Register Page
 */

if ( is_user_logged_in() ) {
    wp_redirect( home_url('/dashboard') );
    exit;
}

wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register - Identity Sync</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body>

<div class="auth-wrapper">

    <div class="auth-card">
        <div class="logo-circle">U</div>
        
        <h1 class="auth-title">NEW <span>IDENTITY</span></h1>
        <p class="auth-subtitle">Create Student Protocol</p>

        <?php if ( isset($_GET['register']) && $_GET['register'] === 'failed' ) : ?>
            <div class="auth-message msg-error">
                Registration failed.
                <?php
                if (isset($_GET['error'])) {
                    echo $_GET['error'] === 'email_exists' ? 'Email already registered.' : 'Please try again.';
                } else {
                    echo 'Email unavailable.';
                }
                ?>
            </div>
        <?php endif; ?>

        <form name="registerform" action="<?php echo esc_url( home_url( '/register' ) ); ?>" method="post">

            <div class="form-stack">
                <div class="input-group">
                    <label class="input-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Official Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="student@university.edu" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1234567890" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Account Type</label>
                    <select name="user_type" class="form-control" required>
                        <option value="unico_customer">Student / Individual Buyer</option>
                        <option value="unico_agent">Agent</option>
                        <option value="unico_reseller">Training Center / Reseller</option>
                    </select>
                </div>

                <div class="input-group">
                    <label class="input-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8" required>
                </div>

                <div style="font-size: 12px; color: #64748B; line-height: 1.5; background: #F1F5F9; padding: 10px; border-radius: 8px;">
                    <strong>Note:</strong> You will receive an email verification link after registration. Please verify your email before making purchases.
                </div>

                <?php wp_nonce_field( 'unicou_register_action', 'unicou_register_nonce' ); ?>

                <button type="submit" name="unicou_register" class="btn-primary">Establish Identity</button>
            </div>

        </form>

        <div class="footer-nav">
            Already verified? <a href="<?php echo home_url('/login'); ?>">Authorize Session</a>
        </div>
    </div>

</div>

<?php get_footer(); ?>
</body>
</html>