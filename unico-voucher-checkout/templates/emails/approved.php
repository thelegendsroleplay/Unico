<?php
if (!defined('ABSPATH')) {
	exit;
}

$codes = is_a($order, 'WC_Order') ? (array) $order->get_meta('_unico_voucher_codes', true) : [];

do_action('woocommerce_email_header', $email->get_heading(), $email);
?>

<p>Great news! Your payment has been verified and approved. Your voucher codes are now ready to use.</p>

<h2>Order Details</h2>
<ul>
	<li><strong>Order Number:</strong> #<?php echo esc_html($order->get_order_number()); ?></li>
	<li><strong>Order Total:</strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?></li>
	<li><strong>Order Date:</strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></li>
</ul>

<?php if (!empty($codes)) : ?>
	<h2>Your Voucher Codes</h2>
	<p>Please save these codes in a secure location. You will need them to access your exam vouchers.</p>
	<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee; border-collapse: collapse;">
		<tbody>
			<?php foreach ($codes as $code) : ?>
				<tr>
					<td style="border: 1px solid #eee; padding: 12px; font-family: monospace; font-size: 14px; font-weight: bold; color: #0b2f3b; background: #f8fafc;">
						<?php echo esc_html($code); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<p><em>No voucher codes were generated for this order. Please contact support if you believe this is an error.</em></p>
<?php endif; ?>

<h2>Important Information</h2>
<ul>
	<li>These codes are unique to your order and cannot be replaced if lost</li>
	<li>All voucher purchases are non-refundable</li>
	<li>Keep these codes confidential and do not share them publicly</li>
</ul>

<p>If you have any questions or need assistance, please contact our support team.</p>

<?php
do_action('woocommerce_email_footer', $email);
?>

