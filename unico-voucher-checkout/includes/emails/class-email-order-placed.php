<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Email_Order_Placed extends WC_Email {
	public function __construct() {
		$this->id = 'unico_vc_order_placed';
		$this->customer_email = true;
		$this->title = 'Voucher Order Placed';
		$this->description = 'Sent when a customer places a voucher order that is under review.';

		$this->heading = 'Order received - Under review';
		$this->subject = 'Order #{order_number} received and under review';

		$this->template_html = 'emails/order-placed.php';
		$this->template_plain = 'emails/plain-order-placed.php';
		$this->template_base = UNICO_VC_PLUGIN_DIR . 'templates/';

		$this->placeholders = [
			'{order_number}' => '',
			'{order_date}' => '',
		];

		parent::__construct();
	}

	public function trigger($order_id) {
		$this->object = wc_get_order($order_id);
		if (!$this->object) {
			return;
		}

		$this->recipient = $this->object->get_billing_email();
		if (!$this->is_enabled() || !$this->get_recipient()) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());

		$this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
	}

	public function get_content_html() {
		return wc_get_template_html($this->template_html, ['order' => $this->object, 'email' => $this], '', $this->template_base);
	}

	public function get_content_plain() {
		return wc_get_template_html($this->template_plain, ['order' => $this->object, 'email' => $this], '', $this->template_base);
	}
}
