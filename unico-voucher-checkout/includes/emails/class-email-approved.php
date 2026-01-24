<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Email_Approved extends WC_Email {
	public function __construct() {
		$this->id = 'unico_vc_approved';
		$this->title = 'Voucher Approved';
		$this->description = 'Sent when a voucher order is approved and codes are generated.';

		$this->heading = 'Your voucher codes are ready';
		$this->subject = 'Your voucher codes are ready';

		$this->template_html = 'emails/approved.php';
		$this->template_plain = 'emails/plain-approved.php';
		$this->template_base = UNICO_VC_PLUGIN_DIR . 'templates/';

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

		$this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
	}

	public function get_content_html() {
		return wc_get_template_html($this->template_html, ['order' => $this->object, 'email' => $this], '', $this->template_base);
	}

	public function get_content_plain() {
		return wc_get_template_html($this->template_plain, ['order' => $this->object, 'email' => $this], '', $this->template_base);
	}
}

