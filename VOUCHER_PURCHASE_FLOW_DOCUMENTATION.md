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
- Display voucher products from Unico System
- Filter by exam type (IELTS, PTE, TOEFL, etc.)
- Show pricing based on user role (Customer, Agent, Reseller)
- Purchase button logic

**Button Logic**:
```php
if ($is_logged_in) {
    $button_label = 'Secure Checkout →';
    $button_url = add_query_arg([
        'unico_add_to_cart' => $product_id,
        'unico_add_to_cart_nonce' => wp_create_nonce('unico_add_to_cart')
    ], home_url('/'));
} else {
    $button_label = 'Authorize Procurement';
    $button_url = home_url('/login');
}
```

**URL Format**: `/?unico_add_to_cart=123&unico_add_to_cart_nonce=...`

---

### Step 2: Add to Cart Handler
**File**: `functions.php`
**Hook**: `init`

**Security Checks**:

1. **Verify Product**:
   - Check if product exists
   - Verify it's a voucher product

2. **Check Login**:
   - If not logged in, redirect to login page

3. **Check Email Verification**:
   - If email not verified, redirect to email verification page

4. **Success**:
   - Add item to `Unico_Cart`
   - Redirect to `/checkout`

---

### Step 3: Email Verification Page (`/email-verification?redirect=checkout`)
**File**: `page-email-verification.php`

**Scenarios Handled**:

1. **User clicked verification link**:
   - Verify token
   - Show success/error message
   - Provide "Continue to Checkout" button

2. **User already verified**:
   - Show "Already Verified" message
   - Provide "Continue to Checkout" button

3. **User needs to verify**:
   - Show "Verify Email" form
   - Send verification email button
   - User checks email → clicks link → verified

4. **User not logged in**:
   - Show "Login Required" message
   - Links to login/register

**After Verification**: User proceeds to checkout

---

### Step 4: Checkout Page (`/checkout`)
**File**: `page-checkout.php`

**Structure**:
```php
get_header();
// Render Custom Checkout
$checkout = Unico_Checkout::get_instance();
$checkout->render_checkout();
get_footer();
```

---

### Step 5: Custom Checkout Card
**File**: `templates/checkout-form.php` (Rendered by `Unico_Checkout`)

**Components**:

#### A. Email Verification Status
- If NOT verified: Show warning banner
- If verified: Show green badge with email

#### B. Purchase OTP Verification
- If NOT verified: Show OTP form
- If verified: Show green badge

**OTP Flow**:
1. User clicks "Send Verification Code"
2. AJAX: `unico_send_purchase_otp`
3. Backend sends 6-digit OTP to email
4. User enters code
5. User clicks "Verify Code"
6. AJAX: `unico_verify_purchase_otp`
7. Backend validates code
8. Page reloads → shows "Identity Verified"

#### C. Voucher Details
- Voucher title and quantity
- Quantity controls (+/- buttons)

#### D. Buyer Information
- Full name (pre-filled)
- Email (pre-filled)

#### E. Payment Method Selection
- **Bank Transfer** (limit: 10 units)
- **Card Payment** (limit: 3 units, optional)

#### F. Bank Transfer Details
- Random active bank account
- Account holder name
- Account number (with copy button)
- IFSC code (with copy button)
- SWIFT code (with copy button)
- Branch name

#### G. Payment Information
- Payment reference number (transaction ID)
- Receipt upload (image only)

#### H. Terms Confirmation
- Checkbox: "NON-REFUNDABLE" terms

#### I. Submit Button
- Disabled if email not verified
- Shows "Confirm Order" or "Verify Email to Purchase"

---

### Step 6: AJAX Handlers
**File**: `functions.php`

#### A. Send Purchase OTP
```php
add_action('wp_ajax_unico_send_purchase_otp', function() {
    // Verify nonce
    // Check user logged in
    // Call: $security->send_purchase_otp($user_id)
    // Return success/error
});
```

