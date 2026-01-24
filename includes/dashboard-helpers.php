<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('unico_format_price')) {
    function unico_format_price($amount) {
        $amount = is_numeric($amount) ? (float) $amount : 0.0;
        if (function_exists('wc_price')) {
            $formatted = wc_price($amount);
            return wp_strip_all_tags((string) $formatted);
        }
        return '$' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('unico_get_voucher_exam_options')) {
    function unico_get_voucher_exam_options() {
        $options = [];

        global $wpdb;
        $table = $wpdb->prefix . 'unico_vouchers';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists === $table) {
            $names = $wpdb->get_col("SELECT DISTINCT exam_name FROM {$table} WHERE exam_name <> '' ORDER BY exam_name ASC");
            if (is_array($names)) {
                foreach ($names as $name) {
                    $name = (string) $name;
                    $options[$name] = [
                        'value' => $name,
                        'label' => $name,
                    ];
                }
            }
        }

        if (class_exists('WooCommerce')) {
            $products = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => ['vouchers', 'voucher'],
                        'operator' => 'IN',
                    ],
                ],
            ]);
            if (is_array($products)) {
                foreach ($products as $pid) {
                    $pid = (int) $pid;
                    $exam = (string) get_post_meta($pid, 'exam_name', true);
                    if ($exam === '') {
                        $exam = (string) get_the_title($pid);
                    }
                    $exam = trim($exam);
                    if ($exam === '') {
                        continue;
                    }
                    if (!isset($options[$exam])) {
                        $options[$exam] = [
                            'value' => $exam,
                            'label' => $exam,
                        ];
                    }
                }
            }
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($options);
    }
}

