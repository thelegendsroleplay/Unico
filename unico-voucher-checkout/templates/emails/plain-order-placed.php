<?php
if (!defined('ABSPATH')) {
	exit;
}

echo "= " . esc_html($email->get_heading()) . " =\n\n";
?>

Thank you for your order. We have received your payment information and it is currently under review.

Order Details:
- Order Number: #<?php echo esc_html($order->get_order_number()); ?>

- Order Date: <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>

- Order Total: <?php echo wp_strip_all_tags($order->get_formatted_order_total()); ?>


What Happens Next?

Our team will verify your payment information within 24-48 hours. Once approved:
- You will receive your voucher codes via email
- Your order status will be updated to "Completed"

If there are any issues with your payment verification, we will contact you with further instructions.

Important: Please note that all voucher purchases are non-refundable once approved.

<?php
echo "\n\n";
echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
