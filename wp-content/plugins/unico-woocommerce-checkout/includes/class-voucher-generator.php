<?php
/**
 * Voucher Generator
 * Generates voucher codes on-demand when orders are approved
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Voucher_Generator {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook into payment approval
        add_action('unico_payment_approved', array($this, 'handle_payment_approved'), 10, 2);
    }

    /**
     * Handle payment approved
     */
    public function handle_payment_approved($order_id, $order) {
        // Generate vouchers is already called in approve_payment
        // This hook is for additional processing if needed
    }

    /**
     * Generate and deliver vouchers for an order
     */
    public function generate_and_deliver_vouchers($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        // Check if vouchers already generated
        $vouchers_generated = $order->get_meta('_vouchers_generated');
        if ($vouchers_generated === 'yes') {
            return true; // Already generated
        }

        $vouchers = array();

        // Loop through order items and generate vouchers
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            $product_name = $item->get_name();

            // Generate vouchers for this item
            for ($i = 0; $i < $quantity; $i++) {
                $voucher_code = $this->generate_voucher_code($product_id, $order_id);

                $vouchers[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'voucher_code' => $voucher_code,
                    'generated_at' => current_time('mysql'),
                );
            }
        }

        // Save vouchers to order meta
        $order->update_meta_data('_vouchers_generated', 'yes');
        $order->update_meta_data('_voucher_codes', wp_json_encode($vouchers));
        $order->update_meta_data('_vouchers_delivered_at', current_time('mysql'));

        $order->add_order_note(
            sprintf(
                __('%d voucher(s) generated and ready for delivery.', 'unico-wc'),
                count($vouchers)
            )
        );

        $order->save();

        // Send vouchers to customer
        $this->send_vouchers_email($order_id, $vouchers);

        // Fire action hook for additional integrations
        do_action('unico_vouchers_delivered', $order_id, $vouchers, $order);

        return true;
    }

    /**
     * Generate unique voucher code
     */
    private function generate_voucher_code($product_id, $order_id) {
        // Get product to determine exam type for prefix
        $product = wc_get_product($product_id);
        $exam_name = '';

        if ($product) {
            $exam_name = $product->get_meta('exam_name');
        }

        // Create prefix based on exam name
        $prefix = 'VCHR';
        if ($exam_name) {
            $exam_prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $exam_name), 0, 4));
            if ($exam_prefix) {
                $prefix = $exam_prefix;
            }
        }

        // Generate random alphanumeric code
        // Format: PREFIX-XXXXX-XXXXX (e.g., IELT-A3B5C-7D9E2)
        $random_part1 = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
        $random_part2 = strtoupper(substr(md5(uniqid(rand(), true) . microtime()), 0, 5));

        $voucher_code = $prefix . '-' . $random_part1 . '-' . $random_part2;

        // Ensure uniqueness by checking if code exists
        if ($this->voucher_code_exists($voucher_code)) {
            // Regenerate if exists (recursive)
            return $this->generate_voucher_code($product_id, $order_id);
        }

        return $voucher_code;
    }

    /**
     * Check if voucher code already exists
     */
    private function voucher_code_exists($code) {
        global $wpdb;

        // Check in order meta across all orders
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = '_voucher_codes'
            AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like($code) . '%'));

        return ($exists > 0);
    }

    /**
     * Send vouchers email to customer
     */
    private function send_vouchers_email($order_id, $vouchers) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        $to = $order->get_billing_email();
        $subject = sprintf(__('Your Voucher Codes - Order #%s', 'unico-wc'), $order->get_order_number());

        // Build email content
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($subject); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                <h2 style="color: #0073aa;"><?php _e('Your Vouchers Are Ready!', 'unico-wc'); ?></h2>

                <p><?php printf(__('Dear %s,', 'unico-wc'), esc_html($order->get_billing_first_name())); ?></p>

                <p><?php _e('Your payment has been verified and your voucher codes have been generated.', 'unico-wc'); ?></p>

                <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
                    <h3 style="margin-top: 0;"><?php _e('Your Voucher Codes:', 'unico-wc'); ?></h3>

                    <?php foreach ($vouchers as $index => $voucher): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: white; border: 1px solid #ddd;">
                            <strong><?php echo esc_html($voucher['product_name']); ?></strong><br>
                            <span style="font-size: 18px; color: #0073aa; font-weight: bold; font-family: 'Courier New', monospace;">
                                <?php echo esc_html($voucher['voucher_code']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p><strong><?php _e('Important Notes:', 'unico-wc'); ?></strong></p>
                <ul>
                    <li><?php _e('Please keep these voucher codes safe.', 'unico-wc'); ?></li>
                    <li><?php _e('Each code can only be used once.', 'unico-wc'); ?></li>
                    <li><?php _e('Follow the instructions provided with your exam to redeem the voucher.', 'unico-wc'); ?></li>
                </ul>

                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">

                <p>
                    <strong><?php _e('Order Details:', 'unico-wc'); ?></strong><br>
                    <?php printf(__('Order Number: #%s', 'unico-wc'), $order->get_order_number()); ?><br>
                    <?php printf(__('Order Date: %s', 'unico-wc'), $order->get_date_created()->date_i18n('F j, Y')); ?><br>
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="color: #0073aa;">
                        <?php _e('View Order Details', 'unico-wc'); ?>
                    </a>
                </p>

                <p><?php _e('Thank you for your purchase!', 'unico-wc'); ?></p>

                <p style="color: #666; font-size: 12px;">
                    <?php echo esc_html(get_bloginfo('name')); ?><br>
                    <?php _e('This is an automated email. Please do not reply.', 'unico-wc'); ?>
                </p>
            </div>
        </body>
        </html>
        <?php
        $message = ob_get_clean();

        // Set email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        // Send email
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            $order->add_order_note(__('Voucher codes email sent to customer.', 'unico-wc'));
        } else {
            $order->add_order_note(__('Failed to send voucher codes email.', 'unico-wc'));
        }

        return $sent;
    }

    /**
     * Get vouchers for an order
     */
    public function get_order_vouchers($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array();
        }

        $vouchers_json = $order->get_meta('_voucher_codes');

        if (!$vouchers_json) {
            return array();
        }

        return json_decode($vouchers_json, true);
    }

    /**
     * Display vouchers in order view (customer-facing)
     */
    public function display_vouchers_in_order_view($order) {
        $vouchers = $this->get_order_vouchers($order->get_id());

        if (empty($vouchers)) {
            return;
        }

        ?>
        <section class="unico-voucher-codes" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border: 1px solid #ddd;">
            <h2><?php _e('Your Voucher Codes', 'unico-wc'); ?></h2>

            <?php foreach ($vouchers as $voucher): ?>
                <div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 4px solid #0073aa;">
                    <strong><?php echo esc_html($voucher['product_name']); ?></strong><br>
                    <span style="font-size: 20px; color: #0073aa; font-weight: bold; font-family: 'Courier New', monospace;">
                        <?php echo esc_html($voucher['voucher_code']); ?>
                    </span>
                    <button type="button" class="button copy-voucher-btn" data-code="<?php echo esc_attr($voucher['voucher_code']); ?>">
                        <?php _e('Copy Code', 'unico-wc'); ?>
                    </button>
                </div>
            <?php endforeach; ?>

            <p style="font-size: 14px; color: #666;">
                <strong><?php _e('Note:', 'unico-wc'); ?></strong>
                <?php _e('Please keep these codes safe. Each code can only be used once.', 'unico-wc'); ?>
            </p>
        </section>
        <?php
    }
}

// Add vouchers display to order view page
add_action('woocommerce_view_order', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_status() === 'completed' && $order->get_payment_method() === 'unico_bank_transfer') {
        $generator = Unico_Voucher_Generator::instance();
        $generator->display_vouchers_in_order_view($order);
    }
}, 20);
