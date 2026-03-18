<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to book an appointment']);
    exit();
}

$user = SessionManager::getUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = getDB();

// Validate required fields
$required = ['service_id', 'appointment_date', 'appointment_time', 'emergency_contact_name', 'emergency_contact_number', 'consent'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Check consent
if ($_POST['consent'] !== '1' && $_POST['consent'] !== 'true' && $_POST['consent'] !== true) {
    echo json_encode(['success' => false, 'message' => 'You must agree to the clinic policies']);
    exit();
}

$service_id = intval($_POST['service_id']);
$appointment_date = $_POST['appointment_date'];
$appointment_time = $_POST['appointment_time'];
$notes = $_POST['notes'] ?? '';

// Check if date is valid (not in the past)
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past']);
    exit();
}

// Validate operating hours based on day of week
$dayOfWeek = date('w', strtotime($appointment_date));
$isValidTime = false;
$time = date('H:i:s', strtotime($appointment_time));

if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday-Friday
    if ($time >= '13:00:00' && $time <= '15:30:00') {
        $isValidTime = true;
    }
    $allowedRange = "1:00 PM - 3:30 PM";
} elseif ($dayOfWeek == 6) { // Saturday
    if ($time >= '09:00:00' && $time <= '12:00:00') {
        $isValidTime = true;
    }
    $allowedRange = "9:00 AM - 12:00 PM";
} else { // Sunday (0)
    echo json_encode(['success' => false, 'message' => 'The clinic is closed on Sundays']);
    exit();
}

if (!$isValidTime) {
    echo json_encode(['success' => false, 'message' => "Invalid appointment time. On this day, we only accept appointments between $allowedRange."]);
    exit();
}

// Check if time slot is available
$check = $db->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE appointment_date = ? AND appointment_time = ? 
    AND status IN ('pending', 'approved')
");
$check->bind_param("ss", $appointment_date, $appointment_time);
$check->execute();
$count = $check->get_result()->fetch_assoc()['count'];

// Get max bookings per day
$max = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_bookings_per_day'")->fetch_assoc();
$maxPerDay = $max['setting_value'] ?? 20;

if ($count >= $maxPerDay) {
    echo json_encode(['success' => false, 'message' => 'This time slot is no longer available']);
    exit();
}

// Check if user already has a pending/approved appointment for this date
$user_check = $db->prepare("
    SELECT appointment_id FROM appointments 
    WHERE user_id = ? AND appointment_date = ? 
    AND status IN ('pending', 'approved')
");
$user_check->bind_param("is", $user['user_id'], $appointment_date);
$user_check->execute();
if ($user_check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have an appointment on this date']);
    exit();
}

// Begin transaction
$db->begin_transaction();

try {
    // Insert appointment
    $insert = $db->prepare("
        INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, notes, consent_agreed, status)
        VALUES (?, ?, ?, ?, ?, 1, 'pending')
    ");
    $insert->bind_param("iisss", $user['user_id'], $service_id, $appointment_date, $appointment_time, $notes);
    $insert->execute();
    $appointment_id = $db->insert_id;
    
    // Update medical info
    $update = $db->prepare("
        UPDATE medical_info 
        SET allergies = ?, medical_conditions = ?, medications = ?,
            emergency_contact_name = ?, emergency_contact_number = ?,
            last_update = NOW()
        WHERE user_id = ?
    ");
    $update->bind_param("sssssi", 
        $_POST['allergies'], 
        $_POST['medical_conditions'], 
        $_POST['medications'],
        $_POST['emergency_contact_name'],
        $_POST['emergency_contact_number'],
        $user['user_id']
    );
    $update->execute();
    
    // Log action
    $log = $db->prepare("
        INSERT INTO audit_log (user_id, action, description, ip_address)
        VALUES (?, 'book_appointment', ?, ?)
    ");
    $desc = "Booked appointment #$appointment_id for $appointment_date at $appointment_time";
    $ip = $_SERVER['REMOTE_ADDR'];
    $log->bind_param("iss", $user['user_id'], $desc, $ip);
    $log->execute();
    
    // Create notification
    $notif = $db->prepare("
        INSERT INTO notifications (user_id, type, subject, message)
        VALUES (?, 'email', 'Appointment Request Submitted', ?)
    ");
    $message = "Your appointment request for " . date('F j, Y', strtotime($appointment_date)) . 
               " at " . date('g:i A', strtotime($appointment_time)) . " has been submitted and is pending approval.";
    $notif->bind_param("is", $user['user_id'], $message);
    $notif->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>