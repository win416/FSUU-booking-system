<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn() || !SessionManager::isDentist()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$appointment_id = (int)($_GET['id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit();
}

$db = getDB();
$dentist_id = (int)SessionManager::getUser()['user_id'];

$stmt = $db->prepare("
    SELECT a.*,
           u.first_name, u.last_name, u.email, u.contact_number, u.fsuu_id, u.role,
           s.service_name,
           m.allergies, m.medical_conditions, m.medications, m.emergency_contact_name, m.emergency_contact_number
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN medical_info m ON u.user_id = m.user_id
    WHERE da.appointment_id = ? AND da.dentist_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $appointment_id, $dentist_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or not assigned to you.']);
    exit();
}

echo json_encode(['success' => true, 'data' => $res->fetch_assoc()]);

