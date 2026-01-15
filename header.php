<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
</head>

<body <?php body_class('font-family: "DejaVu Sans", Tahoma, ui-sans-serif, system-ui'); ?>>

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
            <a href="#">ABOUT US</a>
            <a href="#">STUDY ABROAD</a>
            <a href="#">EXAMS</a>
            <a href="#">LEARNING HUB</a>
            <a href="#">BLOGS</a>
            <a href="#">CONNECT</a>
        </nav>

        <!-- RIGHT -->
        <div class="nav-right">
            <button class="search-btn">🔍</button>
            <a class="signin" href="#">SIGN IN</a>
            <a class="signup" href="#">SIGN UP</a>
        </div>

    </div>
</header>
