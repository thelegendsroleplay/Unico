# Unico WooCommerce Checkout - Quick Start Guide

## 🚀 IMPORTANT: Setup Steps Required

Your new WooCommerce checkout system has been created successfully! Follow these steps to get it working:

---

## Step 1: Install WooCommerce (REQUIRED)

**WooCommerce is NOT currently installed on your site.** You must install it first.

### Option A: Install via WP Admin (Recommended)

1. Go to **WP Admin > Plugins > Add New**
2. Search for **"WooCommerce"**
3. Click **"Install Now"** on the WooCommerce plugin
4. Click **"Activate"**
5. Follow WooCommerce setup wizard (you can skip most steps)

### Option B: Install via WP-CLI

```bash
wp plugin install woocommerce --activate
```

---

## Step 2: Activate the Custom Checkout Plugin

1. Go to **WP Admin > Plugins**
2. Find **"Unico WooCommerce Checkout"**
3. Click **"Activate"**

---

## Step 3: Add Bank Accounts

1. Go to **WooCommerce > Bank Accounts**
2. Fill in the "Add New Bank Account" form:
   - Bank Name (e.g., HDFC Bank)
   - Account Holder Name
   - Account Number
   - IFSC Code (optional)
   - SWIFT Code (optional)
   - Branch (optional)
   - Check "Active" checkbox
3. Click **"Add Bank Account"**
4. **Repeat to add at least 10 bank accounts** for proper random selection

---

## Step 4: Enable Payment Gateway

1. Go to **WooCommerce > Settings > Payments**
2. Find **"Bank Transfer (Unico)"**
3. Toggle to **Enable**
4. Click **"Manage"** to configure:
   - Title: "Bank Transfer" (or customize)
   - Description: Payment method description for customers
   - Instructions: Thank you page instructions
5. Click **"Save changes"**

**Optional:** Disable other payment methods (BACS, COD, etc.)

---

## Step 5: Create Test Products

If you don't have WooCommerce products yet:

1. Go to **Products > Add New**
2. Create a test product:
   - Name: "IELTS Exam Voucher"
   - Price: $100
   - Add custom field: `exam_name` = "IELTS" (for voucher prefix)
3. **Publish**

---

## Step 6: Test the Checkout Flow

### Complete Test Order

1. **Add product to cart**
   - Visit shop page
   - Click "Add to Cart"

2. **Go to checkout**
   - View Cart > Proceed to Checkout

3. **Fill in checkout form**
   - Billing details
   - Select "Bank Transfer (Unico)" payment method
   - Verify random bank account is displayed
   - Enter test Transaction ID: "TEST123456"
   - Upload a test image (any JPG/PNG under 5MB)

4. **Place order**
   - Click "Place Order"
   - Should redirect to "Order Received" page
   - Order status: **"Under Review"**

### Admin Review Process

1. **Go to WooCommerce > Orders**
2. Find your test order
3. Click to view details
4. **Scroll to "Payment Verification" meta box** (right sidebar)
5. View uploaded payment proof
6. Click **"✓ Approve Payment"**

### Verify Voucher Delivery

1. Check your email (order email address)
2. Should receive:
   - Payment Approval Email
   - Voucher Codes Email with generated codes
3. Go to **My Account > Orders > View Order**
4. **Verify voucher codes are displayed**

---

## Step 7: Verify Everything Works

### Checklist

- [ ] WooCommerce installed and activated
- [ ] Unico plugin activated
- [ ] At least 1 bank account added (recommend 10+)
- [ ] Payment gateway enabled
- [ ] Test order placed successfully
- [ ] Order status shows "Under Review"
- [ ] Payment proof visible in admin
- [ ] Admin can approve order
- [ ] Voucher codes generated
- [ ] Email received with vouchers
- [ ] Customer can view vouchers in My Account

---

## Common Issues & Solutions

### Issue: "No active bank accounts available"
**Solution:** Add bank accounts in WooCommerce > Bank Accounts and mark as "Active"

