<?php
require 'includes/db_connection.php';
$db = getDB();

$tables = ['users', 'appointments', 'services'];
foreach ($tables as $table) {
    echo "--- $table TABLE ---\n";
    $res = $db->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) echo json_encode($row) . "\n";
    echo "\n";
}
?>
