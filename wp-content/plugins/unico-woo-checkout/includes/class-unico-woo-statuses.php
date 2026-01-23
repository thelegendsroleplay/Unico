<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Woo_Statuses {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'register_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_order_statuses']);
    }

    public function register_statuses() {
        register_post_status('wc-under-review', [
            'label' => 'Under Review',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Under Review <span class="count">(%s)</span>', 'Under Review <span class="count">(%s)</span>'),
        ]);

        register_post_status('wc-rejected', [
            'label' => 'Rejected',
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>'),
        ]);
    }

    public function add_order_statuses($order_statuses) {
        $new_statuses = [];
        foreach ($order_statuses as $key => $label) {
            $new_statuses[$key] = $label;
            if ('wc-on-hold' === $key) {
                $new_statuses['wc-under-review'] = 'Under Review';
            }
        }
        $new_statuses['wc-rejected'] = 'Rejected';
        return $new_statuses;
    }
}
