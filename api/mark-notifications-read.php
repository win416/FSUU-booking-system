<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

header('Content-Type: application/json');

$user = SessionManager::getUser();
$db = getDB();

// GET: check unread count (used by polling)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['c'];
    echo json_encode(['unread' => $count]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$all = isset($_POST['all']) && $_POST['all'] == 'true';
$notification_id = $_POST['notification_id'] ?? null;

if ($all) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
} else if ($notification_id) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user['user_id']);
} else {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
