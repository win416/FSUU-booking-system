<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = SessionManager::getUser();
$db   = getDB();

// Unread count
$cStmt = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
$cStmt->bind_param("i", $user['user_id']);
$cStmt->execute();
$count = (int)$cStmt->get_result()->fetch_assoc()['c'];

// Latest 8 unread notifications
$stmt = $db->prepare("
    SELECT notification_id, subject, message, created_at
    FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success'       => true,
    'count'         => $count,
    'notifications' => $rows,
]);
