<?php
require 'includes/db_connection.php';
$db = getDB();

echo "--- MEDICAL INFO TABLE ---\n";
$res = $db->query("DESCRIBE medical_info");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";
?>
