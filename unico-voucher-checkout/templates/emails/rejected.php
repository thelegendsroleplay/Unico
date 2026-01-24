<?php
if (!defined('ABSPATH')) {
	exit;
}

$reason = is_a($order, 'WC_Order') ? (string) $order->get_meta('_unico_reject_reason') : '';

do_action('woocommerce_email_header', $email->get_heading(), $email);
?>

<p>Unfortunately, we were unable to verify your payment for order #<?php echo esc_html($order->get_order_number()); ?>.</p>

<h2>Order Details</h2>
<ul>
	<li><strong>Order Number:</strong> #<?php echo esc_html($order->get_order_number()); ?></li>
	<li><strong>Order Total:</strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?></li>
	<li><strong>Order Date:</strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></li>
	<li><strong>Order Status:</strong> <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></li>
</ul>

<?php if ($reason) : ?>
	<h2>Reason for Rejection</h2>
	<p style="padding: 12px; background: #f8fafc; border-left: 4px solid #ef4444;">
		<?php echo nl2br(esc_html($reason)); ?>
	</p>
<?php endif; ?>

<h2>What You Can Do Next</h2>
<p>If you believe this rejection is a mistake or if you have questions about the verification process, please contact our support team with your order number.</p>

<p>We're here to help resolve any issues and get you your voucher codes as quickly as possible.</p>

<h2>Need Help?</h2>
<p>Contact our support team and include your order number (#<?php echo esc_html($order->get_order_number()); ?>) in your message for faster assistance.</p>

<?php
do_action('woocommerce_email_footer', $email);
?>

