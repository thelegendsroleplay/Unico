<?php
/**
 * Database Schema for Voucher Booking System
 * Creates custom tables for vouchers, transactions, logs, and security
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Database {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create all custom database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table 1: Voucher Inventory
        $table_vouchers = $wpdb->prefix . 'unico_vouchers';
        $sql_vouchers = "CREATE TABLE $table_vouchers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            voucher_code varchar(255) NOT NULL,
            voucher_type varchar(100) NOT NULL,
            exam_name varchar(100) NOT NULL,
            voucher_status varchar(50) NOT NULL DEFAULT 'available',
            purchase_price decimal(10,2) NOT NULL,
            selling_price decimal(10,2) NOT NULL,
            expiry_date datetime DEFAULT NULL,
            assigned_to bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            delivered_via varchar(50) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY voucher_code (voucher_code),
            KEY voucher_status (voucher_status),
            KEY exam_name (exam_name),
            KEY assigned_to (assigned_to),
            KEY order_id (order_id)
        ) $charset_collate;";
        dbDelta($sql_vouchers);

        // Table 2: User Wallets
        $table_wallets = $wpdb->prefix . 'unico_wallets';
        $sql_wallets = "CREATE TABLE $table_wallets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            balance decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            last_transaction_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_wallets);

        // Table 3: Wallet Transactions
        $table_wallet_txns = $wpdb->prefix . 'unico_wallet_transactions';
        $sql_wallet_txns = "CREATE TABLE $table_wallet_txns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            transaction_type varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            balance_before decimal(10,2) NOT NULL,
            balance_after decimal(10,2) NOT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_wallet_txns);

        // Table 4: Activity Logs
        $table_logs = $wpdb->prefix . 'unico_activity_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            activity_type varchar(100) NOT NULL,
            activity_description text NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        dbDelta($sql_logs);

        // Table 5: Security & Risk Scoring
        $table_security = $wpdb->prefix . 'unico_security_checks';
        $sql_security = "CREATE TABLE $table_security (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            check_type varchar(100) NOT NULL,
            check_status varchar(50) NOT NULL,
            risk_score int(11) NOT NULL DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            country_code varchar(10) DEFAULT NULL,
            email_verified tinyint(1) NOT NULL DEFAULT 0,
            phone_verified tinyint(1) NOT NULL DEFAULT 0,
            duplicate_detected tinyint(1) NOT NULL DEFAULT 0,
            duplicate_matches longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY risk_score (risk_score),
            KEY ip_address (ip_address),
            KEY check_type (check_type)
        ) $charset_collate;";
        dbDelta($sql_security);

        // Table 6: Email Verification Tokens
        $table_email_verify = $wpdb->prefix . 'unico_email_verification';
        $sql_email_verify = "CREATE TABLE $table_email_verify (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            token varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            verified_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql_email_verify);

        // Table 7: Reseller/Agent Commissions
        $table_commissions = $wpdb->prefix . 'unico_commissions';
        $sql_commissions = "CREATE TABLE $table_commissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            commission_amount decimal(10,2) NOT NULL,
            commission_percentage decimal(5,2) NOT NULL,
            order_total decimal(10,2) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            paid_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_commissions);

        // Table 8: Support Tickets
        $table_tickets = $wpdb->prefix . 'unico_support_tickets';
        $sql_tickets = "CREATE TABLE $table_tickets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_number varchar(50) NOT NULL,
            user_id bigint(20) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'open',
            priority varchar(50) NOT NULL DEFAULT 'medium',
            assigned_to bigint(20) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            last_reply_at datetime DEFAULT NULL,
            closed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_number (ticket_number),
            KEY user_id (user_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";
        dbDelta($sql_tickets);

        // Table 9: Ticket Replies
        $table_ticket_replies = $wpdb->prefix . 'unico_ticket_replies';
        $sql_ticket_replies = "CREATE TABLE $table_ticket_replies (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            message longtext NOT NULL,
            is_staff_reply tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_ticket_replies);

        // Table 10: Bulk Pricing Rules
        $table_pricing = $wpdb->prefix . 'unico_pricing_rules';
        $sql_pricing = "CREATE TABLE $table_pricing (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            rule_name varchar(255) NOT NULL,
            user_role varchar(50) NOT NULL,
            product_category varchar(100) DEFAULT NULL,
            min_quantity int(11) NOT NULL,
            max_quantity int(11) DEFAULT NULL,
            discount_type varchar(50) NOT NULL,
            discount_value decimal(10,2) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_role (user_role),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_pricing);

        // Update version option
        update_option('unico_db_version', '1.0.0');

        return true;
    }

    /**
     * Drop all custom tables (use with caution)
     */
    public function drop_tables() {
        global $wpdb;

        $tables = [
            'unico_vouchers',
            'unico_wallets',
            'unico_wallet_transactions',
            'unico_activity_logs',
            'unico_security_checks',
            'unico_email_verification',
            'unico_commissions',
            'unico_support_tickets',
            'unico_ticket_replies',
            'unico_pricing_rules'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        delete_option('unico_db_version');
    }
}
