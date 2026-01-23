<?php
/**
 * Template Name: Checkout
 * Custom voucher checkout page - fully responsive
 */

// Force redirect to login if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
    exit;
}

if (!session_id()) {
    session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $post_max = ini_get('post_max_size');
    $content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    $to_bytes = function ($value) {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 'g':
                return (int) ($number * 1024 * 1024 * 1024);
            case 'm':
                return (int) ($number * 1024 * 1024);
            case 'k':
                return (int) ($number * 1024);
            default:
                return (int) $number;
        }
    };

    if ($content_length > 0 && $content_length > $to_bytes($post_max)) {
        $errors[] = 'Upload too large. Please use a smaller payment screenshot and try again.';
    } else {
        $errors[] = 'Your submission could not be processed. Please try again and ensure the form is fully completed.';
    }
}

// Get current user and verification status
$current_user = wp_get_current_user();
$is_purchase_verified = false;
if (class_exists('Unico_Security')) {
    $security = Unico_Security::get_instance();
    $is_purchase_verified = $security->is_purchase_verified(get_current_user_id());
}

// Get bank details
$selected_bank = null;
if (class_exists('Unico_Bank_Accounts')) {
    $bank_system = Unico_Bank_Accounts::get_instance();
    $session_bank_id = isset($_SESSION['unico_checkout_bank_id']) ? intval($_SESSION['unico_checkout_bank_id']) : null;

    if ($session_bank_id) {
        $selected_bank = $bank_system->get_bank($session_bank_id);
        if ($selected_bank && (int) $selected_bank->is_active !== 1) {
            $selected_bank = null;
        }
    }

    if (!$selected_bank) {
        $last_bank_id = intval(get_user_meta(get_current_user_id(), 'unico_last_bank_id', true));
        $selected_bank = $bank_system->get_random_active_bank($last_bank_id ? [$last_bank_id] : []);
        if (!$selected_bank) {
            $selected_bank = $bank_system->get_random_active_bank();
        }
        if ($selected_bank) {
            $_SESSION['unico_checkout_bank_id'] = $selected_bank->id;
        }
    }
}

$bank_unavailable = !$is_empty && !$selected_bank;
if ($bank_unavailable) {
    $errors[] = 'Bank transfer is currently unavailable. Please contact support or try again later.';
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
    max-width: 1140px;
    margin: 0 auto;
}

.checkout-title {
    font-size: 28px;
    font-weight: 800;
    color: #103e54;
    margin-bottom: 24px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
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
    gap: 24px;
}

.checkout-main {
    flex: 1;
}

.checkout-sidebar {
    width: 100%;
}

/* Cards */
.checkout-card {
    background: #fff;
    border-radius: 20px;
    padding: 24px 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

/* Verification Notice */
.verification-box {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 20px;
    background: #f3f4f6;
    border: 2px solid #d1d5db;
    border-radius: 14px;
    margin-bottom: 20px;
}

.verification-box.verified {
    background: #d1fae5;
    border-color: #a7f3d0;
}

.verification-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: #e5e7eb;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.verification-box.verified .verification-icon {
    background: #34d399;
    color: #fff;
}

.verification-content {
    flex: 1;
}

.verification-title {
    font-weight: 700;
    color: #374151;
    margin-bottom: 6px;
    font-size: 15px;
}

.verification-box.verified .verification-title {
    color: #065f46;
}

.verification-text {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 12px;
}

.verification-btn {
    display: inline-block;
    padding: 10px 20px;
    background: #374151;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.2s;
}

.verification-btn:hover {
    background: #1f2937;
}

.verification-btn.verify {
    background: #10b981;
}

.verification-btn.verify:hover {
    background: #059669;
}

.otp-input-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-top: 12px;
}

.otp-input {
    padding: 10px 14px;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
    width: 140px;
    text-align: center;
    letter-spacing: 0.2em;
}

