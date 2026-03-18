<?php
require 'includes/db_connection.php';
$db = getDB();

echo "--- users TABLE ---\n";
$res = $db->query("DESCRIBE users");
while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";
?>
