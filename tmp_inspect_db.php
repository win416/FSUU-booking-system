<?php
require 'includes/db_connection.php';
$db = getDB();
$res = $db->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo "--- " . $row[0] . " ---\n";
    $res2 = $db->query("DESCRIBE " . $row[0]);
    while($row2 = $res2->fetch_assoc()) {
        echo json_encode($row2) . "\n";
    }
}
?>
