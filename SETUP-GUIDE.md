# UNICO Voucher Booking System - Complete Setup Guide

## 📋 System Overview

You now have a **production-ready voucher booking platform** with:
- 6 role-based dashboards
- Dynamic student application form system
- Complete security & fraud prevention
- Automated voucher delivery
- Bulk pricing for agents/resellers
- Support ticket system
- Wallet & refund management

---

## 🚀 STEP 1: Create WordPress Pages

You need to create WordPress pages and assign the correct templates. Here's how:

### Go to: wp-admin → Pages → Add New

Create these pages **exactly as specified**:

### **Dashboard Pages** (6 pages)

1. **Customer Dashboard**
   - Page Title: `Customer Dashboard`
   - URL Slug: `customer-dashboard`
   - Template: **Customer Dashboard**
   - Publish

2. **Agent Dashboard**
   - Page Title: `Agent Dashboard`
   - URL Slug: `agent-dashboard`
   - Template: **Agent Dashboard**
   - Publish

3. **Reseller Dashboard**
   - Page Title: `Reseller Dashboard`
   - URL Slug: `reseller-dashboard`
   - Template: **Reseller Dashboard**
   - Publish

4. **Support Dashboard**
   - Page Title: `Support Dashboard`
   - URL Slug: `support-dashboard`
   - Template: **Support Dashboard**
   - Publish

5. **Finance Dashboard**
   - Page Title: `Finance Dashboard`
   - URL Slug: `finance-dashboard`
   - Template: **Finance Dashboard**
   - Publish

6. **Management Dashboard**
   - Page Title: `Management Dashboard`
   - URL Slug: `management-dashboard`
   - Template: **Management Dashboard**
   - Publish

### **Other Essential Pages** (7 pages)

7. **Vouchers**
   - Page Title: `Vouchers`
   - URL Slug: `vouchers`
   - Template: **Vouchers Page**
   - Publish

8. **Student Application Form**
   - Page Title: `Student Application Form`
   - URL Slug: `student-application-form`
   - Template: **Student Application Form**
   - Publish

9. **Support**
   - Page Title: `Support`
   - URL Slug: `support`
   - Template: **Support**
   - Publish

10. **Login**
    - Page Title: `Login`
    - URL Slug: `login`
    - Template: **Login Page** (should already exist)
    - Publish

11. **Register**
    - Page Title: `Register`
    - URL Slug: `register`
    - Template: **Register Page** (should already exist)
    - Publish

12. **Forgot Password**
    - Page Title: `Forgot Password`
    - URL Slug: `forgot-password`
    - Template: **Forgot Password**
    - Publish

13. **Reset Password**
    - Page Title: `Reset Password`
    - URL Slug: `reset-password`
    - Template: **Reset Password**
    - Publish

14. **Email Verification**
    - Page Title: `Email Verification`
    - URL Slug: `email-verification`
    - Template: **Email Verification**
    - Publish

---

## 🛍️ STEP 2: Install and Configure WooCommerce

### Install WooCommerce

1. Go to: **Plugins → Add New**
2. Search for "WooCommerce"
3. Click **Install Now** → **Activate**
4. Complete the setup wizard:
   - Store location: Your country
   - Currency: Your currency (USD, GBP, EUR, INR, etc.)
   - Product types: Physical and digital products
   - Business details: Fill as needed
   - Skip theme setup (we already have a custom theme)

### Create Voucher Products

1. Go to: **Products → Categories**
2. Create category: `vouchers` (lowercase, no spaces)

3. Go to: **Products → Add New**

#### Example: Create PTE Voucher Product

- **Product Name**: PTE Voucher
- **Product Data**: Simple product
- **Regular Price**: 199.00 (or your price)
- **Sale Price**: (optional discount)
- **Categories**: Check "vouchers"
- **Product Short Description**: Official PTE Academic exam voucher with instant delivery
- **Stock**: Managed stock → Set quantity to 0 (inventory managed separately)
- **Custom Fields** (scroll to bottom):
  - Add field: `exam_name` = `PTE`