### Issue: Checkout page refreshes without creating order
**Solutions:**
- Check if WooCommerce is active
- Check if payment gateway is enabled
- Check PHP error logs for validation errors
- Verify file upload limits in php.ini (upload_max_filesize = 10M, post_max_size = 10M)

### Issue: File upload fails
**Solutions:**
- Check file is JPG, PNG, or WEBP
- Check file size is under 5MB
- Check wp-content/uploads directory is writable

### Issue: Vouchers not generated
**Solutions:**
- Verify order was approved (not just status changed manually)
- Check order notes for error messages
- Ensure order status changed to "Processing"

---

## Configuration Options

### Payment Gateway Settings

**Location:** WooCommerce > Settings > Payments > Bank Transfer (Unico) > Manage

- **Title:** Change payment method name shown to customers
- **Description:** Instructions shown during checkout
- **Instructions:** Message shown on order received page

### Bank Accounts Management

**Location:** WooCommerce > Bank Accounts

- **Add/Edit/Delete** bank accounts
- **Toggle Active/Inactive** status
- **Edit details** inline in the table

### Order Statuses

New custom statuses available:

- **Under Review** - Payment awaiting verification
- **Rejected** - Payment verification failed

These appear in:
- WooCommerce > Orders filter dropdown
- Order status badges
- Email templates

---

## Next Steps: Remove Legacy Code

Once you've verified the new system works perfectly:

1. **Backup your database**
2. **Follow the cleanup checklist:** `LEGACY_CODE_REMOVAL_CHECKLIST.md`
3. **Remove old custom checkout files**
4. **Drop old database tables**
5. **Clean up functions.php**

**⚠️ Don't rush this step!** Test thoroughly first.

---

## Advanced Configuration

### Customize Voucher Format

Edit: `wp-content/plugins/unico-woocommerce-checkout/includes/class-voucher-generator.php`

Function: `generate_voucher_code()`

Current format: `PREFIX-XXXXX-XXXXX`

### Add Custom Email Templates

Hook into actions in functions.php:

```php
add_action('unico_payment_approved', 'my_custom_approval_email', 10, 2);
add_action('unico_payment_rejected', 'my_custom_rejection_email', 10, 2);
add_action('unico_vouchers_delivered', 'my_custom_voucher_email', 10, 3);
```

### Integrate with Existing Systems

Available hooks for integration:

```php
do_action('unico_order_status_changed', $order_id, $old_status, $new_status, $order);
do_action('unico_payment_approved', $order_id, $order);
do_action('unico_payment_rejected', $order_id, $order);
do_action('unico_vouchers_delivered', $order_id, $vouchers, $order);
```

---

## File Locations

### Plugin Files
```
/wp-content/plugins/unico-woocommerce-checkout/
├── unico-woocommerce-checkout.php
├── includes/
├── assets/
└── README.md
```

### Documentation
```
/QUICK_START_GUIDE.md (this file)
/LEGACY_CODE_REMOVAL_CHECKLIST.md
/wp-content/plugins/unico-woocommerce-checkout/README.md
```

---

## Support

### Error Logs

Check these files for errors:

- `/wp-content/debug.log` (WordPress errors)
- `/error_log` (PHP errors)
- WooCommerce > Status > Logs

### Debug Mode

Enable in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Order Notes

Every action is logged in WooCommerce order notes:
- Payment proof uploaded
- Admin approval/rejection
- Voucher generation
- Email delivery

---

## Success!

Once you complete all steps above, your new WooCommerce checkout system will be fully operational with:

✅ Random bank account selection
✅ Transaction ID capture
✅ Payment proof upload
✅ Admin verification workflow
✅ Automatic voucher generation
✅ Email notifications
✅ Customer support ticket button

**Happy selling! 🎉**

---

## Need Help?

1. Check the detailed README: `wp-content/plugins/unico-woocommerce-checkout/README.md`
2. Review error logs
3. Test in staging environment first
4. Contact your development team

---

**Document Version:** 1.0
**Created:** 2026-01-23
**Status:** Ready for Deployment
