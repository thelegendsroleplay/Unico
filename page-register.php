<?php get_header(); ?>
<?php
/**
 * Template Name: Register Page
 */

if ( is_user_logged_in() ) {
    wp_redirect(home_url('/'));
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

        <div class="form-stack">
            <div class="input-group">
                <label class="input-label">Registration is managed via applications</label>
                <p style="font-size: 13px; color: #64748B; line-height: 1.6; margin-top: 6px;">
                    Students must submit the
                    <a href="<?php echo home_url('/student-application-form'); ?>">Student Application Form</a>.
                    Agents and training partners must submit the
                    <a href="<?php echo home_url('/agent-application-form'); ?>">Agent Application Form</a>.
                </p>
            </div>
        </div>

        <div class="footer-nav">
            Already verified? <a href="<?php echo home_url('/login'); ?>">Authorize Session</a>
        </div>
    </div>

</div>

<?php get_footer(); ?>
</body>
</html>