- **Publish**

#### Repeat for other exams:

Create similar products for:
- IELTS Voucher (exam_name = IELTS)
- TOEFL Voucher (exam_name = TOEFL)
- Duolingo Voucher (exam_name = Duolingo)
- GRE Voucher (exam_name = GRE)
- GMAT Voucher (exam_name = GMAT)
- LanguageCert Voucher (exam_name = LanguageCert)

---

## 💳 STEP 3: Configure Payment Gateways

### Option A: Razorpay (Indian Payments - UPI, Cards, Net Banking, Wallets)

1. Install Plugin:
   - Go to **Plugins → Add New**
   - Search "Razorpay for WooCommerce"
   - Install and Activate

2. Get Razorpay Credentials:
   - Go to: https://dashboard.razorpay.com
   - Sign up / Login
   - Go to Settings → API Keys
   - Copy: Key ID and Key Secret

3. Configure in WordPress:
   - Go to **WooCommerce → Settings → Payments**
   - Enable **Razorpay**
   - Click **Manage**
   - Enter Key ID and Key Secret
   - Enable "Test Mode" for testing
   - Save changes

### Option B: Stripe (International Cards)

1. Install Plugin:
   - Go to **Plugins → Add New**
   - Search "WooCommerce Stripe Gateway"
   - Install and Activate

2. Get Stripe Credentials:
   - Go to: https://dashboard.stripe.com
   - Sign up / Login
   - Go to Developers → API Keys
   - Copy: Publishable key and Secret key

3. Configure in WordPress:
   - Go to **WooCommerce → Settings → Payments**
   - Enable **Stripe**
   - Click **Manage**
   - Enter keys
   - Save changes

### Option C: PayPal

1. Built into WooCommerce
2. Go to **WooCommerce → Settings → Payments**
3. Enable **PayPal Standard**
4. Enter your PayPal email
5. Save

---

## 🎫 STEP 4: Add Vouchers to Inventory

You need to add voucher codes to the system. There are 3 ways:

### Method 1: Direct Database (Recommended for Bulk)

Use phpMyAdmin or database tool:

```sql
INSERT INTO wp_unico_vouchers (voucher_code, exam_name, selling_price, voucher_status, created_by)
VALUES
('PTE-ABC123DEF', 'PTE', 199.00, 'available', 1),
('PTE-XYZ789GHI', 'PTE', 199.00, 'available', 1),
('IELTS-ABC123', 'IELTS', 249.00, 'available', 1),
('TOEFL-XYZ789', 'TOEFL', 189.00, 'available', 1);
```

### Method 2: CSV Import (Coming Soon)

An admin interface for bulk CSV import will be added.

### Method 3: Manual One-by-One

Use the WordPress admin interface (admin panel coming soon).

---

## 👥 STEP 5: Test User Registration & Roles

### Test Different User Types:

1. **Register as Customer**:
   - Go to `/register`
   - Fill form, select "Student / Individual Buyer"
   - After registration → Verify email
   - Login → Redirects to Customer Dashboard

2. **Register as Agent**:
   - Go to `/register`
   - Fill form, select "Agent"
   - Login → Redirects to Agent Dashboard
   - See bulk pricing (10%, 15%, 20%)

3. **Register as Reseller**:
   - Go to `/register`
   - Fill form, select "Training Center / Reseller"
   - Login → Redirects to Reseller Dashboard
   - See premium pricing (15%, 20%, 25%)
   - View stock levels by exam type

### Manually Assign Staff Roles:

For Support, Finance, and Management roles:

1. Go to **Users → All Users**
2. Edit a user
3. Change **Role** to:
   - Customer Support
   - Finance Management
   - Management

---

## 🔧 STEP 6: System Configuration

### Update Email Settings:

1. Install **WP Mail SMTP** plugin (recommended):
   - Better email delivery
   - Prevents emails going to spam
   - Configure with Gmail, SendGrid, or Mailgun

### Test Email Verification:

1. Register a new user
2. Check email inbox for verification link
3. Click link → Should redirect to verified page

