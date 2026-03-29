<?php
// One-time fix: updates admin password and shows the correct hash
// Run from browser: http://localhost/FSUU-booking-system-1/fix_admin_password.php
// DELETE this file after running!

require_once 'includes/db_connection.php';

$new_hash = password_hash('Admin@12345', PASSWORD_DEFAULT);
$db = getDB();
$stmt = $db->prepare("UPDATE users SET password = ? WHERE email = 'admin@fsuudental.com' AND role = 'admin'");
$stmt->bind_param("s", $new_hash);
$ok = $stmt->execute();

echo "<pre>";
echo "Hash generated: " . $new_hash . "\n\n";
echo "DB rows updated: " . $stmt->affected_rows . "\n";
echo $ok ? "✅ Admin password updated to: Admin@12345\n" : "❌ Update failed: " . $db->error . "\n";
echo "\nDelete this file when done!\n";
echo "</pre>";
