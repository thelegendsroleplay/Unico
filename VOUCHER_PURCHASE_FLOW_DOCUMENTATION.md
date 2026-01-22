# Voucher Purchase Flow - Complete Documentation

## Overview
This document details the complete voucher purchase flow from the vouchers page to order placement, including all security checks, backend logic, and frontend components.

---

## System Architecture

### Multi-Layer Security System

**Layer 1: Email Verification (One-Time)**
- **Purpose**: Verify user owns their email address
- **Method**: Email with verification link
- **When**: Required before first purchase
- **Implementation**: `Unico_Security::is_email_verified()`

**Layer 2: Purchase OTP Verification (Every Purchase)**
- **Purpose**: Identity verification for each transaction
- **Method**: 6-digit OTP sent to email
- **When**: Required for every single purchase
- **Implementation**: `Unico_Security::is_purchase_verified()`

---

## Complete Purchase Flow

### Step 1: Vouchers Page (`/vouchers`)
**File**: `page-vouchers.php`

**Features**:
- Display voucher products from WooCommerce
- Filter by exam type (IELTS, PTE, TOEFL, etc.)
- Show pricing based on user role (Customer, Agent, Reseller)
- Purchase button logic

**Button Logic** (lines 438-460):
```php
if ($is_logged_in) {
    $button_label = 'Secure Checkout →';
    $button_url = add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
} else {
    $button_label = 'Authorize Procurement';
    $button_url = home_url('/login');
}
```

**URL Format**: `/checkout?add-to-cart=123`

---

### Step 2: Add to Cart Redirect Filter
**File**: `functions.php` (lines 588-624)
**Hook**: `woocommerce_add_to_cart_redirect`

**Security Checks**:

1. **Verify Product** (lines 592-601):
   - Check if product exists
   - Verify it's a voucher product

2. **Check Login** (lines 604-608):
   ```php
   if (!is_user_logged_in()) {
       wc_add_notice('Please log in to purchase vouchers.', 'error');
       return home_url('/login');
   }
   ```

3. **Check Email Verification** (lines 611-619):
   ```php
   if (!$security->is_email_verified($user_id)) {
       wc_add_notice('Email verification required...', 'error');
       return home_url('/email-verification?redirect=checkout');
   }
   ```

4. **Success**: Redirect to checkout (line 621)

---

### Step 3: Email Verification Page (`/email-verification?redirect=checkout`)
**File**: `page-email-verification.php`

**Scenarios Handled**:

1. **User clicked verification link** (lines 10-15, 58-104):
   - Verify token
   - Show success/error message
   - Provide "Continue to Checkout" button

2. **User already verified** (lines 39-49, 106-125):
   - Show "Already Verified" message
   - Provide "Continue to Checkout" button

3. **User needs to verify** (lines 127-170):
   - Show "Verify Email" form
   - Send verification email button
   - User checks email → clicks link → verified

4. **User not logged in** (lines 172-186):
   - Show "Login Required" message
   - Links to login/register

**After Verification**: User proceeds to checkout

---

### Step 4: Checkout Page (`/checkout`)
**File**: `page-checkout.php`

**Structure**:
```php
get_header();
// Render WooCommerce checkout
woocommerce_checkout();
get_footer();
```

**Hooks Triggered**:
- `woocommerce_checkout_before_order_review` → Custom voucher card

---

### Step 5: Custom Checkout Card
**File**: `checkout-voucher-card.php`
**Hook**: `woocommerce_checkout_before_order_review` (functions.php:626-694)

**Rendering Logic** (functions.php:653-693):
1. Get cart items
2. Extract voucher product
3. Calculate quantity and total
4. Prepare buyer information
5. Include checkout card template

**Components**:

#### A. Email Verification Status (lines 22-52)
- If NOT verified: Show warning banner
- If verified: Show green badge with email

#### B. Purchase OTP Verification (lines 54-164)
- If NOT verified: Show OTP form
- If verified: Show green badge

