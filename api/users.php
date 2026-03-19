<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Only admins can manage users
if (!SessionManager::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'add_user':
        $fsuu_id      = trim($_POST['fsuu_id'] ?? '');
        $first_name   = trim($_POST['first_name'] ?? '');
        $last_name    = trim($_POST['last_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $contact      = trim($_POST['contact_number'] ?? '');
        $role         = trim($_POST['role'] ?? '');
        $password     = $_POST['password'] ?? '';

        if (!$fsuu_id || !$first_name || !$last_name || !$email || !$role || !$password) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
            exit();
        }
        if (!in_array($role, ['admin', 'dentist', 'staff'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
            exit();
        }
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit();
        }

        // Check duplicate FSUU ID or email
        $check = $db->prepare("SELECT user_id FROM users WHERE fsuu_id = ? OR email = ?");
        $check->bind_param("ss", $fsuu_id, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'FSUU ID or email already exists.']);
            exit();
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (fsuu_id, email, password, first_name, last_name, contact_number, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $fsuu_id, $email, $hashed, $first_name, $last_name, $contact, $role);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add user. Please try again.']);
        }
        break;

    case 'edit_user':
        $user_id    = intval($_POST['user_id'] ?? 0);
        $fsuu_id    = trim($_POST['fsuu_id'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $contact    = trim($_POST['contact_number'] ?? '');
        $role       = trim($_POST['role'] ?? '');

        if (!$user_id || !$fsuu_id || !$first_name || !$last_name || !$email || !$role) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
            exit();
        }
        if (!in_array($role, ['admin', 'dentist', 'staff'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit();
        }

        // Check duplicate FSUU ID or email (excluding this user)
        $check = $db->prepare("SELECT user_id FROM users WHERE (fsuu_id = ? OR email = ?) AND user_id != ?");
        $check->bind_param("ssi", $fsuu_id, $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'FSUU ID or email already used by another user.']);
            exit();
        }

        $stmt = $db->prepare("UPDATE users SET fsuu_id=?, first_name=?, last_name=?, email=?, contact_number=?, role=? WHERE user_id=?");
        $stmt->bind_param("ssssssi", $fsuu_id, $first_name, $last_name, $email, $contact, $role, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
        }
        break;

    case 'reset_password':
        $user_id     = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (!$user_id || strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Invalid user or password too short (min 8 chars).']);
            exit();
        }

        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE user_id=?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password.']);
        }
        break;

    case 'toggle_status':
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit();
        }
        // Prevent self-deactivation
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Get new status for message
            $res = $db->prepare("SELECT is_active FROM users WHERE user_id=?");
            $res->bind_param("i", $user_id);
            $res->execute();
            $row = $res->get_result()->fetch_assoc();
            $status = $row['is_active'] ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "User $status successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        break;

    case 'delete_user':
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            exit();
        }
        // Prevent self-deletion
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            exit();
        }
        $stmt = $db->prepare("DELETE FROM users WHERE user_id=? AND role IN ('admin','dentist','staff')");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user or user not found.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
