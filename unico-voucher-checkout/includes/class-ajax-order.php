<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Ajax_Order {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('wp_ajax_unico_vc_create_order', [$this, 'handle']);
		add_action('wp_ajax_nopriv_unico_vc_create_order', [$this, 'handle']);
		add_action('wp_ajax_unico_vc_request_otp', [$this, 'handle_request_otp']);
		add_action('wp_ajax_nopriv_unico_vc_request_otp', [$this, 'handle_request_otp']);
		add_action('wp_ajax_unico_vc_verify_otp', [$this, 'handle_verify_otp']);
		add_action('wp_ajax_nopriv_unico_vc_verify_otp', [$this, 'handle_verify_otp']);
	}

	public function handle() {
		if (!class_exists('WooCommerce')) {
			wp_send_json_error(['message' => 'WooCommerce is required.'], 400);
		}

		$logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
		$log_context = ['source' => 'unico_vc_checkout'];

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'unico_vc_create_order')) {
			wp_send_json_error(['message' => 'Invalid request.'], 403);
		}

		$allow_guest = (bool) get_option('unico_vc_allow_guest', false);
		if (!$allow_guest && !is_user_logged_in()) {
			wp_send_json_error(['message' => 'Please log in to continue.'], 401);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$qty = isset($_POST['qty']) ? max(1, absint($_POST['qty'])) : 1;
		$buyer_name = isset($_POST['buyer_name']) ? sanitize_text_field(wp_unslash($_POST['buyer_name'])) : '';
		$buyer_email = isset($_POST['buyer_email']) ? sanitize_email(wp_unslash($_POST['buyer_email'])) : '';
		$txn_id = isset($_POST['txn_id']) ? sanitize_text_field(wp_unslash($_POST['txn_id'])) : '';
		$confirm = isset($_POST['confirm']) ? (int) $_POST['confirm'] : 0;
		$bank_key = isset($_POST['bank_key']) ? sanitize_text_field(wp_unslash($_POST['bank_key'])) : '';
		$otp_key = isset($_POST['otp_key']) ? sanitize_text_field(wp_unslash($_POST['otp_key'])) : '';

		$errors = [];

		if (!$product_id) {
			$errors[] = 'No voucher selected.';
		}
		$product = $product_id ? wc_get_product($product_id) : null;
		if (!$product || !$product->exists() || !$product->is_purchasable()) {
			$errors[] = 'Invalid voucher selected.';
		}
		if ($qty < 1) {
			$errors[] = 'Quantity must be at least 1.';
		}
		if (empty($buyer_name)) {
			$errors[] = 'Buyer name is required.';
		}
		if (empty($buyer_email) || !is_email($buyer_email)) {
			$errors[] = 'Valid email is required.';
		}
		if (!$confirm) {
			$errors[] = 'Please confirm non-refundable policy.';
		}
		if (empty($txn_id)) {
			$errors[] = 'Transaction ID is required.';
		}

		$file = $_FILES['receipt'] ?? null;
		if (!$file || empty($file['name'])) {
			$errors[] = 'Receipt upload is required.';
		}

		if (empty($errors) && $product && $product->managing_stock()) {
			if (!$product->has_enough_stock($qty)) {
				$errors[] = 'Not enough stock available.';
			}
		}

		$bank_snapshot = null;
		if ($bank_key) {
			$bank_snapshot = get_transient('unico_vc_bank_' . $bank_key);
		}
		if (!$bank_snapshot || !is_array($bank_snapshot) || empty($bank_snapshot['bank_id'])) {
			$errors[] = 'No bank account available. Please refresh and try again.';
		}

		if (!$this->is_otp_verified($otp_key, $buyer_email)) {
			$errors[] = 'Email verification is required.';
		}

		if (!empty($errors)) {
			wp_send_json_error(['message' => 'Validation failed.', 'errors' => $errors], 422);
		}

		if ($logger) {
			$logger->info('Create order request. product_id=' . (int) $product_id . ' qty=' . (int) $qty, $log_context);
			$logger->info('FILES keys: ' . implode(',', array_keys($_FILES)), $log_context);
		}

		$upload_result = $this->handle_receipt_upload($file, $logger, $log_context);
		if (is_wp_error($upload_result)) {
			wp_send_json_error(['message' => $upload_result->get_error_message()], 422);
		}

		$order = wc_create_order([
			'customer_id' => get_current_user_id(),
		]);
		if (is_wp_error($order) || !$order) {
			wp_send_json_error(['message' => 'Unable to create order.'], 500);
		}

		if (method_exists($order, 'set_created_via')) {
			$order->set_created_via('unico-vc');
		}

		$order->add_product($product, $qty);

		$name_parts = preg_split('/\s+/', trim($buyer_name));
		$first = $name_parts[0] ?? $buyer_name;
		$last = '';
		if (count($name_parts) > 1) {
			$last = trim(implode(' ', array_slice($name_parts, 1)));
		}

		$order->set_billing_first_name($first);
		$order->set_billing_last_name($last);
		$order->set_billing_email($buyer_email);

		$order->set_payment_method('unico_bank_transfer_verify');
		$order->set_payment_method_title('Bank Transfer');

		$order->update_meta_data('_unico_txn_id', $txn_id);
		$order->update_meta_data('_unico_receipt_attachment_id', (int) $upload_result['attachment_id']);
		$order->update_meta_data('_unico_receipt_url', esc_url_raw($upload_result['url']));
		$order->update_meta_data('_unico_selected_bank_id', (int) $bank_snapshot['bank_id']);
		$order->update_meta_data('_unico_selected_bank_snapshot', $bank_snapshot);
		$order->update_meta_data('_unico_otp_verified', 1);
		$order->update_meta_data('_unico_otp_verified_email', $buyer_email);
		$order->update_meta_data('_unico_otp_verified_at', current_time('mysql'));

		$order->calculate_totals();
		$target_status = 'unico-verify';
		$statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
		if (is_array($statuses) && isset($statuses['wc-' . $target_status])) {
			$order->set_status($target_status);
		} else {
			$order->set_status('on-hold');
		}
		$order->save();

		wc_reduce_stock_levels($order->get_id());
		if (function_exists('wc_update_order_stats')) {
			wc_update_order_stats($order->get_id());
		}

		delete_transient('unico_vc_bank_' . $bank_key);

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

		if ($logger) {
			$logger->info('Order created. order_id=' . (int) $order->get_id() . ' receipt_id=' . (int) $upload_result['attachment_id'], $log_context);
		}

		$this->clear_otp($otp_key);

		$redirect = Unico_VC_Plugin::thankyou_url([
			'order_id' => $order->get_id(),
			'key' => $order->get_order_key(),
		]);

		wp_send_json_success([
			'order_id' => $order->get_id(),
			'redirect' => $redirect,
		]);
	}

	private function handle_receipt_upload($file, $logger, $log_context) {
		$max_bytes = 5 * 1024 * 1024;
		if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
			return new WP_Error('unico_vc_receipt_size', 'Receipt must be 5MB or smaller.');
		}

		if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
			return new WP_Error('unico_vc_receipt_upload', 'Receipt upload failed. Please try again.');
		}

		$allowed_mimes = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		];

		$check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
		if (empty($check['ext']) || empty($check['type'])) {
			return new WP_Error('unico_vc_receipt_type', 'Invalid file type. Use JPG, PNG, or WEBP.');
		}

		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if (!function_exists('wp_generate_attachment_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ($logger) {
			$logger->info('Uploading receipt: ' . sanitize_file_name((string) $file['name']) . ' size=' . (int) $file['size'], $log_context);
		}

		$uploaded = wp_handle_upload($file, [
			'test_form' => false,
			'mimes' => $allowed_mimes,
		]);

		if (empty($uploaded) || !empty($uploaded['error'])) {
			$msg = !empty($uploaded['error']) ? (string) $uploaded['error'] : 'Unknown upload error';
			return new WP_Error('unico_vc_receipt_upload', $msg);
		}

		$attachment_id = wp_insert_attachment([
			'post_mime_type' => $uploaded['type'],
			'post_title' => sanitize_text_field(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
			'post_content' => '',
			'post_status' => 'inherit',
		], $uploaded['file']);

		if (!$attachment_id || is_wp_error($attachment_id)) {
			return new WP_Error('unico_vc_receipt_attach', 'Failed to save receipt.');
		}

		$metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
		if (!empty($metadata)) {
			wp_update_attachment_metadata($attachment_id, $metadata);
		}

		if ($logger) {
			$logger->info('Receipt uploaded. attachment_id=' . (int) $attachment_id, $log_context);
		}

		return [
			'attachment_id' => (int) $attachment_id,
			'url' => $uploaded['url'],
		];
	}

	public function handle_request_otp() {
		if (!class_exists('WooCommerce')) {
			wp_send_json_error(['message' => 'WooCommerce is required.'], 400);
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'unico_vc_otp')) {
			wp_send_json_error(['message' => 'Invalid request.'], 403);
		}

		$allow_guest = (bool) get_option('unico_vc_allow_guest', false);
		if (!$allow_guest && !is_user_logged_in()) {
			wp_send_json_error(['message' => 'Please log in to continue.'], 401);
		}

		$buyer_name = isset($_POST['buyer_name']) ? sanitize_text_field(wp_unslash($_POST['buyer_name'])) : '';
		$buyer_email = isset($_POST['buyer_email']) ? sanitize_email(wp_unslash($_POST['buyer_email'])) : '';
		$otp_key = isset($_POST['otp_key']) ? sanitize_text_field(wp_unslash($_POST['otp_key'])) : '';

		if (empty($buyer_name)) {
			wp_send_json_error(['message' => 'Buyer name is required.'], 422);
		}
		if (empty($buyer_email) || !is_email($buyer_email)) {
			wp_send_json_error(['message' => 'Valid email is required.'], 422);
		}

		$otp_data = $otp_key ? $this->get_otp($otp_key) : null;
		if (!$otp_data) {
			$otp_key = wp_generate_uuid4();
		}

		$now = time();
		if ($otp_data && !empty($otp_data['last_sent']) && ($now - (int) $otp_data['last_sent']) < 60) {
			$remaining = 60 - ($now - (int) $otp_data['last_sent']);
			wp_send_json_error(['message' => 'Please wait ' . $remaining . ' seconds before requesting another code.'], 429);
		}

		$code = (string) random_int(100000, 999999);
		$payload = [
			'email' => $buyer_email,
			'name' => $buyer_name,
			'code_hash' => wp_hash_password($code),
			'created' => $now,
			'last_sent' => $now,
			'verified' => false,
			'attempts' => $otp_data['attempts'] ?? 0,
		];

		$this->store_otp($otp_key, $payload);

		$sent = $this->send_otp_email($buyer_email, $buyer_name, $code);
		if (!$sent) {
			wp_send_json_error(['message' => 'Unable to send verification code. Please try again.'], 500);
		}

		wp_send_json_success([
			'otp_key' => $otp_key,
			'message' => 'Verification code sent.',
		]);
	}

	public function handle_verify_otp() {
		if (!class_exists('WooCommerce')) {
			wp_send_json_error(['message' => 'WooCommerce is required.'], 400);
		}

		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (!wp_verify_nonce($nonce, 'unico_vc_otp')) {
			wp_send_json_error(['message' => 'Invalid request.'], 403);
		}

		$buyer_email = isset($_POST['buyer_email']) ? sanitize_email(wp_unslash($_POST['buyer_email'])) : '';
		$otp_key = isset($_POST['otp_key']) ? sanitize_text_field(wp_unslash($_POST['otp_key'])) : '';
		$otp_code = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';

		if (empty($buyer_email) || !is_email($buyer_email)) {
			wp_send_json_error(['message' => 'Valid email is required.'], 422);
		}
		if (empty($otp_key) || empty($otp_code)) {
			wp_send_json_error(['message' => 'Verification code is required.'], 422);
		}

		$otp_data = $this->get_otp($otp_key);
		if (!$otp_data || empty($otp_data['code_hash'])) {
			wp_send_json_error(['message' => 'Verification code expired. Please request a new one.'], 410);
		}
		if (!empty($otp_data['email']) && strtolower($otp_data['email']) !== strtolower($buyer_email)) {
			wp_send_json_error(['message' => 'Email does not match the verification request.'], 403);
		}

		$attempts = (int) ($otp_data['attempts'] ?? 0);
		if ($attempts >= 5) {
			wp_send_json_error(['message' => 'Too many failed attempts. Request a new code.'], 429);
		}

		if (!wp_check_password($otp_code, $otp_data['code_hash'])) {
			$otp_data['attempts'] = $attempts + 1;
			$this->store_otp($otp_key, $otp_data);
			wp_send_json_error(['message' => 'Incorrect verification code.'], 422);
		}

		$otp_data['verified'] = true;
		$otp_data['verified_at'] = time();
		$this->store_otp($otp_key, $otp_data);

		wp_send_json_success(['message' => 'Email verified.']);
	}

	private function store_otp($otp_key, array $data) {
		set_transient('unico_vc_otp_' . $otp_key, $data, 10 * MINUTE_IN_SECONDS);
	}

	private function get_otp($otp_key) {
		if (empty($otp_key)) {
			return null;
		}
		$data = get_transient('unico_vc_otp_' . $otp_key);
		return is_array($data) ? $data : null;
	}

	private function clear_otp($otp_key) {
		if (!empty($otp_key)) {
			delete_transient('unico_vc_otp_' . $otp_key);
		}
	}

	private function is_otp_verified($otp_key, $buyer_email) {
		$otp_data = $this->get_otp($otp_key);
		if (!$otp_data || empty($otp_data['verified'])) {
			return false;
		}
		if (!empty($otp_data['email']) && strtolower($otp_data['email']) !== strtolower((string) $buyer_email)) {
			return false;
		}
		return true;
	}

	private function send_otp_email($email, $name, $code) {
		$subject = 'Your Unico verification code';
		$message = sprintf(
			"Hi %s,\n\nYour verification code is: %s\n\nThis code will expire in 10 minutes.\n\nIf you did not request this, you can ignore this email.",
			$name ? $name : 'there',
			$code
		);
		$headers = ['Content-Type: text/plain; charset=UTF-8'];
		return wp_mail($email, $subject, $message, $headers);
	}
}
