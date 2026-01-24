<?php
if (!defined('ABSPATH')) {
	exit;
}

do_action('woocommerce_email_header', $email->get_heading(), $email);
?>

<p>Thank you for your order. We have received your payment information and it is currently under review.</p>

<h2>Order Details</h2>
<ul>
	<li><strong>Order Number:</strong> #<?php echo esc_html($order->get_order_number()); ?></li>
	<li><strong>Order Date:</strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></li>
	<li><strong>Order Total:</strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?></li>
</ul>

<h2>What Happens Next?</h2>
<p>Our team will verify your payment information within 24-48 hours. Once approved:</p>
<ul>
	<li>You will receive your voucher codes via email</li>
	<li>Your order status will be updated to "Completed"</li>
</ul>

<p>If there are any issues with your payment verification, we will contact you with further instructions.</p>

<p><strong>Important:</strong> Please note that all voucher purchases are non-refundable once approved.</p>

<?php
do_action('woocommerce_email_footer', $email);
