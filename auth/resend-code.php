<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/email_helper.php';

if (empty($_SESSION['pending_verification'])) {
    header('Location: register.php');
    exit();
}

$pv     = &$_SESSION['pending_verification'];
$userId = (int) $pv['user_id'];
$email  = $pv['email'];
$first  = $pv['first_name'];

// Rate-limit: at most one resend every 60 seconds
$lastResend = $_SESSION['last_resend'] ?? 0;
if (time() - $lastResend < 60) {
    header('Location: verify-email.php?resent=1');
    exit();
}

$db = getDB();

// Generate new OTP
$code       = (string) random_int(100000, 999999);
$codeHash   = password_hash($code, PASSWORD_DEFAULT);
$codeExpiry = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

$upd = $db->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?");
$upd->bind_param("ssi", $codeHash, $codeExpiry, $userId);
$upd->execute();

// Reset attempt counter
$_SESSION['otp_attempts'] = 0;
$_SESSION['last_resend']  = time();

// Update expiry in pending session
$pv['expires'] = time() + OTP_EXPIRY_MINUTES * 60;

// Send email
$emailError = '';
$sent = sendVerificationEmail($email, $first, $code, $emailError);

if (!$sent) {
    $_SESSION['email_send_failed'] = true;
}

header('Location: verify-email.php?resent=1');
exit();
