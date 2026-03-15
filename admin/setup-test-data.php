<?php
require_once '../includes/db_connection.php';

$db = getDB();

// Create admin user
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin = $db->prepare("
    INSERT INTO users (fsuu_id, email, password, first_name, last_name, role) 
    VALUES (?, ?, ?, ?, ?, 'admin')
");
$fsuu_id = 'ADMIN001';
$email = 'admin@fsuu.edu.ph';
$first = 'Admin';
$last = 'User';
$admin->bind_param("sssss", $fsuu_id, $email, $admin_password, $first, $last);
$admin->execute();

// Create test student
$student_password = password_hash('student123', PASSWORD_DEFAULT);
$student = $db->prepare("
    INSERT INTO users (fsuu_id, email, password, first_name, last_name, role) 
    VALUES (?, ?, ?, ?, ?, 'student')
");
$fsuu_id = '2020-0001';
$email = 'student@fsuu.edu.ph';
$first = 'Juan';
$last = 'Dela Cruz';
$student->bind_param("sssss", $fsuu_id, $email, $student_password, $first, $last);
$student->execute();
$user_id = $db->insert_id;

// Add medical info for student
$medical = $db->prepare("INSERT INTO medical_info (user_id) VALUES (?)");
$medical->bind_param("i", $user_id);
$medical->execute();

echo "Test data created successfully!<br>";
echo "Admin login: admin@fsuu.edu.ph / admin123<br>";
echo "Student login: student@fsuu.edu.ph / student123";
?>