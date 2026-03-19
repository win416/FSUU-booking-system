<?php
// Mock session for testing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Change to the directory of the script we're testing to resolve relative paths
chdir(__DIR__ . '/api');

// Include the API logic (we need to handle the header() call)
ob_start();
require 'admin-reports.php';
$output = ob_get_clean();

echo "--- API OUTPUT ---\n";
echo $output . "\n";

?>
