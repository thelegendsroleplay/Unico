<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Voucher_Generator {
	public static function generate_codes($count) {
		$count = max(0, (int) $count);
		$codes = [];
		for ($i = 0; $i < $count; $i++) {
			$codes[] = self::unique_code();
		}
		return $codes;
	}

	private static function unique_code() {
		for ($attempt = 0; $attempt < 20; $attempt++) {
			$code = 'TLRP-' . self::chunk(4) . '-' . self::chunk(4) . '-' . self::chunk(4);
			if (!self::code_exists($code)) {
				return $code;
			}
		}
		return 'TLRP-' . strtoupper(wp_generate_password(4, false, false)) . '-' . strtoupper(wp_generate_password(4, false, false)) . '-' . strtoupper(wp_generate_password(4, false, false));
	}

	private static function chunk($len) {
		return strtoupper(wp_generate_password($len, false, false));
	}

	private static function code_exists($code) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like($code) . '%';
		$sql = $wpdb->prepare(
			"SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			'_unico_voucher_codes',
			$like
		);
		return (int) $wpdb->get_var($sql) > 0;
	}
}

