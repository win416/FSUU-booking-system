<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireAdmin();
header('Content-Type: application/json');

$db = getDB();

// ── By date (used by schedule calendar day modal) ────────────────────────────
$date = $_GET['date'] ?? '';
if (!empty($date)) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.appointment_date,
               TIME_FORMAT(a.appointment_time,'%h:%i %p') AS appointment_time,
               a.status, a.notes,
               u.first_name, u.last_name, u.fsuu_id,
               s.service_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.appointment_date = ?
          AND a.status NOT IN ('declined', 'cancelled')
        ORDER BY a.appointment_time ASC
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $appointments = [];
    while ($row = $res->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    // Also fetch blocked schedule information for this date
    $blockStmt = $db->prepare("
        SELECT block_id, reason, is_full_day,
               TIME_FORMAT(start_time,'%h:%i %p') AS start_time,
               TIME_FORMAT(end_time,'%h:%i %p') AS end_time
        FROM blocked_schedules
        WHERE block_date = ?
        ORDER BY start_time ASC
    ");
    $blockStmt->bind_param("s", $date);
    $blockStmt->execute();
    $blockRes = $blockStmt->get_result();
    $blocks = [];
    while ($row = $blockRes->fetch_assoc()) {
        $blocks[] = $row;
    }
    
    echo json_encode(['success' => true, 'appointments' => $appointments, 'blocks' => $blocks]);
    exit();
}

// ── By appointment ID (original behavior) ────────────────────────────────────
$appointment_id = $_GET['id'] ?? '';

if (empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID or date']);
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
echo json_encode(['success' => true, 'data' => $data]);
?>