.otp-input:focus {
    outline: none;
    border-color: #10b981;
}

.otp-message {
    width: 100%;
    margin-top: 8px;
    font-size: 13px;
}

/* Card Header */
.card-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: #9ca3af;
    margin-bottom: 6px;
}

.card-title {
    font-size: 20px;
    font-weight: 800;
    color: #103e54;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.card-qty {
    font-size: 14px;
    color: #6b7280;
    font-weight: 600;
}

/* Form Fields */
.form-row {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 20px;
}

.form-group {
    flex: 1;
}

.form-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #6b7280;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: border-color 0.2s;
    background: #fff;
}

.form-input:focus {
    outline: none;
    border-color: #103e54;
}

/* Quantity Controls */
.qty-controls {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.qty-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 10px;
    font-size: 20px;
    font-weight: 600;
    color: #103e54;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn:hover {
    background: #103e54;
    border-color: #103e54;
    color: #fff;
}

.qty-value {
    width: 60px;
    padding: 10px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    text-align: center;
    font-size: 16px;
    font-weight: 600;
}

/* Payment Methods */
.payment-methods {
    display: grid;
    gap: 12px;
    margin-bottom: 20px;
}

.payment-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-radius: 14px;
    border: 2px solid #e2e8f0;
    background: #fff;
    color: #0f172a;
    transition: border-color 0.2s ease;
}

.payment-option.is-active {
    border-color: #0f766e;
    box-shadow: 0 10px 24px rgba(15, 118, 110, 0.12);
}

.payment-option.is-disabled {
    opacity: 0.6;
    background: #f8fafc;
}

.payment-option input {
    margin-right: 10px;
}

.payment-option-title {
    font-size: 15px;
    font-weight: 700;
}

.payment-option-note {
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.payment-option-badge {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 600;
}

.payment-option.is-active .payment-option-badge {
    background: #ccfbf1;
    color: #0f766e;
}

/* OTP Modal */
.otp-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.otp-modal.is-open {
    display: flex;
}

.otp-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
}

.otp-modal-card {
    position: relative;
    z-index: 2;
    background: #fff;
    border-radius: 20px;
    padding: 28px;
    width: min(420px, 90vw);
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
}

.otp-modal-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.otp-modal-text {
    font-size: 14px;
    color: #475569;
    margin-bottom: 18px;
}

.otp-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: transparent;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #64748b;
}

.otp-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 16px;
}

.otp-modal .otp-input {
    flex: 1;
    min-width: 160px;
}

/* Bank Card */
.bank-card {
    background: linear-gradient(135deg, #f8fafc, #fff);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.bank-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e2e8f0;
}

.bank-icon {
    font-size: 28px;
}

.bank-title {
    font-size: 17px;
    font-weight: 700;
    color: #103e54;
}

.bank-subtitle {
    font-size: 13px;
    color: #64748b;
}

.bank-name {
    font-size: 22px;
    font-weight: 700;
    color: #103e54;
    text-align: center;
    margin-bottom: 16px;
}

.bank-field {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
}

.bank-field:last-child {
    margin-bottom: 0;
}

.bank-field-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    margin-bottom: 6px;
    font-weight: 600;
}