#### B. Verify Purchase OTP
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

### Step 7: Checkout Validation & Processing
**File**: `includes/class-checkout.php`
**Method**: `process_checkout()`

**Validation Checks**:

1. **User logged in**
2. **Email verified**
3. **Purchase OTP verified**
4. **Terms confirmed**
5. **Payment reference provided**
6. **Receipt uploaded**
7. **Quantity limits**

**If any validation fails**: Order placement blocked, user sees error

---

### Step 8: Receipt Upload Handler
**File**: `includes/class-checkout.php`
**Method**: `handle_receipt_upload()`

**Process**:
1. Check file uploaded
2. Validate file type (JPG, PNG, GIF, WEBP)
3. Handle WordPress upload
4. Return attachment ID and URL

---

### Step 9: Save Order
**File**: `includes/class-checkout.php`
**Method**: `create_order()`

**Saved Data (Unico Orders Table)**:
- `user_id`
- `order_number`
- `status` (pending_payment)
- `currency`
- `total_amount`
- `payment_method`
- `payment_reference`
- `receipt_url`
- `customer_name`
- `customer_email`
- `billing_details` (JSON)

**Bank Details Saved in Metadata**:
- Bank name
- Account holder
- Account number
- IFSC/SWIFT code

---

### Step 10: Order Placed
**Flow**:
1. Order created in `unico_orders` table
2. Order items created in `unico_order_items` table
3. Cart cleared
4. User redirected to "Thank You" page (`/order-received?order_id=...`)
5. Order confirmation email sent

**Clean Up**:
- Clear purchase verification for next purchase

---

### Step 11: Voucher Delivery (Manual/Auto)
**File**: `includes/class-voucher-system.php`

**Auto-Delivery** (when order status changes to "completed"):
- Assigns voucher from inventory
- Sends voucher code via email
- Updates voucher status to "delivered"

**Manual Delivery** (via Management Dashboard):
- Admin verifies payment
- Changes order status to "completed"
- Auto-delivery triggers

---

## Frontend Components

### JavaScript
**File**: `assets/js/checkout.js`

**Features**:
- Quantity validation
- Payment method switching
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

### Unico Orders Table (`wp_unico_orders`)
- `id` (Primary Key)
- `order_number` (Unique)
- `user_id`
- `status`
- `currency`
- `total_amount`
- `payment_method`
- `payment_reference`
- `receipt_url`
- `created_at`
- `updated_at`

### Unico Order Items Table (`wp_unico_order_items`)
- `id` (Primary Key)
- `order_id`
- `product_id`
- `product_name`
- `quantity`
- `price`
- `subtotal`

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

---

## File Structure Summary

```
/home/user/Unico/
├── page-vouchers.php              → Voucher catalog page
├── page-checkout.php              → Custom checkout page
├── templates/
│   └── checkout-form.php          → Custom checkout form template
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
    ├── class-order.php           → Order management
    ├── class-cart.php            → Cart management
    ├── class-checkout.php        → Checkout processing
    ├── class-voucher-system.php  → Voucher management
    ├── class-bank-accounts.php   → Bank accounts system
    └── class-init.php            → System initialization
```

---

## Maintenance Notes

### Adding New Exam Types
1. Add to `unico_get_voucher_catalog_definitions()` in `functions.php`
2. Sync products via Management Dashboard

### Adding New Bank Account
1. Use Management Dashboard → Bank Accounts
2. Fill in all required fields
3. Set status to "Active"

### Enabling/Disabling Card Payment
1. Management Dashboard → Settings
2. Toggle "Enable Card Payment" option

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

**Version 2.0** (2026-01-23)
- Removed WooCommerce dependencies
- Updated to use Unico Custom Payment System
- Updated file structure and database schema

**Version 1.0** (2026-01-21)
- Initial documentation
- Complete flow from vouchers to order placement
- All security checks documented
- Frontend and backend logic explained

---

*End of Documentation*
