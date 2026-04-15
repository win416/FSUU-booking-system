<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$db = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Summary Statistics
$stats = [];

// Total appointments in range
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['total_appointments'] = $stmt->get_result()->fetch_assoc()['count'];

// Status breakdown in range
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
$status_breakdown = [];
while ($row = $res->fetch_assoc()) {
    $status_breakdown[$row['status']] = (int)$row['count'];
}
$stats['status_breakdown'] = $status_breakdown;

// New patients in range
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND created_at BETWEEN ? AND ?");
// Assuming 'student' is the patient role as seen in schema, but let me check others: student, dentist, staff, admin
// Actually, let's just count all non-admin registrations or just all registrations if needed. 
// Based on schema: `role` enum('student','dentist','staff','admin')
$start_ts = $start_date . ' 00:00:00';
$end_ts = $end_date . ' 23:59:59';
$stmt->bind_param("ss", $start_ts, $end_ts);
$stmt->execute();
$stats['new_patients'] = $stmt->get_result()->fetch_assoc()['count'];

// 2. Appointment Trends (Daily) - include all dates in range
$stmt = $db->prepare("
    SELECT appointment_date, COUNT(*) as count 
    FROM appointments 
    WHERE appointment_date BETWEEN ? AND ? 
    GROUP BY appointment_date 
    ORDER BY appointment_date ASC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
$appointment_counts = [];
while ($row = $res->fetch_assoc()) {
    $appointment_counts[$row['appointment_date']] = (int)$row['count'];
}

// Generate all dates in range and fill with counts (0 if no appointments)
$trends = [];
$current = new DateTime($start_date);
$end = new DateTime($end_date);
$end->modify('+1 day'); // Include end date
while ($current < $end) {
    $date_str = $current->format('Y-m-d');
    $trends[] = [
        'appointment_date' => $date_str,
        'count' => $appointment_counts[$date_str] ?? 0
    ];
    $current->modify('+1 day');
}
$stats['trends'] = $trends;

// 3. Service Popularity
$stmt = $db->prepare("
    SELECT s.service_name, COUNT(a.appointment_id) as count 
    FROM services s
    LEFT JOIN appointments a ON s.service_id = a.service_id AND a.appointment_date BETWEEN ? AND ?
    GROUP BY s.service_id
    ORDER BY count DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
$services = [];
while ($row = $res->fetch_assoc()) {
    $services[] = $row;
}
$stats['services'] = $services;

// 3b. Program booking frequency
$stmt = $db->prepare("
    SELECT
        COALESCE(NULLIF(TRIM(u.program), ''), 'Unspecified') AS program,
        COUNT(a.appointment_id) AS count
    FROM appointments a
    JOIN users u ON u.user_id = a.user_id
    WHERE a.appointment_date BETWEEN ? AND ?
      AND u.role IN ('student','staff')
    GROUP BY COALESCE(NULLIF(TRIM(u.program), ''), 'Unspecified')
    ORDER BY count DESC, program ASC
    LIMIT 10
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
$programs = [];
while ($row = $res->fetch_assoc()) {
    $programs[] = [
        'program' => $row['program'],
        'count' => (int)$row['count']
    ];
}
$stats['programs'] = $programs;

// 4. Monthly Comparison (Current month vs Last month)
$current_month = date('m');
$current_year = date('Y');
$last_month = date('m', strtotime('-1 month'));
$last_month_year = date('Y', strtotime('-1 month'));

$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE MONTH(appointment_date) = ? AND YEAR(appointment_date) = ?");
$stmt->bind_param("ss", $current_month, $current_year);
$stmt->execute();
$stats['current_month_count'] = $stmt->get_result()->fetch_assoc()['count'];

$stmt->bind_param("ss", $last_month, $last_month_year);
$stmt->execute();
$stats['last_month_count'] = $stmt->get_result()->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'data' => $stats,
    'range' => [
        'start' => $start_date,
        'end' => $end_date
    ]
]);
?>
