<?php
if (!defined('ABSPATH')) {
	exit;
}

$codes = is_a($order, 'WC_Order') ? (array) $order->get_meta('_unico_voucher_codes', true) : [];
?>

<p>Your payment has been approved and your voucher codes are ready.</p>

<?php if (!empty($codes)) : ?>
	<p><strong>Voucher Codes:</strong></p>
	<ul>
		<?php foreach ($codes as $code) : ?>
			<li><?php echo esc_html($code); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<p>Order: #<?php echo esc_html($order->get_order_number()); ?></p>

