I have updated the plan to strictly follow your MUST-FIX requirements.

**Revised Implementation Plan:**

1.  **Legacy Cleanup (Priority)**
    *   **Action:** Delete `page-checkout.php` and `checkout-voucher-card.php` from the theme.
    *   **Verify:** Ensure the Checkout page in WordPress uses the `[woocommerce_checkout]` shortcode and not a custom template.

2.  **Plugin Architecture (`unico-woo-bank-review`)**
    *   **Structure:**
        *   `unico-woo-bank-review.php`
        *   `includes/class-bank-accounts.php`: Admin settings & Session logic.
        *   `includes/class-checkout-flow.php`: Fields, Uploads, Validation, Saving.
        *   `includes/class-order-manager.php`: Statuses, Admin Meta Box, Transitions.
        *   `includes/class-voucher-delivery.php`: Placeholder generation & Email.

3.  **Bank Account Logic (Consistency)**
    *   **Admin:** Settings page to manage accounts (Array of arrays).
    *   **Frontend:**
        *   Check `WC()->session->get('unico_bank_account')`.
        *   If empty, pick random active account and set session.
        *   If set, retrieve from session (ensures persistence on refresh).
    *   **Order Save:** Copy session bank data to Order Meta (`_unico_bank_snapshot`).

4.  **Checkout & File Upload (Robust)**
    *   **Enctype:** Hook `woocommerce_checkout_form_tag` to add `enctype="multipart/form-data"`.
    *   **Fields:** Add `unico_transaction_id` and `unico_payment_proof` to `woocommerce_review_order_before_payment`.
    *   **Validation (`woocommerce_checkout_process`):**
        *   Verify Transaction ID is present.
        *   Verify `$_FILES['unico_payment_proof']` exists and is not empty.
        *   Validate Type (jpg, png, pdf) and Size (max 5MB).
        *   Use `wc_add_notice` for all errors.
    *   **Upload Handling:**
        *   Use `wp_handle_upload` inside the validation/process hook to stage the file.
        *   Create attachment post and link to Order in `woocommerce_checkout_update_order_meta`.

5.  **Order Workflow & Admin UI**
    *   **Status:** Register `wc-under-review`.
    *   **Flow:** Force `Under Review` on creation.
    *   **Admin Meta Box:**
        *   Display Bank Snapshot (what user saw).
        *   Display Transaction ID.
        *   Display Payment Proof (Preview/Download).
        *   **Actions:** "Approve" (-> Completed) / "Reject" (-> Rejected).

6.  **Voucher Delivery (Placeholder)**
    *   **Trigger:** Hook into `woocommerce_order_status_completed`.
    *   **Logic:** Loop `order->get_item_count()` times. Generate dummy codes.
    *   **Storage:** Save codes to Order Meta.
    *   **Display:** Show codes on "Order Received" and "View Order" pages.

7.  **Rejection Flow**
    *   **Status:** Register `wc-rejected`.
    *   **Frontend:** In "My Account > View Order", if status is Rejected, show "Open Support Ticket" button.

**Confirming:** All logic will be in the plugin. Theme will only be touched to delete legacy files.
