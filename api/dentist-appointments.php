<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn() || !SessionManager::isDentist()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$dentist_id = (int)SessionManager::getUser()['user_id'];
$action = $_REQUEST['action'] ?? 'list';

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

if ($action === 'list') {
    $status_filter = strtolower(trim($_GET['status'] ?? 'all'));
    $allowed_filters = ['all', 'pending', 'approved', 'completed', 'cancelled', 'canceled', 'declined', 'no_show'];
    if (!in_array($status_filter, $allowed_filters, true)) {
        $status_filter = 'all';
    }

    $assignedSql = "
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.notes,
               u.first_name, u.last_name, u.fsuu_id, s.service_name,
               da.checked_in_at, da.completed_at
        FROM dentist_appointment_assignments da
        JOIN appointments a ON a.appointment_id = da.appointment_id
        JOIN users u ON u.user_id = a.user_id
        JOIN services s ON s.service_id = a.service_id
        WHERE da.dentist_id = ?
    ";
    if ($status_filter !== 'all') {
        if (in_array($status_filter, ['cancelled', 'canceled', 'declined', 'no_show'], true)) {
            $assignedSql .= " AND LOWER(TRIM(a.status)) IN ('cancelled', 'canceled', 'declined', 'no_show')";
        } else {
            $assignedSql .= " AND LOWER(TRIM(a.status)) = ?";
        }
    }
    $assignedSql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    if ($status_filter !== 'all' && !in_array($status_filter, ['cancelled', 'canceled', 'declined', 'no_show'], true)) {
        $assignedStmt = $db->prepare($assignedSql);
        $assignedStmt->bind_param("is", $dentist_id, $status_filter);
    } else {
        $assignedStmt = $db->prepare($assignedSql);
        $assignedStmt->bind_param("i", $dentist_id);
    }
    $assignedStmt->execute();
    $assignedRes = $assignedStmt->get_result();
    $assigned = [];
    while ($row = $assignedRes->fetch_assoc()) {
        $assigned[] = $row;
    }

    echo json_encode([
        'success' => true,
        'assigned' => $assigned
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$appointment_id = (int)($_POST['appointment_id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required.']);
    exit();
}

if ($action === 'assign') {
    $checkStmt = $db->prepare("
        SELECT a.appointment_id
        FROM appointments a
        LEFT JOIN dentist_appointment_assignments da ON da.appointment_id = a.appointment_id
        WHERE a.appointment_id = ?
          AND da.appointment_id IS NULL
          AND a.appointment_date >= CURDATE()
          AND a.status IN ('pending', 'approved')
    ");
    $checkStmt->bind_param("i", $appointment_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment cannot be assigned.']);
        exit();
    }

    $insert = $db->prepare("INSERT INTO dentist_appointment_assignments (appointment_id, dentist_id) VALUES (?, ?)");
    $insert->bind_param("ii", $appointment_id, $dentist_id);
    if (!$insert->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to assign appointment.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Appointment assigned to you.']);
    exit();
}

$ownerStmt = $db->prepare("
    SELECT da.assignment_id, da.checked_in_at, da.completed_at, a.status
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    WHERE da.appointment_id = ? AND da.dentist_id = ?
    LIMIT 1
");
$ownerStmt->bind_param("ii", $appointment_id, $dentist_id);
$ownerStmt->execute();
$owner = $ownerStmt->get_result()->fetch_assoc();
if (!$owner) {
    echo json_encode(['success' => false, 'message' => 'Appointment is not assigned to you.']);
    exit();
}

if ($action === 'check_in') {
    if ($owner['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved appointments can be checked in.']);
        exit();
    }
    if (!empty($owner['completed_at'])) {
        echo json_encode(['success' => false, 'message' => 'Appointment already completed.']);
        exit();
    }
    if (!empty($owner['checked_in_at'])) {
        echo json_encode(['success' => false, 'message' => 'Patient already checked in.']);
        exit();
    }

    $stmt = $db->prepare("UPDATE dentist_appointment_assignments SET checked_in_at = NOW() WHERE appointment_id = ? AND dentist_id = ?");
    $stmt->bind_param("ii", $appointment_id, $dentist_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to mark check-in.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Patient checked in.']);
    exit();
}

if ($action === 'approve' || $action === 'decline') {
    if ($owner['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending appointments can be updated.']);
        exit();
    }

    $reason = trim($_POST['reason'] ?? '');
    $newStatus = $action === 'approve' ? 'approved' : 'declined';
    $reasonToSave = $newStatus === 'declined' ? ($reason !== '' ? $reason : 'Declined by assigned dentist') : null;

    $db->begin_transaction();
    try {
        $updateAppt = $db->prepare("UPDATE appointments SET status = ?, cancellation_reason = ? WHERE appointment_id = ?");
        $updateAppt->bind_param("ssi", $newStatus, $reasonToSave, $appointment_id);
        $updateAppt->execute();

        $userStmt = $db->prepare("
            SELECT a.user_id, a.appointment_date, a.appointment_time, u.first_name
            FROM appointments a
            JOIN users u ON u.user_id = a.user_id
            WHERE a.appointment_id = ?
            LIMIT 1
        ");
        $userStmt->bind_param("i", $appointment_id);
        $userStmt->execute();
        $appt = $userStmt->get_result()->fetch_assoc();

        if ($appt) {
            $subject = $newStatus === 'approved' ? 'Appointment Approved' : 'Appointment Declined';
            $msg = $newStatus === 'approved'
                ? 'Your appointment on ' . date('F j, Y', strtotime($appt['appointment_date'])) . ' at ' . date('g:i A', strtotime($appt['appointment_time'])) . ' has been approved by your assigned dentist.'
                : 'Your appointment on ' . date('F j, Y', strtotime($appt['appointment_date'])) . ' at ' . date('g:i A', strtotime($appt['appointment_time'])) . ' was declined by your assigned dentist.' . ($reasonToSave ? ' Reason: ' . $reasonToSave : '');

            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, subject, message, status) VALUES (?, 'email', ?, ?, 'pending')");
            $notifStmt->bind_param("iss", $appt['user_id'], $subject, $msg);
            $notifStmt->execute();
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => $newStatus === 'approved' ? 'Appointment approved.' : 'Appointment declined.']);
    } catch (Throwable $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment status.']);
    }
    exit();
}

if ($action === 'complete') {
    if (empty($owner['checked_in_at'])) {
        echo json_encode(['success' => false, 'message' => 'Check-in is required before completion.']);
        exit();
    }
    if (!empty($owner['completed_at']) || $owner['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Appointment is already completed.']);
        exit();
    }

    $db->begin_transaction();
    try {
        $stmt1 = $db->prepare("UPDATE dentist_appointment_assignments SET completed_at = NOW() WHERE appointment_id = ? AND dentist_id = ?");
        $stmt1->bind_param("ii", $appointment_id, $dentist_id);
        $stmt1->execute();

        $status = 'completed';
        $stmt2 = $db->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $stmt2->bind_param("si", $status, $appointment_id);
        $stmt2->execute();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Appointment marked as completed.']);
    } catch (Throwable $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to complete appointment.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
