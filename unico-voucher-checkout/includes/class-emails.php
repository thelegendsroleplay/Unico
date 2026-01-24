<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Emails {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter('woocommerce_email_classes', [$this, 'register_emails']);
	}

	public function register_emails($emails) {
		require_once UNICO_VC_PLUGIN_DIR . 'includes/emails/class-email-approved.php';
		require_once UNICO_VC_PLUGIN_DIR . 'includes/emails/class-email-rejected.php';

		$emails['Unico_VC_Email_Approved'] = new Unico_VC_Email_Approved();
		$emails['Unico_VC_Email_Rejected'] = new Unico_VC_Email_Rejected();

		return $emails;
	}

	public function send_approved($order_id) {
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		if (isset($emails['Unico_VC_Email_Approved'])) {
			$emails['Unico_VC_Email_Approved']->trigger($order_id);
		}
	}

	public function send_rejected($order_id) {
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		if (isset($emails['Unico_VC_Email_Rejected'])) {
			$emails['Unico_VC_Email_Rejected']->trigger($order_id);
		}
	}
}

