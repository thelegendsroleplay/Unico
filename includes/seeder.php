<?php
/**
 * Seeder Logic for Exam Categories and Products
 * Call this once to populate content
 */

function unico_seed_exam_products() {
    if (!class_exists('WooCommerce')) return;

    // 1. Ensure Categories Exist
    $categories = [
        'IELTS',
        'PTE',
        'TOEFL IBT',
        'LANGUAGECERT',
        'DUOLINGO'
    ];

    $cat_ids = [];

    foreach ($categories as $cat_name) {
        $term = term_exists($cat_name, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($cat_name, 'product_cat');
        }
        if (!is_wp_error($term)) {
            $cat_ids[$cat_name] = $term['term_id'];
        }
    }

    // 2. Ensure Products Exist
    $products = [
        [
            'title' => 'LanguageCert SELT Academic B1-C2 (SLRW)',
            'price' => '149',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'LanguageCert SELT General Training A1-C1 (SLRW)',
            'price' => '149',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'LanguageCert SELT A1, A2, B1 (SL)',
            'price' => '135',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'LanguageCert Academic',
            'price' => '0',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'outofstock' // As per image button "OUT OF STOCK"
        ],
        [
            'title' => 'LanguageCert General Training',
            'price' => '149',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'LanguageCert International IESOL B2 LWR',
            'price' => '149',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'LanguageCert International IESOL B2 Speaking',
            'price' => '60',
            'category' => 'LANGUAGECERT',
            'desc' => 'LanguageCert Official Voucher.',
            'stock' => 'instock'
        ],
        [
            'title' => 'Skills for English SELT B1-C2 (SLRW)',
            'price' => '150',
            'category' => 'LANGUAGECERT', // Putting here for now or should create new cat? Image filters don't show "Skills for English"
            'desc' => 'Official exam voucher.',
            'stock' => 'instock'
        ]
    ];

    foreach ($products as $p) {
        // Check if product exists by title
        $existing_products = get_posts([
            'post_type' => 'product',
            'title' => $p['title'],
            'post_status' => 'any',
            'numberposts' => 1,
        ]);
        $existing = !empty($existing_products) ? $existing_products[0] : null;
        
        if (!$existing) {
            $product = new WC_Product_Simple();
            $product->set_name($p['title']);
            $product->set_regular_price($p['price']);
            $product->set_short_description($p['desc']);
            
            if (isset($cat_ids[$p['category']])) {
                $product->set_category_ids([$cat_ids[$p['category']]]);
            }
            
            // Stock status
            $product->set_manage_stock(true);
            if ($p['stock'] === 'instock') {
                $product->set_stock_status('instock');
                $product->set_stock_quantity(100);
            } else {
                $product->set_stock_status('outofstock');
                $product->set_stock_quantity(0);
            }

            $product->save();
        }
    }
}

// Run seeder once on init (can be removed later)
add_action('init', 'unico_seed_exam_products');
