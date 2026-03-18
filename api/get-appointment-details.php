<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireAdmin();
header('Content-Type: application/json');

$db = getDB();

$appointment_id = $_GET['id'] ?? '';

if (empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit();
}

$stmt = $db->prepare("
    SELECT a.*, 
           u.first_name, u.last_name, u.email, u.contact_number, u.fsuu_id, u.role,
           s.service_name,
           m.allergies, m.medical_conditions, m.medications, m.emergency_contact_name, m.emergency_contact_number
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN medical_info m ON u.user_id = m.user_id
    WHERE a.appointment_id = ?
");

$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit();
}

$data = $result->fetch_assoc();

// Format response HTML or JSON? 
// The implementation plan says "Populate detailsContent with the formatted results", 
// which implies I should return HTML or the frontend handles formatting.
// Returning JSON is cleaner.

echo json_encode(['success' => true, 'data' => $data]);
?>
