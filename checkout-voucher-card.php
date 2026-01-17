<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="unico-checkout-node">
    <div class="unico-checkout-card" data-voucher-qty="<?php echo esc_attr(max(1, $voucher_qty)); ?>">
        <div class="unico-checkout-header">
            <div class="unico-checkout-label">Checkout Node</div>
            <div class="unico-checkout-title-row">
                <div class="unico-checkout-title"><?php echo esc_html($title); ?></div>
                <div class="unico-checkout-qty">x<?php echo esc_html(max(1, $voucher_qty)); ?></div>
            </div>
        </div>
        <div class="unico-checkout-row unico-qty-row">
            <div class="unico-field">
                <label>Quantity</label>
                <div class="unico-qty-control">
                    <button type="button" class="unico-qty-btn" data-direction="minus">-</button>
                    <input
                        type="number"
                        name="voucher_cart_quantity"
                        min="1"
                        value="<?php echo esc_attr(max(1, $voucher_qty)); ?>"
                        class="unico-qty-input"
                    >
                    <button type="button" class="unico-qty-btn" data-direction="plus">+</button>
                </div>
            </div>
        </div>
        <div class="unico-checkout-row">
            <div class="unico-field">
                <label>Buyer Full Name</label>
                <input type="text" name="voucher_buyer_full_name" value="<?php echo esc_attr($buyer_name); ?>">
            </div>
            <div class="unico-field">
                <label>Registered Email ID</label>
                <input type="email" name="voucher_buyer_email" value="<?php echo esc_attr($buyer_email); ?>">
            </div>
        </div>
        <div class="unico-checkout-row unico-checkout-methods">
            <button type="button" class="unico-method-button is-active" data-method="bank_transfer" data-limit="10">
                <span>Bank Transfer</span>
                <span class="unico-method-note">Limit 10 units</span>
            </button>
            <button type="button" class="unico-method-button" data-method="card_payment" data-limit="3">
                <span>Card Payment</span>
                <span class="unico-method-note">Limit 3 units</span>
            </button>
            <input type="hidden" name="voucher_payment_mode" value="bank_transfer">
        </div>
        <div class="unico-field">
            <label>Payment Reference Number</label>
            <input type="text" name="voucher_payment_reference" placeholder="Transaction ID...">
        </div>
        <div class="unico-field unico-upload-field">
            <label>Upload Transfer Receipt</label>
            <div class="unico-upload-shell">
                <input type="file" name="voucher_payment_receipt" accept="image/*" class="unico-upload-input">
                <span class="unico-upload-placeholder">Click to upload receipt</span>
            </div>
        </div>
        <div class="unico-confirm-row">
            <label>
                <input type="checkbox" name="voucher_terms_confirmed" value="1">
                <span>I confirm accuracy. Items are <strong class="unico-non-refundable">NON-REFUNDABLE</strong> and cannot be exchanged once fulfillment begins.</span>
            </label>
        </div>
        <div class="unico-checkout-footer">
            <div class="unico-total-block">
                <div class="unico-total-label">Total Settlement</div>
                <div class="unico-total-value">
                    <span class="unico-total-symbol"><?php echo esc_html($symbol); ?></span>
                    <?php echo esc_html($total_display); ?>
                    <span class="unico-total-currency"><?php echo esc_html($currency); ?></span>
                </div>
            </div>
            <div class="unico-checkout-actions">
                <button type="submit" class="unico-confirm-button">Confirm Order</button>
            </div>
        </div>
    </div>
</div>
