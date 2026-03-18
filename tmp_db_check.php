<?php
require 'includes/db_connection.php';
$db = getDB();

echo "--- NOTIFICATIONS TABLE ---\n";
$res = $db->query("DESCRIBE notifications");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";
?>
