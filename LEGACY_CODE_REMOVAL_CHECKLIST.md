# Legacy Custom Payment System Removal Checklist

This checklist helps you safely remove the old custom payment/checkout system and migrate to the new WooCommerce-based system.

## ⚠️ IMPORTANT: Before You Start

1. **Backup your database completely**
2. **Backup all theme files**
3. **Test the new WooCommerce system thoroughly**
4. **Verify all orders are migrated or documented**
5. **Inform your team about the migration**

---

## Phase 1: Testing & Verification (DO THIS FIRST)

### ✅ Pre-Migration Checklist

- [ ] New WooCommerce plugin activated
- [ ] At least 10 bank accounts added in WooCommerce > Bank Accounts
- [ ] Payment gateway enabled and configured
- [ ] Test order placed successfully
- [ ] Payment proof upload works
- [ ] Admin can approve/reject test order
- [ ] Vouchers generated correctly
- [ ] Emails sent successfully
- [ ] Customer can view vouchers in My Account

### ✅ Migration Tasks

- [ ] Export existing orders from `wp_unico_orders` table
- [ ] Document active/pending orders
- [ ] Migrate bank accounts to new system
- [ ] Notify customers about system upgrade (optional)

---

## Phase 2: File Removal

### 🗑️ Theme Files to DELETE

Located in: `/wp-content/themes/unico/` (or your active theme)

#### Page Templates (3 files)
```bash
❌ page-checkout.php (1,303 lines - custom checkout)
❌ page-order-received.php (custom thank you page)
❌ page-vouchers.php (if not needed for product display)
```

**Commands:**
```bash
rm page-checkout.php
rm page-order-received.php
# rm page-vouchers.php  # Only if you don't need custom product catalog
```

#### Core Class Files (12 files)
```bash
❌ includes/class-cart.php
❌ includes/class-checkout.php
❌ includes/class-order.php
❌ includes/class-cart-handlers.php
❌ includes/class-admin-orders.php
❌ includes/class-bank-accounts.php  # Old bank accounts system
❌ includes/class-wallet.php  # Unless you still need wallet functionality
❌ includes/class-pricing.php  # Unless still needed
❌ includes/class-security.php  # If OTP verification not needed
❌ includes/class-voucher-system.php  # Old voucher inventory
❌ includes/class-database.php  # Custom table schema
❌ includes/class-init.php  # Custom system initialization
```

**Commands:**
```bash
cd includes/
rm class-cart.php
rm class-checkout.php
rm class-order.php
rm class-cart-handlers.php
rm class-admin-orders.php
rm class-bank-accounts.php
```

**⚠️ KEEP these if still needed:**
- `class-wallet.php` - If you need wallet/refund functionality
- `class-pricing.php` - If you use role-based pricing
- `class-security.php` - If you use email verification/OTP
- `class-user-roles.php` - If you have custom roles

#### Frontend Assets (2 directories)
```bash
❌ assets/css/checkout.css  # Old checkout styles (16.6 KB)
❌ assets/js/checkout.js  # Old checkout JS (11.3 KB)
```

**⚠️ WARNING:** If these files also contain styles/scripts for OTHER pages (not just checkout), you need to:
1. Extract non-checkout code first
2. Move to new files
3. Then delete old files

**Commands:**
```bash
cd assets/css/
rm checkout.css  # Or rename to checkout-old.css for backup

cd ../js/
rm checkout.js  # Or rename to checkout-old.js for backup
```

#### Documentation (Optional - KEEP for reference)
```bash
✓ KEEP: VOUCHER_PURCHASE_FLOW_DOCUMENTATION.md (for reference)
✓ KEEP: SETUP-GUIDE.md (for reference)
```

---

## Phase 3: Code Cleanup in functions.php

### 🔍 Search and Remove in `functions.php`

Open `functions.php` and remove these sections:

#### 1. Custom Class Includes (Remove these lines)
```php
// OLD - REMOVE THESE
require_once get_template_directory() . '/includes/class-init.php';
require_once get_template_directory() . '/includes/class-database.php';
require_once get_template_directory() . '/includes/class-cart.php';
require_once get_template_directory() . '/includes/class-checkout.php';
require_once get_template_directory() . '/includes/class-order.php';
require_once get_template_directory() . '/includes/class-cart-handlers.php';
require_once get_template_directory() . '/includes/class-admin-orders.php';
require_once get_template_directory() . '/includes/class-bank-accounts.php';
```

