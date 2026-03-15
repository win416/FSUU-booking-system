<?php
// tests/test-connection.php
require_once '../includes/db_connection.php';

$db = getDB();
$result = $db->query("SELECT 1");
if ($result) {
    echo "✅ Database connection successful!";
    echo "<br>Your database is working properly.";
} else {
    echo "❌ Database connection failed!";
    echo "<br>Error: " . $db->error;
}
?>