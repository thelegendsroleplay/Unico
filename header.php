<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<header class="site-header">
    <div class="header-container">

        <!-- Logo -->
        <div class="logo">
            <span class="logo-icon">≡</span>
            <span class="logo-text">UNICOU</span>
        </div>

        <!-- Navigation -->
        <nav class="main-nav">
            <ul>
                <li><a href="#">About Us</a></li>
                <li class="has-dropdown">
                    <a href="#">Study Abroad</a>
                </li>
                <li class="has-dropdown">
                    <a href="#">Exams</a>
                </li>
                <li><a href="#">Learning Hub</a></li>
                <li><a href="#">Blogs</a></li>
                <li class="has-dropdown">
                    <a href="#">Connect</a>
                </li>
            </ul>
        </nav>

        <!-- Right Section -->
        <div class="header-actions">
            <button class="search-btn">🔍</button>
            <a href="#" class="sign-in">Sign In</a>
            <a href="#" class="sign-up">Sign Up</a>
        </div>

    </div>
</header>
