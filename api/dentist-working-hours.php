<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn() || !SessionManager::isDentist()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$user_id = (int)SessionManager::getUser()['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT setting_key, setting_value
        FROM system_settings
        WHERE setting_key IN (
            'dentist_{$user_id}_weekday_start',
            'dentist_{$user_id}_weekday_end',
            'dentist_{$user_id}_saturday_start',
            'dentist_{$user_id}_saturday_end'
        )
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $settings = [
        'weekday_start' => '08:00',
        'weekday_end' => '12:00',
        'saturday_start' => '09:00',
        'saturday_end' => '12:00',
    ];

    while ($row = $res->fetch_assoc()) {
        if ($row['setting_key'] === "dentist_{$user_id}_weekday_start") {
            $settings['weekday_start'] = substr($row['setting_value'], 0, 5);
        } elseif ($row['setting_key'] === "dentist_{$user_id}_weekday_end") {
            $settings['weekday_end'] = substr($row['setting_value'], 0, 5);
        } elseif ($row['setting_key'] === "dentist_{$user_id}_saturday_start") {
            $settings['saturday_start'] = substr($row['setting_value'], 0, 5);
        } elseif ($row['setting_key'] === "dentist_{$user_id}_saturday_end") {
            $settings['saturday_end'] = substr($row['setting_value'], 0, 5);
        }
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weekday_start = trim($_POST['weekday_start'] ?? '');
    $weekday_end = trim($_POST['weekday_end'] ?? '');
    $saturday_start = trim($_POST['saturday_start'] ?? '');
    $saturday_end = trim($_POST['saturday_end'] ?? '');

    if (!$weekday_start || !$weekday_end || !$saturday_start || !$saturday_end) {
        echo json_encode(['success' => false, 'message' => 'All working hour fields are required.']);
        exit();
    }
    if ($weekday_start >= $weekday_end) {
        echo json_encode(['success' => false, 'message' => 'Weekday start time must be before end time.']);
        exit();
    }
    if ($saturday_start >= $saturday_end) {
        echo json_encode(['success' => false, 'message' => 'Saturday start time must be before end time.']);
        exit();
    }

    $pairs = [
        "dentist_{$user_id}_weekday_start" => $weekday_start,
        "dentist_{$user_id}_weekday_end" => $weekday_end,
        "dentist_{$user_id}_saturday_start" => $saturday_start,
        "dentist_{$user_id}_saturday_end" => $saturday_end,
    ];

    foreach ($pairs as $key => $value) {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param("ss", $key, $value);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to save working hours.']);
            exit();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Working hours saved successfully.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
