<?php
// Database setup script
echo "<!DOCTYPE html>\n";
echo "<html><head><title>Database Setup</title></head><body>\n";
echo "<h1>Setting up Database...</h1>\n";
echo "<pre>\n";

// Run database setup
require_once 'config/setup-database.php';

echo "</pre>\n";
echo "<p><strong>Setup completed!</strong></p>\n";
echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>\n";
echo "</body></html>\n";
?>