#### 2. Custom Initialization (Remove these lines)
```php
// OLD - REMOVE THESE
add_action('after_setup_theme', 'unico_init_custom_system');
function unico_init_custom_system() {
    Unico_Init::instance();
}
```

#### 3. AJAX Handlers (Remove these functions)
```php
// Search for and remove:
add_action('wp_ajax_unico_send_purchase_otp', ...);
add_action('wp_ajax_unico_verify_purchase_otp', ...);
add_action('wp_ajax_unico_update_cart_quantity', ...);
add_action('wp_ajax_unico_approve_payment', ...);
add_action('wp_ajax_unico_reject_payment', ...);

// Remove the entire function definitions for:
function unico_send_purchase_otp() { ... }
function unico_verify_purchase_otp() { ... }
function unico_update_cart_quantity() { ... }
```

#### 4. Custom Hooks (Remove these)
```php
// Search for and remove:
add_action('template_redirect', 'handle_add_to_cart');
add_action('unico_order_created', ...);
add_action('unico_payment_approved', ...);
```

#### 5. Helper Functions (Remove these if defined)
```php
// Remove these custom functions:
function unico_format_price() { ... }
function unico_get_voucher_catalog_definitions() { ... }
function unico_cart_has_voucher_items() { ... }
function UNICO() { ... }  // Global instance getter
```

---

## Phase 4: Database Cleanup

### 🗄️ Database Tables to DROP

**⚠️ CRITICAL:** Export/backup all data BEFORE dropping tables!

#### Export Data First
```sql
-- Export all orders
SELECT * FROM wp_unico_orders INTO OUTFILE '/tmp/unico_orders_backup.csv';

-- Export vouchers
SELECT * FROM wp_unico_vouchers INTO OUTFILE '/tmp/unico_vouchers_backup.csv';
```

#### Drop Custom Tables (16 tables)
```sql
-- Order Management Tables
DROP TABLE IF EXISTS wp_unico_orders;
DROP TABLE IF EXISTS wp_unico_order_items;
DROP TABLE IF EXISTS wp_unico_order_meta;
DROP TABLE IF EXISTS wp_unico_bank_payments;

-- Financial Tables
DROP TABLE IF EXISTS wp_unico_wallets;  -- ⚠️ Only if not using wallet feature
DROP TABLE IF EXISTS wp_unico_wallet_transactions;
DROP TABLE IF EXISTS wp_unico_commissions;

-- Product & Inventory Tables
DROP TABLE IF EXISTS wp_unico_vouchers;
DROP TABLE IF EXISTS wp_unico_pricing_rules;  -- ⚠️ Only if not using role-based pricing

-- Security & Support Tables
DROP TABLE IF EXISTS wp_unico_activity_logs;
DROP TABLE IF EXISTS wp_unico_security_checks;
DROP TABLE IF EXISTS wp_unico_email_verification;
DROP TABLE IF EXISTS wp_unico_support_tickets;  -- ⚠️ Only if not using support system
DROP TABLE IF EXISTS wp_unico_ticket_replies;

-- Additional Tables
DROP TABLE IF EXISTS wp_unico_user_approvals;
DROP TABLE IF EXISTS wp_unico_documents;
```

**⚠️ KEEP these tables if:**
- `wp_unico_wallets` - If you still need wallet/refund functionality
- `wp_unico_pricing_rules` - If using role-based pricing
- `wp_unico_support_tickets` - If using support ticket system

#### Remove Database Version Option
```sql
DELETE FROM wp_options WHERE option_name = 'unico_db_version';
```

---

## Phase 5: Search & Replace Verification

### 🔍 Search for Remaining References

Use these search terms to find any remaining old code:

#### In Theme Directory
```bash
cd /path/to/theme/

# Search for class references
grep -r "Unico_Cart" .
grep -r "Unico_Checkout" .
grep -r "Unico_Order" .
grep -r "Unico_Bank_Accounts" .

# Search for table references
grep -r "unico_orders" .
grep -r "unico_order_items" .
grep -r "unico_bank_payments" .

# Search for session variables
grep -r "unico_checkout_bank_id" .
grep -r "unico_purchase_otp" .

# Search for AJAX actions
grep -r "wp_ajax_unico_" .

# Search for hooks
grep -r "unico_add_to_cart" .
grep -r "unico_order_created" .
```

#### Expected Output After Cleanup
```
✅ Should return: 0 matches found
❌ If matches found: Review and remove remaining references
```

---

## Phase 6: WordPress Admin Cleanup

### 🔧 Admin Panel Checks

