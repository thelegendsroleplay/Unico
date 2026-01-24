<?php

if (!defined('ABSPATH')) {
    exit;
}

class Unico_Order {
    private $order_id = 0;
    private $order = null;

    public function __construct($order_id) {
        $this->order_id = (int) $order_id;
        if (class_exists('WooCommerce') && function_exists('wc_get_order') && $this->order_id > 0) {
            $this->order = wc_get_order($this->order_id);
        }
    }

    public static function get_orders(array $args = []) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return [];
        }

        $orderby = isset($args['orderby']) ? (string) $args['orderby'] : 'date_created';
        if ($orderby === 'created_at') {
            $orderby = 'date_created';
        }

        $wc_args = [
            'limit' => isset($args['limit']) ? (int) $args['limit'] : 20,
            'orderby' => $orderby,
            'order' => isset($args['order']) ? (string) $args['order'] : 'DESC',
            'return' => 'ids',
        ];

        if (isset($args['status'])) {
            $wc_args['status'] = $args['status'];
        }

        if (isset($args['user_id'])) {
            $wc_args['customer'] = (int) $args['user_id'];
        }

        if (!empty($args['date_created'])) {
            $wc_args['date_created'] = (string) $args['date_created'];
        }

        $ids = wc_get_orders($wc_args);
        if (!is_array($ids)) {
            return [];
        }

        $orders = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $orders[] = new self($id);
            }
        }
        return $orders;
    }

    public function get_id() {
        return $this->order && method_exists($this->order, 'get_id') ? (int) $this->order->get_id() : 0;
    }

    public function get_order_number() {
        return $this->order && method_exists($this->order, 'get_order_number') ? (string) $this->order->get_order_number() : '';
    }

    public function get_total() {
        return $this->order && method_exists($this->order, 'get_total') ? (float) $this->order->get_total() : 0.0;
    }

    public function get_formatted_total() {
        if (!$this->order || !method_exists($this->order, 'get_formatted_order_total')) {
            return '';
        }
        return (string) $this->order->get_formatted_order_total();
    }

    public function get_status() {
        return $this->order && method_exists($this->order, 'get_status') ? (string) $this->order->get_status() : '';
    }

    public function get_date_created() {
        if (!$this->order || !method_exists($this->order, 'get_date_created')) {
            return '';
        }
        $created = $this->order->get_date_created();
        if ($created && method_exists($created, 'date')) {
            return (string) $created->date('Y-m-d H:i:s');
        }
        return '';
    }

    public function get_customer_name() {
        if (!$this->order) {
            return '';
        }
        $name = '';
        if (method_exists($this->order, 'get_formatted_billing_full_name')) {
            $name = (string) $this->order->get_formatted_billing_full_name();
        }
        if ($name !== '') {
            return $name;
        }
        $first = method_exists($this->order, 'get_billing_first_name') ? (string) $this->order->get_billing_first_name() : '';
        $last = method_exists($this->order, 'get_billing_last_name') ? (string) $this->order->get_billing_last_name() : '';
        return trim($first . ' ' . $last);
    }

    public function get_customer_email() {
        return $this->order && method_exists($this->order, 'get_billing_email') ? (string) $this->order->get_billing_email() : '';
    }

    public function get_payment_method() {
        return $this->order && method_exists($this->order, 'get_payment_method') ? (string) $this->order->get_payment_method() : '';
    }

    public function get_payment_reference() {
        if (!$this->order || !method_exists($this->order, 'get_meta')) {
            return '';
        }
        $value = $this->order->get_meta('_unico_txn_id', true);
        return is_scalar($value) ? (string) $value : '';
    }

    public function add_note($note) {
        if ($this->order && method_exists($this->order, 'add_order_note')) {
            $this->order->add_order_note((string) $note);
        }
        return $this;
    }

    public function update_status($new_status) {
        if ($this->order && method_exists($this->order, 'update_status')) {
            $this->order->update_status((string) $new_status);
        }
        return $this;
    }

    public function update_meta($key, $value) {
        if ($this->order && method_exists($this->order, 'update_meta_data')) {
            $this->order->update_meta_data((string) $key, $value);
            if (method_exists($this->order, 'save')) {
                $this->order->save();
            }
        }
        return $this;
    }

    public function get_meta($key) {
        if ($this->order && method_exists($this->order, 'get_meta')) {
            return $this->order->get_meta((string) $key, true);
        }
        return null;
    }
}
