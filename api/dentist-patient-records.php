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
$action = $_REQUEST['action'] ?? '';

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

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_patient_records (
        record_id INT(11) NOT NULL AUTO_INCREMENT,
        dentist_id INT(11) NOT NULL,
        patient_id INT(11) NOT NULL,
        appointment_id INT(11) DEFAULT NULL,
        treatment_notes TEXT NOT NULL,
        prescription TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (record_id),
        KEY idx_dentist_patient (dentist_id, patient_id),
        KEY idx_appointment (appointment_id),
        CONSTRAINT fk_dpr_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_dpr_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_dpr_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if ($action === 'list') {
    $patient_id = (int)($_GET['patient_id'] ?? 0);
    if ($patient_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
        exit();
    }

    $check = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM dentist_appointment_assignments da
        INNER JOIN appointments a ON a.appointment_id = da.appointment_id
        INNER JOIN users u ON u.user_id = a.user_id
        WHERE da.dentist_id = ?
          AND u.user_id = ?
          AND u.role IN ('student','staff')
          AND LOWER(TRIM(a.status)) NOT IN ('cancelled','canceled','declined','no_show')
    ");
    $check->bind_param("ii", $dentist_id, $patient_id);
    $check->execute();
    $cnt = (int)$check->get_result()->fetch_assoc()['cnt'];
    if ($cnt === 0) {
        echo json_encode(['success' => false, 'message' => 'Patient is not available in your records scope.']);
        exit();
    }

    $stmt = $db->prepare("
        SELECT dpr.*, DATE_FORMAT(dpr.created_at, '%b %e, %Y %l:%i %p') AS created_at_display
        FROM dentist_patient_records dpr
        WHERE dpr.dentist_id = ? AND dpr.patient_id = ?
        ORDER BY dpr.created_at DESC
    ");
    $stmt->bind_param("ii", $dentist_id, $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    while ($row = $res->fetch_assoc()) {
        $row['appointment_label'] = $row['appointment_id'] ? ('Appointment #' . $row['appointment_id']) : 'Manual Entry';
        $records[] = $row;
    }

    echo json_encode(['success' => true, 'records' => $records]);
    exit();
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $treatment_notes = trim($_POST['treatment_notes'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');

    if ($patient_id <= 0 || $treatment_notes === '') {
        echo json_encode(['success' => false, 'message' => 'Patient and treatment notes are required.']);
        exit();
    }

    $check = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM dentist_appointment_assignments da
        INNER JOIN appointments a ON a.appointment_id = da.appointment_id
        INNER JOIN users u ON u.user_id = a.user_id
        WHERE da.dentist_id = ?
          AND u.user_id = ?
          AND u.role IN ('student','staff')
          AND LOWER(TRIM(a.status)) NOT IN ('cancelled','canceled','declined','no_show')
    ");
    $check->bind_param("ii", $dentist_id, $patient_id);
    $check->execute();
    $cnt = (int)$check->get_result()->fetch_assoc()['cnt'];
    if ($cnt === 0) {
        echo json_encode(['success' => false, 'message' => 'This patient is outside your record scope.']);
        exit();
    }

    $appt = $db->prepare("
        SELECT a.appointment_id
        FROM dentist_appointment_assignments da
        INNER JOIN appointments a ON a.appointment_id = da.appointment_id
        WHERE da.dentist_id = ?
          AND a.user_id = ?
          AND LOWER(TRIM(a.status)) NOT IN ('cancelled','canceled','declined','no_show')
        ORDER BY appointment_date DESC, appointment_time DESC
        LIMIT 1
    ");
    $appt->bind_param("ii", $dentist_id, $patient_id);
    $appt->execute();
    $apptRow = $appt->get_result()->fetch_assoc();
    $appointment_id = $apptRow ? (int)$apptRow['appointment_id'] : null;

    if ($appointment_id) {
        $stmt = $db->prepare("
            INSERT INTO dentist_patient_records (dentist_id, patient_id, appointment_id, treatment_notes, prescription)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiss", $dentist_id, $patient_id, $appointment_id, $treatment_notes, $prescription);
    } else {
        $stmt = $db->prepare("
            INSERT INTO dentist_patient_records (dentist_id, patient_id, treatment_notes, prescription)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $dentist_id, $patient_id, $treatment_notes, $prescription);
    }
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save record.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Record saved successfully.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
