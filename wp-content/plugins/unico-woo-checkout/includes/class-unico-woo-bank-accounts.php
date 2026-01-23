<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Woo_Bank_Accounts {
    private static $instance = null;
    private $meta_fields = [
        'unico_bank_name' => 'Bank Name',
        'unico_account_name' => 'Account Name',
        'unico_account_number' => 'Account Number',
        'unico_iban' => 'IBAN',
        'unico_swift' => 'SWIFT',
        'unico_branch' => 'Branch',
        'unico_currency' => 'Currency',
        'unico_notes' => 'Notes',
        'unico_is_active' => 'Active',
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_unico_bank_account', [$this, 'save_meta'], 10, 2);
    }

    public function register_cpt() {
        register_post_type('unico_bank_account', [
            'labels' => [
                'name' => 'Bank Accounts',
                'singular_name' => 'Bank Account',
                'add_new_item' => 'Add New Bank Account',
                'edit_item' => 'Edit Bank Account',
                'menu_name' => 'Bank Accounts',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'woocommerce',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-building',
        ]);
    }

    public function register_meta_boxes() {
        add_meta_box(
            'unico_bank_details',
            'Bank Details',
            [$this, 'render_meta_box'],
            'unico_bank_account',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('unico_bank_account_meta', 'unico_bank_account_meta_nonce');

        $values = [];
        foreach ($this->meta_fields as $key => $label) {
            $values[$key] = get_post_meta($post->ID, $key, true);
        }

        $values['unico_is_active'] = $values['unico_is_active'] !== '' ? $values['unico_is_active'] : 'yes';
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="unico_bank_name">Bank Name</label></th>
                    <td><input type="text" class="regular-text" name="unico_bank_name" id="unico_bank_name" value="<?php echo esc_attr($values['unico_bank_name']); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="unico_account_name">Account Name</label></th>
                    <td><input type="text" class="regular-text" name="unico_account_name" id="unico_account_name" value="<?php echo esc_attr($values['unico_account_name']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="unico_account_number">Account Number</label></th>
                    <td><input type="text" class="regular-text" name="unico_account_number" id="unico_account_number" value="<?php echo esc_attr($values['unico_account_number']); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="unico_iban">IBAN</label></th>
                    <td><input type="text" class="regular-text" name="unico_iban" id="unico_iban" value="<?php echo esc_attr($values['unico_iban']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="unico_swift">SWIFT</label></th>
                    <td><input type="text" class="regular-text" name="unico_swift" id="unico_swift" value="<?php echo esc_attr($values['unico_swift']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="unico_branch">Branch</label></th>
                    <td><input type="text" class="regular-text" name="unico_branch" id="unico_branch" value="<?php echo esc_attr($values['unico_branch']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="unico_currency">Currency</label></th>
                    <td><input type="text" class="regular-text" name="unico_currency" id="unico_currency" value="<?php echo esc_attr($values['unico_currency']); ?>"></td>
                </tr>
                <tr>
                    <th><label for="unico_notes">Notes</label></th>
                    <td><textarea class="large-text" name="unico_notes" id="unico_notes" rows="3"><?php echo esc_textarea($values['unico_notes']); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="unico_is_active">Active</label></th>
                    <td>
                        <select name="unico_is_active" id="unico_is_active">
                            <option value="yes" <?php selected($values['unico_is_active'], 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected($values['unico_is_active'], 'no'); ?>>No</option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['unico_bank_account_meta_nonce']) || !wp_verify_nonce($_POST['unico_bank_account_meta_nonce'], 'unico_bank_account_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach ($this->meta_fields as $key => $label) {
            if (!isset($_POST[$key])) {
                if ($key === 'unico_notes') {
                    update_post_meta($post_id, $key, '');
                }
                continue;
            }

            $value = $_POST[$key];
            if ($key === 'unico_notes') {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }
            update_post_meta($post_id, $key, $value);
        }
    }

    public function get_random_account_id() {
        $accounts = $this->get_active_accounts();
        if (empty($accounts)) {
            return 0;
        }

        $random_index = array_rand($accounts);
        return (int) $accounts[$random_index]->ID;
    }

    public function get_active_accounts() {
        return get_posts([
            'post_type' => 'unico_bank_account',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_key' => 'unico_is_active',
            'meta_value' => 'yes',
        ]);
    }

    public function get_account_snapshot($account_id) {
        $post = get_post($account_id);
        if (!$post) {
            return [];
        }

        $snapshot = [
            'id' => $account_id,
            'title' => $post->post_title,
        ];

        foreach ($this->meta_fields as $key => $label) {
            $snapshot[$key] = get_post_meta($account_id, $key, true);
        }

        return $snapshot;
    }
}
