# Unico WooCommerce Checkout Plugin

Complete WooCommerce checkout solution with bank transfer payment, random bank account selection, payment proof upload, and on-demand voucher generation.

## Features

✅ **Custom Payment Gateway**
- Bank Transfer payment method
- Random active bank account selection per order
- Bank account details fixed for each order (no changes on refresh)

✅ **Required Checkout Fields**
- Transaction ID (text input) - Required
- Payment Proof Upload (screenshot) - Required
- File validation (JPG, PNG, WEBP - Max 5MB)

✅ **Custom Order Status**
- `under-review` - Orders awaiting payment verification
- `rejected` - Orders with payment verification failed

✅ **Admin Features**
- Bank Accounts management page (WooCommerce > Bank Accounts)
- Approve/Reject payment actions
- Quick approve/reject buttons in order meta box
- Bulk actions for multiple orders
- Payment verification column in orders list

✅ **Voucher Generation**
- Automatic on-demand voucher code generation when payment approved
- Format: PREFIX-XXXXX-XXXXX (e.g., IELT-A3B5C-7D9E2)
- Vouchers saved to order meta
- Email delivery to customer with voucher codes

✅ **Email Notifications**
- Payment approved email
- Payment rejected email
- Voucher delivery email

✅ **Customer Features**
- View voucher codes in My Account > Orders > View Order
- Copy voucher codes to clipboard
- Open support ticket button for rejected orders

✅ **Security**
- Nonce verification on all forms
- File type and size validation
- WordPress media handling for uploads
- Secure order meta storage

---

## Installation

### Prerequisites

- WordPress 5.8+
- PHP 7.4+
- **WooCommerce 6.0+** (MUST be installed and activated)

### Steps

1. **Upload Plugin**
   - Upload `unico-woocommerce-checkout` folder to `/wp-content/plugins/`
   - OR install via WP Admin > Plugins > Add New > Upload

2. **Activate Plugin**
   - Go to WP Admin > Plugins
   - Find "Unico WooCommerce Checkout"
   - Click "Activate"

3. **Configure Bank Accounts**
   - Go to WooCommerce > Bank Accounts
   - Add at least one bank account
   - Mark it as "Active"
   - Save changes

4. **Enable Payment Gateway**
   - Go to WooCommerce > Settings > Payments
   - Enable "Bank Transfer (Unico)"
   - Configure title and description
   - Save changes

5. **Test Checkout**
   - Add a product to cart
   - Go to checkout
   - Select "Bank Transfer" payment method
   - Verify bank account is displayed
   - Complete test order

---

## File Structure

```
unico-woocommerce-checkout/
├── unico-woocommerce-checkout.php    # Main plugin file
├── includes/
│   ├── class-order-status.php        # Custom order statuses
│   ├── class-bank-accounts.php       # Bank accounts management
│   ├── class-custom-payment-gateway.php  # Payment gateway
│   ├── class-checkout-fields.php     # Checkout fields handler
│   ├── class-admin-orders.php        # Admin order actions
│   ├── class-voucher-generator.php   # Voucher generation system
│   └── class-emails.php              # Email notifications
├── assets/
│   ├── css/
│   │   ├── checkout.css              # Frontend styles
│   │   └── admin.css                 # Admin styles
│   └── js/
│       ├── checkout.js               # Frontend scripts
│       └── admin.js                  # Admin scripts
└── README.md
```

---

## Usage

### For Customers

1. **Checkout Process**
   - Select product and proceed to checkout
   - Choose "Bank Transfer" payment method
   - Bank account details will be displayed (randomly selected)
   - Enter Transaction ID from your bank transfer
   - Upload payment proof screenshot
   - Click "Place Order"
   - Order status: "Under Review"

2. **After Approval**
   - Receive email with voucher codes
   - View vouchers in My Account > Orders > View Order
   - Copy voucher codes for use

3. **If Rejected**
   - Receive rejection email
   - See rejection notice on order view page
   - Click "Open Support Ticket" button

### For Admins

1. **Managing Bank Accounts**
   - Go to WooCommerce > Bank Accounts
   - Add new bank accounts with full details
   - Toggle Active/Inactive status
   - Edit or delete existing accounts

2. **Reviewing Orders**
   - Go to WooCommerce > Orders
   - Filter by "Under Review" status
   - View payment proof image inline
   - Check transaction ID

3. **Approve Payment**
   - Method 1: Click "Approve Payment" in Order Actions dropdown
   - Method 2: Click "✓ Approve Payment" button in Payment Verification meta box
   - Method 3: Select multiple orders > Bulk Actions > Approve Payments
   - Actions triggered:
     - Order status → Processing
     - Generate voucher codes
     - Send voucher email to customer
     - Send approval notification