.bank-field-value {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.bank-field-text {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    font-family: 'Courier New', monospace;
    word-break: break-all;
}

.copy-btn {
    padding: 8px 14px;
    background: #e8f4f8;
    border: 1px solid #b8d4e0;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #103e54;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
}

.copy-btn:hover {
    background: #d1e8f0;
}

/* File Upload */
.upload-box {
    position: relative;
    border: 2px dashed #d1d5db;
    border-radius: 14px;
    padding: 30px 20px;
    text-align: center;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.2s;
}

.upload-box:hover {
    border-color: #103e54;
    background: #f0f7fa;
}

.upload-box input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.upload-icon {
    font-size: 36px;
    margin-bottom: 10px;
}

.upload-text {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.upload-text strong {
    color: #103e54;
}

.upload-hint {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 6px;
}

.upload-preview {
    display: none;
    margin-top: 12px;
    padding: 12px;
    background: #d1fae5;
    border-radius: 8px;
    font-size: 13px;
    color: #065f46;
    font-weight: 500;
}

/* Order Summary */
.summary-card {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    position: sticky;
    top: 100px;
}

.summary-title {
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #103e54;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 2px solid #e5e7eb;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 14px;
    color: #6b7280;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    padding: 16px 0 0;
    margin-top: 12px;
    border-top: 2px solid #e5e7eb;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

/* Terms & Submit */
.checkout-footer {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    margin-top: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.terms-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
    color: #374151;
    line-height: 1.5;
    cursor: pointer;
    margin-bottom: 20px;
}

.terms-label input[type="checkbox"] {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    margin-top: 2px;
    accent-color: #103e54;
}

.submit-btn {
    width: 100%;
    padding: 18px 32px;
    background: #f97316;
    color: #fff;
    border: none;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 8px 25px rgba(249, 115, 22, 0.35);
}

.submit-btn:hover {
    background: #ea580c;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(249, 115, 22, 0.4);
}

/* Desktop Styles */
@media (min-width: 768px) {
    .checkout-wrapper {
        padding: 40px 24px 80px;
    }

    .checkout-title {
        font-size: 36px;
    }

    .checkout-grid {
        flex-direction: row;
        align-items: flex-start;
    }

    .checkout-main {
        flex: 1;
        min-width: 0;
    }

    .checkout-sidebar {
        width: 340px;
        flex-shrink: 0;
    }

    .checkout-card {
        padding: 28px;
        border-radius: 24px;
    }

    .form-row {
        flex-direction: row;
    }

    .submit-btn {
        width: auto;
        min-width: 220px;
        float: right;
    }
}

@media (min-width: 1024px) {
    .checkout-sidebar {
        width: 380px;
    }

    .checkout-card {
        padding: 32px;
    }
}
</style>

<div class="checkout-wrapper">
    <div class="checkout-container">
        <h1 class="checkout-title">Checkout</h1>

        <?php if (!empty($errors)): ?>
            <div class="checkout-alert checkout-alert-error">
                <?php foreach ($errors as $error): ?>
                    <p style="margin: 0 0 5px;"><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($notices)): ?>
            <div class="checkout-alert checkout-alert-info">
                <?php foreach ($notices as $notice): ?>
                    <p style="margin: 0 0 5px;"><?php echo esc_html($notice); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_empty): ?>
            <div class="checkout-empty">
                <p>Your cart is empty.</p>
                <a href="<?php echo esc_url(home_url('/vouchers')); ?>" class="checkout-empty-btn">Browse Vouchers</a>
            </div>
        <?php else: ?>
            <form action="" method="post" enctype="multipart/form-data" id="checkout-form">
                <?php wp_nonce_field('unico_checkout_action', 'unico_checkout_nonce'); ?>
                <input type="hidden" name="unico_checkout" value="1">
                <input type="hidden" name="voucher_payment_mode" value="bank_transfer">

                <div class="checkout-grid">
                    <!-- Main Column -->
                    <div class="checkout-main">

                        <!-- Verification Box -->
                        <?php if (!$is_purchase_verified): ?>
                            <div class="verification-box">
                                <div class="verification-icon">🔒</div>
                                <div class="verification-content">
                                    <div class="verification-title">Purchase Verification Required</div>
                                    <div class="verification-text">
                                        We'll send a verification code to <strong><?php echo esc_html($current_user->user_email); ?></strong>
                                    </div>

                                    <button type="button" id="unico-open-otp-modal" class="verification-btn">
                                        Verify with Email OTP
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="verification-box verified">
                                <div class="verification-icon">✓</div>
                                <div class="verification-content">
                                    <div class="verification-title">Identity Verified</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Cart Items -->
                        <?php foreach ($cart_items as $key => $item): ?>
                            <div class="checkout-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                                    <div>
                                        <div class="card-label">Voucher</div>
                                        <div class="card-title"><?php echo esc_html($item['product_name']); ?></div>
                                    </div>
                                    <div class="card-qty">x<?php echo esc_html($item['quantity']); ?></div>
                                </div>

                                <!-- Quantity -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Quantity</label>
                                        <div class="qty-controls">
                                            <button type="button" class="qty-btn" onclick="updateCartQty('<?php echo esc_attr($item['product_id']); ?>', -1)">−</button>
                                            <input type="text" class="qty-value" value="<?php echo esc_attr($item['quantity']); ?>" readonly>
                                            <button type="button" class="qty-btn" onclick="updateCartQty('<?php echo esc_attr($item['product_id']); ?>', 1)">+</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Buyer Details -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Buyer Full Name</label>
                                        <input type="text" name="voucher_buyer_full_name" class="form-input"
                                               value="<?php echo esc_attr($current_user->display_name ?: $current_user->user_login); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="voucher_buyer_email" class="form-input"
                                               value="<?php echo esc_attr($current_user->user_email); ?>" required>
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="payment-methods">
                                    <label class="payment-option is-active">
                                        <div>
                                            <div class="payment-option-title">
                                                Bank Transfer
                                            </div>
                                            <div class="payment-option-note">Limit 10 units per order · Manual verification</div>
                                        </div>
                                        <span class="payment-option-badge">Enabled</span>
                                    </label>
                                    <label class="payment-option is-disabled">
                                        <div>
                                            <div class="payment-option-title">
                                                Card Payment
                                            </div>
                                            <div class="payment-option-note">Coming soon · Disabled for now</div>
                                        </div>
                                        <span class="payment-option-badge">Disabled</span>
                                    </label>
                                </div>

                                <!-- Bank Details -->
                                <?php if ($selected_bank): ?>
                                    <div class="bank-card">
                                        <div class="bank-header">
                                            <span class="bank-icon">🏦</span>
                                            <div>
                                                <div class="bank-title">Bank Transfer Details</div>
                                                <div class="bank-subtitle">Transfer exact amount to the account below</div>
                                            </div>
                                        </div>

                                        <div class="bank-name"><?php echo esc_html($selected_bank->bank_name); ?></div>

                                        <div class="bank-field">
                                            <div class="bank-field-label">Account Holder Name</div>
                                            <div class="bank-field-value">
                                                <span class="bank-field-text"><?php echo esc_html($selected_bank->account_holder); ?></span>
                                            </div>
                                        </div>

                                        <div class="bank-field">
                                            <div class="bank-field-label">Account Number</div>
                                            <div class="bank-field-value">
                                                <span class="bank-field-text" id="account-number"><?php echo esc_html($selected_bank->account_number); ?></span>
                                                <button type="button" class="copy-btn" onclick="copyToClipboard('account-number', this)">Copy</button>
                                            </div>
                                        </div>

                                        <?php if (!empty($selected_bank->ifsc_code)): ?>
                                            <div class="bank-field">
                                                <div class="bank-field-label">IFSC Code</div>
                                                <div class="bank-field-value">
                                                    <span class="bank-field-text" id="ifsc-code"><?php echo esc_html($selected_bank->ifsc_code); ?></span>
                                                    <button type="button" class="copy-btn" onclick="copyToClipboard('ifsc-code', this)">Copy</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($selected_bank->swift_code)): ?>
                                            <div class="bank-field">
                                                <div class="bank-field-label">SWIFT Code</div>
                                                <div class="bank-field-value">
                                                    <span class="bank-field-text" id="swift-code"><?php echo esc_html($selected_bank->swift_code); ?></span>
                                                    <button type="button" class="copy-btn" onclick="copyToClipboard('swift-code', this)">Copy</button>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($selected_bank->branch_name)): ?>
                                            <div class="bank-field">
                                                <div class="bank-field-label">Branch Name</div>
                                                <div class="bank-field-value">
                                                    <span class="bank-field-text"><?php echo esc_html($selected_bank->branch_name); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="selected_bank_id" value="<?php echo esc_attr($selected_bank->id); ?>">
                                <?php endif; ?>

                                <!-- Transaction ID -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Transaction ID / Reference Number</label>
                                        <input type="text" name="voucher_payment_reference" class="form-input"
                                               placeholder="Enter your payment reference" required>
                                    </div>
                                </div>

                                <!-- Receipt Upload -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Upload Payment Screenshot</label>
                                        <div class="upload-box" id="upload-box">
                                            <input type="file" name="voucher_payment_receipt" id="receipt-input"
                                                   accept="image/png,image/jpeg,image/jpg,image/webp,image/gif" required>
                                            <div class="upload-icon">📄</div>
                                            <div class="upload-text">
                                                <strong>Click to upload</strong> or drag and drop
                                            </div>
                                            <div class="upload-hint">PNG, JPG, or WEBP up to 5MB</div>
                                        </div>
                                        <div class="upload-preview" id="upload-preview"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Terms & Submit (Mobile) -->
                        <div class="checkout-footer">
                            <label class="terms-label">
                                <input type="checkbox" name="voucher_terms_confirmed" value="1" required>
                                <span>I confirm that the details provided are accurate and I agree to the non-refundable terms.</span>
                            </label>
                            <button type="submit" class="submit-btn" <?php echo $bank_unavailable ? 'disabled' : ''; ?>>
                                <?php echo $bank_unavailable ? 'Bank Unavailable' : 'Place Order'; ?>
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="checkout-sidebar">
                        <div class="summary-card">
                            <h3 class="summary-title">Order Summary</h3>

                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-row">
                                    <span><?php echo esc_html($item['product_name']); ?> × <?php echo esc_html($item['quantity']); ?></span>
                                    <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div class="summary-total">
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

