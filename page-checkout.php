<?php
/**
 * Template Name: Checkout
 */

// Force redirect to login if not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

// Get cart instance
$cart = Unico_Cart::get_instance();
$cart_items = $cart->get_cart();
$is_empty = empty($cart_items);

// Get errors/notices from Unico_Checkout
$checkout = Unico_Checkout::get_instance();
$errors = $checkout->get_errors();
$notices = $checkout->get_notices();
?>

<div class="unico-checkout-page-wrapper">
    <div class="unico-container">
        <h1 class="unico-page-title">Checkout</h1>

        <?php if (!empty($errors)): ?>
            <div class="unico-alert unico-alert-danger">
                <?php foreach ($errors as $error) echo '<p>' . esc_html($error) . '</p>'; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($notices)): ?>
            <div class="unico-alert unico-alert-info">
                <?php foreach ($notices as $notice) echo '<p>' . esc_html($notice) . '</p>'; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_empty): ?>
            <div class="unico-empty-cart">
                <p>Your cart is empty.</p>
                <a href="<?php echo home_url('/vouchers'); ?>" class="unico-btn">Browse Vouchers</a>
            </div>
        <?php else: ?>
            <form action="" method="post" enctype="multipart/form-data" class="unico-checkout-form">
                <?php wp_nonce_field('unico_checkout_action', 'unico_checkout_nonce'); ?>
                <input type="hidden" name="unico_checkout" value="1">

                <div class="unico-checkout-grid">
                    <!-- Left Column: Order Details -->
                    <div class="unico-checkout-main">
                        
                        <!-- Purchase Verification -->
                        <?php
                        $is_purchase_verified = false;
                        if (class_exists('Unico_Security')) {
                            $security = Unico_Security::get_instance();
                            $is_purchase_verified = $security->is_purchase_verified(get_current_user_id());
                        }
                        $current_user = wp_get_current_user();
                        ?>

                        <?php if (!$is_purchase_verified): ?>
                            <div class="unico-verification-notice" style="background: #e2e3e5; border: 2px solid #d6d8db; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#383d41" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    <div style="flex-grow: 1;">
                                        <strong style="color: #383d41; display: block; margin-bottom: 6px;">🔒 Purchase Verification Required</strong>
                                        <p style="color: #383d41; margin: 0; font-size: 14px; line-height: 1.5; margin-bottom: 10px;">
                                            We'll send a verification code to <strong><?php echo esc_html($current_user->user_email); ?></strong>. Complete payment after verification.
                                        </p>
                                        
                                        <div id="unico-otp-step-1">
                                            <button type="button" id="unico-send-otp-btn" style="padding: 8px 16px; background: #383d41; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                                                Send Verification Code
                                            </button>
                                        </div>

                                        <div id="unico-otp-step-2" style="display: none; margin-top: 10px;">
                                            <input type="text" id="unico-otp-input" placeholder="Enter 6-digit code" style="padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 150px; margin-right: 8px;">
                                            <button type="button" id="unico-verify-otp-btn" style="padding: 8px 16px; background: #28a745; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                                                Verify Code
                                            </button>
                                            <p id="unico-otp-message" style="margin-top: 8px; font-size: 13px;"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="unico-verification-badge" style="background: #d4edda; border: 2px solid #c3e6cb; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#155724" stroke-width="2.5">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span style="color: #155724; font-weight: 600; font-size: 14px;">
                                        ✓ Identity Verified for this purchase
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Cart Items -->
                        <?php foreach ($cart_items as $key => $item): ?>
                            <div class="unico-checkout-card" 
                                 data-voucher-qty="<?php echo esc_attr($item['quantity']); ?>"
                                 data-product-id="<?php echo esc_attr($item['product_id']); ?>">
                                <div class="unico-checkout-header">
                                    <div class="unico-checkout-label">Voucher</div>
                                    <div class="unico-checkout-title-row">
                                        <div class="unico-checkout-title"><?php echo esc_html($item['product_name']); ?></div>
                                        <div class="unico-checkout-qty">x<span id="unico-qty-display"><?php echo esc_html($item['quantity']); ?></span></div>
                                    </div>
                                </div>

                                <div class="unico-checkout-row unico-qty-row">
                                    <div class="unico-field">
                                        <label>Quantity</label>
                                        <div class="unico-qty-control">
                                            <button type="button" class="unico-qty-btn" data-direction="minus" onclick="updateCartQty('<?php echo $item['product_id']; ?>', -1)">-</button>
                                            <input type="number" name="quantity_<?php echo $key; ?>" value="<?php echo esc_attr($item['quantity']); ?>" class="unico-qty-input" readonly>
                                            <button type="button" class="unico-qty-btn" data-direction="plus" onclick="updateCartQty('<?php echo $item['product_id']; ?>', 1)">+</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="unico-checkout-row">
                                    <div class="unico-field">
                                        <label>Buyer Full Name</label>
                                        <input type="text" name="voucher_buyer_full_name" value="<?php echo esc_attr($current_user->first_name . ' ' . $current_user->last_name); ?>" required>
                                    </div>
                                    <div class="unico-field">
                                        <label>Registered Email ID</label>
                                        <input type="email" name="voucher_buyer_email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                                    </div>
                                </div>

                                <div class="unico-checkout-row unico-checkout-methods">
                                    <button type="button" class="unico-method-button is-active" data-method="bank_transfer" data-limit="10">
                                        <span>Bank Transfer</span>
                                        <span class="unico-method-note">Limit 10 units</span>
                                    </button>
                                    <input type="hidden" name="voucher_payment_mode" value="bank_transfer">
                                </div>

                                <!-- Bank Details -->
                                <?php
                                $selected_bank = null;
                                if (class_exists('Unico_Bank_Accounts')) {
                                    $bank_system = Unico_Bank_Accounts::get_instance();
                                    $selected_bank = $bank_system->get_random_active_bank();
                                }
                                ?>
                                <div class="unico-bank-details" id="bank-transfer-details" style="display: block;">
                                    <?php if ($selected_bank): ?>
                                        <div class="unico-bank-card">
                                            <div class="unico-bank-header">
                                                <span class="unico-bank-icon">🏦</span>
                                                <div>
                                                    <div class="unico-bank-title">Bank Transfer Details</div>
                                                    <div class="unico-bank-subtitle">Transfer exact amount to the account below</div>
                                                </div>
                                            </div>
                                            <div class="unico-bank-info">
                                                <div class="unico-bank-name"><?php echo esc_html($selected_bank->bank_name); ?></div>
                                                <div class="unico-bank-field">
                                                    <label>Account Holder Name</label>
                                                    <div class="unico-bank-value"><span><?php echo esc_html($selected_bank->account_holder); ?></span></div>
                                                </div>
                                                <div class="unico-bank-field">
                                                    <label>Account Number</label>
                                                    <div class="unico-bank-value">
                                                        <span id="account-number"><?php echo esc_html($selected_bank->account_number); ?></span>
                                                        <button type="button" class="unico-copy-btn" onclick="copyToClipboard('account-number')">Copy</button>
                                                    </div>
                                                </div>
                                                <?php if (!empty($selected_bank->ifsc_code)): ?>
                                                    <div class="unico-bank-field">
                                                        <label>IFSC Code</label>
                                                        <div class="unico-bank-value">
                                                            <span id="ifsc-code"><?php echo esc_html($selected_bank->ifsc_code); ?></span>
                                                            <button type="button" class="unico-copy-btn" onclick="copyToClipboard('ifsc-code')">Copy</button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" name="selected_bank_id" value="<?php echo esc_attr($selected_bank->id); ?>">
                                    <?php endif; ?>
                                </div>

                                <!-- Transaction ID & Receipt Upload -->
                                <div class="unico-checkout-row">
                                    <div class="unico-field">
                                        <label>Transaction ID / Reference Number</label>
                                        <input type="text" name="voucher_payment_reference" placeholder="Enter transaction ID" required>
                                    </div>
                                </div>

                                <div class="unico-checkout-row">
                                    <div class="unico-field">
                                        <label>Upload Payment Receipt</label>
                                        <div class="unico-upload-box">
                                            <input type="file" name="voucher_payment_receipt" class="unico-upload-input" accept="image/*,.pdf" required>
                                            <div class="unico-upload-placeholder">
                                                <span>Click to upload receipt</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Terms & Submit -->
                        <div class="unico-checkout-footer">
                            <label class="unico-terms-label">
                                <input type="checkbox" name="voucher_terms_confirmed" value="1" required>
                                I confirm that the details provided are accurate and I agree to the non-refundable terms.
                            </label>

                            <button type="submit" class="unico-submit-btn">Place Order</button>
                        </div>

                    </div>
                    
                    <!-- Right Column: Order Summary (Optional, but good to have) -->
                    <div class="unico-checkout-sidebar">
                        <div class="unico-summary-card">
                            <h3>Order Summary</h3>
                            <div class="unico-summary-row">
                                <span>Subtotal</span>
                                <span><?php echo $cart->get_total('display'); ?></span>
                            </div>
                            <div class="unico-summary-total">
                                <span>Total</span>
                                <span><?php echo $cart->get_total('display'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
