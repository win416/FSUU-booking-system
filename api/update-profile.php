<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = SessionManager::getUser()['user_id'];
$db = getDB();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_personal') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact = trim($_POST['contact_number']);

        if (!$first_name || !$last_name || !$contact) {
            throw new Exception('All fields are required');
        }

        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, contact_number = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $first_name, $last_name, $contact, $user_id);
        
        if ($stmt->execute()) {
            // Refresh session with updated values using the proper session manager
            $updated_user = SessionManager::getUser();
            $updated_user['first_name']     = $first_name;
            $updated_user['last_name']      = $last_name;
            $updated_user['contact_number'] = $contact;
            SessionManager::setUser($updated_user);
            
            echo json_encode(['success' => true, 'message' => 'Personal information updated successfully', 'refresh' => true]);
        } else {
            throw new Exception('Failed to update personal information');
        }

    } elseif ($action === 'update_medical') {
        $allergies = trim($_POST['allergies']);
        $conditions = trim($_POST['medical_conditions']);
        $medications = trim($_POST['medications']);
        $emergency_name = trim($_POST['emergency_contact_name']);
        $emergency_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
        $emergency_number = trim($_POST['emergency_contact_number']);

        if (!$emergency_name || !$emergency_number) {
            throw new Exception('Emergency contact details are required');
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
        $stmt = $db->prepare("INSERT INTO medical_info (user_id, allergies, medical_conditions, medications, emergency_contact_name, emergency_contact_relationship, emergency_contact_number) 
                              VALUES (?, ?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              allergies = VALUES(allergies), 
                              medical_conditions = VALUES(medical_conditions), 
                              medications = VALUES(medications), 
                              emergency_contact_name = VALUES(emergency_contact_name), 
                              emergency_contact_relationship = VALUES(emergency_contact_relationship), 
                              emergency_contact_number = VALUES(emergency_contact_number)");
        $stmt->bind_param("issssss", $user_id, $allergies, $conditions, $medications, $emergency_name, $emergency_relationship, $emergency_number);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Medical record updated successfully']);
        } else {
            throw new Exception('Failed to update medical record');
        }

    } elseif ($action === 'update_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (strlen($new) < 8) {
            throw new Exception('New password must be at least 8 characters');
        }

        if ($new !== $confirm) {
            throw new Exception('Passwords do not match');
        }

        // Verify current password (skip if account has no password set, e.g. Google sign-in)
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $storedHash = $res['password'] ?? '';

        if (!empty($storedHash) && !password_verify($current, $storedHash)) {
            throw new Exception('Incorrect current password');
        }

        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->bind_param("si", $hashed, $user_id);

        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            throw new Exception('Failed to update password');
        }

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
