<?php
/**
 * The template for displaying all pages
 */

get_header(); ?>

<div class="container" style="padding: 60px 0; min-height: 500px;">
    <?php
    while ( have_posts() ) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <?php if ( ! is_checkout() && ! is_cart() && ! is_account_page() ) : ?>
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
