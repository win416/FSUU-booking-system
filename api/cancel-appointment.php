<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user = SessionManager::getUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$appointment_id = $_POST['appointment_id'] ?? null;
$reason = $_POST['reason'] ?? '';

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit();
}

// Verify appointment belongs to user and is pending
$stmt = $db->prepare("SELECT status FROM appointments WHERE appointment_id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user['user_id']);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit();
}

$appt = $res->fetch_assoc();
if ($appt['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending appointments can be cancelled']);
    exit();
}

$update = $db->prepare("UPDATE appointments SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE appointment_id = ?");
$update->bind_param("si", $reason, $appointment_id);

if ($update->execute()) {
    // Create notification for the user
    $notif_stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, subject, message, status)
        VALUES (?, 'email', 'Appointment Cancelled', ?, 'pending')
    ");
    $notif_msg = "You have successfully cancelled your appointment.";
    if ($reason) $notif_msg .= " Reason: " . $reason;
    $notif_stmt->bind_param("is", $user['user_id'], $notif_msg);
    $notif_stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error cancelling appointment']);
}
?>
