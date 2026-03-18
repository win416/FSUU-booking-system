<?php
// Mock session data
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock POST data for appointment ID 2
$_POST['appointment_id'] = '2';
$_POST['status'] = 'approved';

// Capture output
ob_start();
chdir('api'); // Change to api directory to satisfy relative paths
include 'update-appointment.php';
chdir('..');
$output = ob_get_clean();

echo "--- API RESPONSE ---\n";
echo $output . "\n";
echo "--- END RESPONSE ---\n";

// Check DB status after
require_once 'includes/db_connection.php';
$db = getDB();
$res = $db->query("SELECT status FROM appointments WHERE appointment_id = 2");
$row = $res->fetch_assoc();
echo "Current status in DB: " . ($row['status'] ?? 'NULL') . "\n";
?>
