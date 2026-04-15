<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('processAppointmentRemindersIfDue')) {
    function processAppointmentRemindersIfDue(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        try {
            $db = getDB();

            $db->query("
                CREATE TABLE IF NOT EXISTS appointment_reminders (
                    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
                    appointment_id INT NOT NULL,
                    reminder_hours INT NOT NULL,
                    delivery_status VARCHAR(20) NOT NULL DEFAULT 'sent',
                    error_message TEXT NULL,
                    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_appointment_reminder (appointment_id),
                    KEY idx_sent_at (sent_at),
                    CONSTRAINT fk_reminder_appointment FOREIGN KEY (appointment_id)
                        REFERENCES appointments(appointment_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $settingsStmt = $db->prepare("
                SELECT setting_key, setting_value
                FROM system_settings
                WHERE setting_key IN ('reminder_hours', 'reminder_last_run_at')
            ");
            $settingsStmt->execute();
            $settingsRes = $settingsStmt->get_result();
            $settings = [];
            while ($row = $settingsRes->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $reminderHours = (int)($settings['reminder_hours'] ?? 24);
            if ($reminderHours < 1) {
                $reminderHours = 24;
            }

            $lastRunAt = $settings['reminder_last_run_at'] ?? null;
            if ($lastRunAt && (time() - strtotime($lastRunAt)) < 300) {
                return;
            }

            $lockResult = $db->query("SELECT GET_LOCK('fsuu_appointment_reminder_dispatch', 0) AS got_lock");
            $gotLock = $lockResult ? (int)($lockResult->fetch_assoc()['got_lock'] ?? 0) : 0;
            if ($gotLock !== 1) {
                return;
            }

            try {
                $dueStmt = $db->prepare("
                    SELECT
                        a.appointment_id,
                        a.user_id,
                        a.appointment_date,
                        a.appointment_time,
                        u.email,
                        u.first_name,
                        u.last_name,
                        s.service_name
                    FROM appointments a
                    JOIN users u ON u.user_id = a.user_id
                    JOIN services s ON s.service_id = a.service_id
                    LEFT JOIN appointment_reminders ar ON ar.appointment_id = a.appointment_id
                    WHERE ar.appointment_id IS NULL
                      AND u.email IS NOT NULL
                      AND u.email <> ''
                      AND LOWER(TRIM(a.status)) IN ('pending', 'approved')
                      AND TIMESTAMP(a.appointment_date, a.appointment_time) > NOW()
                      AND TIMESTAMP(a.appointment_date, a.appointment_time) <= DATE_ADD(NOW(), INTERVAL ? HOUR)
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC
                    LIMIT 100
                ");
                $dueStmt->bind_param("i", $reminderHours);
                $dueStmt->execute();
                $dueAppointments = $dueStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($dueAppointments as $appt) {
                    $fullName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                    $formattedDate = date('F j, Y', strtotime($appt['appointment_date']));
                    $formattedTime = date('g:i A', strtotime($appt['appointment_time']));
                    $subject = 'Appointment Reminder';
                    $message = "This is a reminder that your dental appointment for {$appt['service_name']} is scheduled on {$formattedDate} at {$formattedTime}.";

                    $sent = false;
                    $errorMessage = null;

                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USER;
                        $mail->Password = SMTP_PASS;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;
                        $mail->Timeout = 15;
                        $mail->CharSet = PHPMailer::CHARSET_UTF8;

                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($appt['email'], $fullName);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = "
                            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:20px;border:1px solid #e5e7eb;border-radius:10px;'>
                                <h2 style='margin-top:0;color:#29ABE2;'>FSUU Dental Clinic</h2>
                                <p>Hello " . htmlspecialchars($fullName) . ",</p>
                                <p>This is a reminder for your upcoming appointment.</p>
                                <div style='background:#f8fafc;border-left:4px solid #29ABE2;padding:12px 14px;border-radius:6px;margin:14px 0;'>
                                    <p style='margin:0 0 6px;'><strong>Service:</strong> " . htmlspecialchars($appt['service_name']) . "</p>
                                    <p style='margin:0 0 6px;'><strong>Date:</strong> {$formattedDate}</p>
                                    <p style='margin:0;'><strong>Time:</strong> {$formattedTime}</p>
                                </div>
                                <p>Please arrive a few minutes early.</p>
                            </div>
                        ";
                        $mail->AltBody = "Appointment Reminder\nService: {$appt['service_name']}\nDate: {$formattedDate}\nTime: {$formattedTime}";
                        $mail->send();
                        $sent = true;
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                    }

                    if ($sent) {
                        $reminderStmt = $db->prepare("
                            INSERT INTO appointment_reminders (appointment_id, reminder_hours, delivery_status, sent_at)
                            VALUES (?, ?, 'sent', NOW())
                        ");
                        $reminderStmt->bind_param("ii", $appt['appointment_id'], $reminderHours);
                        $reminderStmt->execute();

                        $notifStmt = $db->prepare("
                            INSERT INTO notifications (user_id, type, subject, message, status, sent_at)
                            VALUES (?, 'email', ?, ?, 'sent', NOW())
                        ");
                        $notifStmt->bind_param("iss", $appt['user_id'], $subject, $message);
                        $notifStmt->execute();
                    } else {
                        error_log("[FSUU-Reminder] Failed for appointment_id={$appt['appointment_id']}: " . ($errorMessage ?? 'Unknown SMTP error'));
                    }
                }

                $upsertLastRun = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value)
                    VALUES ('reminder_last_run_at', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $upsertLastRun->execute();
            } finally {
                $db->query("SELECT RELEASE_LOCK('fsuu_appointment_reminder_dispatch')");
            }
        } catch (Throwable $e) {
            error_log('[FSUU-Reminder] Dispatch error: ' . $e->getMessage());
        }
    }
}

