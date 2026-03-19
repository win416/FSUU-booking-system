<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

header('Content-Type: application/json');

$user = SessionManager::getUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$notification_id = intval($_POST['notification_id'] ?? 0);
if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
    exit();
}

$stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $notification_id, $user['user_id']);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not delete notification']);
}
?>
