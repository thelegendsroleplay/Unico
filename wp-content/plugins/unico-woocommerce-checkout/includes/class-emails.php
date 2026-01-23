<?php
/**
 * Email Notifications
 * Custom email notifications for order status changes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Emails {

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
        // Send email on payment approval
        add_action('unico_payment_approved', array($this, 'send_approval_email'), 10, 2);

        // Send email on payment rejection
        add_action('unico_payment_rejected', array($this, 'send_rejection_email'), 10, 2);

        // Add support ticket button to order view for rejected orders
        add_action('woocommerce_view_order', array($this, 'display_support_ticket_button'), 5);

        // Handle support ticket redirect
        add_action('template_redirect', array($this, 'handle_support_ticket_redirect'));
    }

    /**
     * Send approval email
     */
    public function send_approval_email($order_id, $order) {
        $to = $order->get_billing_email();
        $subject = sprintf(__('Payment Approved - Order #%s', 'unico-wc'), $order->get_order_number());

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
                <div style="background: #4CAF50; color: white; padding: 15px; text-align: center;">
                    <h2 style="margin: 0;"><?php _e('✓ Payment Approved!', 'unico-wc'); ?></h2>
                </div>

                <div style="padding: 20px;">
                    <p><?php printf(__('Dear %s,', 'unico-wc'), esc_html($order->get_billing_first_name())); ?></p>

                    <p><?php _e('Great news! Your payment has been verified and approved.', 'unico-wc'); ?></p>

                    <p><?php _e('Your order is now being processed and your vouchers will be delivered shortly.', 'unico-wc'); ?></p>

                    <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;">
                        <p style="margin: 0;"><strong><?php _e('Order Number:', 'unico-wc'); ?></strong> #<?php echo $order->get_order_number(); ?></p>
                        <p style="margin: 5px 0 0 0;"><strong><?php _e('Order Total:', 'unico-wc'); ?></strong> <?php echo $order->get_formatted_order_total(); ?></p>
                    </div>

                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($order->get_view_order_url()); ?>"
                           style="background: #0073aa; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;">
                            <?php _e('View Order Details', 'unico-wc'); ?>
                        </a>
                    </p>

                    <p><?php _e('Thank you for your patience!', 'unico-wc'); ?></p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666;">
                    <p style="margin: 0;"><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p style="margin: 5px 0 0 0;"><?php _e('This is an automated email. Please do not reply.', 'unico-wc'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $message = ob_get_clean();

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send rejection email
     */
    public function send_rejection_email($order_id, $order) {
        $to = $order->get_billing_email();
        $subject = sprintf(__('Payment Issue - Order #%s', 'unico-wc'), $order->get_order_number());

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
                <div style="background: #f44336; color: white; padding: 15px; text-align: center;">
                    <h2 style="margin: 0;"><?php _e('Payment Verification Issue', 'unico-wc'); ?></h2>
                </div>

                <div style="padding: 20px;">
                    <p><?php printf(__('Dear %s,', 'unico-wc'), esc_html($order->get_billing_first_name())); ?></p>

                    <p><?php _e('We were unable to verify your payment for the following order:', 'unico-wc'); ?></p>

                    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #f44336; margin: 20px 0;">
                        <p style="margin: 0;"><strong><?php _e('Order Number:', 'unico-wc'); ?></strong> #<?php echo $order->get_order_number(); ?></p>
                        <p style="margin: 5px 0 0 0;"><strong><?php _e('Order Total:', 'unico-wc'); ?></strong> <?php echo $order->get_formatted_order_total(); ?></p>
                    </div>

                    <p><strong><?php _e('What happens next?', 'unico-wc'); ?></strong></p>
                    <ul>
                        <li><?php _e('Your order has been marked as rejected.', 'unico-wc'); ?></li>
                        <li><?php _e('No charges will be processed.', 'unico-wc'); ?></li>
                        <li><?php _e('If you believe this is a mistake, please contact our support team.', 'unico-wc'); ?></li>
                    </ul>

                    <p style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url($order->get_view_order_url()); ?>"
                           style="background: #0073aa; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 10px;">
                            <?php _e('View Order', 'unico-wc'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('open_ticket', $order_id, $order->get_view_order_url())); ?>"
                           style="background: #f44336; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;">
                            <?php _e('Open Support Ticket', 'unico-wc'); ?>
                        </a>
                    </p>

                    <p><?php _e('We apologize for any inconvenience.', 'unico-wc'); ?></p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666;">
                    <p style="margin: 0;"><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p style="margin: 5px 0 0 0;"><?php _e('This is an automated email. Please do not reply.', 'unico-wc'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $message = ob_get_clean();

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Display support ticket button on order view for rejected orders
     */
    public function display_support_ticket_button($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Only show for rejected orders
        if ($order->get_status() !== 'rejected') {
            return;
        }

        // Only show for bank transfer orders
        if ($order->get_payment_method() !== 'unico_bank_transfer') {
            return;
        }

        ?>
        <div class="unico-rejected-order-notice" style="background: #fff3cd; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0;">
            <h3 style="color: #f44336; margin-top: 0;">
                <?php _e('Payment Verification Issue', 'unico-wc'); ?>
            </h3>
            <p><?php _e('Your payment could not be verified. If you believe this is an error, please contact our support team.', 'unico-wc'); ?></p>

            <p style="margin-bottom: 0;">
                <a href="<?php echo esc_url(add_query_arg('open_ticket', $order_id, wc_get_account_endpoint_url('orders'))); ?>"
                   class="button button-primary"
                   style="background: #f44336; border-color: #f44336;">
                    <?php _e('Open Support Ticket', 'unico-wc'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle support ticket redirect
     */
    public function handle_support_ticket_redirect() {
        if (!isset($_GET['open_ticket'])) {
            return;
        }

        $order_id = intval($_GET['open_ticket']);
        $order = wc_get_order($order_id);

        if (!$order || !is_user_logged_in() || $order->get_customer_id() !== get_current_user_id()) {
            return;
        }

        // Check if you have a support ticket system
        // If you have existing support system, integrate here

        // For now, redirect to contact page or show a simple form
        // You can customize this based on your existing support system

        // Example: Redirect to contact page with order ID
        $contact_url = home_url('/contact');
        $redirect_url = add_query_arg(array(
            'subject' => 'Payment Issue - Order #' . $order->get_order_number(),
            'order_id' => $order_id,
        ), $contact_url);

        // Or create a simple support ticket in the database
        $this->create_support_ticket($order_id, $order);

        // Redirect with success message
        wc_add_notice(__('Support ticket created. Our team will contact you soon.', 'unico-wc'), 'success');
        wp_redirect($order->get_view_order_url());
        exit;
    }

    /**
     * Create support ticket
     */
    private function create_support_ticket($order_id, $order) {
        // This is a placeholder function
        // Integrate with your existing support ticket system

        // Example: Add order note
        $order->add_order_note(
            __('Customer opened a support ticket regarding payment verification.', 'unico-wc'),
            false,
            true
        );

        // If you have unico_support_tickets table, insert here
        global $wpdb;

        $table_name = $wpdb->prefix . 'unico_support_tickets';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $order->get_customer_id(),
                    'order_id' => $order_id,
                    'subject' => 'Payment Verification Issue - Order #' . $order->get_order_number(),
                    'message' => 'Customer is disputing payment rejection.',
                    'status' => 'open',
                    'priority' => 'high',
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        }

        // Send email to admin
        $admin_email = get_option('admin_email');
        $subject = 'Support Ticket: Payment Issue - Order #' . $order->get_order_number();
        $message = sprintf(
            "A support ticket has been opened by %s regarding order #%s.\n\nReason: Payment verification rejection\n\nOrder URL: %s",
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $order->get_order_number(),
            admin_url('post.php?post=' . $order_id . '&action=edit')
        );

        wp_mail($admin_email, $subject, $message);
    }
}
