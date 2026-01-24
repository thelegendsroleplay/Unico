<?php

if (!defined('ABSPATH')) {
	exit;
}

class Unico_VC_Bank_Manager {
	private static $instance = null;

	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('init', [$this, 'register_cpt']);
		add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
		add_action('save_post_unico_bank', [$this, 'save_meta'], 10, 2);
	}

	public function register_cpt() {
		register_post_type('unico_bank', [
			'labels' => [
				'name' => 'Banks',
				'singular_name' => 'Bank',
			],
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => 'woocommerce',
			'menu_position' => 56,
			'supports' => ['title'],
			'capability_type' => 'shop_order',
			'map_meta_cap' => true,
		]);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'unico_bank_details',
			'Bank Details',
			[$this, 'render_meta_box'],
			'unico_bank',
			'normal',
			'high'
		);
	}

	public function render_meta_box($post) {
		wp_nonce_field('unico_vc_save_bank', 'unico_vc_save_bank_nonce');

		$fields = $this->get_bank_meta($post->ID);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="unico_bank_active">Active</label></th>
				<td><input type="checkbox" id="unico_bank_active" name="unico_bank_active" value="1" <?php checked(!empty($fields['active'])); ?>></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_weight">Weight</label></th>
				<td><input type="number" id="unico_bank_weight" name="unico_bank_weight" value="<?php echo esc_attr((int) $fields['weight']); ?>" min="1" step="1"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_display_name">Display Name</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_display_name" name="unico_bank_display_name" value="<?php echo esc_attr($fields['display_name']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_account_holder">Account Holder</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_account_holder" name="unico_bank_account_holder" value="<?php echo esc_attr($fields['account_holder']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_account_number">Account Number</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_account_number" name="unico_bank_account_number" value="<?php echo esc_attr($fields['account_number']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_ifsc">IFSC</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_ifsc" name="unico_bank_ifsc" value="<?php echo esc_attr($fields['ifsc']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_bank_name">Bank Name</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_bank_name" name="unico_bank_bank_name" value="<?php echo esc_attr($fields['bank_name']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_branch">Branch</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_branch" name="unico_bank_branch" value="<?php echo esc_attr($fields['branch']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_upi">UPI ID (optional)</label></th>
				<td><input type="text" class="regular-text" id="unico_bank_upi" name="unico_bank_upi" value="<?php echo esc_attr($fields['upi']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="unico_bank_instructions">Instructions (optional)</label></th>
				<td><textarea class="large-text" rows="4" id="unico_bank_instructions" name="unico_bank_instructions"><?php echo esc_textarea($fields['instructions']); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	public function save_meta($post_id, $post) {
		if (!isset($_POST['unico_vc_save_bank_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['unico_vc_save_bank_nonce'])), 'unico_vc_save_bank')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$active = isset($_POST['unico_bank_active']) ? 1 : 0;
		$weight = isset($_POST['unico_bank_weight']) ? max(1, (int) $_POST['unico_bank_weight']) : 1;

		update_post_meta($post_id, '_unico_bank_active', $active);
		update_post_meta($post_id, '_unico_bank_weight', $weight);
		update_post_meta($post_id, '_unico_bank_display_name', sanitize_text_field(wp_unslash($_POST['unico_bank_display_name'] ?? '')));
		update_post_meta($post_id, '_unico_bank_account_holder', sanitize_text_field(wp_unslash($_POST['unico_bank_account_holder'] ?? '')));
		update_post_meta($post_id, '_unico_bank_account_number', sanitize_text_field(wp_unslash($_POST['unico_bank_account_number'] ?? '')));
		update_post_meta($post_id, '_unico_bank_ifsc', sanitize_text_field(wp_unslash($_POST['unico_bank_ifsc'] ?? '')));
		update_post_meta($post_id, '_unico_bank_bank_name', sanitize_text_field(wp_unslash($_POST['unico_bank_bank_name'] ?? '')));
		update_post_meta($post_id, '_unico_bank_branch', sanitize_text_field(wp_unslash($_POST['unico_bank_branch'] ?? '')));
		update_post_meta($post_id, '_unico_bank_upi', sanitize_text_field(wp_unslash($_POST['unico_bank_upi'] ?? '')));
		update_post_meta($post_id, '_unico_bank_instructions', sanitize_textarea_field(wp_unslash($_POST['unico_bank_instructions'] ?? '')));
	}

	private function get_bank_meta($post_id) {
		return [
			'active' => (int) get_post_meta($post_id, '_unico_bank_active', true),
			'weight' => (int) (get_post_meta($post_id, '_unico_bank_weight', true) ?: 1),
			'display_name' => (string) get_post_meta($post_id, '_unico_bank_display_name', true),
			'account_holder' => (string) get_post_meta($post_id, '_unico_bank_account_holder', true),
			'account_number' => (string) get_post_meta($post_id, '_unico_bank_account_number', true),
			'ifsc' => (string) get_post_meta($post_id, '_unico_bank_ifsc', true),
			'bank_name' => (string) get_post_meta($post_id, '_unico_bank_bank_name', true),
			'branch' => (string) get_post_meta($post_id, '_unico_bank_branch', true),
			'upi' => (string) get_post_meta($post_id, '_unico_bank_upi', true),
			'instructions' => (string) get_post_meta($post_id, '_unico_bank_instructions', true),
		];
	}

	public function has_active_banks() {
		return !empty($this->get_active_banks());
	}

	private function get_active_banks() {
		$query = new WP_Query([
			'post_type' => 'unico_bank',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => [
				[
					'key' => '_unico_bank_active',
					'value' => 1,
					'compare' => '=',
				],
			],
		]);
		return array_map('absint', (array) $query->posts);
	}

	public function get_random_bank_snapshot() {
		$ids = $this->get_active_banks();
		if (empty($ids)) {
			return null;
		}

		$pool = [];
		foreach ($ids as $id) {
			$weight = (int) (get_post_meta($id, '_unico_bank_weight', true) ?: 1);
			$weight = max(1, $weight);
			$pool[] = [
				'id' => $id,
				'weight' => $weight,
			];
		}

		$total_weight = array_sum(array_column($pool, 'weight'));
		$rand = mt_rand(1, max(1, $total_weight));
		$running = 0;
		$selected_id = (int) $pool[0]['id'];
		foreach ($pool as $entry) {
			$running += (int) $entry['weight'];
			if ($rand <= $running) {
				$selected_id = (int) $entry['id'];
				break;
			}
		}

		$meta = $this->get_bank_meta($selected_id);
		return [
			'bank_id' => $selected_id,
			'display_name' => $meta['display_name'] ?: get_the_title($selected_id),
			'account_holder' => $meta['account_holder'],
			'account_number' => $meta['account_number'],
			'ifsc' => $meta['ifsc'],
			'bank_name' => $meta['bank_name'],
			'branch' => $meta['branch'],
			'upi' => $meta['upi'],
			'instructions' => $meta['instructions'],
		];
	}
}

