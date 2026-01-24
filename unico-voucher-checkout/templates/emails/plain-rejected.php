<?php
if (!defined('ABSPATH')) {
	exit;
}

$reason = is_a($order, 'WC_Order') ? (string) $order->get_meta('_unico_reject_reason') : '';
?>

Your payment verification was rejected.

<?php if ($reason) : ?>
Reason: <?php echo esc_html($reason); ?>
<?php endif; ?>

If you believe this is a mistake, please contact support.
Order: #<?php echo esc_html($order->get_order_number()); ?>

