<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = getDB();
$error = '';
$success = '';

$email = trim($_GET['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php');
    exit;
}

// Look up the unverified user
$stmt = $db->prepare("SELECT user_id, first_name, verification_code, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['is_verified']) {
    // Already verified or doesn't exist — send to login
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['code'] ?? '');

    if (empty($entered)) {
        $error = 'Please enter the verification code.';
    } elseif ($entered !== $user['verification_code']) {
        $error = 'Invalid verification code. Please try again.';
    } else {
        // Mark user as verified and clear the code
        $upd = $db->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE user_id = ?");
        $upd->bind_param("i", $user['user_id']);
        if ($upd->execute()) {
            header('Location: login.php?verified=1');
            exit;
        } else {
            $error = 'Verification failed. Please try again.';
        }
    }
}

// Resend code
if (isset($_POST['resend'])) {
    $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $upd = $db->prepare("UPDATE users SET verification_code = ? WHERE user_id = ?");
    $upd->bind_param("si", $new_code, $user['user_id']);
    $upd->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($email, $user['first_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Your New FSUU Dental Clinic Verification Code';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;'>
                <h2 style='color:#2c6fad;'>FSUU Dental Clinic</h2>
                <p>Hello, <strong>{$user['first_name']}</strong>!</p>
                <p>Here is your new verification code:</p>
                <div style='font-size:36px;font-weight:bold;letter-spacing:8px;text-align:center;padding:16px 0;color:#2c6fad;'>{$new_code}</div>
                <p style='color:#888;font-size:13px;'>This code expires in " . OTP_EXPIRY_MINUTES . " minutes. Do not share it with anyone.</p>
            </div>";
        $mail->AltBody = "Your new verification code is: {$new_code}";
        $mail->send();
        $success = 'A new verification code has been sent to your email.';
    } catch (Exception $e) {
        $error = 'Could not resend code. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="stylesheet" href="../assets/css/auth-register.css">
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card" style="max-width:440px;">
                <div class="card-header text-center">
                    <h4>Verify Your Email</h4>
                    <p class="mb-0">A 6-digit code was sent to<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (!$user['is_verified']): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Verification Code</label>
                            <input type="text" name="code" class="form-control text-center"
                                   maxlength="6" placeholder="______"
                                   style="font-size:1.8rem;letter-spacing:8px;" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-register w-100">Verify</button>
                    </form>

                    <form method="POST" action="" class="mt-3 text-center">
                        <small class="text-muted">Didn't receive the code?</small><br>
                        <button type="submit" name="resend" class="btn btn-link btn-sm p-0 mt-1">Resend Code</button>
                    </form>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="register.php">&larr; Back to Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const codeInput = document.querySelector('input[name="code"]');
        if (codeInput) {
            codeInput.addEventListener('input', function () {
                if (this.value.replace(/\D/g, '').length === 6) {
                    this.value = this.value.replace(/\D/g, '');
                    this.closest('form').submit();
                }
            });
        }
    </script>
</html>
