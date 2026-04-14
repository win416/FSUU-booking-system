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

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_profiles (
        dentist_id INT(11) NOT NULL,
        specialization VARCHAR(150) DEFAULT NULL,
        digital_signature_path VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (dentist_id),
        CONSTRAINT fk_dentist_profile_user FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_REQUEST['action'] ?? 'get';

if ($action === 'get') {
    $stmt = $db->prepare("SELECT specialization, digital_signature_path FROM dentist_profiles WHERE dentist_id = ?");
    $stmt->bind_param("i", $dentist_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode([
        'success' => true,
        'profile' => [
            'specialization' => $row['specialization'] ?? '',
            'digital_signature_path' => $row['digital_signature_path'] ?? null
        ]
    ]);
    exit();
}

if ($action === 'save_specialization' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialization = trim($_POST['specialization'] ?? '');
    if ($specialization === '') {
        echo json_encode(['success' => false, 'message' => 'Specialization is required.']);
        exit();
    }

    $stmt = $db->prepare("
        INSERT INTO dentist_profiles (dentist_id, specialization)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE specialization = VALUES(specialization)
    ");
    $stmt->bind_param("is", $dentist_id, $specialization);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save specialization.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Specialization saved successfully.']);
    exit();
}

if ($action === 'upload_signature' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['digital_signature']) || $_FILES['digital_signature']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
        exit();
    }

    $file = $_FILES['digital_signature'];
    $maxSize = 2 * 1024 * 1024;
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2 MB.']);
        exit();
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP are allowed.']);
        exit();
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uploadDir = __DIR__ . '/../img/uploads/signatures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    foreach (glob($uploadDir . 'dentist_' . $dentist_id . '.*') as $old) {
        @unlink($old);
    }
    $filename = 'dentist_' . $dentist_id . '_' . time() . '.' . $ext;
    $uploadPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save signature file.']);
        exit();
    }

    $relativePath = 'img/uploads/signatures/' . $filename;
    $stmt = $db->prepare("
        INSERT INTO dentist_profiles (dentist_id, digital_signature_path)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE digital_signature_path = VALUES(digital_signature_path)
    ");
    $stmt->bind_param("is", $dentist_id, $relativePath);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save signature in profile.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Digital signature uploaded successfully.',
        'path' => $relativePath
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
