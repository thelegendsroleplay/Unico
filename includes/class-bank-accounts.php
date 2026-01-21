<?php
/**
 * Bank Accounts Management System
 * Handles offline bank payment accounts for checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Bank_Accounts {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->create_table();
    }

    /**
     * Create bank accounts table
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'unico_bank_accounts';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            bank_name varchar(255) NOT NULL,
            account_holder varchar(255) NOT NULL,
            account_number varchar(100) NOT NULL,
            ifsc_code varchar(50) DEFAULT NULL,
            swift_code varchar(50) DEFAULT NULL,
            branch_name varchar(255) DEFAULT NULL,
            bank_logo_url varchar(500) DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            display_order int(11) NOT NULL DEFAULT 0,
            country varchar(100) DEFAULT 'India',
            currency varchar(10) DEFAULT 'INR',
            notes longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY display_order (display_order)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add a new bank account
     */
    public function add_bank($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $insert_data = [
            'bank_name' => sanitize_text_field($data['bank_name']),
            'account_holder' => sanitize_text_field($data['account_holder']),
            'account_number' => sanitize_text_field($data['account_number']),
            'ifsc_code' => isset($data['ifsc_code']) ? sanitize_text_field($data['ifsc_code']) : '',
            'swift_code' => isset($data['swift_code']) ? sanitize_text_field($data['swift_code']) : '',
            'branch_name' => isset($data['branch_name']) ? sanitize_text_field($data['branch_name']) : '',
            'bank_logo_url' => isset($data['bank_logo_url']) ? esc_url_raw($data['bank_logo_url']) : '',
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'display_order' => isset($data['display_order']) ? intval($data['display_order']) : 0,
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : 'India',
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'INR',
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : ''
        ];

        $inserted = $wpdb->insert($table, $insert_data);

        if ($inserted) {
            return ['success' => true, 'id' => $wpdb->insert_id, 'message' => 'Bank account added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to add bank account: ' . $wpdb->last_error];
        }
    }

    /**
     * Update bank account
     */
    public function update_bank($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $update_data = [
            'bank_name' => sanitize_text_field($data['bank_name']),
            'account_holder' => sanitize_text_field($data['account_holder']),
            'account_number' => sanitize_text_field($data['account_number']),
            'ifsc_code' => isset($data['ifsc_code']) ? sanitize_text_field($data['ifsc_code']) : '',
            'swift_code' => isset($data['swift_code']) ? sanitize_text_field($data['swift_code']) : '',
            'branch_name' => isset($data['branch_name']) ? sanitize_text_field($data['branch_name']) : '',
            'bank_logo_url' => isset($data['bank_logo_url']) ? esc_url_raw($data['bank_logo_url']) : '',
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'display_order' => isset($data['display_order']) ? intval($data['display_order']) : 0,
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : 'India',
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'INR',
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : ''
        ];

        $updated = $wpdb->update($table, $update_data, ['id' => intval($id)]);

        if ($updated !== false) {
            return ['success' => true, 'message' => 'Bank account updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update bank account: ' . $wpdb->last_error];
        }
    }

    /**
     * Delete bank account
     */
    public function delete_bank($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $deleted = $wpdb->delete($table, ['id' => intval($id)]);

        if ($deleted) {
            return ['success' => true, 'message' => 'Bank account deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete bank account'];
        }
    }

    /**
     * Toggle bank active status
     */
    public function toggle_active($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $bank = $this->get_bank($id);
        if (!$bank) {
            return ['success' => false, 'message' => 'Bank not found'];
        }

        $new_status = $bank->is_active ? 0 : 1;
        $updated = $wpdb->update($table, ['is_active' => $new_status], ['id' => intval($id)]);

        if ($updated !== false) {
            return ['success' => true, 'message' => 'Bank status updated', 'is_active' => $new_status];
        } else {
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }

    /**
     * Get single bank account
     */
    public function get_bank($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
    }

    /**
     * Get all bank accounts
     */
    public function get_all_banks($active_only = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $where = $active_only ? "WHERE is_active = 1" : "";

        return $wpdb->get_results("SELECT * FROM $table $where ORDER BY display_order ASC, id DESC");
    }

    /**
     * Get random active bank account
     * This is used during checkout to select one bank to display
     */
    public function get_random_active_bank() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $banks = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY display_order ASC");

        if (empty($banks)) {
            return null;
        }

        // Return random bank from active banks
        $random_index = array_rand($banks);
        return $banks[$random_index];
    }

    /**
     * Get bank statistics
     */
    public function get_bank_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_bank_accounts';

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_active = 1");

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active
        ];
    }
}
