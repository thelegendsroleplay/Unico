<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Admin_Verification {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', [$this, 'register_menu'], 99);
		add_action('admin_post_unico_vc_approve', [$this, 'handle_approve']);
		add_action('admin_post_unico_vc_reject', [$this, 'handle_reject']);
		add_action('add_meta_boxes', [$this, 'add_order_metabox']);
		add_action('woocommerce_order_details_after_order_table', [$this, 'render_customer_codes'], 10, 1);
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			'Bank Transfer Verification',
			'Bank Transfer Verification',
			'manage_woocommerce',
			'unico-vc-verification',
			[$this, 'render_page']
		);
	}

	public function render_page() {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('Unauthorized');
		}

		$action = isset($_GET['unico_action']) ? sanitize_text_field(wp_unslash($_GET['unico_action'])) : '';
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		if ($action === 'reject' && $order_id) {
			$this->render_reject_form($order_id);
			return;
		}

		$debug_order_id = isset($_GET['debug_order_id']) ? absint($_GET['debug_order_id']) : 0;

		$order_ids = [];

		$wc_ids = wc_get_orders([
			'limit' => 200,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => 'any',
			'return' => 'ids',
		]);
		if (is_array($wc_ids)) {
			$order_ids = array_merge($order_ids, array_map('absint', $wc_ids));
		}

		$post_ids = get_posts([
			'post_type' => 'shop_order',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => 200,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [
				[
					'key' => '_unico_txn_id',
					'compare' => 'EXISTS',
				],
			],
		]);
		if (is_array($post_ids)) {
			$order_ids = array_merge($order_ids, array_map('absint', $post_ids));
		}

		$recent = get_option('unico_vc_recent_order_ids', []);
		if (is_array($recent)) {
			$order_ids = array_merge($order_ids, array_map('absint', $recent));
		}

		if ($debug_order_id) {
			$order_ids[] = (int) $debug_order_id;
		}

		$order_ids = array_values(array_unique(array_filter($order_ids)));

		$orders = [];
		foreach ($order_ids as $id) {
			$order = wc_get_order($id);
			if ($order) {
				$orders[] = $order;
			}
		}

		$orders = array_values(array_filter($orders, static function ($order) {
			if (!$order || !is_a($order, 'WC_Order')) {
				return false;
			}
			$status = $order->get_status();
			if (in_array($status, ['completed', 'cancelled', 'refunded', 'failed'], true)) {
				return false;
			}
			$pm = (string) $order->get_payment_method();
			$txn = (string) $order->get_meta('_unico_txn_id');
			$receipt_id = (int) $order->get_meta('_unico_receipt_attachment_id');
			$receipt_url = (string) $order->get_meta('_unico_receipt_url');
			return ($pm === 'unico_bank_transfer_verify') || !empty($txn) || $receipt_id > 0 || !empty($receipt_url);
		}));

		echo '<div class="wrap">';
		echo '<h1>Bank Transfer Verification</h1>';

		$notice = isset($_GET['notice']) ? sanitize_text_field(wp_unslash($_GET['notice'])) : '';
		if ($notice === 'approved') {
			echo '<div class="notice notice-success is-dismissible"><p>Order approved and customer notified via email.</p></div>';
		} elseif ($notice === 'rejected') {
			echo '<div class="notice notice-success is-dismissible"><p>Order rejected and customer notified via email.</p></div>';
		} elseif ($notice === 'already_approved') {
			echo '<div class="notice notice-warning is-dismissible"><p>This order has already been approved.</p></div>';
		} elseif ($notice === 'already_cancelled') {
			echo '<div class="notice notice-warning is-dismissible"><p>This order is already cancelled.</p></div>';
		}

		if ($debug_order_id) {
			$this->render_debug($debug_order_id);
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>Order</th><th>Status</th><th>Customer</th><th>Total</th><th>Transaction ID</th><th>Receipt</th><th>Bank</th><th>Payment Method</th><th>Date</th><th>Actions</th>';
		echo '</tr></thead><tbody>';

		if (empty($orders)) {
			echo '<tr><td colspan="10">No orders pending verification.</td></tr>';
		} else {
			foreach ($orders as $order) {
				$this->render_row($order);
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	private function render_debug($order_id) {
		$order = wc_get_order($order_id);
		echo '<div class="notice notice-info" style="padding:12px 12px; margin: 12px 0;">';
		echo '<p><strong>Debug order:</strong> ' . esc_html((string) $order_id) . '</p>';
		if (!$order) {
			echo '<p>Order not found by wc_get_order().</p>';
			echo '</div>';
			return;
		}
		$pm = (string) $order->get_payment_method();
		$txn = (string) $order->get_meta('_unico_txn_id');
		$receipt_id = (int) $order->get_meta('_unico_receipt_attachment_id');
		$receipt_url = (string) $order->get_meta('_unico_receipt_url');

		$status = (string) $order->get_status();
		if (strpos($status, 'unico-payment-ver') === 0) {
			$order->set_status('unico-verify');
			$order->save();
		}

		$recent = get_option('unico_vc_recent_order_ids', []);
		if (!is_array($recent)) {
			$recent = [];
		}
		array_unshift($recent, (int) $order->get_id());
		$recent = array_values(array_unique(array_filter(array_map('absint', $recent))));
		if (count($recent) > 500) {
			$recent = array_slice($recent, 0, 500);
		}
		update_option('unico_vc_recent_order_ids', $recent, false);

		echo '<p>Status: <code>' . esc_html($order->get_status()) . '</code> | Payment method: <code>' . esc_html($pm) . '</code></p>';
		echo '<p>Txn meta: <code>' . esc_html($txn ?: '-') . '</code> | Receipt ID: <code>' . esc_html((string) $receipt_id) . '</code> | Receipt URL: <code>' . esc_html($receipt_url ?: '-') . '</code></p>';
		$edit = get_edit_post_link($order_id);
		if ($edit) {
			echo '<p><a class="button button-secondary" href="' . esc_url($edit) . '">Open order edit</a></p>';
		}
		echo '</div>';
	}

	private function render_row($order) {
		$order_id = $order->get_id();
		$txn = (string) $order->get_meta('_unico_txn_id');
		$receipt_id = (int) $order->get_meta('_unico_receipt_attachment_id');
		$receipt_url = (string) $order->get_meta('_unico_receipt_url');
		$bank = $order->get_meta('_unico_selected_bank_snapshot', true);
		$bank_label = is_array($bank) ? ($bank['display_name'] ?? ($bank['bank_name'] ?? '')) : '';
		$pm = (string) $order->get_payment_method();

		$receipt_html = '-';
		if ($receipt_id) {
			$mime = get_post_mime_type($receipt_id);
			$url = wp_get_attachment_url($receipt_id);
			if ($mime && strpos($mime, 'image/') === 0) {
				$thumb = wp_get_attachment_image($receipt_id, [48, 48], true);
				$receipt_html = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . $thumb . '</a>';
			} elseif ($url) {
				$receipt_html = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">Open</a>';
			}
		} elseif ($receipt_url) {
			$receipt_html = '<a href="' . esc_url($receipt_url) . '" target="_blank" rel="noopener noreferrer">Open</a>';
		}

		$approve_url = wp_nonce_url(admin_url('admin-post.php?action=unico_vc_approve&order_id=' . $order_id), 'unico_vc_approve_' . $order_id);
		$reject_url = admin_url('admin.php?page=unico-vc-verification&unico_action=reject&order_id=' . $order_id);

		echo '<tr>';
		echo '<td><a href="' . esc_url(get_edit_post_link($order_id)) . '">#' . esc_html($order->get_order_number()) . '</a></td>';
		echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
		echo '<td>' . esc_html($order->get_formatted_billing_full_name() ?: $order->get_billing_email()) . '</td>';
		echo '<td>' . wp_kses_post($order->get_formatted_order_total()) . '</td>';
		echo '<td>' . esc_html($txn ?: '-') . '</td>';
		echo '<td>' . wp_kses_post($receipt_html) . '</td>';
		echo '<td>' . esc_html($bank_label ?: '-') . '</td>';
		echo '<td>' . esc_html($pm ?: '-') . '</td>';
		echo '<td>' . esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : '-') . '</td>';
		echo '<td><a class="button button-primary" href="' . esc_url($approve_url) . '">Approve</a> <a class="button" href="' . esc_url($reject_url) . '">Reject</a></td>';
		echo '</tr>';
	}

	private function render_reject_form($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			echo '<div class="wrap"><h1>Reject Order</h1><p>Order not found.</p></div>';
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>Reject Order #' . esc_html($order->get_order_number()) . '</h1>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="unico_vc_reject" />';
		echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '" />';
		wp_nonce_field('unico_vc_reject_' . $order_id);
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">Reason</th><td><textarea name="reason" class="large-text" rows="5" required></textarea></td></tr>';
		echo '</table>';
		submit_button('Reject Order', 'primary');
		echo '</form></div>';
	}

	public function handle_approve() {
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		if (!$order_id || !current_user_can('manage_woocommerce') || !check_admin_referer('unico_vc_approve_' . $order_id)) {
			wp_die('Unauthorized');
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_die('Order not found');
		}

		$existing_codes = $order->get_meta('_unico_voucher_codes', true);
		if (!empty($existing_codes) && is_array($existing_codes)) {
			$order->add_order_note('Approval attempted but codes already exist. No new codes generated.');
			wp_safe_redirect(admin_url('admin.php?page=unico-vc-verification&notice=already_approved'));
			exit;
		}

		$total_qty = 0;
		foreach ($order->get_items() as $item) {
			$total_qty += (int) $item->get_quantity();
		}

		if ($total_qty <= 0) {
			wp_die('No items to generate codes for');
		}

		$codes = Unico_VC_Voucher_Generator::generate_codes($total_qty);
		$order->update_meta_data('_unico_voucher_codes', $codes);
		$order->update_meta_data('_unico_approved_email_sent', current_time('mysql'));
		$order->add_order_note('Payment approved. Voucher codes generated and email sent.');
		$order->set_status('completed');
		$order->save();

		Unico_VC_Emails::instance()->send_approved($order_id);

		wp_safe_redirect(admin_url('admin.php?page=unico-vc-verification&notice=approved'));
		exit;
	}

	public function handle_reject() {
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		if (!$order_id || !current_user_can('manage_woocommerce') || !check_admin_referer('unico_vc_reject_' . $order_id)) {
			wp_die('Unauthorized');
		}

		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
		if (empty($reason)) {
			wp_die('Rejection reason is required');
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_die('Order not found');
		}

		if ($order->get_status() === 'cancelled') {
			$order->add_order_note('Rejection attempted but order already cancelled.');
			wp_safe_redirect(admin_url('admin.php?page=unico-vc-verification&notice=already_cancelled'));
			exit;
		}

		$order->update_meta_data('_unico_reject_reason', $reason);
		$order->update_meta_data('_unico_rejected_email_sent', current_time('mysql'));
		$order->add_order_note('Payment rejected. Reason: ' . $reason . ' | Email sent to customer.');
		$order->set_status('cancelled');
		$order->save();

		Unico_VC_Emails::instance()->send_rejected($order_id);

		wp_safe_redirect(admin_url('admin.php?page=unico-vc-verification&notice=rejected'));
		exit;
	}

	public function add_order_metabox() {
		add_meta_box(
			'unico_vc_payment_meta',
			'Unico Voucher Payment',
			[$this, 'render_order_metabox'],
			'shop_order',
			'side',
			'high'
		);
	}

	public function render_order_metabox($post) {
		$order = wc_get_order($post->ID);
		if (!$order) {
			echo '<p>Order not found.</p>';
			return;
		}

		$txn = (string) $order->get_meta('_unico_txn_id');
		$receipt_id = (int) $order->get_meta('_unico_receipt_attachment_id');
		$receipt_url = (string) $order->get_meta('_unico_receipt_url');
		$codes = (array) $order->get_meta('_unico_voucher_codes', true);

		echo '<p><strong>Transaction ID:</strong> ' . esc_html($txn ?: '-') . '</p>';

		if ($receipt_id) {
			$mime = get_post_mime_type($receipt_id);
			$url = wp_get_attachment_url($receipt_id);
			if ($mime && strpos($mime, 'image/') === 0) {
				echo wp_kses_post(wp_get_attachment_image($receipt_id, 'medium'));
			} elseif ($url) {
				echo '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">Download receipt</a></p>';
			}
		} elseif ($receipt_url) {
			echo '<p><a href="' . esc_url($receipt_url) . '" target="_blank" rel="noopener noreferrer">Download receipt</a></p>';
		}

		if (!empty($codes) && in_array($order->get_status(), ['completed'], true)) {
			echo '<p><strong>Voucher Codes:</strong></p>';
			echo '<ul>';
			foreach ($codes as $code) {
				echo '<li>' . esc_html($code) . '</li>';
			}
			echo '</ul>';
		}
	}

	public function render_customer_codes($order) {
		if (!$order || !is_a($order, 'WC_Order')) {
			return;
		}
		if (!in_array($order->get_status(), ['completed'], true)) {
			return;
		}
		$codes = (array) $order->get_meta('_unico_voucher_codes', true);
		if (empty($codes)) {
			return;
		}
		echo '<section class="woocommerce-order-details">';
		echo '<h2>Voucher Codes</h2>';
		echo '<ul>';
		foreach ($codes as $code) {
			echo '<li>' . esc_html($code) . '</li>';
		}
		echo '</ul>';
		echo '</section>';
	}
}
