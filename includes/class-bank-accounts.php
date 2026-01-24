<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Bank_Accounts {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    }

    public function get_all_banks() {
        $query = new WP_Query([
            'post_type' => 'unico_bank',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $banks = [];
        if (!empty($query->posts)) {
            foreach ($query->posts as $post) {
                $banks[] = $this->bank_from_post($post);
            }
        }
        wp_reset_postdata();
        return $banks;
    }

    public function get_bank_stats() {
        $banks = $this->get_all_banks();
        $active = 0;
        foreach ($banks as $bank) {
            if (!empty($bank->is_active)) {
                $active++;
            }
        }
        return [
            'total' => count($banks),
            'active' => $active,
            'inactive' => count($banks) - $active,
        ];
    }

    public function add_bank($data) {
        $bank_name = sanitize_text_field(wp_unslash($data['bank_name'] ?? ''));
        $account_holder = sanitize_text_field(wp_unslash($data['account_holder'] ?? ''));
        $account_number = sanitize_text_field(wp_unslash($data['account_number'] ?? ''));
        $ifsc_code = sanitize_text_field(wp_unslash($data['ifsc_code'] ?? ''));
        $swift_code = sanitize_text_field(wp_unslash($data['swift_code'] ?? ''));
        $branch_name = sanitize_text_field(wp_unslash($data['branch_name'] ?? ''));
        $country = sanitize_text_field(wp_unslash($data['country'] ?? ''));
        $currency = sanitize_text_field(wp_unslash($data['currency'] ?? ''));
        $bank_logo_url = esc_url_raw(wp_unslash($data['bank_logo_url'] ?? ''));
        $display_order = isset($data['display_order']) ? (int) $data['display_order'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($data['notes'] ?? ''));
        $is_active = isset($data['is_active']) ? 1 : 0;

        if ($bank_name === '' || $account_holder === '' || $account_number === '') {
            return ['success' => false, 'message' => 'Bank name, account holder, and account number are required.'];
        }

        $post_id = wp_insert_post([
            'post_type' => 'unico_bank',
            'post_status' => 'publish',
            'post_title' => $bank_name,
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            return ['success' => false, 'message' => 'Unable to create bank record.'];
        }

        $this->update_bank_meta($post_id, [
            'active' => $is_active,
            'weight' => max(1, 1 + max(0, 100 - $display_order)),
            'display_name' => $bank_name,
            'account_holder' => $account_holder,
            'account_number' => $account_number,
            'ifsc' => $ifsc_code,
            'bank_name' => $bank_name,
            'branch' => $branch_name,
            'swift_code' => $swift_code,
            'country' => $country,
            'currency' => $currency,
            'bank_logo_url' => $bank_logo_url,
            'display_order' => $display_order,
            'notes' => $notes,
        ]);

        return ['success' => true, 'message' => 'Bank account added successfully.'];
    }

    public function update_bank($bank_id, $data) {
        $bank_id = (int) $bank_id;
        if ($bank_id <= 0) {
            return ['success' => false, 'message' => 'Invalid bank account.'];
        }

        $bank_name = sanitize_text_field(wp_unslash($data['bank_name'] ?? ''));
        $account_holder = sanitize_text_field(wp_unslash($data['account_holder'] ?? ''));
        $account_number = sanitize_text_field(wp_unslash($data['account_number'] ?? ''));
        $ifsc_code = sanitize_text_field(wp_unslash($data['ifsc_code'] ?? ''));
        $swift_code = sanitize_text_field(wp_unslash($data['swift_code'] ?? ''));
        $branch_name = sanitize_text_field(wp_unslash($data['branch_name'] ?? ''));
        $country = sanitize_text_field(wp_unslash($data['country'] ?? ''));
        $currency = sanitize_text_field(wp_unslash($data['currency'] ?? ''));
        $bank_logo_url = esc_url_raw(wp_unslash($data['bank_logo_url'] ?? ''));
        $display_order = isset($data['display_order']) ? (int) $data['display_order'] : 0;
        $notes = sanitize_textarea_field(wp_unslash($data['notes'] ?? ''));
        $is_active = isset($data['is_active']) ? 1 : 0;

        if ($bank_name === '' || $account_holder === '' || $account_number === '') {
            return ['success' => false, 'message' => 'Bank name, account holder, and account number are required.'];
        }

        wp_update_post([
            'ID' => $bank_id,
            'post_title' => $bank_name,
        ]);

        $this->update_bank_meta($bank_id, [
            'active' => $is_active,
            'weight' => max(1, 1 + max(0, 100 - $display_order)),
            'display_name' => $bank_name,
            'account_holder' => $account_holder,
            'account_number' => $account_number,
            'ifsc' => $ifsc_code,
            'bank_name' => $bank_name,
            'branch' => $branch_name,
            'swift_code' => $swift_code,
            'country' => $country,
            'currency' => $currency,
            'bank_logo_url' => $bank_logo_url,
            'display_order' => $display_order,
            'notes' => $notes,
        ]);

        return ['success' => true, 'message' => 'Bank account updated successfully.'];
    }

    public function delete_bank($bank_id) {
        $bank_id = (int) $bank_id;
        if ($bank_id <= 0) {
            return ['success' => false, 'message' => 'Invalid bank account.'];
        }

        $deleted = wp_delete_post($bank_id, true);
        if (!$deleted) {
            return ['success' => false, 'message' => 'Unable to delete bank account.'];
        }
        return ['success' => true, 'message' => 'Bank account deleted successfully.'];
    }

    public function toggle_active($bank_id) {
        $bank_id = (int) $bank_id;
        if ($bank_id <= 0) {
            return ['success' => false, 'message' => 'Invalid bank account.'];
        }

        $active = (int) get_post_meta($bank_id, '_unico_bank_active', true);
        $new_value = $active ? 0 : 1;
        update_post_meta($bank_id, '_unico_bank_active', $new_value);
        return ['success' => true, 'message' => 'Bank status updated successfully.'];
    }

    private function bank_from_post($post) {
        $id = (int) $post->ID;
        $bank = (object) [
            'id' => $id,
            'bank_name' => (string) get_post_meta($id, '_unico_bank_bank_name', true),
            'account_holder' => (string) get_post_meta($id, '_unico_bank_account_holder', true),
            'account_number' => (string) get_post_meta($id, '_unico_bank_account_number', true),
            'ifsc_code' => (string) get_post_meta($id, '_unico_bank_ifsc', true),
            'swift_code' => (string) get_post_meta($id, '_unico_bank_swift_code', true),
            'branch_name' => (string) get_post_meta($id, '_unico_bank_branch', true),
            'country' => (string) get_post_meta($id, '_unico_bank_country', true),
            'currency' => (string) get_post_meta($id, '_unico_bank_currency', true),
            'bank_logo_url' => (string) get_post_meta($id, '_unico_bank_logo_url', true),
            'display_order' => (int) get_post_meta($id, '_unico_bank_display_order', true),
            'notes' => (string) get_post_meta($id, '_unico_bank_notes', true),
            'is_active' => (int) get_post_meta($id, '_unico_bank_active', true) ? 1 : 0,
        ];

        if ($bank->bank_name === '') {
            $bank->bank_name = (string) get_the_title($id);
        }

        return $bank;
    }

    private function update_bank_meta($post_id, array $fields) {
        $post_id = (int) $post_id;

        if (isset($fields['active'])) {
            update_post_meta($post_id, '_unico_bank_active', (int) $fields['active']);
        }
        if (isset($fields['weight'])) {
            update_post_meta($post_id, '_unico_bank_weight', max(1, (int) $fields['weight']));
        }
        if (isset($fields['display_name'])) {
            update_post_meta($post_id, '_unico_bank_display_name', (string) $fields['display_name']);
        }
        if (isset($fields['account_holder'])) {
            update_post_meta($post_id, '_unico_bank_account_holder', (string) $fields['account_holder']);
        }
        if (isset($fields['account_number'])) {
            update_post_meta($post_id, '_unico_bank_account_number', (string) $fields['account_number']);
        }
        if (isset($fields['ifsc'])) {
            update_post_meta($post_id, '_unico_bank_ifsc', (string) $fields['ifsc']);
        }
        if (isset($fields['bank_name'])) {
            update_post_meta($post_id, '_unico_bank_bank_name', (string) $fields['bank_name']);
        }
        if (isset($fields['branch'])) {
            update_post_meta($post_id, '_unico_bank_branch', (string) $fields['branch']);
        }
        if (isset($fields['swift_code'])) {
            update_post_meta($post_id, '_unico_bank_swift_code', (string) $fields['swift_code']);
        }
        if (isset($fields['country'])) {
            update_post_meta($post_id, '_unico_bank_country', (string) $fields['country']);
        }
        if (isset($fields['currency'])) {
            update_post_meta($post_id, '_unico_bank_currency', (string) $fields['currency']);
        }
        if (isset($fields['bank_logo_url'])) {
            update_post_meta($post_id, '_unico_bank_logo_url', (string) $fields['bank_logo_url']);
        }
        if (isset($fields['display_order'])) {
            update_post_meta($post_id, '_unico_bank_display_order', (int) $fields['display_order']);
        }
        if (isset($fields['notes'])) {
            update_post_meta($post_id, '_unico_bank_notes', (string) $fields['notes']);
        }
    }
}

