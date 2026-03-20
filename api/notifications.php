<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();

// Pending bookings (new, unactioned)
$stmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.created_at,
           u.first_name, u.last_name, u.fsuu_id, s.service_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
while ($row = $res->fetch_assoc()) {
    $notifications[] = [
        'id'           => $row['appointment_id'],
        'patient'      => $row['first_name'] . ' ' . $row['last_name'],
        'fsuu_id'      => $row['fsuu_id'],
        'service'      => $row['service_name'],
        'date'         => $row['appointment_date'],
        'time'         => $row['appointment_time'],
        'created_at'   => $row['created_at'],
    ];
}

// Count all pending
$count = $db->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'")->fetch_assoc()['c'];

echo json_encode([
    'success'       => true,
    'count'         => (int)$count,
    'notifications' => $notifications,
]);
