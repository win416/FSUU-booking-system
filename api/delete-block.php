<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Ensure only admins/dentists can delete schedule blocks
if (!SessionManager::isAdmin() && !SessionManager::isDentist()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $block_id = $_POST['block_id'] ?? '';

    if (empty($block_id)) {
        echo json_encode(['success' => false, 'message' => 'Block ID is required']);
        exit();
    }

    if (SessionManager::isAdmin()) {
        $stmt = $db->prepare("DELETE FROM blocked_schedules WHERE block_id = ?");
        $stmt->bind_param("i", $block_id);
    } else {
        $current_user_id = SessionManager::getUser()['user_id'];
        $stmt = $db->prepare("DELETE FROM blocked_schedules WHERE block_id = ? AND created_by = ?");
        $stmt->bind_param("ii", $block_id, $current_user_id);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Block removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Block not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
