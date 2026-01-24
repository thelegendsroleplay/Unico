<?php
if (!defined('ABSPATH')) {
	exit;
}

$reason = is_a($order, 'WC_Order') ? (string) $order->get_meta('_unico_reject_reason') : '';

echo "= " . esc_html($email->get_heading()) . " =\n\n";
?>

Unfortunately, we were unable to verify your payment for order #<?php echo esc_html($order->get_order_number()); ?>.

Order Details:
- Order Number: #<?php echo esc_html($order->get_order_number()); ?>

- Order Total: <?php echo wp_strip_all_tags($order->get_formatted_order_total()); ?>

- Order Date: <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>

- Order Status: <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>


<?php if ($reason) : ?>
Reason for Rejection:
<?php echo esc_html($reason); ?>


<?php endif; ?>
What You Can Do Next:
If you believe this rejection is a mistake or if you have questions about the verification process, please contact our support team with your order number.

We're here to help resolve any issues and get you your voucher codes as quickly as possible.

Need Help?
Contact our support team and include your order number (#<?php echo esc_html($order->get_order_number()); ?>) in your message for faster assistance.

<?php
echo "\n";
echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
?>

