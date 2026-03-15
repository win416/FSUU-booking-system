<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// This script should be run every hour via cron job
// For XAMPP, you can use Windows Task Scheduler

$db = getDB();

// Get appointments for tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$reminder_hours = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reminder_hours'")->fetch_assoc();
$reminder_hours = $reminder_hours['setting_value'] ?? 24;

$appointments = $db->prepare("
    SELECT a.*, u.email, u.first_name, u.last_name, u.contact_number, s.service_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.appointment_date = ? 
    AND a.status IN ('approved')
    AND a.appointment_id NOT IN (
        SELECT notification_id FROM notifications 
        WHERE type = 'reminder' AND status = 'sent'
    )
");
$appointments->bind_param("s", $tomorrow);
$appointments->execute();
$result = $appointments->get_result();

while ($appt = $result->fetch_assoc()) {
    // Send email notification
    $to = $appt['email'];
    $subject = "Appointment Reminder - FSUU Dental Clinic";
    
    $message = "
    <html>
    <head>
        <title>Appointment Reminder</title>
    </head>
    <body>
        <h2>Hello {$appt['first_name']}!</h2>
        <p>This is a reminder for your dental appointment tomorrow:</p>
        <p><strong>Date:</strong> " . date('F j, Y', strtotime($appt['appointment_date'])) . "</p>
        <p><strong>Time:</strong> " . date('g:i A', strtotime($appt['appointment_time'])) . "</p>
        <p><strong>Service:</strong> {$appt['service_name']}</p>
        <p>Please arrive 10 minutes before your scheduled time.</p>
        <p>Thank you,<br>FSUU Dental Clinic</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: FSUU Dental Clinic <noreply@fsuu.edu.ph>" . "\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        // Mark notification as sent
        $update = $db->prepare("
            INSERT INTO notifications (user_id, type, subject, message, status, sent_at)
            VALUES (?, 'reminder', ?, ?, 'sent', NOW())
        ");
        $subject_db = "Appointment Reminder";
        $update->bind_param("iss", $appt['user_id'], $subject_db, $message);
        $update->execute();
    }
}

echo "Reminders sent for " . $result->num_rows . " appointments.";
?>