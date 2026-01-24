<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Checkout_Page {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode('unico_voucher_checkout', [$this, 'render_checkout']);
		add_shortcode('unico_voucher_thankyou', [$this, 'render_thankyou']);

		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function enqueue_assets() {
		if (!is_singular('page')) {
			return;
		}

		$content = (string) get_post_field('post_content', get_the_ID());
		if (!has_shortcode($content, 'unico_voucher_checkout') && !has_shortcode($content, 'unico_voucher_thankyou')) {
			return;
		}

		wp_enqueue_style('unico-vc-checkout');
		wp_enqueue_script('unico-vc-checkout');

		wp_localize_script('unico-vc-checkout', 'UnicoVC', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('unico_vc_create_order'),
			'currencySymbol' => get_woocommerce_currency_symbol(),
			'currencyCode' => get_woocommerce_currency(),
			'allowGuest' => (bool) get_option('unico_vc_allow_guest', false),
		]);
	}

	private function current_user_prefill() {
		$name = '';
		$email = '';
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			$email = $user->user_email;
			$name = trim($user->first_name . ' ' . $user->last_name);
			if (empty($name)) {
				$name = $user->display_name;
			}
		}
		return [$name, $email];
	}

	public function render_checkout() {
		if (!class_exists('WooCommerce')) {
			return '<div class="unico-vc-card"><p>WooCommerce is required.</p></div>';
		}

		$allow_guest = (bool) get_option('unico_vc_allow_guest', false);
		if (!$allow_guest && !is_user_logged_in()) {
			return '<div class="unico-vc-page"><div class="unico-vc-card"><p>Please log in to continue.</p></div></div>';
		}

		$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
		$product = $product_id ? wc_get_product($product_id) : null;
		$is_valid_product = $product && $product->exists() && $product->is_purchasable();

		[$buyer_name, $buyer_email] = $this->current_user_prefill();

		$bank_available = Unico_VC_Bank_Manager::instance()->has_active_banks();
		$bank_key = '';
		$bank = null;
		if ($bank_available) {
			$bank = Unico_VC_Bank_Manager::instance()->get_random_bank_snapshot();
			if ($bank) {
				$bank_key = wp_generate_uuid4();
				set_transient('unico_vc_bank_' . $bank_key, $bank, 30 * MINUTE_IN_SECONDS);
			}
		}

		$unit_price = $is_valid_product ? (float) wc_get_price_to_display($product) : 0.0;

		ob_start();
		?>
		<div class="unico-vc-page">
			<div
				class="unico-vc-card"
				id="unico-vc-checkout"
				data-product-id="<?php echo esc_attr($product_id); ?>"
				data-product-price="<?php echo esc_attr((string) $unit_price); ?>"
				data-bank-key="<?php echo esc_attr($bank_key); ?>"
			>
				<div class="unico-vc-head">
					<div class="unico-vc-kicker">CHECKOUT NODE</div>
					<div class="unico-vc-title-row">
						<div class="unico-vc-title" id="unico-vc-product-name"><?php echo $is_valid_product ? esc_html($product->get_name()) : 'No voucher selected'; ?></div>
						<div class="unico-vc-badge" id="unico-vc-qty-badge">X1</div>
					</div>
				</div>

				<?php if (!$is_valid_product) : ?>
					<div class="unico-vc-empty">
						<p>No voucher selected.</p>
						<a class="unico-vc-link" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Go to shop</a>
					</div>
				<?php else : ?>
					<div class="unico-vc-errors" id="unico-vc-errors" role="alert" aria-live="polite"></div>

					<div class="unico-vc-row unico-vc-qty-row">
						<div class="unico-vc-label">QUANTITY</div>
						<div class="unico-vc-qty">
							<button type="button" class="unico-vc-qty-btn" data-action="decrease">-</button>
							<input type="number" min="1" max="10" step="1" value="1" class="unico-vc-qty-input" id="unico-vc-qty" inputmode="numeric" />
							<button type="button" class="unico-vc-qty-btn" data-action="increase">+</button>
						</div>
					</div>

					<div class="unico-vc-grid">
						<div class="unico-vc-field">
							<div class="unico-vc-label">BUYER FULL NAME</div>
							<input type="text" id="unico-vc-buyer-name" value="<?php echo esc_attr($buyer_name); ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?> />
						</div>
						<div class="unico-vc-field">
							<div class="unico-vc-label">REGISTERED EMAIL ID</div>
							<input type="email" id="unico-vc-buyer-email" value="<?php echo esc_attr($buyer_email); ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?> />
						</div>
					</div>

					<div class="unico-vc-pay">
						<button type="button" class="unico-vc-pay-btn is-active" data-method="bank">BANK TRANSFER <span>LIMIT: 10 UNITS</span></button>
						<button type="button" class="unico-vc-pay-btn is-disabled" data-method="card" disabled>CARD PAYMENT <span>COMING SOON</span></button>
					</div>

					<div class="unico-vc-bank">
						<div class="unico-vc-bank-box">
							<div class="unico-vc-bank-title">PAYMENT INFORMATION</div>
							<?php if (!$bank_available || !$bank) : ?>
								<div class="unico-vc-bank-note">Bank transfer is temporarily unavailable. Please contact support.</div>
							<?php else : ?>
								<div class="unico-vc-bank-lines">
									<div><strong>Please transfer the total amount to:</strong></div>
									<div>Bank Name: <?php echo esc_html($bank['bank_name']); ?></div>
									<div>Account Name: <?php echo esc_html($bank['account_holder']); ?></div>
									<div>Account Number: <?php echo esc_html($bank['account_number']); ?></div>
									<div>IFSC Code: <?php echo esc_html($bank['ifsc']); ?></div>
								</div>
							<?php endif; ?>
						</div>

						<div class="unico-vc-field">
							<div class="unico-vc-label">PAYMENT REFERENCE NUMBER <span class="unico-vc-req">*</span></div>
							<input type="text" id="unico-vc-txn" placeholder="Transaction ID..." />
						</div>

						<div class="unico-vc-field">
							<div class="unico-vc-label">UPLOAD TRANSFER RECEIPT <span class="unico-vc-req">*</span></div>
							<div class="unico-vc-upload">
								<input type="file" id="unico-vc-receipt" accept=".jpg,.jpeg,.png,.webp" />
								<button type="button" class="unico-vc-upload-btn" id="unico-vc-upload-btn">CLICK TO UPLOAD RECEIPT</button>
								<div class="unico-vc-upload-meta" id="unico-vc-upload-meta">No file selected</div>
							</div>
						</div>
					</div>

					<label class="unico-vc-confirm">
						<input type="checkbox" id="unico-vc-confirm" />
						<span>Confirm accuracy. Items are <strong class="unico-vc-danger">NON-REFUNDABLE</strong> and cannot be exchanged once fulfillment begins.</span>
					</label>

					<div class="unico-vc-footer">
						<div class="unico-vc-total">
							<div class="unico-vc-total-label">TOTAL SETTLEMENT</div>
							<div class="unico-vc-total-amount" id="unico-vc-total"><?php echo wp_kses_post(wc_price($unit_price)); ?></div>
						</div>
						<button type="button" class="unico-vc-submit" id="unico-vc-submit" <?php echo ($bank_available && $bank) ? '' : 'disabled'; ?>>CONFIRM ORDER</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_thankyou() {
		if (!class_exists('WooCommerce')) {
			return '<div class="unico-vc-card"><p>WooCommerce is required.</p></div>';
		}

		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		$key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
		$order = $order_id ? wc_get_order($order_id) : null;

		$is_valid = false;
		if ($order && $order instanceof WC_Order && $key && hash_equals($order->get_order_key(), $key)) {
			$is_valid = true;
		}

		$title = $is_valid ? 'Order Received' : 'Order not found';
		$status = $is_valid ? wc_get_order_status_name($order->get_status()) : '';
		$total = $is_valid ? $order->get_formatted_order_total() : '';

		ob_start();
		?>
		<div class="unico-vc-page">
			<div class="unico-vc-card unico-vc-thankyou">
				<div class="unico-vc-head">
					<div class="unico-vc-kicker">ORDER STATUS</div>
					<div class="unico-vc-title-row">
						<div class="unico-vc-title"><?php echo esc_html($title); ?></div>
						<div class="unico-vc-badge"><?php echo $is_valid ? esc_html($status) : ''; ?></div>
					</div>
				</div>

				<?php if ($is_valid) : ?>
					<div class="unico-vc-bank-box">
						<div class="unico-vc-bank-lines">
							<div>Order ID: <strong>#<?php echo esc_html($order->get_order_number()); ?></strong></div>
							<div>Total: <strong><?php echo wp_kses_post($total); ?></strong></div>
							<div>Date: <strong><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></strong></div>
						</div>
					</div>
					<div class="unico-vc-bank-note">
						<strong>What happens next?</strong><br>
						Your order is currently under review. Our team will verify your payment within 24-48 hours.
						Once approved, you will receive an email with your voucher codes.
					</div>
					<?php if ($order->get_status() === 'completed') : ?>
						<div class="unico-vc-bank-box" style="background: #f0fdf4; border-color: #86efac;">
							<div class="unico-vc-bank-lines">
								<div><strong>✓ Order Approved</strong></div>
								<div>Your voucher codes have been sent to your email: <?php echo esc_html($order->get_billing_email()); ?></div>
							</div>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<div class="unico-vc-bank-note">
						We couldn't verify this order. Please check your order confirmation email for the correct link,
						or contact support if you need assistance.
					</div>
				<?php endif; ?>

				<div class="unico-vc-footer">
					<?php if ($is_valid && is_user_logged_in()) : ?>
						<a class="unico-vc-submit unico-vc-linkbtn" href="<?php echo esc_url($order->get_view_order_url()); ?>" style="background: #0b3b4a; margin-right: 8px;">VIEW ORDER</a>
					<?php endif; ?>
					<a class="unico-vc-submit unico-vc-linkbtn" href="<?php echo esc_url(home_url('/')); ?>">GO HOME</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
