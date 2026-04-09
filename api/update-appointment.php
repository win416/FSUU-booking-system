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
    $action = $_POST['action'] ?? 'status_change';
    
    if (empty($appointment_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
        exit();
    }

    // Handle reschedule action
    if ($action === 'reschedule') {
        $new_date = $_POST['appointment_date'] ?? '';
        $new_time = $_POST['appointment_time'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if (empty($new_date) || empty($new_time)) {
            echo json_encode(['success' => false, 'message' => 'Date and time are required']);
            exit();
        }
        
        // Validate date is not in the past
        if (strtotime($new_date) < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'message' => 'Cannot reschedule to a past date']);
            exit();
        }
        
        // Get old appointment details for notification
        $old_stmt = $db->prepare("SELECT a.*, u.first_name FROM appointments a JOIN users u ON a.user_id = u.user_id WHERE a.appointment_id = ?");
        $old_stmt->bind_param("i", $appointment_id);
        $old_stmt->execute();
        $old_appt = $old_stmt->get_result()->fetch_assoc();
        
        if (!$old_appt) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit();
        }
        
        // Update appointment
        $stmt = $db->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, updated_at = NOW() WHERE appointment_id = ?");
        $stmt->bind_param("ssi", $new_date, $new_time, $appointment_id);
        
        if ($stmt->execute()) {
            // Create notification for the patient
            $old_date_formatted = date('M d, Y', strtotime($old_appt['appointment_date']));
            $old_time_formatted = date('g:i A', strtotime($old_appt['appointment_time']));
            $new_date_formatted = date('M d, Y', strtotime($new_date));
            $new_time_formatted = date('g:i A', strtotime($new_time));
            
            $notif_message = "Your appointment has been rescheduled from {$old_date_formatted} at {$old_time_formatted} to {$new_date_formatted} at {$new_time_formatted}.";
            if ($reason) {
                $notif_message .= " Reason: " . $reason;
            }
            
            $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, type, subject, message, status) VALUES (?, 'email', 'Appointment Rescheduled', ?, 'pending')");
            $notif_stmt->bind_param("is", $old_appt['user_id'], $notif_message);
            $notif_stmt->execute();
            
            file_put_contents('../debug_api.log', date('[Y-m-d H:i:s] ') . "Reschedule successful for appointment #$appointment_id\n", FILE_APPEND);
            
            echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
        }
        exit();
    }
    
    // Handle status change (original functionality)
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