4. **Reject Payment**
   - Method 1: Click "Reject Payment" in Order Actions dropdown
   - Method 2: Click "✗ Reject Payment" button in Payment Verification meta box
   - Method 3: Select multiple orders > Bulk Actions > Reject Payments
   - Actions triggered:
     - Order status → Rejected
     - Send rejection email to customer
     - No vouchers generated

---

## WooCommerce Hooks Used

### Filters
- `woocommerce_payment_gateways` - Register custom payment gateway
- `wc_order_statuses` - Add custom order statuses
- `woocommerce_reports_order_statuses` - Include custom statuses in reports
- `woocommerce_order_actions` - Add approve/reject actions
- `bulk_actions-edit-shop_order` - Add bulk approve/reject
- `manage_edit-shop_order_columns` - Add payment verification column

### Actions
- `woocommerce_update_options_payment_gateways_*` - Save gateway settings
- `woocommerce_thankyou_*` - Display payment info on thank you page
- `woocommerce_before_checkout_form` - Enable file upload
- `woocommerce_after_checkout_validation` - Additional validation
- `woocommerce_admin_order_data_after_billing_address` - Display payment details
- `woocommerce_email_after_order_table` - Add bank details to emails
- `woocommerce_view_order` - Display vouchers/support button
- `woocommerce_order_status_changed` - Handle status changes

### Custom Actions (for integrations)
- `unico_order_status_changed` - Fired when order status changes
- `unico_payment_approved` - Fired when payment is approved
- `unico_payment_rejected` - Fired when payment is rejected
- `unico_vouchers_delivered` - Fired when vouchers are generated

---

## Order Meta Keys

The plugin stores the following order meta data:

- `_transaction_id` - Customer's transaction/reference number
- `_payment_proof_id` - WordPress attachment ID of uploaded screenshot
- `_payment_proof_url` - URL to payment proof image
- `_selected_bank_id` - ID of bank account shown to customer
- `_bank_details` - JSON encoded bank account details
- `_payment_verified` - yes/no verification status
- `_payment_rejected` - yes if rejected
- `_vouchers_generated` - yes if vouchers generated
- `_voucher_codes` - JSON array of generated voucher codes
- `_vouchers_delivered_at` - Timestamp of voucher delivery

---

## Voucher Code Format

Generated voucher codes follow this format:

```
PREFIX-XXXXX-XXXXX
```

**Examples:**
- `IELT-A3B5C-7D9E2` (IELTS exam)
- `PTE-K8M2N-Q4R6T` (PTE exam)
- `VCHR-P9L3H-W7Y2Z` (Generic)

**Prefix Logic:**
- Uses first 4 letters of exam name if available
- Falls back to "VCHR" if no exam name

**Uniqueness:**
- Each code is checked against existing vouchers
- Regenerated if duplicate found

---

## Customization

### Change Voucher Format

Edit `includes/class-voucher-generator.php`:

```php
private function generate_voucher_code($product_id, $order_id) {
    // Your custom logic here
}
```

### Add Custom Email Templates

Hook into actions:

```php
add_action('unico_payment_approved', 'my_custom_approval_action', 10, 2);
add_action('unico_payment_rejected', 'my_custom_rejection_action', 10, 2);
```

### Integrate with Existing Voucher System

Replace the voucher generation in `includes/class-voucher-generator.php`:

```php
public function generate_and_deliver_vouchers($order_id) {
    // Your integration logic here
    // Call your existing voucher system API
}
```

---

## Troubleshooting

### "No WooCommerce" Error
- **Solution:** Install and activate WooCommerce plugin first

### "No Active Bank Accounts" Error
- **Solution:** Go to WooCommerce > Bank Accounts and add at least one active bank account

### Checkout Refreshes Without Creating Order
- **Solution:** Check PHP error logs for validation errors
- Verify file upload is working (check php.ini: `upload_max_filesize`, `post_max_size`)
- Ensure payment gateway is enabled

### Vouchers Not Generated
- **Solution:** Check order notes for errors
- Verify order status changed to "Processing"
- Check email logs for delivery issues

### File Upload Fails
- **Solution:** Check file size (max 5MB)
- Verify file type (JPG, PNG, WEBP only)
- Check WordPress upload directory permissions

---

## Support

For issues or questions:
1. Check WordPress error logs: `/wp-content/debug.log`
2. Check order notes in WooCommerce for detailed error messages
3. Verify all prerequisites are met
4. Contact your development team

---

## Changelog

### Version 1.0.0 (2026-01-23)
- Initial release
- Custom payment gateway with random bank selection
- Transaction ID and payment proof upload
- Admin approve/reject functionality
- On-demand voucher generation
- Email notifications
- Support ticket integration

---

## Credits

Developed for Unico Exam Voucher Platform
