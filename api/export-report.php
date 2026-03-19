<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

if (!SessionManager::isAdmin()) {
    if (($_GET['format'] ?? '') === 'csv') {
        http_response_code(403);
        exit('Unauthorized');
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');
$format     = strtolower($_GET['format'] ?? 'json');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))   $end_date   = date('Y-m-d');

// Fetch detailed appointment rows
$stmt = $db->prepare("
    SELECT
        a.appointment_id,
        a.appointment_date,
        TIME_FORMAT(a.appointment_time, '%h:%i %p') AS appointment_time,
        CONCAT(u.last_name, ', ', u.first_name) AS patient_name,
        u.fsuu_id,
        u.email,
        u.contact_number,
        s.service_name,
        a.status,
        a.notes,
        a.created_at
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// ── JSON response ────────────────────────────────────────────────────────────
if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
    exit();
}

// ── CSV export ───────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename = 'fsuu_dental_report_' . $start_date . '_to_' . $end_date . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Report header info
    fputcsv($out, ['FSUU Dental Clinic - Appointments Report']);
    fputcsv($out, ['Period:', $start_date . ' to ' . $end_date]);
    fputcsv($out, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Total Records:', count($rows)]);
    fputcsv($out, []); // blank line

    // Column headers
    fputcsv($out, [
        'No.',
        'Appointment ID',
        'Date',
        'Time',
        'Patient Name',
        'FSUU ID',
        'Email',
        'Contact',
        'Service',
        'Status',
        'Notes',
        'Booked At'
    ]);

    // Data rows
    foreach ($rows as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['appointment_id'],
            $r['appointment_date'],
            $r['appointment_time'],
            $r['patient_name'],
            $r['fsuu_id'],
            $r['email'],
            $r['contact_number'],
            $r['service_name'],
            ucfirst($r['status']),
            $r['notes'] ?? '',
            $r['created_at']
        ]);
    }

    fclose($out);
    exit();
}

// Fallback
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid format. Use json or csv.']);
