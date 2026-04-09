<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Ensure only admins can update blocks
if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $block_id = $_POST['block_id'] ?? '';
    $block_date = $_POST['block_date'] ?? '';
    $is_full_day = isset($_POST['is_full_day']) ? 1 : 0;
    $start_time = !$is_full_day ? ($_POST['start_time'] ?? null) : null;
    $end_time = !$is_full_day ? ($_POST['end_time'] ?? null) : null;
    $reason = $_POST['reason'] ?? '';

    if (empty($block_id)) {
        echo json_encode(['success' => false, 'message' => 'Block ID is required']);
        exit();
    }

    if (empty($block_date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        exit();
    }

    if (!$is_full_day && (empty($start_time) || empty($end_time))) {
        echo json_encode(['success' => false, 'message' => 'Start and end time are required for partial day blocks']);
        exit();
    }

    // Verify block exists
    $check = $db->prepare("SELECT block_id FROM blocked_schedules WHERE block_id = ?");
    $check->bind_param("i", $block_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Block not found']);
        exit();
    }

    $stmt = $db->prepare("
        UPDATE blocked_schedules 
        SET block_date = ?, start_time = ?, end_time = ?, reason = ?, is_full_day = ?
        WHERE block_id = ?
    ");
    
    $stmt->bind_param("ssssii", $block_date, $start_time, $end_time, $reason, $is_full_day, $block_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Block updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
