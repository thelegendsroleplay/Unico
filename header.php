<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class('antialiased font-sans'); ?>>

<header class="site-header">
    <div class="nav-pill">

        <!-- LEFT -->
    <div class="logo">
        <a href="<?php echo esc_url(home_url('/')); ?>">
            <img 
            src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png"
            alt="<?php bloginfo('name'); ?>"
            class="logo-img"
            >
        </a>
    </div>



        <!-- CENTER -->
<nav class="nav-center">

    <!-- ABOUT US -->
    <a href="<?php echo esc_url(home_url('/about-us')); ?>" class="<?php echo is_page('about-us') ? 'active' : ''; ?>">ABOUT US</a>

    <!-- EXAMS (WIDE DROPDOWN) -->
    <div class="nav-item has-dropdown">
        <a href="#">
            EXAMS
            <svg class="nav-arrow" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </a>

        <div class="dropdown wide">
            <a href="#">IELTS</a>
            <a href="#">PTE Academic</a>
            <a href="#">TOEFL iBT</a>
            <a href="#">LanguageCert</a>
            <a href="#">Skills for English</a>
            <a href="#">Duolingo</a>
            <a href="#">Oxford ELLT</a>
            <a href="#">Password Skills Plus</a>
            <a href="#">Exam Comparison</a>
        </div>
    </div>

    <!-- BLOGS -->
    <a href="#">BLOGS</a>

    <!-- CONNECT (SMALL DROPDOWN) -->
    <div class="nav-item has-dropdown">
        <a href="#">
             CONNECT
        <svg class="nav-arrow" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </a>
        <div class="dropdown small">
            <a href="#">Student</a>
            <a href="#">Agent</a>
            <!-- <a href="#">Training Centers</a> -->
            <a href="#">Edu Institute</a>
        </div>
    </div>

</nav>


        <!-- RIGHT -->
<div class="nav-right">
    <button class="search-btn" aria-label="Search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </button>

    <?php if ( is_user_logged_in() ) :
        // Get user's role-appropriate dashboard
        $user = wp_get_current_user();
        $dashboard_url = home_url('/');

        if (in_array('administrator', $user->roles)) {
            $dashboard_url = home_url('/management-dashboard');
        } elseif (in_array('unico_agent', $user->roles)) {
            $dashboard_url = home_url('/agent-dashboard');
        } elseif (in_array('unico_reseller', $user->roles)) {
            $dashboard_url = home_url('/reseller-dashboard');
        } elseif (in_array('unico_support', $user->roles)) {
            $dashboard_url = home_url('/support-dashboard');
        } elseif (in_array('unico_finance', $user->roles)) {
            $dashboard_url = home_url('/finance-dashboard');
        } elseif (in_array('unico_student', $user->roles)) {
            $dashboard_url = home_url('/student-dashboard');
        } elseif (in_array('unico_customer', $user->roles)) {
            $dashboard_url = home_url('/customer-dashboard');
        }
    ?>

        <a class="signin" href="<?php echo esc_url($dashboard_url); ?>">
            DASHBOARD
        </a>

        <a class="signup" href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>">
            LOG OUT
        </a>

    <?php else : ?>

        <a class="signin" href="<?php echo esc_url( home_url('/login') ); ?>">
            SIGN IN
        </a>

        <a class="signup" href="<?php echo esc_url( home_url('/register') ); ?>">
            SIGN UP
        </a>

    <?php endif; ?>
</div>


    </div>
</header>
