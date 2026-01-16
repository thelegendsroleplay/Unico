<?php
/**
 * Template Name: Vouchers Page
 */

get_header();

// Get available voucher products from WooCommerce
$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => 'vouchers'
        ]
    ]
];

$voucher_products = new WP_Query($args);

// Get user role for pricing
$current_user = wp_get_current_user();
$user_roles = $current_user->roles;
$show_bulk_pricing = in_array('unico_agent', $user_roles) || in_array('unico_reseller', $user_roles);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Vouchers - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #333; }
        .page-container { max-width: 1400px; margin: 0 auto; padding: 40px 20px; }
        .page-header { background: linear-gradient(135deg, #103e54 0%, #1a5a7a 100%); color: white; padding: 60px 40px; border-radius: 12px; margin-bottom: 40px; text-align: center; }
        .page-header h1 { font-size: 48px; margin-bottom: 15px; }
        .page-header p { font-size: 18px; opacity: 0.9; max-width: 700px; margin: 0 auto; }
        .info-banner { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 20px 25px; margin-bottom: 40px; border-radius: 8px; }
        .info-banner h3 { color: #004085; margin-bottom: 10px; }
        .info-banner ul { margin-left: 20px; color: #004085; }
        .vouchers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 40px; }
        .voucher-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transition: transform 0.3s, box-shadow 0.3s; }
        .voucher-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); }
        .voucher-header { background: linear-gradient(135deg, #e84e33 0%, #c43d2a 100%); color: white; padding: 30px; text-align: center; }
        .voucher-icon { font-size: 48px; margin-bottom: 10px; }
        .voucher-name { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .voucher-tagline { opacity: 0.9; font-size: 14px; }
        .voucher-body { padding: 30px; }
        .price-section { text-align: center; margin-bottom: 25px; }
        .price-label { font-size: 14px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; }
        .price-value { font-size: 42px; font-weight: 700; color: #e84e33; margin: 10px 0; }
        .price-original { font-size: 18px; color: #6c757d; text-decoration: line-through; }
        .savings { background: #d4edda; color: #155724; padding: 8px 15px; border-radius: 20px; display: inline-block; font-weight: 600; font-size: 14px; margin-top: 10px; }
        .features-list { list-style: none; margin: 25px 0; }
        .features-list li { padding: 10px 0; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; }
        .features-list li:last-child { border-bottom: none; }
        .features-list li:before { content: "✓"; color: #28a745; font-weight: 700; margin-right: 10px; font-size: 18px; }
        .bulk-pricing { background: #fff3cd; border-radius: 8px; padding: 15px; margin: 20px 0; }
        .bulk-pricing h4 { color: #856404; margin-bottom: 10px; font-size: 16px; }
        .bulk-pricing-item { font-size: 14px; color: #856404; padding: 5px 0; }
        .btn-buy { background: #e84e33; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; transition: background 0.2s; text-decoration: none; display: block; text-align: center; }
        .btn-buy:hover { background: #d43f2a; color: white; }
        .stock-status { padding: 10px; text-align: center; font-weight: 600; border-radius: 8px; margin-bottom: 20px; }
        .in-stock { background: #d4edda; color: #155724; }
        .low-stock { background: #fff3cd; color: #856404; }
        .out-stock { background: #f8d7da; color: #721c24; }
        .no-products { text-align: center; padding: 80px 20px; }
        .no-products-icon { font-size: 80px; opacity: 0.3; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .vouchers-grid { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

<div class="page-container">

    <div class="page-header">
        <h1>🎓 Exam Vouchers</h1>
        <p>Purchase official exam vouchers at discounted rates. Instant digital delivery upon payment confirmation.</p>
    </div>

    <?php if ($show_bulk_pricing): ?>
    <div class="info-banner">
        <h3>🏢 You Have Access to Bulk Pricing!</h3>
        <ul>
            <li>Automatic discounts applied based on quantity</li>
            <li>Higher quantities = bigger savings</li>
            <li>View your pricing tiers in your dashboard</li>
        </ul>
    </div>
    <?php endif; ?>

    <div class="vouchers-grid">
        <?php if ($voucher_products->have_posts()): ?>
            <?php while ($voucher_products->have_posts()): $voucher_products->the_post();
                global $product;
                $exam_name = get_post_meta(get_the_ID(), 'exam_name', true) ?: get_the_title();
                $stock_qty = $product->get_stock_quantity();

                // Determine stock status
                if ($stock_qty === null || $stock_qty > 50) {
                    $stock_class = 'in-stock';
                    $stock_text = 'In Stock';
                } elseif ($stock_qty > 10) {
                    $stock_class = 'in-stock';
                    $stock_text = $stock_qty . ' Available';
                } elseif ($stock_qty > 0) {
                    $stock_class = 'low-stock';
                    $stock_text = 'Only ' . $stock_qty . ' Left!';
                } else {
                    $stock_class = 'out-stock';
                    $stock_text = 'Out of Stock';
                }
            ?>
            <div class="voucher-card">
                <div class="voucher-header">
                    <div class="voucher-icon">🎫</div>
                    <h2 class="voucher-name"><?php echo esc_html($exam_name); ?></h2>
                    <p class="voucher-tagline">Official Exam Voucher</p>
                </div>

                <div class="voucher-body">
                    <div class="stock-status <?php echo $stock_class; ?>">
                        <?php echo $stock_text; ?>
                    </div>

                    <div class="price-section">
                        <div class="price-label">Price</div>
                        <div class="price-value"><?php echo $product->get_price_html(); ?></div>
                        <?php if ($product->is_on_sale()): ?>
                        <div class="price-original"><?php echo wc_price($product->get_regular_price()); ?></div>
                        <div class="savings">Save <?php echo round((($product->get_regular_price() - $product->get_sale_price()) / $product->get_regular_price()) * 100); ?>%</div>
                        <?php endif; ?>
                    </div>

                    <ul class="features-list">
                        <li>Instant digital delivery</li>
                        <li>100% authentic voucher code</li>
                        <li>Valid globally</li>
                        <li>Email & dashboard access</li>
                        <li>24/7 customer support</li>
                    </ul>

                    <?php if ($show_bulk_pricing && in_array('unico_agent', $user_roles)): ?>
                    <div class="bulk-pricing">
                        <h4>Agent Bulk Pricing:</h4>
                        <div class="bulk-pricing-item">5-10 qty: 10% off</div>
                        <div class="bulk-pricing-item">11-25 qty: 15% off</div>
                        <div class="bulk-pricing-item">26+ qty: 20% off</div>
                    </div>
                    <?php elseif ($show_bulk_pricing && in_array('unico_reseller', $user_roles)): ?>
                    <div class="bulk-pricing">
                        <h4>Reseller Premium Pricing:</h4>
                        <div class="bulk-pricing-item">10-50 qty: 15% off</div>
                        <div class="bulk-pricing-item">51-100 qty: 20% off</div>
                        <div class="bulk-pricing-item">101+ qty: 25% off</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($product->is_in_stock()): ?>
                    <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="btn-buy">
                        Buy Now
                    </a>
                    <?php else: ?>
                    <button class="btn-buy" style="background: #6c757d; cursor: not-allowed;" disabled>
                        Out of Stock
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <div class="no-products" style="grid-column: 1/-1;">
                <div class="no-products-icon">🎫</div>
                <h2>No Vouchers Available Yet</h2>
                <p style="color: #6c757d; margin-top: 10px;">Check back soon for available exam vouchers.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="info-banner">
        <h3>How It Works</h3>
        <ol style="margin-left: 20px; color: #004085;">
            <li style="margin-bottom: 10px;"><strong>Select Your Exam:</strong> Choose the exam voucher you need</li>
            <li style="margin-bottom: 10px;"><strong>Complete Payment:</strong> Pay securely via card, UPI, net banking, or wallet</li>
            <li style="margin-bottom: 10px;"><strong>Instant Delivery:</strong> Receive your unique voucher code via email immediately</li>
            <li><strong>Book Your Test:</strong> Use the code on the official exam booking website</li>
        </ol>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
