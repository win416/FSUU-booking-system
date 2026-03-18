<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireAdmin();
header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug log
    file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Updating appointment. POST: " . json_encode($_POST) . "\n", FILE_APPEND);
    
    $appointment_id = $_POST['appointment_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? null;

    if (empty($appointment_id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // Validate status
    $valid_statuses = ['pending', 'approved', 'completed', 'cancelled', 'declined'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }

    $stmt = $db->prepare("UPDATE appointments SET status = ?, cancellation_reason = ? WHERE appointment_id = ?");
    $stmt->bind_param("ssi", $status, $reason, $appointment_id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Update successful. Affected rows: $affected\n", FILE_APPEND);
        
        // Create notification for the user
        $notif_stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, subject, message, status)
            SELECT user_id, 'email', ?, ?, 'pending'
            FROM appointments 
            WHERE appointment_id = ?
        ");
        
        $subject = "Appointment " . ucfirst($status);
        $message = "Your appointment has been " . $status;
        if ($reason) {
            $message .= ". Reason: " . $reason;
        }
        
        $notif_stmt->bind_param("ssi", $subject, $message, $appointment_id);
        if ($notif_stmt->execute()) {
            file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Notification created.\n", FILE_APPEND);
        } else {
            file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Notification failed: " . $db->error . "\n", FILE_APPEND);
        }

        echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
    } else {
        file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Update failed: " . $db->error . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