**OTP Flow**:
1. User clicks "Send Verification Code" (line 68)
2. AJAX: `unico_send_purchase_otp` (line 94)
3. Backend sends 6-digit OTP to email
4. User enters code (line 74)
5. User clicks "Verify Code" (line 75)
6. AJAX: `unico_verify_purchase_otp` (line 129)
7. Backend validates code
8. Page reloads → shows "Identity Verified"

#### C. Voucher Details (lines 167-189)
- Voucher title and quantity
- Quantity controls (+/- buttons)

#### D. Buyer Information (lines 192-200)
- Full name (pre-filled)
- Email (pre-filled)

#### E. Payment Method Selection (lines 207-219)
- **Bank Transfer** (limit: 10 units)
- **Card Payment** (limit: 3 units, optional)

#### F. Bank Transfer Details (lines 230-337)
- Random active bank account
- Account holder name
- Account number (with copy button)
- IFSC code (with copy button)
- SWIFT code (with copy button)
- Branch name

#### G. Payment Information (lines 338-348)
- Payment reference number (transaction ID)
- Receipt upload (image only)

#### H. Terms Confirmation (lines 349-354)
- Checkbox: "NON-REFUNDABLE" terms

#### I. Submit Button (lines 365-373)
- Disabled if email not verified
- Shows "Confirm Order" or "Verify Email to Purchase"

---

### Step 6: AJAX Handlers
**File**: `functions.php`

#### A. Send Purchase OTP (lines 697-714)
```php
add_action('wp_ajax_unico_send_purchase_otp', function() {
    // Verify nonce
    // Check user logged in
    // Call: $security->send_purchase_otp($user_id)
    // Return success/error
});
```

#### B. Verify Purchase OTP (lines 716-738)
```php
add_action('wp_ajax_unico_verify_purchase_otp', function() {
    // Verify nonce
    // Check user logged in
    // Sanitize code
    // Call: $security->verify_purchase_otp($user_id, $code)
    // Return success/error
});
```

---

### Step 7: Checkout Validation
**File**: `functions.php` (lines 740-809)
**Hook**: `woocommerce_checkout_process`

**Validation Checks**:

1. **User logged in** (lines 744-747)
2. **Email verified** (lines 750-758):
   ```php
   if (!$security->is_email_verified($user_id)) {
       wc_add_notice('Email verification required...', 'error');
       throw new Exception('Email verification required');
   }
   ```

3. **Purchase OTP verified** (lines 760-763):
   ```php
   if (!$security->is_purchase_verified($user_id)) {
       wc_add_notice('Identity verification required...', 'error');
       throw new Exception('Identity verification required');
   }
   ```

4. **Update cart quantity** (lines 765-791)
5. **Terms confirmed** (lines 792-794)
6. **Payment reference provided** (lines 795-797)
7. **Receipt uploaded** (lines 798-800)
8. **Quantity limits** (lines 801-808):
   - Card payment: max 3 units
   - Bank transfer: max 10 units

**If any validation fails**: Order placement blocked, user sees error

---

### Step 8: Receipt Upload Handler
**File**: `functions.php` (lines 811-854)
**Function**: `unico_handle_voucher_receipt_upload()`

**Process**:
1. Check file uploaded
2. Validate file type (JPG, PNG, GIF, WEBP)
3. Handle WordPress upload
4. Create attachment
5. Generate metadata
6. Return attachment ID and URL

---

### Step 9: Save Order Metadata
**File**: `functions.php` (lines 856-923)
**Hook**: `woocommerce_checkout_update_order_meta`

**Saved Data**:
- `voucher_buyer_full_name`
- `voucher_buyer_email`
- `voucher_payment_mode` (bank_transfer / card_payment)
- `voucher_payment_reference` (transaction ID)
- `voucher_terms_confirmed`
- `selected_bank_id`
- `_voucher_payment_receipt_id`
- `_voucher_payment_receipt_url`
- `_voucher_verification_status` (pending)

