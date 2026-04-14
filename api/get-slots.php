<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$date = $_GET['date'] ?? '';
$service_id = $_GET['service_id'] ?? '';

if (!$date || !$service_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$db = getDB();

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_appointment_assignments (
        assignment_id INT(11) NOT NULL AUTO_INCREMENT,
        appointment_id INT(11) NOT NULL,
        dentist_id INT(11) NOT NULL,
        checked_in_at DATETIME DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (assignment_id),
        UNIQUE KEY uq_appointment (appointment_id),
        KEY idx_dentist (dentist_id),
        CONSTRAINT fk_daa_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
        CONSTRAINT fk_daa_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Get booking cap setting
$hours = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'max_bookings_per_day'");
$settings = [];
while($row = $hours->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$maxPerSlot = (int)($settings['max_bookings_per_day'] ?? 20);

// Build dynamic slot range from active dentists' configured working hours
$dayOfWeek = (int)date('w', strtotime($date));
if ($dayOfWeek === 0) { // Sunday
    echo json_encode([
        'success' => true, 
        'slots' => [], 
        'maxPerDay' => $maxPerSlot, 
        'date' => $date,
        'message' => 'The clinic is closed on Sundays.'
    ]);
    exit();
}

$dentistsStmt = $db->prepare("
    SELECT d.user_id,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_start')), '08:00') AS weekday_start,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_end')), '12:00') AS weekday_end,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_wednesday_start')), COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_start')), '08:00')) AS wednesday_start,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_wednesday_end')), COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_end')), '12:00')) AS wednesday_end,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_saturday_start')), '09:00') AS saturday_start,
           COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_saturday_end')), '12:00') AS saturday_end
    FROM users d
    WHERE d.role = 'dentist' AND d.is_active = 1
");
$dentistsStmt->execute();
$dentistsRes = $dentistsStmt->get_result();

$ranges = [];
while ($dentist = $dentistsRes->fetch_assoc()) {
    if (in_array($dayOfWeek, [1, 2, 4, 5], true)) {
        $startRaw = $dentist['weekday_start'] ?? '08:00';
        $endRaw = $dentist['weekday_end'] ?? '21:00';
    } elseif ($dayOfWeek === 3) {
        $startRaw = $dentist['wednesday_start'] ?? ($dentist['weekday_start'] ?? '08:00');
        $endRaw = $dentist['wednesday_end'] ?? '17:00';
    } else {
        $startRaw = $dentist['saturday_start'] ?? '08:00';
        $endRaw = $dentist['saturday_end'] ?? '16:00';
    }

    $startNorm = date('H:i:s', strtotime($startRaw));
    $endNorm = date('H:i:s', strtotime($endRaw));
    if ($startNorm >= $endNorm) continue;
    $ranges[] = ['start' => $startNorm, 'end' => $endNorm];
}

if (empty($ranges)) {
    echo json_encode([
        'success' => true,
        'slots' => [],
        'maxPerDay' => $maxPerSlot,
        'date' => $date,
        'message' => 'No dentists are available on this day.'
    ]);
    exit();
}

$start = min(array_column($ranges, 'start'));
$end = max(array_column($ranges, 'end'));

// Generate time slots (every 30 minutes)
$slots = [];
$current = strtotime($start);
$end_time = strtotime($end);

while ($current < $end_time) {
    $time = date('H:i:s', $current);

    // Global block only (admin/clinic), not dentist personal block
    $blocked = $db->prepare("
        SELECT bs.block_id
        FROM blocked_schedules bs
        LEFT JOIN users u ON u.user_id = bs.created_by
        WHERE bs.block_date = ?
          AND (bs.created_by IS NULL OR u.role <> 'dentist')
          AND (bs.is_full_day = 1 OR (bs.start_time <= ? AND bs.end_time > ?))
        LIMIT 1
    ");
    $blocked->bind_param("sss", $date, $time, $time);
    $blocked->execute();
    $is_blocked = $blocked->get_result()->num_rows > 0;

    // Current load for this slot
    $booked = $db->prepare("
        SELECT COUNT(*) as count
        FROM appointments
        WHERE appointment_date = ? AND appointment_time = ?
          AND status IN ('pending', 'approved')
    ");
    $booked->bind_param("ss", $date, $time);
    $booked->execute();
    $booked_count = (int)$booked->get_result()->fetch_assoc()['count'];

    // Assigned count for this slot
    $assigned = $db->prepare("
        SELECT COUNT(*) as assigned_count
        FROM dentist_appointment_assignments da
        JOIN appointments a ON a.appointment_id = da.appointment_id
        WHERE a.appointment_date = ? AND a.appointment_time = ?
          AND a.status IN ('pending', 'approved')
    ");
    $assigned->bind_param("ss", $date, $time);
    $assigned->execute();
    $assigned_count = (int)$assigned->get_result()->fetch_assoc()['assigned_count'];
    $unassigned_count = max(0, $booked_count - $assigned_count);

    // How many dentists are free at this slot (working + not personally blocked + no assigned booking at this slot)
    $freeDentistsStmt = $db->prepare("
        SELECT COUNT(*) AS free_count
        FROM users d
        WHERE d.role = 'dentist' AND d.is_active = 1
          AND (
              (? IN (1,2,4,5)
                AND ? >= COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_start')), '08:00')
                AND ? <  COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_end')),   '12:00'))
              OR
              (? = 3
                AND ? >= COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_wednesday_start')), COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_start')), '08:00'))
                AND ? <  COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_wednesday_end')),   COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_weekday_end')), '12:00')))
              OR
              (? = 6
                AND ? >= COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_saturday_start')), '09:00')
                AND ? <  COALESCE((SELECT setting_value FROM system_settings WHERE setting_key = CONCAT('dentist_', d.user_id, '_saturday_end')),   '12:00'))
          )
          AND NOT EXISTS (
              SELECT 1
              FROM blocked_schedules bs
              WHERE bs.created_by = d.user_id
                AND bs.block_date = ?
                AND (bs.is_full_day = 1 OR (bs.start_time <= ? AND bs.end_time > ?))
          )
          AND NOT EXISTS (
              SELECT 1
              FROM dentist_appointment_assignments da
              JOIN appointments a ON a.appointment_id = da.appointment_id
              WHERE da.dentist_id = d.user_id
                AND a.appointment_date = ?
                AND a.appointment_time = ?
                AND a.status IN ('pending', 'approved')
          )
    ");
    $freeDentistsStmt->bind_param(
        "issississsssss",
        $dayOfWeek, $time, $time,
        $dayOfWeek, $time, $time,
        $dayOfWeek, $time, $time,
        $date, $time, $time,
        $date, $time
    );
    $freeDentistsStmt->execute();
    $free_count = (int)$freeDentistsStmt->get_result()->fetch_assoc()['free_count'];

    $is_available = !$is_blocked && ($booked_count < $maxPerSlot) && ($free_count > $unassigned_count);

    $slots[] = [
        'time' => $time,
        'available' => $is_available,
        'booked' => $booked_count,
        'blocked' => $is_blocked
    ];
    
    $current = strtotime('+30 minutes', $current);
}

echo json_encode([
    'success' => true,
    'slots' => $slots,
    'maxPerDay' => $maxPerSlot,
    'date' => $date
]);
?>
