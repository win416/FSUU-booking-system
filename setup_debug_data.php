<?php
require 'includes/db_connection.php';
$db = getDB();

// Ensure there is at least one pending appointment
$check = $db->query("SELECT appointment_id FROM appointments WHERE status = 'pending'");
if ($check->num_rows == 0) {
    // Create one if none exist
    $db->query("INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, status) VALUES (1, 1, '2026-03-20', '10:00:00', 'pending')");
    echo "Created a pending appointment.\n";
} else {
    echo "Pending appointments exist.\n";
}

// Check if we can find an admin to log in as? 
// I'll just look for a user and make them an admin if needed, or find an existing one.
?>
