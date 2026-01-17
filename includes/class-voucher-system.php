<?php
/**
 * Voucher Management System
 * Handles voucher generation, storage, delivery, and inventory management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Voucher_System {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('woocommerce_check_cart_items', [$this, 'enforce_sales_lock']);
    }

    public static function get_system_flags() {
        $flags = get_option('unico_system_flags', []);
        if (!is_array($flags)) {
            $flags = [];
        }
        return $flags;
    }

    public static function is_sales_locked() {
        $flags = self::get_system_flags();
        return !empty($flags['sales_locked']);
    }

    public static function set_sales_lock($locked, $user_id, $reason = '') {
        $flags = self::get_system_flags();
        $locked = $locked ? 1 : 0;
        $flags['sales_locked'] = $locked;
        $flags['sales_locked_by'] = $user_id;
        $flags['sales_locked_at'] = current_time('mysql');
        $flags['sales_locked_reason'] = $reason;
        update_option('unico_system_flags', $flags);
        if (class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();
            $type = $locked ? 'sales_locked' : 'sales_unlocked';
            $description = $locked ? 'Voucher sales locked' : 'Voucher sales unlocked';
            $security->log_activity($user_id, $type, $description, ['reason' => $reason]);
        }
    }

    /**
     * Add voucher to inventory
     */
    public function add_voucher($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        $defaults = [
            'voucher_code' => '',
            'voucher_type' => 'standard',
            'exam_name' => '',
            'voucher_status' => 'available',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'expiry_date' => null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($data['voucher_code']) || empty($data['exam_name'])) {
            return new WP_Error('missing_data', 'Voucher code and exam name are required.');
        }

        $security = Unico_Security::get_instance();
        
        // Hash for duplicate checking
        $code_hash = hash('sha256', $data['voucher_code']);

        // Check for duplicate voucher code using hash
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE voucher_code_hash = %s",
            $code_hash
        ));

        if ($exists) {
            return new WP_Error('duplicate_voucher', 'This voucher code already exists.');
        }

        // Encrypt code for storage
        $encrypted_code = $security->encrypt_data($data['voucher_code']);

        // Insert voucher
        $inserted = $wpdb->insert($table, [
            'voucher_code' => $encrypted_code,
            'voucher_code_hash' => $code_hash,
            'voucher_type' => sanitize_text_field($data['voucher_type']),
            'exam_name' => sanitize_text_field($data['exam_name']),
            'voucher_status' => 'available',
            'purchase_price' => floatval($data['purchase_price']),
            'selling_price' => floatval($data['selling_price']),
            'expiry_date' => $data['expiry_date'],
            'created_by' => $data['created_by'],
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            $voucher_id = $wpdb->insert_id;

            // Log activity
            $security->log_activity(get_current_user_id(), 'voucher_added', "Voucher added (Encrypted ID: $voucher_id)", [
                'voucher_id' => $voucher_id,
                'exam_name' => $data['exam_name']
            ]);

            return $voucher_id;
        }

        return new WP_Error('insert_failed', 'Failed to add voucher to inventory.');
    }

    /**
     * Bulk import vouchers
     */
    public function bulk_import_vouchers($vouchers_array, $exam_name) {
        if (!current_user_can('manage_voucher_inventory')) {
            return new WP_Error('permission_denied', 'You do not have permission to bulk import vouchers.');
        }
        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($vouchers_array as $voucher_data) {
            $result = $this->add_voucher([
                'voucher_code' => $voucher_data['code'],
                'exam_name' => $exam_name,
                'voucher_type' => isset($voucher_data['type']) ? $voucher_data['type'] : 'standard',
                'purchase_price' => isset($voucher_data['purchase_price']) ? $voucher_data['purchase_price'] : 0,
                'selling_price' => isset($voucher_data['selling_price']) ? $voucher_data['selling_price'] : 0,
                'expiry_date' => isset($voucher_data['expiry_date']) ? $voucher_data['expiry_date'] : null
            ]);

            if (is_wp_error($result)) {
                $failed++;
                $errors[] = $result->get_error_message();
            } else {
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Get available voucher for order
     */
    public function get_available_voucher($exam_name, $exclude_ids = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        $exclude_sql = '';
        if (!empty($exclude_ids)) {
            $exclude_ids = array_map('intval', $exclude_ids);
            $exclude_sql = " AND id NOT IN (" . implode(',', $exclude_ids) . ")";
        }

        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table
            WHERE exam_name = %s
            AND voucher_status = 'available'
            AND (expiry_date IS NULL OR expiry_date > NOW())
            $exclude_sql
            ORDER BY expiry_date ASC, id ASC
            LIMIT 1",
            $exam_name
        ));

        return $voucher;
    }

    /**
     * Assign voucher to order
     */
    public function assign_voucher_to_order($voucher_id, $order_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        $updated = $wpdb->update($table, [
            'voucher_status' => 'assigned',
            'assigned_to' => $user_id,
            'order_id' => $order_id,
            'updated_at' => current_time('mysql')
        ], [
            'id' => $voucher_id,
            'voucher_status' => 'available'
        ]);

        if ($updated) {
            // Log activity
            $security = Unico_Security::get_instance();
            $security->log_activity($user_id, 'voucher_assigned', "Voucher assigned to order #{$order_id}", [
                'voucher_id' => $voucher_id,
                'order_id' => $order_id
            ]);

            return true;
        }

        return false;
    }

    /**
     * Deliver voucher via email
     */
    public function deliver_voucher($voucher_id, $order_id, $delivery_method = 'automatic') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $voucher_id
        ));

        if (!$voucher) {
            return new WP_Error('voucher_not_found', 'Voucher not found.');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found.');
        }

        // Get customer email
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Send email with voucher
        $subject = 'Your ' . $voucher->exam_name . ' Voucher - Order #' . $order_id;
        $message = $this->get_voucher_email_template($voucher, $order, $customer_name);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $email_sent = wp_mail($customer_email, $subject, $message, $headers);

        if ($email_sent) {
            // Update voucher status
            $wpdb->update($table, [
                'voucher_status' => 'delivered',
                'delivered_at' => current_time('mysql'),
                'delivered_via' => $delivery_method,
                'updated_at' => current_time('mysql')
            ], ['id' => $voucher_id]);

            // Add order note
            $order->add_order_note(
                sprintf('Voucher delivered: %s (ID: %d)', $voucher->voucher_code, $voucher_id)
            );

            // Log activity
            $security = Unico_Security::get_instance();
            $security->log_activity($order->get_customer_id(), 'voucher_delivered', "Voucher delivered to {$customer_email}", [
                'voucher_id' => $voucher_id,
                'order_id' => $order_id,
                'delivery_method' => $delivery_method
            ]);

            return true;
        }

        return new WP_Error('email_failed', 'Failed to send voucher email.');
    }

    /**
     * Get voucher email template
     */
    private function get_voucher_email_template($voucher, $order, $customer_name) {
        $order_date = $order->get_date_created()->format('F j, Y');
        $expiry_text = $voucher->expiry_date ? date('F j, Y', strtotime($voucher->expiry_date)) : 'No expiry';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Your Voucher</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #4a4a4a; background-color: #f5f5f5; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">

                <!-- Header -->
                <div style="background-color: #103e54; color: white; padding: 30px 20px; text-align: center;">
                    <h1 style="margin: 0; font-size: 28px;"><?php echo get_bloginfo('name'); ?></h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">Your Digital Voucher</p>
                </div>

                <!-- Body -->
                <div style="padding: 40px 30px;">
                    <h2 style="color: #103e54; margin-top: 0;">Hello <?php echo esc_html($customer_name); ?>!</h2>

                    <p>Thank you for your purchase! Your <?php echo esc_html($voucher->exam_name); ?> voucher is ready.</p>

                    <!-- Voucher Card -->
                    <div style="background: linear-gradient(135deg, #e95134 0%, #103e54 100%); border-radius: 8px; padding: 30px; margin: 30px 0; text-align: center; color: white;">
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">
                            <?php echo esc_html($voucher->exam_name); ?> Voucher
                        </div>
                        <div style="font-size: 32px; font-weight: bold; letter-spacing: 2px; margin: 15px 0; font-family: 'Courier New', monospace; background-color: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px;">
                            <?php echo esc_html($voucher->voucher_code); ?>
                        </div>
                        <div style="font-size: 12px; opacity: 0.8; margin-top: 10px;">
                            Order #<?php echo $order->get_id(); ?> | <?php echo $order_date; ?>
                        </div>
                    </div>

                    <!-- Details -->
                    <div style="background-color: #f8f9fa; border-left: 4px solid #e95134; padding: 20px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #103e54; font-size: 16px;">Voucher Details</h3>
                        <table style="width: 100%; font-size: 14px;">
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Exam:</td>
                                <td style="padding: 8px 0; font-weight: bold;"><?php echo esc_html($voucher->exam_name); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Voucher Code:</td>
                                <td style="padding: 8px 0; font-weight: bold; font-family: 'Courier New', monospace;"><?php echo esc_html($voucher->voucher_code); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #666;">Valid Until:</td>
                                <td style="padding: 8px 0; font-weight: bold;"><?php echo $expiry_text; ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Instructions -->
                    <div style="margin: 30px 0;">
                        <h3 style="color: #103e54; font-size: 16px;">How to Use Your Voucher</h3>
                        <ol style="padding-left: 20px; line-height: 1.8;">
                            <li>Visit the official exam booking website</li>
                            <li>Select your test date and location</li>
                            <li>Enter your voucher code at checkout</li>
                            <li>Complete your booking</li>
                        </ol>
                    </div>

                    <!-- Support -->
                    <div style="background-color: #fff3e0; border-radius: 8px; padding: 20px; margin: 30px 0;">
                        <p style="margin: 0; font-size: 14px;">
                            <strong>Need help?</strong> Our support team is available 24/7 to assist you.
                                <br><a href="<?php echo home_url('/support'); ?>" style="color: #e95134;">Contact Support</a>
                        </p>
                    </div>

                    <!-- CTA Button -->
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo $order->get_view_order_url(); ?>" style="background-color: #e95134; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                            View Order Details
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <div style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                    <p style="margin: 0 0 10px 0; color: #666; font-size: 12px;">
                        This voucher was purchased on <?php echo $order_date; ?>
                    </p>
                    <p style="margin: 0; color: #999; font-size: 11px;">
                        © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. All rights reserved.
                    </p>
                </div>

            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Auto-deliver vouchers for completed orders
     */
    public function auto_deliver_vouchers($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if already delivered
        if ($order->get_meta('_vouchers_delivered')) {
            return;
        }

        $items = $order->get_items();
        $vouchers_delivered = [];

        foreach ($items as $item_id => $item) {
            $product = $item->get_product();

            // Check if this is a voucher product
            if ($product && $this->is_voucher_product($product)) {
                $quantity = $item->get_quantity();
                $exam_name = $product->get_meta('exam_name');

                if (!$exam_name) {
                    $exam_name = $product->get_name();
                }

                // Assign and deliver vouchers for each quantity
                for ($i = 0; $i < $quantity; $i++) {
                    $voucher = $this->get_available_voucher($exam_name, array_column($vouchers_delivered, 'id'));

                    if ($voucher) {
                        $assigned = $this->assign_voucher_to_order($voucher->id, $order_id, $order->get_customer_id());

                        if ($assigned) {
                            $delivered = $this->deliver_voucher($voucher->id, $order_id, 'automatic');

                            if (!is_wp_error($delivered)) {
                                $vouchers_delivered[] = [
                                    'id' => $voucher->id,
                                    'code' => $voucher->voucher_code,
                                    'exam' => $exam_name
                                ];
                            }
                        }
                    } else {
                        // No voucher available - notify admin
                        $order->add_order_note(
                            sprintf('⚠️ No available voucher for %s (Quantity: %d)', $exam_name, $quantity - $i),
                            false
                        );

                        // Send admin notification
                        $this->notify_admin_low_stock($exam_name, $order_id);
                    }
                }
            }
        }

        if (!empty($vouchers_delivered)) {
            $order->update_meta_data('_vouchers_delivered', $vouchers_delivered);
            $order->update_meta_data('_vouchers_delivered_at', current_time('mysql'));
            $order->save();

            $order->add_order_note(
                sprintf('✅ %d voucher(s) automatically delivered', count($vouchers_delivered))
            );
        }
    }

    /**
     * Check if product is a voucher
     */
    private function is_voucher_product($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
        return in_array('vouchers', $categories) || $product->get_meta('is_voucher') === 'yes';
    }

    public function enforce_sales_lock() {
        if (!self::is_sales_locked()) {
            return;
        }
        if (!function_exists('WC')) {
            return;
        }
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        $block = false;
        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data'])) {
                continue;
            }
            $product = $cart_item['data'];
            if (!$product) {
                continue;
            }
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
            if (in_array('vouchers', $categories) || $product->get_meta('is_voucher') === 'yes') {
                $block = true;
                break;
            }
        }
        if ($block) {
            wc_add_notice('Voucher sales are temporarily paused. Please contact support.', 'error');
            if (class_exists('Unico_Security')) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $security = Unico_Security::get_instance();
                    $security->log_activity($user_id, 'order_blocked_sales_locked', 'Checkout blocked due to sales lock');
                }
            }
        }
    }

    /**
     * Notify admin about low stock
     */
    private function notify_admin_low_stock($exam_name, $order_id) {
        $admin_email = get_option('admin_email');
        $subject = 'Low Voucher Stock Alert - ' . $exam_name;
        $message = "
        <p>Warning: Low or no stock for <strong>{$exam_name}</strong> vouchers.</p>
        <p>Order #{$order_id} could not be fulfilled completely.</p>
        <p>Please add more vouchers to inventory immediately.</p>
        <p><a href='" . admin_url('admin.php?page=unico-voucher-inventory') . "'>Manage Voucher Inventory</a></p>
        ";

        wp_mail($admin_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    /**
     * Get voucher statistics
     */
    public function get_voucher_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'available' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE voucher_status = 'available'"),
            'assigned' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE voucher_status = 'assigned'"),
            'delivered' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE voucher_status = 'delivered'"),
            'expired' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE expiry_date < NOW() AND voucher_status = 'available'")
        ];
    }

    /**
     * Get vouchers by exam name
     */
    public function get_vouchers_by_exam($exam_name, $status = 'all') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';

        $where = $wpdb->prepare("WHERE exam_name = %s", $exam_name);

        if ($status !== 'all') {
            $where .= $wpdb->prepare(" AND voucher_status = %s", $status);
        }

        return $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");
    }
}
