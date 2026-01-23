<?php
/**
 * Custom Order Status - Under Review
 */

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Order_Status {

    /**
     * Single instance
     */
    private static $instance = null;

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
        add_action('init', array($this, 'register_order_status'));
        add_filter('wc_order_statuses', array($this, 'add_order_status_to_list'));
        add_filter('woocommerce_reports_order_statuses', array($this, 'add_status_to_reports'));
        add_action('woocommerce_order_status_changed', array($this, 'handle_status_change'), 10, 4);
    }

    /**
     * Register custom order status
     */
    public function register_order_status() {
        register_post_status('wc-under-review', array(
            'label'                     => _x('Under Review', 'Order status', 'unico-wc'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Under Review <span class="count">(%s)</span>',
                'Under Review <span class="count">(%s)</span>',
                'unico-wc'
            ),
        ));

        // Also register rejected status
        register_post_status('wc-rejected', array(
            'label'                     => _x('Rejected', 'Order status', 'unico-wc'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Rejected <span class="count">(%s)</span>',
                'Rejected <span class="count">(%s)</span>',
                'unico-wc'
            ),
        ));
    }

    /**
     * Add custom status to order statuses list
     */
    public function add_order_status_to_list($order_statuses) {
        $new_statuses = array();

        // Add under-review after on-hold
        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;

            if ('wc-on-hold' === $key) {
                $new_statuses['wc-under-review'] = _x('Under Review', 'Order status', 'unico-wc');
            }
        }

        // Add rejected before cancelled
        $final_statuses = array();
        foreach ($new_statuses as $key => $status) {
            if ('wc-cancelled' === $key) {
                $final_statuses['wc-rejected'] = _x('Rejected', 'Order status', 'unico-wc');
            }
            $final_statuses[$key] = $status;
        }

        return $final_statuses;
    }

    /**
     * Add custom status to reports
     */
    public function add_status_to_reports($statuses) {
        $statuses[] = 'under-review';
        return $statuses;
    }

    /**
     * Handle status changes
     */
    public function handle_status_change($order_id, $old_status, $new_status, $order) {
        // Log status change
        $order->add_order_note(
            sprintf(
                __('Order status changed from %s to %s', 'unico-wc'),
                wc_get_order_status_name($old_status),
                wc_get_order_status_name($new_status)
            )
        );

        // Fire custom action hooks
        do_action('unico_order_status_changed', $order_id, $old_status, $new_status, $order);
        do_action('unico_order_status_' . $new_status, $order_id, $order);
    }
}
