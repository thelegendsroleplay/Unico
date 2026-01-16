<?php
/**
 * Template Name: Debug Dashboard
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG DASHBOARD</h1>";
echo "<p>If you see this, basic template loading works.</p>";

// Test 1: Check if logged in
echo "<h2>Test 1: Login Status</h2>";
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    echo "<p>✓ Logged in as: " . $current_user->user_login . "</p>";
    echo "<p>Roles: " . implode(', ', $current_user->roles) . "</p>";
} else {
    echo "<p>✗ NOT logged in</p>";
}

// Test 2: Check if classes exist
echo "<h2>Test 2: Classes</h2>";
echo "<p>Unico_Voucher_System exists: " . (class_exists('Unico_Voucher_System') ? '✓' : '✗') . "</p>";
echo "<p>Unico_Wallet exists: " . (class_exists('Unico_Wallet') ? '✓' : '✗') . "</p>";
echo "<p>Unico_Security exists: " . (class_exists('Unico_Security') ? '✓' : '✗') . "</p>";

// Test 3: Check if WooCommerce is active
echo "<h2>Test 3: WooCommerce</h2>";
if (function_exists('wc_get_orders')) {
    echo "<p>✓ WooCommerce is active</p>";
    try {
        $test_orders = wc_get_orders(['limit' => 1]);
        echo "<p>✓ Can query orders</p>";
    } catch (Exception $e) {
        echo "<p>✗ Error querying orders: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✗ WooCommerce NOT active</p>";
}

// Test 4: Check database tables
echo "<h2>Test 4: Database Tables</h2>";
global $wpdb;
$tables_to_check = [
    'unico_vouchers',
    'unico_wallets',
    'unico_support_tickets'
];

foreach ($tables_to_check as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    echo "<p>Table {$table}: " . ($exists ? '✓' : '✗') . "</p>";
}

// Test 5: Try to get voucher stats
echo "<h2>Test 5: Voucher System Test</h2>";
try {
    if (class_exists('Unico_Voucher_System')) {
        $voucher_system = Unico_Voucher_System::get_instance();
        $stats = $voucher_system->get_voucher_stats();
        echo "<p>✓ Voucher stats retrieved:</p>";
        echo "<pre>" . print_r($stats, true) . "</pre>";
    } else {
        echo "<p>✗ Unico_Voucher_System class not found</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 6: Try to render a simple dashboard element
echo "<h2>Test 6: Simple Dashboard Element</h2>";
echo '<div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
    <h1>Test Dashboard Header</h1>
    <p>If you see this styled, CSS is working</p>
</div>';

echo '<div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin: 20px 0;">
    <h3>Test Card</h3>
    <p>This is a test stat card</p>
</div>';
