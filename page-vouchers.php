<?php
/**
 * Template Name: Vouchers Page
 * Description: Displays exam vouchers with filtering.
 */

get_header();

// 1. Get Categories
$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'slug' => ['ielts', 'pte', 'toefl-ibt', 'languagecert', 'duolingo']
]);
?>

<div class="unico-vouchers-page">
    
    <!-- Hero / Header -->
    <div class="vouchers-hero">
        <div class="container">
            <h1>EXAM <span class="highlight">RESOURCES</span></h1>
            <p class="subtitle">INTEGRATED ACADEMIC TESTING NODE</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="container">
        <div class="vouchers-toolbar">
            <div class="vouchers-filters">
                <button class="filter-btn active" data-filter="all">ALL</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-filter="<?php echo esc_attr($cat->slug); ?>">
                        <?php echo esc_html(strtoupper($cat->name)); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="vouchers-actions">
                <a href="#" class="partner-btn">PARTNER NODE ACCESS</a>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="vouchers-grid">
            <?php
            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => ['ielts', 'pte', 'toefl-ibt', 'languagecert', 'duolingo'],
                        'operator' => 'IN'
                    ]
                ]
            ];
            $loop = new WP_Query($args);

            if ($loop->have_posts()):
                while ($loop->have_posts()): $loop->the_post();
                    global $product;
                    
                    // Get Category for filtering
                    $cats = get_the_terms($product->get_id(), 'product_cat');
                    $cat_slugs = [];
                    if ($cats) {
                        foreach ($cats as $c) {
                            $cat_slugs[] = $c->slug;
                        }
                    }
                    $filter_class = implode(' ', $cat_slugs);
                    
                    // Main Category Name (for display)
                    $main_cat_name = !empty($cats) ? $cats[0]->name : '';
            ?>
                <div class="voucher-card <?php echo esc_attr($filter_class); ?>">
                    <div class="card-badges">
                        <span class="badge-provider"><?php echo esc_html(strtoupper($main_cat_name)); ?></span>
                        <span class="badge-tag">COUNTRY-WISE</span>
                    </div>
                    
                    <h3 class="card-title"><?php the_title(); ?></h3>
                    
                    <div class="card-desc">
                        <?php echo get_the_excerpt() ? get_the_excerpt() : 'Official Exam Voucher'; ?>
                    </div>
                    
                    <div class="card-pricing">
                        <span class="price-label">OFFICIAL RATE</span>
                        <div class="price-value">
                            <?php echo $product->get_price_html(); ?> 
                            <span class="currency">USD</span>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <?php if ($product->is_in_stock()): ?>
                            <a href="<?php echo esc_url(add_query_arg('product_id', $product->get_id(), home_url('/checkout/'))); ?>" class="checkout-btn">
                                SECURE CHECKOUT &rarr;
                            </a>
                        <?php else: ?>
                            <button class="checkout-btn disabled" disabled>
                                OUT OF STOCK
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <p>No vouchers found.</p>
            <?php
            endif;
            wp_reset_postdata();
            ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.filter-btn');
    const cards = document.querySelectorAll('.voucher-card');

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.getAttribute('data-filter');

            cards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'flex';
                } else {
                    if (card.classList.contains(filter)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });
});
</script>

<?php get_footer(); ?>
