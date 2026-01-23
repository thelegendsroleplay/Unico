<?php
/**
 * Wallet & Refund Management System
 * Handles user wallets, transactions, and refunds
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Wallet {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get or create user wallet
     */
    public function get_wallet($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_wallets';

        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));

        if (!$wallet) {
            // Create new wallet
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'balance' => 0.00,
                'currency' => 'USD', // Default to USD in custom system
                'created_at' => current_time('mysql')
            ]);

            $wallet = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d",
                $user_id
            ));
        }

        return $wallet;
    }

    /**
     * Get wallet balance
     */
    public function get_balance($user_id) {
        $wallet = $this->get_wallet($user_id);
        return $wallet ? floatval($wallet->balance) : 0.00;
    }

    /**
     * Add funds to wallet
     */
    public function add_funds($user_id, $amount, $description = '', $reference_type = null, $reference_id = null) {
        global $wpdb;

        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than zero.');
        }

        $user = get_userdata($user_id);
        if ($user && in_array('unico_reseller', (array) $user->roles, true)) {
            return new WP_Error('reseller_prepaid_only', 'Resellers are prepaid-only and cannot receive wallet credits.');
        }

        $wallet = $this->get_wallet($user_id);
        $balance_before = floatval($wallet->balance);
        $balance_after = $balance_before + floatval($amount);

        // Update wallet balance
        $wallets_table = $wpdb->prefix . 'unico_wallets';
        $wpdb->update($wallets_table, [
            'balance' => $balance_after,
            'last_transaction_at' => current_time('mysql')
        ], ['user_id' => $user_id]);

        // Record transaction
        $txn_table = $wpdb->prefix . 'unico_wallet_transactions';
        $wpdb->insert($txn_table, [
            'user_id' => $user_id,
            'transaction_type' => 'credit',
            'amount' => floatval($amount),
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'description' => $description,
            'created_at' => current_time('mysql')
        ]);

        // Log activity
        $security = Unico_Security::get_instance();
        $security->log_activity($user_id, 'wallet_credit', $description, [
            'amount' => $amount,
            'balance' => $balance_after
        ]);

        return [
            'success' => true,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'transaction_id' => $wpdb->insert_id
        ];
    }

    /**
     * Deduct funds from wallet
     */
    public function deduct_funds($user_id, $amount, $description = '', $reference_type = null, $reference_id = null) {
        global $wpdb;

        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than zero.');
        }

        $user = get_userdata($user_id);
        if ($user && in_array('unico_reseller', (array) $user->roles, true)) {
            return new WP_Error('reseller_prepaid_only', 'Resellers are prepaid-only and cannot use wallet payments.');
        }

        $wallet = $this->get_wallet($user_id);
        $balance_before = floatval($wallet->balance);

        if ($balance_before < $amount) {
            return new WP_Error('insufficient_balance', 'Insufficient wallet balance.');
        }

        $balance_after = $balance_before - floatval($amount);

        // Update wallet balance
        $wallets_table = $wpdb->prefix . 'unico_wallets';
        $wpdb->update($wallets_table, [
            'balance' => $balance_after,
            'last_transaction_at' => current_time('mysql')
        ], ['user_id' => $user_id]);

        // Record transaction
        $txn_table = $wpdb->prefix . 'unico_wallet_transactions';
        $wpdb->insert($txn_table, [
            'user_id' => $user_id,
            'transaction_type' => 'debit',
            'amount' => floatval($amount),
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'description' => $description,
            'created_at' => current_time('mysql')
        ]);

        // Log activity
        $security = Unico_Security::get_instance();
        $security->log_activity($user_id, 'wallet_debit', $description, [
            'amount' => $amount,
            'balance' => $balance_after
        ]);

        return [
            'success' => true,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'transaction_id' => $wpdb->insert_id
        ];
    }

    /**
     * Get wallet transaction history
     */
    public function get_transactions($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_wallet_transactions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }

    /**
     * Process refund to wallet
     */
    public function process_refund($order_id, $amount = null) {
        $order = class_exists('Unico_Order') ? new Unico_Order($order_id) : false;
        if (!$order || !$order->get_id()) {
            return new WP_Error('invalid_order', 'Order not found.');
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return new WP_Error('invalid_customer', 'Customer not found.');
        }

        // If amount not specified, refund full order total
        if ($amount === null) {
            $amount = $order->get_total();
        }

        $result = $this->add_funds(
            $user_id,
            $amount,
            sprintf('Refund for Order #%d', $order_id),
            'refund',
            $order_id
        );

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'reseller_prepaid_only') {
                $order->add_order_note('Refund not applied to wallet (Reseller prepaid-only). Please process refund via original payment gateway.');
            }
            return $result;
        }

        // Add order note
        $order->add_note(
            sprintf('Refund of %s processed to customer wallet', unico_format_price($amount))
        );

        // Update order status
        $order->update_status('refunded');

        return [
            'success' => true,
            'amount' => $amount,
            'order_id' => $order_id,
            'user_id' => $user_id
        ];
    }

    /**
     * Allow wallet payment in checkout
     */
    public function apply_wallet_discount($cart_total, $user_id) {
        $balance = $this->get_balance($user_id);

        if ($balance >= $cart_total) {
            // Full payment from wallet
            return [
                'wallet_used' => $cart_total,
                'remaining_to_pay' => 0,
                'wallet_balance_after' => $balance - $cart_total
            ];
        } else {
            // Partial payment from wallet
            return [
                'wallet_used' => $balance,
                'remaining_to_pay' => $cart_total - $balance,
                'wallet_balance_after' => 0
            ];
        }
    }

    /**
     * Get wallet statistics for dashboard
     */
    public function get_wallet_stats($user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_wallet_transactions';

        if ($user_id) {
            // Stats for specific user
            $where = $wpdb->prepare("WHERE user_id = %d", $user_id);
        } else {
            // Overall stats
            $where = "";
        }

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credits,
                SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debits
            FROM $table
            $where
        ");

        if ($user_id) {
            $wallet = $this->get_wallet($user_id);
            $stats->current_balance = floatval($wallet->balance);
        }

        return $stats;
    }

    /**
     * Format wallet balance for display
     */
    public function format_balance($user_id) {
        $balance = $this->get_balance($user_id);
        return unico_format_price($balance);
    }
}
