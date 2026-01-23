<?php
/**
 * Bank Accounts Management
 * Stores bank accounts in WordPress options
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Bank_Accounts {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Option key for bank accounts
     */
    const OPTION_KEY = 'unico_bank_accounts';

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 60);
        add_action('admin_post_save_unico_bank_accounts', array($this, 'save_bank_accounts'));
        add_action('wp_ajax_delete_bank_account', array($this, 'ajax_delete_bank'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Bank Accounts', 'unico-wc'),
            __('Bank Accounts', 'unico-wc'),
            'manage_woocommerce',
            'unico-bank-accounts',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Get all bank accounts
     */
    public function get_all_accounts() {
        $accounts = get_option(self::OPTION_KEY, array());
        return is_array($accounts) ? $accounts : array();
    }

    /**
     * Get active bank accounts only
     */
    public function get_active_accounts() {
        $accounts = $this->get_all_accounts();
        return array_filter($accounts, function($account) {
            return !empty($account['active']);
        });
    }

    /**
     * Get random active bank account
     */
    public function get_random_active_account($exclude_id = null) {
        $active_accounts = $this->get_active_accounts();

        if (empty($active_accounts)) {
            return null;
        }

        // Exclude specific account if provided
        if ($exclude_id !== null) {
            $active_accounts = array_filter($active_accounts, function($account) use ($exclude_id) {
                return $account['id'] !== $exclude_id;
            });
        }

        if (empty($active_accounts)) {
            // If all filtered out, get all active again
            $active_accounts = $this->get_active_accounts();
        }

        // Get random account
        $random_key = array_rand($active_accounts);
        return $active_accounts[$random_key];
    }

    /**
     * Get bank account by ID
     */
    public function get_account_by_id($id) {
        $accounts = $this->get_all_accounts();
        foreach ($accounts as $account) {
            if ($account['id'] === $id) {
                return $account;
            }
        }
        return null;
    }

    /**
     * Save bank accounts
     */
    public function save_bank_accounts() {
        // Security check
        if (!current_user_can('manage_woocommerce') ||
            !isset($_POST['unico_bank_nonce']) ||
            !wp_verify_nonce($_POST['unico_bank_nonce'], 'save_unico_bank_accounts')) {
            wp_die(__('Security check failed', 'unico-wc'));
        }

        $accounts = $this->get_all_accounts();

        // Handle new account
        if (isset($_POST['add_new']) && $_POST['add_new'] === '1') {
            $new_account = array(
                'id' => uniqid('bank_'),
                'bank_name' => sanitize_text_field($_POST['bank_name']),
                'account_holder' => sanitize_text_field($_POST['account_holder']),
                'account_number' => sanitize_text_field($_POST['account_number']),
                'ifsc_code' => sanitize_text_field($_POST['ifsc_code']),
                'swift_code' => sanitize_text_field($_POST['swift_code']),
                'branch' => sanitize_text_field($_POST['branch']),
                'active' => isset($_POST['active']) ? 1 : 0,
            );

            $accounts[] = $new_account;
        }

        // Handle updates to existing accounts
        if (isset($_POST['accounts']) && is_array($_POST['accounts'])) {
            foreach ($_POST['accounts'] as $id => $data) {
                foreach ($accounts as $key => $account) {
                    if ($account['id'] === $id) {
                        $accounts[$key] = array(
                            'id' => $id,
                            'bank_name' => sanitize_text_field($data['bank_name']),
                            'account_holder' => sanitize_text_field($data['account_holder']),
                            'account_number' => sanitize_text_field($data['account_number']),
                            'ifsc_code' => sanitize_text_field($data['ifsc_code']),
                            'swift_code' => sanitize_text_field($data['swift_code']),
                            'branch' => sanitize_text_field($data['branch']),
                            'active' => isset($data['active']) ? 1 : 0,
                        );
                        break;
                    }
                }
            }
        }

        update_option(self::OPTION_KEY, $accounts);

        wp_redirect(add_query_arg('saved', '1', admin_url('admin.php?page=unico-bank-accounts')));
        exit;
    }

    /**
     * AJAX delete bank account
     */
    public function ajax_delete_bank() {
        check_ajax_referer('unico_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $id = sanitize_text_field($_POST['id']);
        $accounts = $this->get_all_accounts();

        $accounts = array_filter($accounts, function($account) use ($id) {
            return $account['id'] !== $id;
        });

        update_option(self::OPTION_KEY, array_values($accounts));

        wp_send_json_success('Bank account deleted');
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $accounts = $this->get_all_accounts();
        ?>
        <div class="wrap">
            <h1><?php _e('Bank Accounts for Checkout', 'unico-wc'); ?></h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Bank accounts saved successfully.', 'unico-wc'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php _e('Manage bank accounts that will be randomly displayed to customers at checkout.', 'unico-wc'); ?></p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_unico_bank_accounts">
                <?php wp_nonce_field('save_unico_bank_accounts', 'unico_bank_nonce'); ?>

                <h2><?php _e('Existing Bank Accounts', 'unico-wc'); ?></h2>

                <?php if (!empty($accounts)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Active', 'unico-wc'); ?></th>
                                <th><?php _e('Bank Name', 'unico-wc'); ?></th>
                                <th><?php _e('Account Holder', 'unico-wc'); ?></th>
                                <th><?php _e('Account Number', 'unico-wc'); ?></th>
                                <th><?php _e('IFSC Code', 'unico-wc'); ?></th>
                                <th><?php _e('SWIFT Code', 'unico-wc'); ?></th>
                                <th><?php _e('Branch', 'unico-wc'); ?></th>
                                <th><?php _e('Actions', 'unico-wc'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][active]"
                                               value="1"
                                               <?php checked(!empty($account['active']), true); ?>>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][bank_name]"
                                               value="<?php echo esc_attr($account['bank_name']); ?>"
                                               class="regular-text"
                                               required>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][account_holder]"
                                               value="<?php echo esc_attr($account['account_holder']); ?>"
                                               class="regular-text"
                                               required>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][account_number]"
                                               value="<?php echo esc_attr($account['account_number']); ?>"
                                               class="regular-text"
                                               required>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][ifsc_code]"
                                               value="<?php echo esc_attr($account['ifsc_code']); ?>"
                                               class="regular-text">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][swift_code]"
                                               value="<?php echo esc_attr($account['swift_code']); ?>"
                                               class="regular-text">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="accounts[<?php echo esc_attr($account['id']); ?>][branch]"
                                               value="<?php echo esc_attr($account['branch']); ?>"
                                               class="regular-text">
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button button-small delete-bank-account"
                                                data-id="<?php echo esc_attr($account['id']); ?>">
                                            <?php _e('Delete', 'unico-wc'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No bank accounts found. Add your first bank account below.', 'unico-wc'); ?></p>
                <?php endif; ?>

                <hr>

                <h2><?php _e('Add New Bank Account', 'unico-wc'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Active', 'unico-wc'); ?></label></th>
                        <td>
                            <input type="checkbox" name="active" value="1" checked>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Bank Name', 'unico-wc'); ?> *</label></th>
                        <td>
                            <input type="text" name="bank_name" class="regular-text" placeholder="e.g., HDFC Bank">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Account Holder Name', 'unico-wc'); ?> *</label></th>
                        <td>
                            <input type="text" name="account_holder" class="regular-text" placeholder="e.g., Unico Pvt Ltd">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Account Number', 'unico-wc'); ?> *</label></th>
                        <td>
                            <input type="text" name="account_number" class="regular-text" placeholder="e.g., 1234567890">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('IFSC Code', 'unico-wc'); ?></label></th>
                        <td>
                            <input type="text" name="ifsc_code" class="regular-text" placeholder="e.g., HDFC0001234">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('SWIFT Code', 'unico-wc'); ?></label></th>
                        <td>
                            <input type="text" name="swift_code" class="regular-text" placeholder="e.g., HDFCINBB">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Branch', 'unico-wc'); ?></label></th>
                        <td>
                            <input type="text" name="branch" class="regular-text" placeholder="e.g., Mumbai Main">
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="add_new" value="0" id="add_new_field">

                <p class="submit">
                    <button type="submit" name="add_new_btn" class="button button-primary" onclick="document.getElementById('add_new_field').value='1';">
                        <?php _e('Add Bank Account', 'unico-wc'); ?>
                    </button>
                    <button type="submit" class="button">
                        <?php _e('Save All Changes', 'unico-wc'); ?>
                    </button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.delete-bank-account').on('click', function(e) {
                e.preventDefault();
                if (!confirm('<?php _e('Are you sure you want to delete this bank account?', 'unico-wc'); ?>')) {
                    return;
                }

                var button = $(this);
                var id = button.data('id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_bank_account',
                        id: id,
                        nonce: '<?php echo wp_create_nonce('unico_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
}
