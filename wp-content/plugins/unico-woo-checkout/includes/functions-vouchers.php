<?php

if (!defined('ABSPATH')) {
    exit;
}

function deliver_vouchers($order_id) {
    do_action('unico_deliver_vouchers', $order_id);
}
