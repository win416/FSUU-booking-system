<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'get_patient':
            $patient_id = intval($_GET['id'] ?? 0);
            if (!$patient_id) throw new Exception('Invalid patient ID');

            $stmt = $db->prepare("
                SELECT u.user_id, u.fsuu_id, u.first_name, u.last_name, u.email,
                       u.contact_number, u.role, u.is_active, u.created_at,
                       m.allergies, m.medical_conditions, m.medications,
                       m.emergency_contact_name, m.emergency_contact_number
                FROM users u
                LEFT JOIN medical_info m ON m.user_id = u.user_id
                WHERE u.user_id = ? AND u.role IN ('student','staff')
            ");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $patient = $stmt->get_result()->fetch_assoc();

            if (!$patient) throw new Exception('Patient not found');

            // Appointment counts
            $cnt = $db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(status='pending') as pending,
                    SUM(status='approved') as approved,
                    SUM(status='completed') as completed,
                    SUM(status='cancelled') as cancelled
                FROM appointments WHERE user_id = ?
            ");
            $cnt->bind_param("i", $patient_id);
            $cnt->execute();
            $patient['stats'] = $cnt->get_result()->fetch_assoc();

            echo json_encode(['success' => true, 'patient' => $patient]);
            break;

        case 'get_appointments':
            $patient_id = intval($_GET['id'] ?? 0);
            if (!$patient_id) throw new Exception('Invalid patient ID');

            $stmt = $db->prepare("
                SELECT a.appointment_id, a.appointment_date, a.appointment_time,
                       a.status, a.notes, s.service_name
                FROM appointments a
                JOIN services s ON s.service_id = a.service_id
                WHERE a.user_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'appointments' => $rows]);
            break;

        case 'update_personal':
            $patient_id  = intval($_POST['patient_id'] ?? 0);
            $first_name  = trim($_POST['first_name'] ?? '');
            $last_name   = trim($_POST['last_name'] ?? '');
            $contact     = trim($_POST['contact_number'] ?? '');
            $fsuu_id     = trim($_POST['fsuu_id'] ?? '');

            if (!$patient_id || !$first_name || !$last_name || !$fsuu_id)
                throw new Exception('Required fields are missing');

            // Check FSUU ID uniqueness (excluding this user)
            $ck = $db->prepare("SELECT user_id FROM users WHERE fsuu_id = ? AND user_id != ?");
            $ck->bind_param("si", $fsuu_id, $patient_id);
            $ck->execute();
            if ($ck->get_result()->num_rows > 0)
                throw new Exception('FSUU ID is already used by another account');

            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, contact_number=?, fsuu_id=? WHERE user_id=? AND role IN ('student','staff')");
            $stmt->bind_param("ssssi", $first_name, $last_name, $contact, $fsuu_id, $patient_id);
            if (!$stmt->execute() || $stmt->affected_rows < 0)
                throw new Exception('Failed to update personal information');

            echo json_encode(['success' => true, 'message' => 'Personal information updated']);
            break;

        case 'update_medical':
            $patient_id       = intval($_POST['patient_id'] ?? 0);
            $allergies        = trim($_POST['allergies'] ?? '');
            $conditions       = trim($_POST['medical_conditions'] ?? '');
            $medications      = trim($_POST['medications'] ?? '');
            $emergency_name   = trim($_POST['emergency_contact_name'] ?? '');
            $emergency_number = trim($_POST['emergency_contact_number'] ?? '');

            if (!$patient_id) throw new Exception('Invalid patient ID');

            // Upsert medical_info
            $check = $db->prepare("SELECT user_id FROM medical_info WHERE user_id = ?");
            $check->bind_param("i", $patient_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $stmt = $db->prepare("UPDATE medical_info SET allergies=?, medical_conditions=?, medications=?, emergency_contact_name=?, emergency_contact_number=? WHERE user_id=?");
                $stmt->bind_param("sssssi", $allergies, $conditions, $medications, $emergency_name, $emergency_number, $patient_id);
            } else {
                $stmt = $db->prepare("INSERT INTO medical_info (user_id, allergies, medical_conditions, medications, emergency_contact_name, emergency_contact_number) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("isssss", $patient_id, $allergies, $conditions, $medications, $emergency_name, $emergency_number);
            }

            if (!$stmt->execute()) throw new Exception('Failed to update medical information');
            echo json_encode(['success' => true, 'message' => 'Medical information updated']);
            break;

        case 'toggle_status':
            $patient_id = intval($_POST['patient_id'] ?? 0);
            if (!$patient_id) throw new Exception('Invalid patient ID');

            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id=? AND role IN ('student','staff')");
            $stmt->bind_param("i", $patient_id);
            if (!$stmt->execute() || $stmt->affected_rows === 0)
                throw new Exception('Failed to update status');

            $res = $db->prepare("SELECT is_active FROM users WHERE user_id=?");
            $res->bind_param("i", $patient_id);
            $res->execute();
            $row = $res->get_result()->fetch_assoc();
            $status = $row['is_active'] ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "Account $status", 'is_active' => (bool)$row['is_active']]);
            break;

        case 'reset_password':
            $patient_id   = intval($_POST['patient_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';

            if (!$patient_id) throw new Exception('Invalid patient ID');
            if (strlen($new_password) < 8) throw new Exception('Password must be at least 8 characters');

            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE user_id=? AND role IN ('student','staff')");
            $stmt->bind_param("si", $hashed, $patient_id);
            if (!$stmt->execute() || $stmt->affected_rows === 0)
                throw new Exception('Failed to reset password');

            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            break;

        case 'delete_patient':
            $patient_id = intval($_POST['patient_id'] ?? 0);
            if (!$patient_id) throw new Exception('Invalid patient ID');

            // Verify patient exists and is a student/staff
            $check = $db->prepare("SELECT user_id FROM users WHERE user_id=? AND role IN ('student','staff')");
            $check->bind_param("i", $patient_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0)
                throw new Exception('Patient not found');

            // Delete related records in order (respecting foreign keys)
            // Delete appointments
            $del1 = $db->prepare("DELETE FROM appointments WHERE user_id=?");
            $del1->bind_param("i", $patient_id);
            $del1->execute();

            // Delete notifications
            $del3 = $db->prepare("DELETE FROM notifications WHERE user_id=?");
            $del3->bind_param("i", $patient_id);
            $del3->execute();

            // Delete medical info
            $del4 = $db->prepare("DELETE FROM medical_info WHERE user_id=?");
            $del4->bind_param("i", $patient_id);
            $del4->execute();

            // Delete messages (both sent and received)
            $del5 = $db->prepare("DELETE FROM messages WHERE sender_id=? OR receiver_id=?");
            $del5->bind_param("ii", $patient_id, $patient_id);
            $del5->execute();

            // Delete the user account
            $del6 = $db->prepare("DELETE FROM users WHERE user_id=?");
            $del6->bind_param("i", $patient_id);
            if (!$del6->execute())
                throw new Exception('Failed to delete patient account');

            echo json_encode(['success' => true, 'message' => 'Patient deleted successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
