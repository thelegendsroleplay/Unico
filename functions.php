<?php

add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        'unico-header',
        get_template_directory_uri() . '/assets/css/header.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'unico-header-js',
        get_template_directory_uri() . '/assets/js/header.js',
        [],
        '1.0',
        true
    );
    
    /* FOOTER */
    wp_enqueue_style(
        'unico-footer',
        get_template_directory_uri() . '/assets/css/footer.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'unico-footer-js',
        get_template_directory_uri() . '/assets/js/footer.js',
        [],
        '1.0',
        true
    );

});
wp_enqueue_style(
    'unico-home',
    get_template_directory_uri() . '/assets/css/home.css',
    [],
    '1.0'
);
