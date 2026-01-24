<?php
if (!defined('ABSPATH')) {
	exit;
}

$reason = is_a($order, 'WC_Order') ? (string) $order->get_meta('_unico_reject_reason') : '';
?>

<p>Your payment verification was rejected.</p>

<?php if ($reason) : ?>
	<p><strong>Reason:</strong> <?php echo nl2br(esc_html($reason)); ?></p>
<?php endif; ?>

<p>If you believe this is a mistake, please contact support.</p>
<p>Order: #<?php echo esc_html($order->get_order_number()); ?></p>

