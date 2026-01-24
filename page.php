<?php
/**
 * The template for displaying all pages
 */

get_header(); ?>

<?php
$page_id = (int) get_queried_object_id();
$content = $page_id ? (string) get_post_field('post_content', $page_id) : '';
$request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
$is_voucher_checkout_flow = in_array($request_path, ['checkout', 'voucher-order-received'], true) || has_shortcode($content, 'unico_voucher_checkout') || has_shortcode($content, 'unico_voucher_thankyou');
?>

<div class="<?php echo $is_voucher_checkout_flow ? 'unico-page-fullwidth' : 'container unico-page-container'; ?>" <?php echo $is_voucher_checkout_flow ? '' : 'style="padding: 60px 0; min-height: 500px;"'; ?>>
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <?php if ( ! $is_voucher_checkout_flow && ! is_checkout() && ! is_cart() && ! is_account_page() ) : ?>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header>
            <?php endif; ?>

            <div class="entry-content">
                <?php
                the_content();

                wp_link_pages( array(
                    'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'unico' ),
                    'after'  => '</div>',
                ) );
                ?>
            </div>
        </article>
        <?php
    endwhile; // End of the loop.
    ?>
</div>

<?php
get_footer();
