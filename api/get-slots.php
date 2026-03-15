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

// Get operating hours from settings
$hours = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('operating_hours_start', 'operating_hours_end', 'max_bookings_per_day')");
$settings = [];
while($row = $hours->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$start = $settings['operating_hours_start'] ?? '08:00';
$end = $settings['operating_hours_end'] ?? '17:00';
$maxPerDay = $settings['max_bookings_per_day'] ?? 20;

// Generate time slots (every 30 minutes)
$slots = [];
$current = strtotime($start);
$end_time = strtotime($end);

while ($current < $end_time) {
    $time = date('H:i:s', $current);
    
    // Check if this time is blocked
    $blocked = $db->prepare("
        SELECT block_id FROM blocked_schedules 
        WHERE block_date = ? 
        AND (is_full_day = 1 OR (start_time <= ? AND end_time >= ?))
    ");
    $blocked->bind_param("sss", $date, $time, $time);
    $blocked->execute();
    $is_blocked = $blocked->get_result()->num_rows > 0;
    
    // Count bookings for this time slot
    $booked = $db->prepare("
        SELECT COUNT(*) as count FROM appointments 
        WHERE appointment_date = ? AND appointment_time = ? 
        AND status IN ('pending', 'approved')
    ");
    $booked->bind_param("ss", $date, $time);
    $booked->execute();
    $booked_count = $booked->get_result()->fetch_assoc()['count'];
    
    $slots[] = [
        'time' => $time,
        'available' => !$is_blocked && $booked_count < $maxPerDay,
        'booked' => $booked_count,
        'blocked' => $is_blocked
    ];
    
    $current = strtotime('+30 minutes', $current);
}

echo json_encode([
    'success' => true,
    'slots' => $slots,
    'maxPerDay' => $maxPerDay,
    'date' => $date
]);
?>