**Bank Details Saved** (lines 876-900):
- `_bank_name`
- `_bank_account_holder`
- `_bank_account_number`
- `_bank_ifsc_code`
- `_bank_swift_code`
- `_bank_branch`

**Order Note Added**: "Voucher order marked as pending payment verification"

---

### Step 10: Order Placed
**WooCommerce Flow**:
1. Order created
2. Order status: "Pending payment"
3. User redirected to "Thank You" page
4. Order confirmation email sent (optional)

**Clean Up** (functions.php:926-931):
```php
add_action('woocommerce_thankyou', function($order_id) {
    // Clear purchase verification for next purchase
    $security->clear_purchase_verification(get_current_user_id());
});
```

---

### Step 11: Voucher Delivery (Manual/Auto)
**File**: `includes/class-voucher-system.php`

**Auto-Delivery** (when order status changes to "completed"):
- Hook: `woocommerce_order_status_changed`
- Assigns voucher from inventory
- Sends voucher code via email
- Updates voucher status to "delivered"

**Manual Delivery** (via admin dashboard):
- Admin verifies payment
- Changes order status to "completed"
- Auto-delivery triggers

---

## Frontend Components

### JavaScript
**File**: `assets/js/checkout.js`

**Features**:
- Quantity validation (+/- buttons)
- Payment method switching
- Quantity limits enforcement
- Receipt file validation
- Bank details copy-to-clipboard
- Real-time error display

### CSS
**File**: `assets/css/checkout.css`

**Styling For**:
- Checkout card design
- Verification badges
- Bank transfer card
- Payment method buttons
- Quantity controls
- Upload field
- Submit button

---

## Security Features

### 1. Email Verification (Layer 1)
- **Class**: `Unico_Security`
- **Method**: `send_verification_email($user_id)`
- **Storage**: `wp_usermeta` → `email_verified` = 1
- **Token**: Random hash with 24-hour expiry

### 2. Purchase OTP (Layer 2)
- **Class**: `Unico_Security`
- **Method**: `send_purchase_otp($user_id)`
- **Code**: 6-digit random number
- **Storage**: Transient (10-minute expiry)
- **Key**: `unico_purchase_otp_{user_id}`

### 3. Nonce Verification
- All AJAX requests protected
- Nonce: `unico_purchase_verification`

### 4. File Upload Validation
- Only image files allowed
- MIME type checking
- WordPress sanitization

---

## Database Schema

### Order Meta Keys
```
_customer_user                      → User ID
voucher_buyer_full_name             → Buyer name
voucher_buyer_email                 → Buyer email
voucher_payment_mode                → bank_transfer / card_payment
voucher_payment_reference           → Transaction ID
selected_bank_id                    → Bank account ID
_bank_name                          → Bank name
_bank_account_holder                → Account holder
_bank_account_number                → Account number
_bank_ifsc_code                     → IFSC code
_bank_swift_code                    → SWIFT code
_bank_branch                        → Branch name
_voucher_payment_receipt_id         → Attachment ID
_voucher_payment_receipt_url        → Image URL
_voucher_verification_status        → pending / verified / rejected
voucher_terms_confirmed             → 1
```

---

## Error Handling

### Common Errors

**"Please log in to purchase vouchers"**
- User not logged in
- Redirect: `/login`

**"Email verification required"**
- Email not verified
- Redirect: `/email-verification?redirect=checkout`

**"Identity verification required"**
- Purchase OTP not verified
- Show: OTP form on checkout page

**"Transaction ID is required"**
- Payment reference empty
- Fix: Fill in transaction ID field

**"Upload of payment receipt image is required"**
- No receipt uploaded
- Fix: Upload receipt image

**"Card Payment is limited to 3 units"**
- Quantity > 3 for card payment
- Fix: Reduce quantity or switch to bank transfer

