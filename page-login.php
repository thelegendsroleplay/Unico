<?php get_header(); ?>
<?php
/**
 * Template Name: Login Page
 */

if ( is_user_logged_in() ) {
    wp_redirect( home_url('/dashboard') );
    exit;
}

// Enqueue ONLY the auth CSS
wp_enqueue_style('auth-css', get_template_directory_uri() . '/assets/css/auth.css', [], '2.0');

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Identity Sync</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body>

<div class="auth-wrapper">
    
    <div class="auth-card">
        <div class="logo-circle">U</div>
        
        <h1 class="auth-title">IDENTITY <span>SYNC</span></h1>
        <p class="auth-subtitle">Protocol V1.1 Authentication</p>

        <?php if ( isset($_GET['login']) && $_GET['login'] === 'failed' ) : ?>
            <div class="auth-message msg-error">Incorrect credentials. Please try again.</div>
        <?php endif; ?>
        <?php if ( isset($_GET['registered']) && $_GET['registered'] === 'true' ) : ?>
            <div class="auth-message msg-success">Account created. Please login.</div>
        <?php endif; ?>

        <form name="loginform" action="<?php echo esc_url( home_url( '/login' ) ); ?>" method="post">
            
            <div class="form-stack">
                <div class="input-group">
                    <label class="input-label">Official User ID</label>
                    <input type="text" name="user_email" class="form-control" placeholder="username or email" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Password Node</label>
                    <a href="<?php echo wp_lostpassword_url(); ?>" class="forgot-password">Recover Access?</a>
                    <input type="password" name="user_password" class="form-control" placeholder="••••••••" required>
                </div>

                <?php wp_nonce_field( 'unicou_login_action', 'unicou_login_nonce' ); ?>
                
                <button type="submit" name="unicou_login" class="btn-primary">Authorize Session</button>
            </div>

        </form>

        <div class="footer-nav">
            New Student? <a href="<?php echo home_url('/register'); ?>">Establish Identity</a>
        </div>
    </div>

</div>

<?php get_footer(); ?>
</body>
</html>