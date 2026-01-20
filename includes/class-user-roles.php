<?php
/**
 * Custom User Roles Management
 * Creates and manages 6 distinct user roles with specific capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_User_Roles {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create all custom user roles
     */
    public function create_roles() {

        // Role 0: Student (applying for universities)
        add_role('unico_student', 'Student', [
            'read' => true,
            'view_own_orders' => true,
            'view_own_vouchers' => true,
            'create_support_tickets' => true,
            'view_own_tickets' => true,
            'make_purchases' => true,
            'view_own_wallet' => true,
            'access_student_dashboard' => true,
            'apply_for_universities' => true
        ]);

        // Role 1: Customer (default buyer)
        add_role('unico_customer', 'Customer', [
            'read' => true,
            'view_own_orders' => true,
            'view_own_vouchers' => true,
            'create_support_tickets' => true,
            'view_own_tickets' => true,
            'make_purchases' => true,
            'view_own_wallet' => true,
            'access_customer_dashboard' => true
        ]);

        // Role 2: Agent (training center representative)
        add_role('unico_agent', 'Agent', [
            'read' => true,
            'view_own_orders' => true,
            'view_own_vouchers' => true,
            'create_support_tickets' => true,
            'view_own_tickets' => true,
            'make_purchases' => true,
            'view_own_wallet' => true,
            'access_bulk_pricing' => true,
            'view_commission_reports' => true,
            'manage_sub_accounts' => true,
            'access_agent_dashboard' => true
        ]);

        // Role 3: Training Center / Reseller (bulk buyer)
        add_role('unico_reseller', 'Training Center / Reseller', [
            'read' => true,
            'view_own_orders' => true,
            'view_own_vouchers' => true,
            'create_support_tickets' => true,
            'view_own_tickets' => true,
            'make_purchases' => true,
            'view_own_wallet' => true,
            'access_bulk_pricing' => true,
            'view_commission_reports' => true,
            'manage_sub_accounts' => true,
            'access_reseller_dashboard' => true,
            'distribute_vouchers' => true,
            'view_stock_levels' => true
        ]);

        // Role 4: Customer Support
        add_role('unico_support', 'Customer Support', [
            'read' => true,
            'view_all_tickets' => true,
            'reply_to_tickets' => true,
            'close_tickets' => true,
            'assign_tickets' => true,
            'view_customer_orders' => true,
            'view_customer_vouchers' => true,
            'access_support_dashboard' => true,
            'view_activity_logs' => true,
            'resend_vouchers' => true
        ]);

        // Role 5: Finance Management
        add_role('unico_finance', 'Finance Management', [
            'read' => true,
            'view_all_orders' => true,
            'view_sales_reports' => true,
            'view_revenue_analytics' => true,
            'manage_refunds' => true,
            'manage_commissions' => true,
            'access_finance_dashboard' => true,
            'view_payment_logs' => true,
            'export_financial_reports' => true,
            'view_wallet_transactions' => true,
            'approve_payouts' => true
        ]);

        // Role 6: Management (overview access)
        add_role('unico_management', 'Management', [
            'read' => true,
            'view_all_orders' => true,
            'view_all_vouchers' => true,
            'view_sales_reports' => true,
            'view_revenue_analytics' => true,
            'view_user_reports' => true,
            'access_management_dashboard' => true,
            'view_activity_logs' => true,
            'view_security_reports' => true,
            'view_commission_reports' => true,
            'export_reports' => true
        ]);

        // Enhance Administrator role with all custom capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_voucher_system');
            $admin->add_cap('manage_voucher_inventory');
            $admin->add_cap('manual_voucher_delivery');
            $admin->add_cap('view_all_orders');
            $admin->add_cap('view_all_vouchers');
            $admin->add_cap('view_all_tickets');
            $admin->add_cap('manage_user_roles');
            $admin->add_cap('manage_pricing_rules');
            $admin->add_cap('access_admin_dashboard');
            $admin->add_cap('view_security_reports');
            $admin->add_cap('manage_security_settings');
            $admin->add_cap('view_activity_logs');
            $admin->add_cap('manage_commissions');
            $admin->add_cap('manage_wallets');
        }
    }

    /**
     * Get user's dashboard URL based on role
     */
    public static function get_dashboard_url($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return home_url();
        }

        $roles = $user->roles;

        if (in_array('administrator', $roles)) {
            return admin_url('admin.php?page=unico-admin-dashboard');
        } elseif (in_array('unico_management', $roles)) {
            return home_url('/management-dashboard');
        } elseif (in_array('unico_finance', $roles)) {
            return home_url('/finance-dashboard');
        } elseif (in_array('unico_support', $roles)) {
            return home_url('/support-dashboard');
        } elseif (in_array('unico_reseller', $roles)) {
            return home_url('/reseller-dashboard');
        } elseif (in_array('unico_agent', $roles)) {
            return home_url('/agent-dashboard');
        } elseif (in_array('unico_customer', $roles)) {
            return home_url('/customer-dashboard');
        }

        return home_url('/dashboard');
    }

    /**
     * Check if user has specific capability
     */
    public static function user_can($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return user_can($user_id, $capability);
    }

    /**
     * Get all Unico custom roles
     */
    public static function get_custom_roles() {
        return [
            'unico_student' => 'Student',
            'unico_customer' => 'Customer',
            'unico_agent' => 'Agent',
            'unico_reseller' => 'Training Center / Reseller',
            'unico_support' => 'Customer Support',
            'unico_finance' => 'Finance Management',
            'unico_management' => 'Management'
        ];
    }

    /**
     * Assign role to user on registration
     */
    public function assign_role_on_registration($user_id) {
        $user = get_userdata($user_id);

        // Check if user already has a role
        if (empty($user->roles)) {
            // Default to customer role
            $user->add_role('unico_customer');
        }

        // Remove default subscriber role if exists
        if (in_array('subscriber', $user->roles)) {
            $user->remove_role('subscriber');
        }
    }

    public static function create_user_approval($user_id, $requested_role) {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_user_approvals';
        $requested_role = sanitize_text_field($requested_role);
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'requested_role' => $requested_role,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        if (class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();
            $security->log_activity($user_id, 'user_approval_created', 'User approval request created', [
                'requested_role' => $requested_role
            ]);
        }
    }

    public static function set_user_approval_status($approval_id, $status, $reviewed_by, $remarks = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'unico_user_approvals';
        $status = sanitize_text_field($status);
        $remarks = sanitize_textarea_field($remarks);
        $wpdb->update($table, [
            'status' => $status,
            'reviewed_by' => $reviewed_by,
            'reviewed_at' => current_time('mysql'),
            'remarks' => $remarks
        ], [
            'id' => $approval_id
        ]);
        if (class_exists('Unico_Security')) {
            $security = Unico_Security::get_instance();
            $security->log_activity($reviewed_by, 'user_approval_updated', 'User approval status updated', [
                'approval_id' => $approval_id,
                'status' => $status
            ]);
        }
    }

    /**
     * Remove all custom roles (use with caution)
     */
    public function remove_roles() {
        $roles = [
            'unico_customer',
            'unico_agent',
            'unico_reseller',
            'unico_support',
            'unico_finance',
            'unico_management'
        ];

        foreach ($roles as $role) {
            remove_role($role);
        }
    }

    /**
     * Get role display name
     */
    public static function get_role_display_name($role_slug) {
        $roles = self::get_custom_roles();
        return isset($roles[$role_slug]) ? $roles[$role_slug] : ucfirst($role_slug);
    }
}
