<?php
/**
 * Template Name: Test Dashboard (No Login Required)
 */

echo "<!DOCTYPE html><html><head><title>Test Dashboard</title></head><body>";
echo "<h1>TEST DASHBOARD LOADED SUCCESSFULLY!</h1>";
echo "<p>If you see this, the template is working.</p>";

if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    echo "<p>You are logged in as: " . $current_user->user_login . "</p>";
    echo "<p>Your role(s): " . implode(', ', $current_user->roles) . "</p>";
    echo "<p>User ID: " . $current_user->ID . "</p>";
} else {
    echo "<p style='color: red;'>YOU ARE NOT LOGGED IN</p>";
}

echo "</body></html>";
?>
