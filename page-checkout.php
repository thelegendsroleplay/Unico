<?php
/**
 * Template Name: Checkout
 * WooCommerce checkout wrapper
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
    exit;
}

get_header();

$cart_empty = true;
if (function_exists('WC') && WC()->cart) {
    $cart_empty = WC()->cart->is_empty();
}
?>

<style>
/* Checkout Page Specific Styles - Mobile First */
.checkout-wrapper {
    background: #f5f7fb;
    min-height: calc(100vh - 150px);
    padding: 20px 16px 60px;
}

.checkout-container {
    max-width: 760px;
    margin: 0 auto;
}

.checkout-title {
    font-size: 24px;
    font-weight: 800;
    color: #103e54;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* Alerts */
.checkout-alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
}

.checkout-alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.checkout-alert-info {
    background: #e8f4fd;
    border: 1px solid #bcd4f5;
    color: #1e40af;
}

/* Empty Cart */
.checkout-empty {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.checkout-empty p {
    font-size: 18px;
    color: #6b7280;
    margin-bottom: 20px;
}

.checkout-empty-btn {
    display: inline-block;
    padding: 14px 28px;
    background: #f97316;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    border-radius: 999px;
    text-decoration: none;
    transition: all 0.2s;
}

.checkout-empty-btn:hover {
    background: #ea580c;
    transform: translateY(-2px);
}

/* Main Layout Grid */
.checkout-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.checkout-main {
    flex: 1;
}

/* Cards */
.checkout-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

@media (min-width: 768px) {
    .checkout-wrapper {
        padding: 40px 24px 80px;
    }

    .checkout-title {
        font-size: 30px;
    }
}
</style>

<div class="checkout-wrapper">
    <div class="checkout-container">
        <h1 class="checkout-title">Checkout</h1>

        <?php if ($cart_empty) : ?>
            <div class="checkout-empty">
                <p>Your cart is empty.</p>
                <a href="<?php echo esc_url(home_url('/vouchers')); ?>" class="checkout-empty-btn">Browse Vouchers</a>
            </div>
        <?php else : ?>
            <div class="checkout-grid">
                <div class="checkout-main">
                    <div class="checkout-card">
                        <?php
                        if (function_exists('wc_print_notices')) {
                            wc_print_notices();
                        }
                        echo do_shortcode('[woocommerce_checkout]');
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
?>