**"Bank Transfer is limited to 10 units"**
- Quantity > 10 for bank transfer
- Fix: Reduce quantity

---

## File Structure Summary

```
/home/user/Unico/
├── page-vouchers.php              → Voucher catalog page
├── page-checkout.php              → WooCommerce checkout wrapper
├── checkout-voucher-card.php      → Custom checkout card
├── page-email-verification.php    → Email verification page
├── functions.php                  → Backend logic & hooks
├── header.php                     → HTML header
├── footer.php                     → HTML footer
├── assets/
│   ├── css/
│   │   ├── checkout.css          → Checkout styling
│   │   └── auth.css              → Auth pages styling
│   └── js/
│       └── checkout.js           → Checkout interactions
└── includes/
    ├── class-security.php        → Security & verification
    ├── class-voucher-system.php  → Voucher management
    ├── class-bank-accounts.php   → Bank accounts system
    └── class-init.php            → System initialization
```

---

## Testing Checklist

### Vouchers Page
- [ ] Displays voucher products
- [ ] Filter by exam type works
- [ ] Purchase button shows correct label
- [ ] Non-logged-in users redirected to login
- [ ] Logged-in users can proceed to checkout

### Email Verification
- [ ] Unverified users redirected
- [ ] Email sent successfully
- [ ] Verification link works
- [ ] Returns to checkout after verification
- [ ] Already verified users skip this step

### Checkout Page
- [ ] Custom voucher card displays
- [ ] Email verification badge shows
- [ ] OTP verification form appears
- [ ] OTP code sent to email
- [ ] Code validation works
- [ ] Page reloads after verification

### Payment Details
- [ ] Bank details display
- [ ] Copy buttons work
- [ ] Quantity controls functional
- [ ] Payment method switching works
- [ ] Receipt upload accepts images only
- [ ] Terms checkbox required

### Order Placement
- [ ] All validations enforced
- [ ] Order created successfully
- [ ] Metadata saved correctly
- [ ] Receipt uploaded and attached
- [ ] Thank you page displays
- [ ] Purchase verification cleared

### Delivery
- [ ] Admin can verify payment
- [ ] Order status change to "completed"
- [ ] Voucher auto-delivered
- [ ] Email sent with voucher code

---

## Maintenance Notes

### Adding New Exam Types
1. Add to `$exam_filters` in `page-vouchers.php`
2. Add tagline to `$taglines` array
3. Create WooCommerce product with `exam_name` meta

### Adding New Bank Account
1. Use Management Dashboard → Bank Accounts
2. Fill in all required fields
3. Set status to "Active"

### Enabling/Disabling Card Payment
1. WooCommerce → Settings → Unico Settings
2. Toggle "Enable Card Payment" option
3. Save changes

### Customizing OTP Expiry
- File: `includes/class-security.php`
- Method: `send_purchase_otp()`
- Transient expiry: `10 * MINUTE_IN_SECONDS`

---

## Support & Troubleshooting

### Blank Page Issues
**Cause**: Duplicate HTML structure
**Fix**: Ensure templates use only `get_header()` and `get_footer()`
**Check**: No `<!DOCTYPE>`, `<html>`, `<head>`, `<body>` tags in template

### OTP Not Received
**Check**:
1. SMTP settings configured
2. Email address valid
3. Spam folder
4. Transient not expired (10 minutes)

### Receipt Upload Fails
**Check**:
1. File is an image (JPG, PNG, GIF, WEBP)
2. File size under server limit
3. Upload directory writable

### Order Not Created
**Check**:
1. All validation checks pass
2. Email verified
3. Purchase OTP verified
4. Terms confirmed
5. Receipt uploaded
6. Payment reference provided

---

## Revision History

**Version 1.0** (2026-01-21)
- Initial documentation
- Complete flow from vouchers to order placement
- All security checks documented
- Frontend and backend logic explained

---

*End of Documentation*
