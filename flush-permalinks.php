<?php
/**
 * Template Name: Flush Permalinks
 */

// Flush rewrite rules
flush_rewrite_rules();

echo "<!DOCTYPE html><html><head><title>Permalinks Flushed</title></head><body>";
echo "<h1>✓ Permalinks Flushed Successfully!</h1>";
echo "<p>Rewrite rules have been refreshed.</p>";
echo "<p><a href='/'>Go to Homepage</a></p>";
echo "<p><a href='/management-dashboard/'>Try Management Dashboard</a></p>";
echo "</body></html>";
?>