#### Remove Custom Admin Pages
- [ ] Check WP Admin sidebar for old "Orders" menu (non-WooCommerce)
- [ ] Remove any custom bank accounts page (if separate from WooCommerce)
- [ ] Remove custom dashboard pages that referenced old system

#### Verify WooCommerce Settings
- [ ] Go to WooCommerce > Settings > Payments
- [ ] Ensure ONLY "Bank Transfer (Unico)" is enabled
- [ ] Disable any other custom payment methods

#### Clean Up User Meta
```sql
-- Remove old verification flags (optional)
DELETE FROM wp_usermeta WHERE meta_key = 'email_verified';
DELETE FROM wp_usermeta WHERE meta_key = 'unico_last_bank_id';
```

---

## Phase 7: Post-Cleanup Testing

### ✅ Final Verification

- [ ] Visit homepage - No errors
- [ ] Visit product page - No errors
- [ ] Add to cart - Works correctly (WooCommerce cart)
- [ ] View cart - WooCommerce cart displays
- [ ] Proceed to checkout - WooCommerce checkout loads
- [ ] Complete test order - Order created successfully
- [ ] Admin approve order - Vouchers generated
- [ ] Customer receives vouchers - Email delivered
- [ ] My Account > Orders - Lists all orders correctly
- [ ] Check PHP error log - No errors related to old system

### 📊 Performance Check

After cleanup, you should see:

- [ ] Faster page load times (less PHP includes)
- [ ] Smaller theme directory size
- [ ] Cleaner database (fewer tables)
- [ ] No PHP errors in logs
- [ ] No console errors in browser

---

## Rollback Plan (If Something Breaks)

### 🔙 Emergency Rollback Steps

If you encounter critical issues:

1. **Restore Database Backup**
   ```bash
   mysql -u username -p database_name < backup.sql
   ```

2. **Restore Theme Files**
   ```bash
   cp -r backup/includes/* includes/
   cp backup/page-checkout.php .
   cp backup/page-order-received.php .
   ```

3. **Deactivate New Plugin**
   - WP Admin > Plugins > Deactivate "Unico WooCommerce Checkout"

4. **Test Old System**
   - Place a test order
   - Verify everything works

5. **Investigate Issue**
   - Check error logs
   - Review what broke
   - Fix and retry cleanup

---

## Summary of Files to Remove

### Quick Reference

```
Theme Root:
  ❌ page-checkout.php
  ❌ page-order-received.php
  ❌ page-vouchers.php (optional)

includes/:
  ❌ class-cart.php
  ❌ class-checkout.php
  ❌ class-order.php
  ❌ class-cart-handlers.php
  ❌ class-admin-orders.php
  ❌ class-bank-accounts.php
  ❌ class-database.php
  ❌ class-init.php
  ⚠️  class-wallet.php (keep if needed)
  ⚠️  class-pricing.php (keep if needed)
  ⚠️  class-security.php (keep if needed)
  ⚠️  class-voucher-system.php (remove)

assets/css/:
  ❌ checkout.css (old)

assets/js/:
  ❌ checkout.js (old)
```

### Database:
```
❌ 16 custom tables (wp_unico_*)
⚠️  Keep wallet/pricing/support tables if needed
```

### functions.php:
```
❌ Class includes
❌ AJAX handlers
❌ Custom hooks
❌ Helper functions
```

---

## Timeline Recommendation

**Week 1:**
- Phase 1: Testing & Verification
- Test new system thoroughly
- Train admin staff

**Week 2:**
- Phase 2-3: File cleanup
- Remove theme files
- Clean functions.php
- Test after each step

**Week 3:**
- Phase 4: Database cleanup
- Export data
- Drop tables
- Final verification

**Week 4:**
- Phase 5-7: Search & verify
- Complete testing
- Monitor for issues
- Document any customizations

---

## Success Criteria

✅ Your migration is successful when:

1. No PHP errors in logs
2. No console errors in browser
3. Checkout works perfectly
4. Orders created in WooCommerce
5. Vouchers generated and delivered
6. Emails sent correctly
7. Admin can approve/reject
8. Customers can view vouchers
9. All old references removed
10. Database cleaned up

---

## Need Help?

If you encounter issues during cleanup:

1. Check this document first
2. Review error logs
3. Test in staging environment
4. Keep backups safe
5. Contact your development team

**Remember:** Take your time, test thoroughly, and don't rush the cleanup process!

---

## Document Version

- Version: 1.0
- Created: 2026-01-23
- Author: Unico Development Team
- Status: Ready for Use
