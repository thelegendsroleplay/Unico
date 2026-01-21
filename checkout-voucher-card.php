<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="unico-checkout-node">
    <?php
    // Check email verification status
    $is_email_verified = false;
    $user_email = '';
    if (is_user_logged_in() && class_exists('Unico_Security')) {
        $security = Unico_Security::get_instance();
        $user_id = get_current_user_id();
        $is_email_verified = $security->is_email_verified($user_id);
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
    }
    ?>

    <?php if (!$is_email_verified): ?>
        <div class="unico-verification-notice" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#856404" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div>
                    <strong style="color: #856404; display: block; margin-bottom: 6px;">⚠️ Email Verification Required</strong>
                    <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                        You must verify your email (<strong><?php echo esc_html($user_email); ?></strong>) before completing this purchase.
                    </p>
                    <a href="<?php echo home_url('/email-verification?redirect=checkout'); ?>" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #856404; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px;">
                        Verify Email Now →
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="unico-verification-badge" style="background: #d4edda; border: 2px solid #c3e6cb; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#155724" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
                <span style="color: #155724; font-weight: 600; font-size: 14px;">
                    ✓ Email Verified: <?php echo esc_html($user_email); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

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

        <?php
        // Get random active bank account for display
        $selected_bank = null;
        if (class_exists('Unico_Bank_Accounts')) {
            $bank_system = Unico_Bank_Accounts::get_instance();
            $selected_bank = $bank_system->get_random_active_bank();
        }
        ?>

        <!-- Bank Transfer Details (shown when bank transfer is selected) -->
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
                        <?php if (!empty($selected_bank->bank_logo_url)): ?>
                            <div class="unico-bank-logo">
                                <img src="<?php echo esc_url($selected_bank->bank_logo_url); ?>" alt="<?php echo esc_attr($selected_bank->bank_name); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="unico-bank-name"><?php echo esc_html($selected_bank->bank_name); ?></div>

                        <div class="unico-bank-field">
                            <label>Account Holder Name</label>
                            <div class="unico-bank-value">
                                <span><?php echo esc_html($selected_bank->account_holder); ?></span>
                            </div>
                        </div>

                        <div class="unico-bank-field">
                            <label>Account Number</label>
                            <div class="unico-bank-value">
                                <span id="account-number"><?php echo esc_html($selected_bank->account_number); ?></span>
                                <button type="button" class="unico-copy-btn" onclick="copyToClipboard('account-number', this)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($selected_bank->ifsc_code)): ?>
                        <div class="unico-bank-field">
                            <label>IFSC Code</label>
                            <div class="unico-bank-value">
                                <span id="ifsc-code"><?php echo esc_html($selected_bank->ifsc_code); ?></span>
                                <button type="button" class="unico-copy-btn" onclick="copyToClipboard('ifsc-code', this)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($selected_bank->swift_code)): ?>
                        <div class="unico-bank-field">
                            <label>SWIFT Code</label>
                            <div class="unico-bank-value">
                                <span id="swift-code"><?php echo esc_html($selected_bank->swift_code); ?></span>
                                <button type="button" class="unico-copy-btn" onclick="copyToClipboard('swift-code', this)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($selected_bank->branch_name)): ?>
                        <div class="unico-bank-field">
                            <label>Branch</label>
                            <div class="unico-bank-value">
                                <span><?php echo esc_html($selected_bank->branch_name); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="unico-bank-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div>
                            <strong>Important:</strong> Transfer the exact amount displayed below and upload the payment receipt with transaction ID.
                        </div>
                    </div>
                </div>
                <input type="hidden" name="selected_bank_id" value="<?php echo esc_attr($selected_bank->id); ?>">
            <?php else: ?>
                <div class="unico-bank-card" style="background: #fff3cd; border-color: #ffc107;">
                    <div class="unico-bank-notice">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <div>
                            <strong>Notice:</strong> No bank accounts are currently configured. Please contact support or use card payment.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
                <button type="submit" class="unico-confirm-button" <?php echo !$is_email_verified ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    <?php echo $is_email_verified ? 'Confirm Order' : '🔒 Verify Email to Purchase'; ?>
                </button>
                <?php if (!$is_email_verified): ?>
                    <p style="font-size: 12px; color: #856404; margin: 8px 0 0; text-align: center;">
                        Email verification required before purchase
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
