<?php
require_once '../includes/db_connection.php';
$db = getDB();
$result = $db->query('SHOW TABLES');
if ($result) {
    echo "Tables in database:\n";
    while ($row = $result->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo 'No tables found or error: ' . $db->error;
}
?>