<?php
/**
 * Template Name: Checkout (Custom - No WooCommerce)
 */

// Redirect to login if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$cart = Unico_Cart::get_instance();

// Redirect if cart is empty
if ($cart->is_empty()) {
    wp_redirect(home_url('/vouchers'));
    exit;
}

// Get checkout handler for notices/errors
$checkout = Unico_Checkout::get_instance();

get_header();
?>

<div class="unico-checkout-page-wrapper">
    <div class="unico-checkout-container">

        <?php
        // Display errors and notices
        $checkout->display_errors();
        $checkout->display_notices();
        ?>

        <form method="post" id="unico-checkout-form" enctype="multipart/form-data">
            <?php wp_nonce_field('unico_checkout_action', 'unico_checkout_nonce'); ?>
            <input type="hidden" name="unico_checkout" value="1">

            <?php
            // Get current user and cart details
            $current_user = wp_get_current_user();
            $buyer_name = $current_user->display_name;
            $buyer_email = $current_user->user_email;

            // Get first voucher item from cart (assuming single voucher type per cart)
            $cart_contents = $cart->get_cart();
            $first_item = reset($cart_contents);
            $title = $first_item['product_name'];
            $voucher_qty = $cart->get_voucher_cart_quantity();
            $product_id = $first_item['product_id'];
            $unit_price = $first_item['price'];
            $total_numeric = $cart->get_cart_total();
            $total_display = number_format($total_numeric, 2);
            $currency = 'USD';
            $symbol = '$';

            // Check purchase verification status
            $is_purchase_verified = false;
            if (is_user_logged_in() && class_exists('Unico_Security')) {
                $security = Unico_Security::get_instance();
                $user_id = get_current_user_id();
                $is_purchase_verified = $security->is_purchase_verified($user_id);
            }
            ?>

            <?php include get_template_directory() . '/checkout-voucher-card-custom.php'; ?>

        </form>
    </div>
</div>

<script>
function copyToClipboard(elementId, button) {
    var text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(function() {
        var originalText = button.innerHTML;
        button.innerHTML = '✓ Copied!';
        button.style.background = '#28a745';
        setTimeout(function() {
            button.innerHTML = originalText;
            button.style.background = '';
        }, 2000);
    });
}
</script>

<?php get_footer(); ?>
