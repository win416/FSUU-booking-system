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
$appointment_time = date('H:i:s', strtotime($_POST['appointment_time']));
$notes = $_POST['notes'] ?? '';

// Check if date is valid (not in the past)
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past']);
    exit();
}

// Validate day first
$dayOfWeek = date('w', strtotime($appointment_date));
$time = $appointment_time;

if ((int)$dayOfWeek === 0) { // Sunday
    echo json_encode(['success' => false, 'message' => 'The clinic is closed on Sundays']);
    exit();
}

// Get max bookings per slot
$max = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_bookings_per_day'")->fetch_assoc();
$maxPerSlot = (int)($max['setting_value'] ?? 20);

// Respect global blocked schedules (admin/clinic blocks), not dentist personal blocks
$globalBlockedStmt = $db->prepare("
    SELECT bs.block_id
    FROM blocked_schedules bs
    LEFT JOIN users u ON u.user_id = bs.created_by
    WHERE bs.block_date = ?
      AND (bs.created_by IS NULL OR u.role <> 'dentist')
      AND (bs.is_full_day = 1 OR (bs.start_time <= ? AND bs.end_time > ?))
    LIMIT 1
");
$globalBlockedStmt->bind_param("sss", $appointment_date, $appointment_time, $appointment_time);
$globalBlockedStmt->execute();
if ($globalBlockedStmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is unavailable. Please choose another time.']);
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
    // Current load at this slot (pending/approved only)
    $loadStmt = $db->prepare("
        SELECT COUNT(*) AS total_count
        FROM appointments
        WHERE appointment_date = ? AND appointment_time = ?
          AND status IN ('pending', 'approved')
    ");
    $loadStmt->bind_param("ss", $appointment_date, $appointment_time);
    $loadStmt->execute();
    $totalCount = (int)($loadStmt->get_result()->fetch_assoc()['total_count'] ?? 0);
    if ($totalCount >= $maxPerSlot) {
        throw new Exception('This time slot is no longer available');
    }

    // Dentist availability at selected time (dynamic, not hardcoded)
    $dayOfWeekInt = (int)$dayOfWeek;
    $freeDentistsStmt = $db->prepare("
        SELECT d.user_id
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
        ORDER BY d.user_id ASC
    ");
    $freeDentistsStmt->bind_param(
        "issississsssss",
        $dayOfWeekInt, $appointment_time, $appointment_time,
        $dayOfWeekInt, $appointment_time, $appointment_time,
        $dayOfWeekInt, $appointment_time, $appointment_time,
        $appointment_date, $appointment_time, $appointment_time,
        $appointment_date, $appointment_time
    );
    $freeDentistsStmt->execute();
    $freeDentistsRes = $freeDentistsStmt->get_result();
    $freeDentists = [];
    while ($row = $freeDentistsRes->fetch_assoc()) {
        $freeDentists[] = (int)$row['user_id'];
    }

    // Legacy unassigned appointments at same slot consume dentist capacity too
    $assignedCountStmt = $db->prepare("
        SELECT COUNT(*) AS assigned_count
        FROM dentist_appointment_assignments da
        JOIN appointments a ON a.appointment_id = da.appointment_id
        WHERE a.appointment_date = ? AND a.appointment_time = ?
          AND a.status IN ('pending', 'approved')
    ");
    $assignedCountStmt->bind_param("ss", $appointment_date, $appointment_time);
    $assignedCountStmt->execute();
    $assignedCount = (int)($assignedCountStmt->get_result()->fetch_assoc()['assigned_count'] ?? 0);
    $unassignedCount = max(0, $totalCount - $assignedCount);

    if (count($freeDentists) <= $unassignedCount) {
        throw new Exception('No dentist is available at this selected time. Please refresh slots and choose another available time.');
    }

    // Pick dentist with lightest load for this date
    $selectedDentistId = null;
    $bestLoad = PHP_INT_MAX;
    foreach ($freeDentists as $dentistId) {
        $loadByDentistStmt = $db->prepare("
            SELECT COUNT(*) AS dentist_load
            FROM dentist_appointment_assignments da
            JOIN appointments a ON a.appointment_id = da.appointment_id
            WHERE da.dentist_id = ?
              AND a.appointment_date = ?
              AND a.status IN ('pending', 'approved')
        ");
        $loadByDentistStmt->bind_param("is", $dentistId, $appointment_date);
        $loadByDentistStmt->execute();
        $dentistLoad = (int)($loadByDentistStmt->get_result()->fetch_assoc()['dentist_load'] ?? 0);
        if ($dentistLoad < $bestLoad) {
            $bestLoad = $dentistLoad;
            $selectedDentistId = $dentistId;
        }
    }

    if (!$selectedDentistId) {
        throw new Exception('No dentist is available at this selected time. Please refresh slots and choose another available time.');
    }

    // Insert appointment
    $insert = $db->prepare("
        INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, notes, consent_agreed, status)
        VALUES (?, ?, ?, ?, ?, 1, 'pending')
    ");
    $insert->bind_param("iisss", $user['user_id'], $service_id, $appointment_date, $appointment_time, $notes);
    $insert->execute();
    $appointment_id = $db->insert_id;

    // Auto-assign this booking to the selected available dentist
    $assignStmt = $db->prepare("INSERT INTO dentist_appointment_assignments (appointment_id, dentist_id) VALUES (?, ?)");
    $assignStmt->bind_param("ii", $appointment_id, $selectedDentistId);
    $assignStmt->execute();

    // Fetch assigned dentist info for patient feedback
    $dentistInfoStmt = $db->prepare("
        SELECT user_id, first_name, last_name, email
        FROM users
        WHERE user_id = ? AND role = 'dentist'
        LIMIT 1
    ");
    $dentistInfoStmt->bind_param("i", $selectedDentistId);
    $dentistInfoStmt->execute();
    $dentistInfo = $dentistInfoStmt->get_result()->fetch_assoc();
    
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
    $assignedDentistName = $dentistInfo
        ? ('Dr. ' . trim(($dentistInfo['first_name'] ?? '') . ' ' . ($dentistInfo['last_name'] ?? '')))
        : 'your assigned dentist';
    $message = "Your appointment request for " . date('F j, Y', strtotime($appointment_date)) .
               " at " . date('g:i A', strtotime($appointment_time)) .
               " has been submitted and is pending approval. Assigned dentist: " . $assignedDentistName . ".";
    $notif->bind_param("is", $user['user_id'], $message);
    $notif->execute();

    // Notify assigned dentist
    $dentistNotif = $db->prepare("
        INSERT INTO notifications (user_id, type, subject, message, status)
        VALUES (?, 'email', 'New Appointment Assigned', ?, 'pending')
    ");
    $patientName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $dentistMessage = "A new appointment has been assigned to you for " .
        date('F j, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time)) . ".";
    if ($patientName !== '') {
        $dentistMessage .= " Patient: " . $patientName . ".";
    }
    $dentistNotif->bind_param("is", $selectedDentistId, $dentistMessage);
    $dentistNotif->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => $appointment_id,
        'assigned_dentist' => $dentistInfo ? [
            'user_id' => (int)$dentistInfo['user_id'],
            'name' => trim(($dentistInfo['first_name'] ?? '') . ' ' . ($dentistInfo['last_name'] ?? '')),
            'email' => $dentistInfo['email'] ?? ''
        ] : null
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
