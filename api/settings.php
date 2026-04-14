<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$action = $_POST['action'] ?? '';

/**
 * Upsert a key/value pair into system_settings.
 */
function saveSetting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

switch ($action) {

    // ── Booking & Hours ──────────────────────────────────────────────────────
    case 'save_booking_settings':
        $max     = intval($_POST['max_bookings_per_day'] ?? 0);
        $remind  = intval($_POST['reminder_hours'] ?? 0);
        $wd_start = trim($_POST['weekday_start'] ?? '');
        $wd_end   = trim($_POST['weekday_end'] ?? '');
        $we_start = trim($_POST['wednesday_start'] ?? '');
        $we_end   = trim($_POST['wednesday_end'] ?? '');
        $sa_start = trim($_POST['saturday_start'] ?? '');
        $sa_end   = trim($_POST['saturday_end'] ?? '');

        if ($max < 1 || $remind < 1 || !$wd_start || !$wd_end || !$we_start || !$we_end || !$sa_start || !$sa_end) {
            echo json_encode(['success' => false, 'message' => 'All fields are required and must be valid.']);
            exit();
        }
        if ($wd_start >= $wd_end) {
            echo json_encode(['success' => false, 'message' => 'M/T/Th/F start time must be before end time.']);
            exit();
        }
        if ($we_start >= $we_end) {
            echo json_encode(['success' => false, 'message' => 'Wednesday start time must be before end time.']);
            exit();
        }
        if ($sa_start >= $sa_end) {
            echo json_encode(['success' => false, 'message' => 'Saturday start time must be before end time.']);
            exit();
        }

        saveSetting($db, 'max_bookings_per_day', $max);
        saveSetting($db, 'reminder_hours', $remind);
        saveSetting($db, 'weekday_start', $wd_start);
        saveSetting($db, 'weekday_end', $wd_end);
        saveSetting($db, 'wednesday_start', $we_start);
        saveSetting($db, 'wednesday_end', $we_end);
        saveSetting($db, 'saturday_start', $sa_start);
        saveSetting($db, 'saturday_end', $sa_end);

        echo json_encode(['success' => true, 'message' => 'Booking settings saved successfully.']);
        break;

    // ── Clinic Info ──────────────────────────────────────────────────────────
    case 'save_clinic_info':
        $clinic_name    = trim($_POST['clinic_name'] ?? '');
        $clinic_email   = trim($_POST['clinic_email'] ?? '');
        $clinic_phone   = trim($_POST['clinic_phone'] ?? '');
        $clinic_address = trim($_POST['clinic_address'] ?? '');

        if (!$clinic_name) {
            echo json_encode(['success' => false, 'message' => 'Clinic name is required.']);
            exit();
        }
        if ($clinic_email && !filter_var($clinic_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit();
        }

        saveSetting($db, 'clinic_name', $clinic_name);
        saveSetting($db, 'clinic_email', $clinic_email);
        saveSetting($db, 'clinic_phone', $clinic_phone);
        saveSetting($db, 'clinic_address', $clinic_address);

        echo json_encode(['success' => true, 'message' => 'Clinic information saved successfully.']);
        break;

    // ── Add Service ──────────────────────────────────────────────────────────
    case 'add_service':
        $name     = trim($_POST['service_name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $duration = intval($_POST['duration_minutes'] ?? 0);

        if (!$name || $duration < 5) {
            echo json_encode(['success' => false, 'message' => 'Service name and a valid duration (min 5 min) are required.']);
            exit();
        }

        // Check duplicate name
        $check = $db->prepare("SELECT service_id FROM services WHERE service_name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A service with that name already exists.']);
            exit();
        }

        $stmt = $db->prepare("INSERT INTO services (service_name, description, duration_minutes, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("ssi", $name, $desc, $duration);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Service added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add service.']);
        }
        break;

    // ── Edit Service ─────────────────────────────────────────────────────────
    case 'edit_service':
        $service_id = intval($_POST['service_id'] ?? 0);
        $name       = trim($_POST['service_name'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $duration   = intval($_POST['duration_minutes'] ?? 0);

        if (!$service_id || !$name || $duration < 5) {
            echo json_encode(['success' => false, 'message' => 'Service name and a valid duration (min 5 min) are required.']);
            exit();
        }

        // Check duplicate name excluding this service
        $check = $db->prepare("SELECT service_id FROM services WHERE service_name = ? AND service_id != ?");
        $check->bind_param("si", $name, $service_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Another service with that name already exists.']);
            exit();
        }

        $stmt = $db->prepare("UPDATE services SET service_name=?, description=?, duration_minutes=? WHERE service_id=?");
        $stmt->bind_param("ssii", $name, $desc, $duration, $service_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Service updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update service.']);
        }
        break;

    // ── Toggle Service Active ────────────────────────────────────────────────
    case 'toggle_service':
        $service_id = intval($_POST['service_id'] ?? 0);
        if (!$service_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid service ID.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE services SET is_active = NOT is_active WHERE service_id=?");
        $stmt->bind_param("i", $service_id);
        if ($stmt->execute()) {
            $res = $db->prepare("SELECT is_active FROM services WHERE service_id=?");
            $res->bind_param("i", $service_id);
            $res->execute();
            $row = $res->get_result()->fetch_assoc();
            $status = $row['is_active'] ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "Service $status successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update service status.']);
        }
        break;

    // ── Delete Service ───────────────────────────────────────────────────────
    case 'delete_service':
        $service_id = intval($_POST['service_id'] ?? 0);
        if (!$service_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid service ID.']);
            exit();
        }
        // Check if service is used in any appointment
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE service_id=?");
        $check->bind_param("i", $service_id);
        $check->execute();
        $cnt = $check->get_result()->fetch_assoc()['cnt'];
        if ($cnt > 0) {
            echo json_encode(['success' => false, 'message' => "Cannot delete: this service is used in $cnt appointment(s). Deactivate it instead."]);
            exit();
        }
        $stmt = $db->prepare("DELETE FROM services WHERE service_id=?");
        $stmt->bind_param("i", $service_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Service deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete service or service not found.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
