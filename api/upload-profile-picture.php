<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$db       = getDB();
$uploader = SessionManager::getUser();

// Patients upload their own; admins can upload for any patient_id passed
$target_user_id = $uploader['user_id'];
if (SessionManager::isAdmin() && !empty($_POST['patient_id'])) {
    $target_user_id = intval($_POST['patient_id']);
}

if (empty($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit();
}

$file     = $_FILES['profile_picture'];
$maxSize  = 2 * 1024 * 1024; // 2 MB
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Validate size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2 MB.']);
    exit();
}

// Validate MIME type using finfo (safer than relying on browser-supplied type)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
    exit();
}

// Build upload path
$ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
$uploadDir = __DIR__ . '/../img/uploads/profiles/';
$filename  = 'user_' . $target_user_id . '.' . strtolower($ext);
$uploadPath = $uploadDir . $filename;

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Remove any previous picture for this user (different extension)
foreach (glob($uploadDir . 'user_' . $target_user_id . '.*') as $old) {
    @unlink($old);
}

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the file. Check server permissions.']);
    exit();
}

$relativePath = 'img/uploads/profiles/' . $filename;

// Ensure column exists (safe ALTER — only adds if missing)
$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");

$stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
$stmt->bind_param("si", $relativePath, $target_user_id);

if ($stmt->execute()) {
    // Update session for own upload
    if ($target_user_id === $uploader['user_id']) {
        $_SESSION['profile_picture'] = $relativePath;
    }
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated.',
        'path'    => $relativePath . '?v=' . time()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
}
?>
