<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Voucher_System {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    }

    private function vouchers_table() {
        global $wpdb;
        return $wpdb->prefix . 'unico_vouchers';
    }

    private function table_exists($table) {
        global $wpdb;
        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        return is_string($found) && $found === $table;
    }

    public function get_voucher_stats() {
        $table = $this->vouchers_table();
        if (!$this->table_exists($table)) {
            return [
                'total' => 0,
                'available' => 0,
                'assigned' => 0,
                'delivered' => 0,
                'expired' => 0,
            ];
        }

        global $wpdb;
        $rows = $wpdb->get_results("SELECT voucher_status, COUNT(*) AS cnt FROM {$table} GROUP BY voucher_status");
        $stats = [
            'total' => 0,
            'available' => 0,
            'assigned' => 0,
            'delivered' => 0,
            'expired' => 0,
        ];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $status = isset($row->voucher_status) ? (string) $row->voucher_status : '';
                $count = isset($row->cnt) ? (int) $row->cnt : 0;
                $stats['total'] += $count;
                if (isset($stats[$status])) {
                    $stats[$status] += $count;
                }
            }
        }
        return $stats;
    }

    public function get_vouchers_by_exam($exam_name, $status = null) {
        $table = $this->vouchers_table();
        if (!$this->table_exists($table)) {
            return [];
        }

        global $wpdb;
        $exam_name = (string) $exam_name;
        if ($status !== null && $status !== '') {
            $status = (string) $status;
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE exam_name = %s AND voucher_status = %s", $exam_name, $status));
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE exam_name = %s", $exam_name));
    }

    public function add_voucher(array $data) {
        $table = $this->vouchers_table();
        if (!$this->table_exists($table)) {
            return new WP_Error('unico_vouchers_missing', 'Voucher inventory table not found.');
        }

        $voucher_code = isset($data['voucher_code']) ? (string) $data['voucher_code'] : '';
        $exam_name = isset($data['exam_name']) ? (string) $data['exam_name'] : '';
        $purchase_price = isset($data['purchase_price']) ? (float) $data['purchase_price'] : 0.0;
        $selling_price = isset($data['selling_price']) ? (float) $data['selling_price'] : 0.0;
        $expiry_date = isset($data['expiry_date']) ? $data['expiry_date'] : null;

        if ($voucher_code === '' || $exam_name === '') {
            return new WP_Error('unico_voucher_invalid', 'Voucher code and exam name are required.');
        }

        global $wpdb;
        $insert = [
            'voucher_code' => $voucher_code,
            'exam_name' => $exam_name,
            'purchase_price' => $purchase_price,
            'selling_price' => $selling_price,
            'voucher_status' => 'available',
            'created_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%f', '%f', '%s', '%s'];

        if ($expiry_date) {
            $insert['expiry_date'] = (string) $expiry_date;
            $format[] = '%s';
        }

        $ok = $wpdb->insert($table, $insert, $format);
        if (!$ok) {
            return new WP_Error('unico_voucher_insert_failed', 'Unable to add voucher to inventory.');
        }
        return (int) $wpdb->insert_id;
    }

    public function auto_deliver_vouchers($order_id, $order_obj = null) {
        $table = $this->vouchers_table();
        if (!$this->table_exists($table)) {
            return new WP_Error('unico_vouchers_missing', 'Voucher inventory table not found.');
        }

        if (!class_exists('WooCommerce') || !function_exists('wc_get_order')) {
            return new WP_Error('unico_wc_missing', 'WooCommerce is required.');
        }

        $order_id = (int) $order_id;
        $order = $order_obj instanceof Unico_Order ? $order_obj : null;
        if ($order === null) {
            $order = new Unico_Order($order_id);
        }
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            return new WP_Error('unico_order_missing', 'Order not found.');
        }

        $qty_needed = 0;
        foreach ($wc_order->get_items() as $item) {
            if (method_exists($item, 'get_quantity')) {
                $qty_needed += (int) $item->get_quantity();
            }
        }
        $qty_needed = max(1, $qty_needed);

        $customer_id = (int) $wc_order->get_customer_id();
        global $wpdb;

        $available = $wpdb->get_results($wpdb->prepare(
            "SELECT id, voucher_code FROM {$table} WHERE voucher_status = 'available' ORDER BY created_at ASC LIMIT %d",
            $qty_needed
        ));

        if (!is_array($available) || count($available) < $qty_needed) {
            return new WP_Error('unico_voucher_stock', 'Not enough voucher stock available.');
        }

        $delivered_ids = [];
        foreach ($available as $row) {
            $vid = isset($row->id) ? (int) $row->id : 0;
            if ($vid <= 0) {
                continue;
            }
            $updated = $wpdb->update(
                $table,
                [
                    'voucher_status' => 'delivered',
                    'assigned_to' => $customer_id,
                    'order_id' => $order_id,
                    'delivered_at' => current_time('mysql'),
                ],
                ['id' => $vid, 'voucher_status' => 'available'],
                ['%s', '%d', '%d', '%s'],
                ['%d', '%s']
            );
            if ($updated) {
                $delivered_ids[] = $vid;
            }
        }

        if (empty($delivered_ids)) {
            return new WP_Error('unico_voucher_delivery', 'Voucher delivery failed.');
        }

        $wc_order->update_meta_data('_vouchers_delivered', 1);
        $wc_order->update_meta_data('_unico_delivered_voucher_ids', $delivered_ids);
        $wc_order->save();

        return $delivered_ids;
    }
}

