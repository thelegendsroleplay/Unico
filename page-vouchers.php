<?php
/**
 * Template Name: Vouchers Page
 */

get_header();

// Exam filters (slug => [label, meta exam_name])
$exam_filters = [
    'all'          => ['label' => 'All',         'meta' => null],
    'ielts'        => ['label' => 'IELTS',       'meta' => 'IELTS'],
    'pte'          => ['label' => 'PTE',         'meta' => 'PTE'],
    'toefl'        => ['label' => 'TOEFL iBT',   'meta' => 'TOEFL'],
    'languagecert' => ['label' => 'LanguageCert','meta' => 'LanguageCert'],
    'duolingo'     => ['label' => 'Duolingo',    'meta' => 'Duolingo'],
];

$active_exam_slug = isset($_GET['exam']) ? sanitize_key($_GET['exam']) : 'all';
if (!array_key_exists($active_exam_slug, $exam_filters)) {
    $active_exam_slug = 'all';
}
$active_exam_meta = $exam_filters[$active_exam_slug]['meta'];

// Get available voucher products from WooCommerce
$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'tax_query'      => [
        [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => 'vouchers',
        ],
    ],
];

if ($active_exam_meta) {
    $args['meta_query'] = [
        [
            'key'     => 'exam_name',
            'value'   => $active_exam_meta,
            'compare' => '=',
        ],
    ];
}

$voucher_products = new WP_Query($args);

