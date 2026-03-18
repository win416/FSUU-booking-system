<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireLogin();
header('Content-Type: application/json');

$db = getDB();

// Get blocked dates for the calendar
// We return full day blocks as events
$stmt = $db->prepare("
    SELECT block_id, block_date, start_time, end_time, reason, is_full_day
    FROM blocked_schedules
    WHERE block_date >= CURDATE()
");

$stmt->execute();
$result = $stmt->get_result();

$events = [];

while ($row = $result->fetch_assoc()) {
    $event = [
        'id' => 'block_' . $row['block_id'],
        'title' => 'Unavailable' . ($row['reason'] ? ' - ' . $row['reason'] : ''),
        'start' => $row['block_date'],
        'allDay' => true,
        'className' => 'blocked-date',
        'display' => 'background',
        'backgroundColor' => '#ffcccc',
        'extendedProps' => [
            'reason' => $row['reason'],
            'isFullDay' => (bool)$row['is_full_day']
        ]
    ];
    
    if (!$row['is_full_day']) {
        $event['allDay'] = false;
        $event['start'] = $row['block_date'] . 'T' . $row['start_time'];
        $event['end'] = $row['block_date'] . 'T' . $row['end_time'];
    }
    
    $events[] = $event;
}

echo json_encode($events);
?>
