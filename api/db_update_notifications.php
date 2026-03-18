<?php
require 'includes/db_connection.php';
$db = getDB();

$sql = "ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER status";
if ($db->query($sql)) {
    echo "Successfully added is_read column.\n";
} else {
    echo "Error: " . $db->error . "\n";
}
?>