### Test Voucher Purchase Flow:

1. Login as customer
2. Go to `/vouchers`
3. Click "Buy Now" on a voucher
4. Complete payment (use test mode)
5. Check email for voucher code
6. Check Customer Dashboard → "My Vouchers"

### Test Password Reset:

1. Logout
2. Go to `/login`
3. Click "Forgot Password"
4. Enter email
5. Check email for reset link
6. Click link → Set new password

---

## 📊 STEP 7: Access All Dashboards

### Customer Dashboard
- URL: `/customer-dashboard`
- Shows: Orders, vouchers, wallet balance
- Access: Customer role

### Agent Dashboard
- URL: `/agent-dashboard`
- Shows: Bulk pricing tiers, commissions, orders
- Access: Agent role
- Pricing: 10%, 15%, 20% discounts

### Reseller Dashboard
- URL: `/reseller-dashboard`
- Shows: Stock levels, premium pricing, inventory
- Access: Reseller role
- Pricing: 15%, 20%, 25% discounts

### Support Dashboard
- URL: `/support-dashboard`
- Shows: All tickets, status filters, ticket management
- Access: Customer Support role

### Finance Dashboard
- URL: `/finance-dashboard`
- Shows: Revenue, orders, commissions, date filters
- Access: Finance Management role

### Management Dashboard
- URL: `/management-dashboard`
- Shows: System overview, key metrics, user stats
- Access: Management role

### Admin Dashboard
- URL: `/wp-admin`
- Full WordPress admin access
- Access: Administrator role

---

## 🎯 STEP 8: Update Homepage Links

Make sure your homepage links are correct:

1. Edit `front-page.php`
2. Update these links if needed:
   - "APPLY NOW" → `/student-application-form`
   - "BUY DISCOUNTED VOUCHER" → `/vouchers`
   - Footer links → `/support`, `/login`, `/register`

---

## 🔒 Security Checklist

✅ **Email verification** - Required before purchases
✅ **IP logging** - All registrations tracked
✅ **Risk scoring** - Fraud prevention active
✅ **Activity logs** - Complete audit trail
✅ **Password reset** - Secure token system
✅ **Role-based access** - Proper permissions
✅ **HTTPS** - Ensure SSL certificate installed

---

## 📝 What Works Now

✅ User registration with role selection
✅ Email verification system
✅ Role-based dashboard redirects
✅ Voucher purchase and auto-delivery
✅ Bulk pricing for agents/resellers
✅ Student application form submissions
✅ Support ticket creation
✅ Wallet and refund system
✅ Password reset flow
✅ Security and fraud detection
✅ Activity logging

---

## 🎨 Customization

### Change Colors:

Edit the gradient colors in dashboard PHP files:
- Customer: `#103e54` (teal)
- Agent: `#e84e33` (red)
- Reseller: `#103e54` (teal)
- Support: `#6f42c1` (purple)
- Finance: `#28a745` (green)
- Management: `#17a2b8` (cyan)

### Add More Form Fields:

Student Application Form fields are in the database:
- Table: `wp_unico_form_fields`
- Add/edit/remove fields via SQL or admin interface (coming soon)

---

## 🆘 Troubleshooting

**Dashboard shows blank?**
→ Make sure you created the WordPress page with the correct template

**Vouchers not delivered?**
→ Check: Email settings, voucher inventory, product has exam_name meta field

**Payment not working?**
→ Check: WooCommerce settings, payment gateway credentials, test mode

**User can't login?**
→ Check: Email verification status, correct password, user role assigned

**Emails going to spam?**
→ Install WP Mail SMTP plugin, configure proper sender

---

## 📞 Support

If you encounter issues:
1. Check error logs: `/wp-content/debug.log`
2. Enable WordPress debugging in `wp-config.php`
3. Check browser console for JavaScript errors

---

## 🎉 You're Ready!

Your voucher booking system is fully functional and ready for production use.

**Next Steps:**
1. Create all WordPress pages listed above
2. Set up payment gateways
3. Add voucher inventory
4. Test complete purchase flow
5. Launch!
