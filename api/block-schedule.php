<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Ensure only admins/dentists can block schedules
if (!SessionManager::isAdmin() && !SessionManager::isDentist()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $block_date = $_POST['block_date'] ?? '';
    $is_full_day = isset($_POST['is_full_day']) ? 1 : 0;
    $start_time = !$is_full_day ? ($_POST['start_time'] ?? null) : null;
    $end_time = !$is_full_day ? ($_POST['end_time'] ?? null) : null;
    $reason = $_POST['reason'] ?? '';
    $created_by = SessionManager::getUser()['user_id'];

    if (empty($block_date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required']);
        exit();
    }

    if (!$is_full_day && (empty($start_time) || empty($end_time))) {
        echo json_encode(['success' => false, 'message' => 'Start and end time are required for partial day blocks']);
        exit();
    }

    $stmt = $db->prepare("
        INSERT INTO blocked_schedules (block_date, start_time, end_time, reason, is_full_day, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("ssssii", $block_date, $start_time, $end_time, $reason, $is_full_day, $created_by);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Schedule blocked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
