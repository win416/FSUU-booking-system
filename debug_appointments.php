<?php
require 'includes/db_connection.php';
$db = getDB();

echo "--- APPOINTMENTS SCHEMA ---\n";
$res = $db->query("DESCRIBE appointments");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";

echo "\n--- PENDING APPOINTMENTS ---\n";
$res = $db->query("SELECT appointment_id, status FROM appointments WHERE status = 'pending' LIMIT 5");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";

echo "\n--- ADMIN USERS ---\n";
$res = $db->query("SELECT user_id, email, role FROM users WHERE role = 'admin' LIMIT 1");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";
?>