<?php if (!$is_purchase_verified): ?>
    <div class="otp-modal" id="otp-modal" aria-hidden="true">
        <div class="otp-modal-overlay" data-otp-close></div>
        <div class="otp-modal-card" role="dialog" aria-modal="true" aria-labelledby="otp-modal-title">
            <button type="button" class="otp-modal-close" data-otp-close aria-label="Close">×</button>
            <div class="otp-modal-title" id="otp-modal-title">Email OTP Verification</div>
            <div class="otp-modal-text">
                We will send a 6-digit code to <strong><?php echo esc_html($current_user->user_email); ?></strong>. Enter it below to verify this purchase.
            </div>

            <div id="otp-step-1">
                <button type="button" id="unico-send-otp-btn" class="verification-btn">
                    Send Verification Code
                </button>
            </div>

            <div id="otp-step-2" style="display: none;">
                <div class="otp-input-row">
                    <input type="text" id="unico-otp-input" class="otp-input" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    <button type="button" id="unico-verify-otp-btn" class="verification-btn verify">
                        Verify
                    </button>
                </div>
            </div>
            <div id="otp-message" class="otp-message" role="status" aria-live="polite"></div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload preview
    var receiptInput = document.getElementById('receipt-input');
    var uploadBox = document.getElementById('upload-box');
    var uploadPreview = document.getElementById('upload-preview');

    if (receiptInput) {
        receiptInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                uploadPreview.textContent = '✓ ' + file.name;
                uploadPreview.style.display = 'block';
                uploadBox.style.borderColor = '#10b981';
                uploadBox.style.background = '#f0fdf4';
            }
        });
    }

    var otpModal = document.getElementById('otp-modal');
    var openOtpBtn = document.getElementById('unico-open-otp-modal');
    var closeOtpBtns = document.querySelectorAll('[data-otp-close]');

    function openOtpModal() {
        if (!otpModal) return;
        otpModal.classList.add('is-open');
        otpModal.setAttribute('aria-hidden', 'false');
        var input = document.getElementById('unico-otp-input');
        if (input) {
            input.focus();
        }
    }

    function closeOtpModal() {
        if (!otpModal) return;
        otpModal.classList.remove('is-open');
        otpModal.setAttribute('aria-hidden', 'true');
    }

    if (openOtpBtn) {
        openOtpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openOtpModal();
        });
    }

    closeOtpBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeOtpModal();
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeOtpModal();
        }
    });

    // OTP Send Button
    var sendOtpBtn = document.getElementById('unico-send-otp-btn');
    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Sending...';

            jQuery.ajax({
                url: unicoCheckout.ajax_url,
                type: 'POST',
                data: {
                    action: 'unico_send_purchase_otp',
                    nonce: unicoCheckout.nonce_verification
                },
                success: function(response) {
                    if (response.success) {
                        document.getElementById('otp-step-1').style.display = 'none';
                        document.getElementById('otp-step-2').style.display = 'block';
                        document.getElementById('otp-message').textContent = response.data.message;
                        document.getElementById('otp-message').style.color = '#059669';
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Send Verification Code';
                        document.getElementById('otp-message').textContent = response.data.message || 'Failed to send code';
                        document.getElementById('otp-message').style.color = '#dc2626';
                    }
                },
                error: function() {
                    btn.disabled = false;
                    btn.textContent = 'Send Verification Code';
                    document.getElementById('otp-message').textContent = 'Network error. Please try again.';
                    document.getElementById('otp-message').style.color = '#dc2626';
                }
            });
        });
    }

    // OTP Verify Button
    var verifyOtpBtn = document.getElementById('unico-verify-otp-btn');
    if (verifyOtpBtn) {
        var otpInput = document.getElementById('unico-otp-input');
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }

        verifyOtpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var btn = this;
            var code = document.getElementById('unico-otp-input').value;
            var message = document.getElementById('otp-message');

            if (!code || code.length < 6) {
                message.textContent = 'Please enter the 6-digit verification code';
                message.style.color = '#dc2626';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Verifying...';

            jQuery.ajax({
                url: unicoCheckout.ajax_url,
                type: 'POST',
                data: {
                    action: 'unico_verify_purchase_otp',
                    code: code,
                    nonce: unicoCheckout.nonce_verification
                },
                success: function(response) {
                    if (response.success) {
                        message.textContent = '✓ Verified! Reloading...';
                        message.style.color = '#059669';
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Verify';
                        message.textContent = response.data.message || 'Invalid code';
                        message.style.color = '#dc2626';
                    }
                },
                error: function() {
                    btn.disabled = false;
                    btn.textContent = 'Verify';
                    message.textContent = 'Network error. Please try again.';
                    message.style.color = '#dc2626';
                }
            });
        });
    }
});

// Copy to clipboard function
function copyToClipboard(elementId, button) {
    var element = document.getElementById(elementId);
    if (!element) return;

    var text = element.textContent || element.innerText;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            if (button) {
                var originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = '#d1fae5';
                button.style.color = '#065f46';
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.style.color = '';
                }, 2000);
            }
        });
    } else {
        // Fallback
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        if (button) {
            button.textContent = 'Copied!';
            setTimeout(function() {
                button.textContent = 'Copy';
            }, 2000);
        }
    }
}

// Update cart quantity
function updateCartQty(productId, change) {
    jQuery.ajax({
        url: unicoCheckout.ajax_url,
        type: 'POST',
        data: {
            action: 'unico_update_cart_quantity',
            product_id: productId,
            change: change,
            nonce: unicoCheckout.nonce_update_cart
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Failed to update quantity');
            }
        },
        error: function() {
            alert('Network error. Please try again.');
        }
    });
}
</script>

<?php get_footer(); ?>