// Get user role for pricing / messaging
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();
$user_roles = $current_user ? (array) $current_user->roles : [];
$is_student = $is_logged_in && in_array('unico_customer', $user_roles, true);
$is_agent = $is_logged_in && (in_array('unico_agent', $user_roles, true) || in_array('unico_reseller', $user_roles, true));
$show_bulk_pricing = in_array('unico_agent', $user_roles, true) || in_array('unico_reseller', $user_roles, true);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Resources - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fb;
            color: #0f172a;
        }
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }
        .page-header {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 24px;
        }
        .page-title {
            font-size: 44px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 900;
        }
        .page-title span {
            display: inline-block;
        }
        .page-title-primary {
            color: #103e54;
            margin-right: 6px;
        }
        .page-title-accent {
            color: #e95134;
        }
        .page-subtitle {
            font-size: 12px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }
        .filters-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid #cbd5f5;
            background: #ffffff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #1e293b;
            text-decoration: none;
            transition: all 0.18s ease;
        }
        .filter-pill:hover {
            border-color: #103e54;
            color: #103e54;
        }
        .filter-pill.active {
            background: #103e54;
            color: #ffffff;
            border-color: #103e54;
        }
        .filters-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .btn-partner-node {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 26px;
            border-radius: 999px;
            background: #103e54;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-decoration: none;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.32);
            transition: background 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
        }
        .btn-partner-node:hover {
            background: #0b3045;
            transform: translateY(-1px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.4);
        }
        .vouchers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 26px;
        }
        .voucher-card {
            position: relative;
            background: #ffffff;
            border-radius: 26px;
            padding: 24px 22px 22px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .voucher-card-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .voucher-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .voucher-brand {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #111827;
        }
        .voucher-scope-pill {
            padding: 5px 12px;
            border-radius: 999px;
            background: #e9fff3;
            color: #16a34a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }
        .voucher-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
        }
        .voucher-tagline {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 22px;
            font-style: italic;
        }
        .voucher-divider {
            height: 1px;
            background: #e5e7eb;
            margin-bottom: 14px;
        }
        .voucher-rate {
            margin-top: auto;
            margin-bottom: 18px;
        }
        .voucher-rate-label {
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .voucher-rate-value {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .voucher-rate-symbol {
            font-size: 20px;
            font-weight: 800;
        }
        .voucher-rate-currency {
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .btn-authorize {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 14px 20px;
            border-radius: 9999px;
            border: none;
            background: #f97316;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.18);
        }
        .btn-authorize:hover {
            filter: brightness(1.03);
            transform: translateY(-1px);
            box-shadow: 0 20px 44px rgba(15, 23, 42, 0.25);
        }
        .btn-authorize.disabled {
            background: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        .bulk-note {
            margin-top: 10px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }
        .no-products {
            text-align: center;
            padding: 80px 20px;
        }
        .no-products-icon {
            font-size: 80px;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        .no-products h2 {
            font-size: 22px;
            margin-bottom: 6px;
        }
        .no-products p {
            color: #6b7280;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .page-title {
                font-size: 30px;
            }
            .filters-row {
                align-items: flex-start;
            }
            .filters-right {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

<div class="page-container">

    <div class="page-header">
        <h1 class="page-title">
            <span class="page-title-primary">Exam</span>
            <span class="page-title-accent">Resources</span>
        </h1>
        <p class="page-subtitle">Integrated Academic Testing Node</p>
    </div>

    <div class="filters-row">
        <div class="filter-pills">
            <?php foreach ($exam_filters as $slug => $data): ?>
                <?php
                if ($slug === 'all') {
                    $url = remove_query_arg('exam');
                } else {
                    $url = add_query_arg('exam', $slug);
                }
                $is_active = ($slug === $active_exam_slug);
                ?>
                <a href="<?php echo esc_url($url); ?>" class="filter-pill<?php echo $is_active ? ' active' : ''; ?>">
                    <?php echo esc_html($data['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="filters-right">
            <a href="<?php echo esc_url(home_url('/register')); ?>" class="btn-partner-node">Partner Node Access</a>
        </div>
    </div>

    <div class="vouchers-grid">
        <?php if ($voucher_products->have_posts()): ?>
            <?php
            $currency_code = get_woocommerce_currency();
            $taglines = [
                'ielts'        => 'British Council/IDP Official.',
                'pte'          => 'Pearson Official Standard.',
                'toefl'        => 'ETS TOEFL iBT Global.',
                'duolingo'     => 'Duolingo Access.',
                'languagecert' => 'LanguageCert Official Voucher.',
            ];
            $scopes = [
                'duolingo' => 'Global',
            ];
            ?>
            <?php while ($voucher_products->have_posts()): $voucher_products->the_post();
                global $product;
                $product_id = get_the_ID();
                $exam_family = get_post_meta($product_id, 'exam_name', true) ?: get_the_title();
                $exam_key = strtolower($exam_family);
                $tagline = isset($taglines[$exam_key]) ? $taglines[$exam_key] : 'Official exam voucher.';

                $scope_meta = get_post_meta($product_id, 'price_nature', true);
                if (!$scope_meta) {
                    $scope_meta = get_post_meta($product_id, 'voucher_scope', true);
                }
                if ($scope_meta) {
                    $scope = $scope_meta;
                } else {
                    $scope = isset($scopes[$exam_key]) ? $scopes[$exam_key] : 'Country-wise';
                }
                $scope_label = strtoupper(str_replace(' ', '-', $scope));

                $raw_price = (float) $product->get_price();
                $display_price = $raw_price > 0 ? number_format($raw_price, 0) : number_format($raw_price, 2);
                $currency_for_label = $currency_code;
                $symbol = '$';
                if ($currency_for_label === 'GBP') {
                    $symbol = '£';
                } elseif ($currency_for_label === 'EUR') {
                    $symbol = '€';
                }

                $brand_label = strtoupper($exam_family);
                $title = get_the_title();
            ?>
            <div class="voucher-card">
                <div class="voucher-card-inner">
                    <div class="voucher-card-top">
                        <div class="voucher-brand">
                            <span><?php echo esc_html($brand_label); ?></span>
                        </div>
                        <span class="voucher-scope-pill">
                            <?php echo esc_html($scope_label); ?>
                        </span>
                    </div>

                    <h2 class="voucher-title">
                        <?php echo esc_html($title); ?>
                    </h2>
                    <p class="voucher-tagline">
                        "<?php echo esc_html($tagline); ?>"
                    </p>

                    <div class="voucher-divider"></div>

                    <div class="voucher-rate">
                        <div class="voucher-rate-label">Official Rate</div>
                        <div class="voucher-rate-value">
                            <span class="voucher-rate-symbol"><?php echo esc_html($symbol); ?></span>
                            <?php echo esc_html($display_price); ?>
                            <span class="voucher-rate-currency">
                                <?php echo esc_html($currency_for_label); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($product->is_in_stock()): ?>
                    <?php
                        if ($is_logged_in) {
                            $button_label = 'Secure Checkout →';
                            if (function_exists('wc_get_checkout_url')) {
                                $button_url = add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
                            } else {
                                $button_url = $product->add_to_cart_url();
                            }
                        } else {
                            $button_label = 'Authorize Procurement';
                            $button_url = home_url('/login');
                        }
                    ?>
                    <a href="<?php echo esc_url($button_url); ?>" class="btn-authorize">
                        <?php echo esc_html($button_label); ?>
                    </a>
                    <?php else: ?>
                    <button class="btn-authorize disabled" disabled>
                        Out of Stock
                    </button>
                    <?php endif; ?>

                    <?php if ($show_bulk_pricing && in_array('unico_agent', $user_roles)): ?>
                        <div class="bulk-note">
                            Agent bulk pricing applies automatically at checkout.
                        </div>
                    <?php elseif ($show_bulk_pricing && in_array('unico_reseller', $user_roles)): ?>
                        <div class="bulk-note">
                            Reseller premium pricing applies automatically at checkout.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <div class="no-products" style="grid-column: 1/-1;">
                <div class="no-products-icon">🎫</div>
                <h2>No Vouchers Available Yet</h2>
                <p>Check back soon for available exam vouchers.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
