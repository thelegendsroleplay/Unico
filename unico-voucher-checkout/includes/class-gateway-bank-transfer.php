<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Gateway_Bank_Transfer extends WC_Payment_Gateway {
	public function __construct() {
		$this->id = 'unico_bank_transfer_verify';
		$this->method_title = 'Unico Bank Transfer (Verification)';
		$this->method_description = 'Used for Unico custom voucher checkout orders.';
		$this->has_fields = false;

		$this->enabled = 'yes';
		$this->title = 'Bank Transfer';
	}

	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->update_status('unico-verify');
		}
		return [
			'result' => 'success',
			'redirect' => $this->get_return_url($order),
		];
	}
}
