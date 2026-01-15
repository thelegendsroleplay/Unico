<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
</head>

<body <?php body_class('antialiased font-sans'); ?>>

<header class="site-header">
    <div class="nav-pill">

        <!-- LEFT -->
    <div class="logo">
        <a href="<?php echo esc_url(home_url('/')); ?>">
            <img 
            src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.svg"
            alt="<?php bloginfo('name'); ?>"
            class="logo-img"
            >
        </a>
    </div>



        <!-- CENTER -->
<nav class="nav-center">

    <!-- STUDY ABROAD (MEGA MENU) -->
    <div class="nav-item has-mega">
         <a href="#">
        STUDY ABROAD
        <svg class="nav-arrow" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </a>

        <div class="mega-menu">
            <div class="mega-col">
                <a href="#">United Kingdom</a>
                <a href="#">United States</a>
                <a href="#">Europe Hub</a>
                <a href="#">Finland</a>
                <a href="#">Malaysia</a>
            </div>
            <div class="mega-col">
                <a href="#">Australia</a>
                <a href="#">Germany</a>
                <a href="#">New Zealand</a>
                <a href="#">Sweden</a>
                <a href="#">Turkey</a>
            </div>
            <div class="mega-col">
                <a href="#">Canada</a>
                <a href="#">Italy</a>
                <a href="#">Ireland</a>
                <a href="#">Dubai</a>
                <a href="#">Cyprus</a>
            </div>
        </div>
    </div>

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

    <!-- LEARNING HUB -->
    <a href="#">LEARNING HUB</a>

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
            <a href="#">Student Admission</a>
            <a href="#">Agent Registration</a>
            <a href="#">Training Centers</a>
            <a href="#">Institutional Sync</a>
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

            <a class="signin" href="#">SIGN IN</a>
            <a class="signup" href="#">SIGN UP</a>
        </div>

    </div>
</header>
