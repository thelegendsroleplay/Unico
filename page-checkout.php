<?php
/**
 * Template Name: Checkout
 */

get_header();
?>

<div class="unico-checkout-page-wrapper">
    <?php
    if (function_exists('woocommerce_checkout')) {
        woocommerce_checkout();
    } elseif (shortcode_exists('woocommerce_checkout')) {
        echo do_shortcode('[woocommerce_checkout]');
    } else {
        echo '<p>Checkout is unavailable.</p>';
    }
    ?>
</div>

<?php get_footer(); ?>
