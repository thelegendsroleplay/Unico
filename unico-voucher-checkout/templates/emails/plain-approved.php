<?php
if (!defined('ABSPATH')) {
	exit;
}

$codes = is_a($order, 'WC_Order') ? (array) $order->get_meta('_unico_voucher_codes', true) : [];
?>

Your payment has been approved and your voucher codes are ready.

<?php if (!empty($codes)) : ?>
Voucher Codes:
<?php foreach ($codes as $code) : ?>
- <?php echo esc_html($code); ?>
<?php endforeach; ?>
<?php endif; ?>

Order: #<?php echo esc_html($order->get_order_number()); ?>

