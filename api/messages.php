<?php
date_default_timezone_set('Asia/Manila');
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user    = SessionManager::getUser();
$db      = getDB();
$isAdmin = SessionManager::isAdmin();
$action  = $_REQUEST['action'] ?? '';

// ── Auto-create messages table if missing ──────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS messages (
    message_id   INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT NOT NULL,
    receiver_id  INT NOT NULL,
    subject      VARCHAR(255) DEFAULT '',
    message_text TEXT NOT NULL,
    is_read      TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_thread   (sender_id, receiver_id)
)");
// Add subject column if upgrading from older schema
$db->query("ALTER TABLE messages ADD COLUMN IF NOT EXISTS subject VARCHAR(255) DEFAULT '' AFTER receiver_id");

// ── Helper: get first admin user_id ────────────────────────────────────────
function getAdminId($db) {
    $r = $db->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id ASC LIMIT 1");
    return $r ? (int)$r->fetch_assoc()['user_id'] : null;
}

// ── unread_count ────────────────────────────────────────────────────────────
if ($action === 'unread_count') {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];

    // For admin: also fetch recent unread previews (one per sender)
    $previews = [];
    if ($isAdmin) {
        $res = $db->prepare("
            SELECT m.sender_id, u.first_name, u.last_name, u.profile_picture,
                   MAX(m.created_at) as last_at,
                   (SELECT message_text FROM messages WHERE sender_id = m.sender_id AND receiver_id = ? ORDER BY created_at DESC LIMIT 1) as last_msg
            FROM messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE m.receiver_id = ? AND m.is_read = 0
            GROUP BY m.sender_id
            ORDER BY last_at DESC
            LIMIT 5
        ");
        $res->bind_param("ii", $user['user_id'], $user['user_id']);
        $res->execute();
        $rows = $res->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) {
            $previews[] = [
                'user_id'  => $r['sender_id'],
                'name'     => $r['first_name'] . ' ' . $r['last_name'],
                'avatar'   => $r['profile_picture'],
                'last_msg' => $r['last_msg'],
                'last_at'  => $r['last_at'],
            ];
        }
    } else {
        // Patient: show latest unread from admin
        $adminId = getAdminId($db);
        if ($adminId) {
            $res = $db->prepare("
                SELECT message_text, created_at FROM messages
                WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
                ORDER BY created_at DESC LIMIT 3
            ");
            $res->bind_param("ii", $adminId, $user['user_id']);
            $res->execute();
            $previews = $res->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    echo json_encode(['success' => true, 'count' => $count, 'previews' => $previews]);
    exit();
}

// ── get_conversations (admin only) ─────────────────────────────────────────
if ($action === 'get_conversations') {
    if (!$isAdmin) { echo json_encode(['success' => false]); exit(); }

    $id = $user['user_id'];
    $stmt = $db->prepare("
        SELECT
            u.user_id, u.first_name, u.last_name, u.fsuu_id, u.profile_picture,
            grp.subject,
            grp.last_at,
            grp.unread,
            (SELECT m2.message_text FROM messages m2
             WHERE ((m2.sender_id = u.user_id AND m2.receiver_id = ?)
                 OR (m2.sender_id = ? AND m2.receiver_id = u.user_id))
               AND IFNULL(m2.subject, '') = grp.subject
             ORDER BY m2.created_at DESC LIMIT 1) AS last_msg
        FROM (
            SELECT
                IF(sender_id = ?, receiver_id, sender_id) AS other_id,
                IFNULL(subject, '') AS subject,
                MAX(created_at) AS last_at,
                SUM(receiver_id = ? AND is_read = 0) AS unread
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY IF(sender_id = ?, receiver_id, sender_id), IFNULL(subject, '')
        ) grp
        JOIN users u ON u.user_id = grp.other_id
        WHERE u.role != 'admin'
        ORDER BY grp.last_at DESC
    ");
    $stmt->bind_param("iiiiiii", $id, $id, $id, $id, $id, $id, $id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'conversations' => $rows]);
    exit();
}

// ── get_thread ──────────────────────────────────────────────────────────────
if ($action === 'get_thread') {
    $otherId = (int)($_GET['with'] ?? 0);
    $subject = isset($_GET['subject']) ? trim($_GET['subject']) : null;
    if (!$otherId) { echo json_encode(['success' => false, 'message' => 'Missing user']); exit(); }

    // Mark messages from other as read (scoped to subject if provided)
    if ($subject !== null) {
        $mark = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND IFNULL(subject,'') = ?");
        $mark->bind_param("iis", $otherId, $user['user_id'], $subject);
    } else {
        $mark = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $mark->bind_param("ii", $otherId, $user['user_id']);
    }
    $mark->execute();

    // Fetch messages, filtered by subject when provided
    if ($subject !== null) {
        $stmt = $db->prepare("
            SELECT m.message_id, m.sender_id, m.message_text, m.subject, m.is_read, m.created_at,
                   u.first_name, u.last_name, u.profile_picture
            FROM messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
              AND IFNULL(m.subject,'') = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("iiiis", $user['user_id'], $otherId, $otherId, $user['user_id'], $subject);
    } else {
        $stmt = $db->prepare("
            SELECT m.message_id, m.sender_id, m.message_text, m.subject, m.is_read, m.created_at,
                   u.first_name, u.last_name, u.profile_picture
            FROM messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("iiii", $user['user_id'], $otherId, $otherId, $user['user_id']);
    }
    $stmt->execute();
    $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get other user info
    $info = $db->prepare("SELECT user_id, first_name, last_name, fsuu_id, profile_picture, role FROM users WHERE user_id = ?");
    $info->bind_param("i", $otherId);
    $info->execute();
    $other = $info->get_result()->fetch_assoc();

    echo json_encode(['success' => true, 'messages' => $msgs, 'other' => $other]);
    exit();
}

// ── send ────────────────────────────────────────────────────────────────────
if ($action === 'send') {
    $message    = trim($_POST['message'] ?? '');
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $subject    = trim($_POST['subject'] ?? '');

    if (!$message || !$receiverId) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit();
    }

    // Patients can only send to admins
    if (!$isAdmin) {
        $check = $db->prepare("SELECT user_id, email FROM users WHERE user_id = ? AND role = 'admin'");
        $check->bind_param("i", $receiverId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
            exit();
        }
    }

    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user['user_id'], $receiverId, $subject, $message);

    if ($stmt->execute()) {
        $emailSent = false;

        // Fetch recipient info for email notification
        $rInfo = $db->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $rInfo->bind_param("i", $receiverId);
        $rInfo->execute();
        $recipient = $rInfo->get_result()->fetch_assoc();

        if ($recipient && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
            $senderName    = $user['first_name'] . ' ' . $user['last_name'];
            $recipientName = $recipient['first_name'] . ' ' . $recipient['last_name'];
            $loginUrl      = SITE_URL . '/auth/login.php';
            $sentAt        = date('F j, Y g:i A');
            $msgPreview    = nl2br(htmlspecialchars($message));
            $subjectLine   = $subject ?: 'New Message from FSUU Dental Clinic';

            $htmlBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:32px 0;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);max-width:600px;width:100%;'>

        <!-- Header -->
        <tr>
          <td style='background:#1A1A1A;padding:24px 32px;'>
            <table width='100%'><tr>
              <td>
                <div style='color:#29ABE2;font-size:1rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;'>FSUU Dental Clinic</div>
                <div style='color:#ffffff;font-size:1.25rem;font-weight:700;margin-top:4px;'>You have a new message</div>
              </td>
            </tr></table>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style='padding:28px 32px;'>
            <p style='margin:0 0 16px;font-size:0.95rem;color:#374151;'>
              Hello, <strong>{$recipientName}</strong>!<br>
              <strong>{$senderName}</strong> sent you a message" . ($subject ? " about <em>" . htmlspecialchars($subject) . "</em>" : "") . ":
            </p>

            <!-- Message bubble -->
            <div style='background:#f0f9ff;border-left:4px solid #29ABE2;border-radius:6px;padding:16px 20px;margin:0 0 24px;font-size:0.95rem;line-height:1.7;color:#1e293b;'>
              {$msgPreview}
            </div>

            <table width='100%' style='font-size:0.83rem;color:#64748b;margin-bottom:24px;'>
              <tr>
                <td style='padding:4px 0;'><strong style='color:#374151;'>From:</strong> {$senderName}</td>
              </tr>
              " . ($subject ? "<tr><td style='padding:4px 0;'><strong style='color:#374151;'>Subject:</strong> " . htmlspecialchars($subject) . "</td></tr>" : "") . "
              <tr>
                <td style='padding:4px 0;'><strong style='color:#374151;'>Sent:</strong> {$sentAt}</td>
              </tr>
            </table>

            <!-- CTA button -->
            <table width='100%'><tr><td align='center'>
              <a href='{$loginUrl}'
                 style='display:inline-block;background:#29ABE2;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:700;font-size:0.95rem;letter-spacing:0.3px;'>
                 View Message &rarr;
              </a>
            </td></tr></table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:0.78rem;color:#94a3b8;text-align:center;'>
            This is an automated notification from FSUU Dental Clinic.<br>
            Please do not reply to this email — log in to respond.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";

            try {
                $debugLog = '';
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                $mail->Timeout    = 15;
                $mail->CharSet    = PHPMailer::CHARSET_UTF8;
                // Capture full SMTP debug output to variable
                $mail->SMTPDebug  = 2;
                $mail->Debugoutput = function($str, $level) use (&$debugLog) {
                    $debugLog .= trim($str) . "\n";
                };

                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME); // Ensure replies go to system email
                $mail->addAddress($recipient['email'], $recipientName);
                $mail->isHTML(true);
                $mail->Subject = $subjectLine;
                $mail->Body    = $htmlBody;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
                
                // Add headers to improve deliverability
                $mail->addCustomHeader('X-Mailer', 'FSUU Dental System');
                $mail->addCustomHeader('X-Priority', '3');

                $mail->send();
                $emailSent = true;
                // Log success with debug for confirmation
                error_log("[FSUU-Mailer] SUCCESS sent to user_id={$receiverId} ({$recipient['email']})");
            } catch (Exception $e) {
                // Email failure is non-fatal — message is still saved
                $emailSent    = false;
                $emailErrMsg  = $e->getMessage();
                $smtpInfo     = $mail->ErrorInfo ?? '';
                error_log("[FSUU-Mailer] FAILED user_id={$receiverId} ({$recipient['email']}): {$emailErrMsg} | ErrorInfo: {$smtpInfo}");
                error_log("[FSUU-Mailer] SMTP Debug:\n{$debugLog}");

                // Detect Gmail daily sending limit
                if (stripos($smtpInfo, '5.4.5') !== false || stripos($smtpInfo, 'limit exceeded') !== false
                    || stripos($emailErrMsg, 'limit exceeded') !== false) {
                    $emailErrMsg = 'Gmail daily sending limit exceeded. Emails will resume tomorrow.';
                }
            } catch (\Exception $e) {
                $emailSent   = false;
                $emailErrMsg = $e->getMessage();
                error_log("[FSUU-Mailer] Unexpected error for user_id={$receiverId}: {$emailErrMsg}\nDebug:\n{$debugLog}");
            }
        }

        echo json_encode([
            'success'      => true,
            'message_id'   => $db->insert_id,
            'created_at'   => date('Y-m-d H:i:s'),
            'email_sent'   => $emailSent,
            'email_error'  => $emailSent ? null : ($emailErrMsg ?? 'Unknown error'),
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error']);
    }
    exit();
}

// ── get_sent (grouped by recipient + subject) ─────────────────────────────
if ($action === 'get_sent') {
    $uid = $user['user_id'];
    $stmt = $db->prepare("
        SELECT
            u.user_id, u.first_name, u.last_name, u.profile_picture,
            IFNULL(m.subject, '') AS subject,
            MAX(m.created_at) AS last_at,
            (SELECT m2.message_text FROM messages m2
             WHERE m2.sender_id = ? AND m2.receiver_id = u.user_id
               AND IFNULL(m2.subject,'') = IFNULL(m.subject,'')
             ORDER BY m2.created_at DESC LIMIT 1) AS last_msg
        FROM messages m
        JOIN users u ON u.user_id = m.receiver_id
        WHERE m.sender_id = ?
        GROUP BY u.user_id, IFNULL(m.subject, '')
        ORDER BY last_at DESC
    ");
    $stmt->bind_param("ii", $uid, $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'sent' => $rows]);
    exit();
}

// ── get_inbox (patient: all threads I'm part of, grouped by subject) ───────────────────────
if ($action === 'get_inbox') {
    $uid = $user['user_id'];
    $stmt = $db->prepare("
        SELECT
            u.user_id, u.first_name, u.last_name, u.profile_picture, u.role,
            grp.subject,
            grp.last_at,
            grp.unread,
            (SELECT m2.message_text FROM messages m2
             WHERE ((m2.sender_id = u.user_id AND m2.receiver_id = ?)
                 OR (m2.sender_id = ? AND m2.receiver_id = u.user_id))
               AND IFNULL(m2.subject,'') = grp.subject
             ORDER BY m2.created_at DESC LIMIT 1) AS last_msg
        FROM (
            SELECT
                IF(sender_id = ?, receiver_id, sender_id) AS other_id,
                IFNULL(subject, '') AS subject,
                MAX(created_at) AS last_at,
                SUM(receiver_id = ? AND is_read = 0) AS unread
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY IF(sender_id = ?, receiver_id, sender_id), IFNULL(subject, '')
        ) grp
        JOIN users u ON u.user_id = grp.other_id
        ORDER BY grp.last_at DESC
    ");
    $stmt->bind_param("iiiiiii", $uid, $uid, $uid, $uid, $uid, $uid, $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'threads' => $rows]);
    exit();
}

// ── search_recipients ──────────────────────────────────────────────────────
if ($action === 'search_recipients') {
    $q = '%' . $db->real_escape_string(trim($_GET['q'] ?? '')) . '%';
    if (!$isAdmin) {
        // Patients can only message admins
        $stmt = $db->prepare("
            SELECT user_id, first_name, last_name, email, role, profile_picture
            FROM users
            WHERE role = 'admin' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
            ORDER BY first_name ASC LIMIT 8
        ");
    } else {
        // Admins can message any patient
        $stmt = $db->prepare("
            SELECT user_id, first_name, last_name, email, role, profile_picture
            FROM users
            WHERE role != 'admin' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
            ORDER BY first_name ASC LIMIT 8
        ");
    }
    $stmt->bind_param("sss", $q, $q, $q);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'results' => $results]);
    exit();
}

// ── get_admin_id (for patient to know who to message) ──────────────────────
if ($action === 'get_admin_id') {
    $adminId = getAdminId($db);
    echo json_encode(['success' => true, 'admin_id' => $adminId]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
