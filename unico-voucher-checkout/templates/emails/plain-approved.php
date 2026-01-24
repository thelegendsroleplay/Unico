<?php
if (!defined('ABSPATH')) {
	exit;
}

$codes = is_a($order, 'WC_Order') ? (array) $order->get_meta('_unico_voucher_codes', true) : [];

echo "= " . esc_html($email->get_heading()) . " =\n\n";
?>

Great news! Your payment has been verified and approved. Your voucher codes are now ready to use.

Order Details:
- Order Number: #<?php echo esc_html($order->get_order_number()); ?>

- Order Total: <?php echo wp_strip_all_tags($order->get_formatted_order_total()); ?>

- Order Date: <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>


<?php if (!empty($codes)) : ?>
Your Voucher Codes:
Please save these codes in a secure location. You will need them to access your exam vouchers.

<?php foreach ($codes as $code) : ?>
<?php echo esc_html($code); ?>

<?php endforeach; ?>
<?php else : ?>
No voucher codes were generated for this order. Please contact support if you believe this is an error.
<?php endif; ?>

Important Information:
- These codes are unique to your order and cannot be replaced if lost
- All voucher purchases are non-refundable
- Keep these codes confidential and do not share them publicly

If you have any questions or need assistance, please contact our support team.

<?php
echo "\n";
echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
?>

