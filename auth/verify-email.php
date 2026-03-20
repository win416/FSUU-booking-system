<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/email_helper.php';

// Must have a pending verification session
if (empty($_SESSION['pending_verification'])) {
    header('Location: register.php');
    exit();
}

$pv        = &$_SESSION['pending_verification'];
$userId    = (int) $pv['user_id'];
$email     = $pv['email'];
$firstName = $pv['first_name'];

// Mask email: john@urios.edu.ph → j***@urios.edu.ph
$atPos       = strpos($email, '@');
$maskedEmail = substr($email, 0, 1) . str_repeat('*', max(1, $atPos - 1)) . substr($email, $atPos);

$error   = '';
$info    = '';
$success = '';

// Show email-send-failed notice from register
if (!empty($_SESSION['email_send_failed'])) {
    $info = 'We couldn\'t deliver your verification email. Please use the <strong>Resend Code</strong> button below.';
    unset($_SESSION['email_send_failed']);
}
if (isset($_GET['resent'])) {
    $info = 'A new verification code has been sent to <strong>' . htmlspecialchars($maskedEmail) . '</strong>.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the 6 digits
    $code = '';
    for ($i = 1; $i <= 6; $i++) {
        $code .= preg_replace('/\D/', '', $_POST['d' . $i] ?? '');
    }

    if (strlen($code) !== 6) {
        $error = 'Please enter all 6 digits of the verification code.';
    } else {
        // Rate-limit wrong attempts
        if (!isset($_SESSION['otp_attempts'])) {
            $_SESSION['otp_attempts'] = 0;
        }
        if ($_SESSION['otp_attempts'] >= OTP_MAX_ATTEMPTS) {
            $error = 'Too many incorrect attempts. Please request a new code.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT verification_code, code_expiry FROM users WHERE user_id = ? AND is_verified = 0"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                // Already verified or user not found — redirect to login
                unset($_SESSION['pending_verification'], $_SESSION['otp_attempts']);
                header('Location: login.php?verified=1');
                exit();
            }

            if (strtotime($row['code_expiry']) < time()) {
                $error = 'This code has expired. Please request a new one.';
            } elseif (!password_verify($code, $row['verification_code'])) {
                $_SESSION['otp_attempts']++;
                $remaining = OTP_MAX_ATTEMPTS - $_SESSION['otp_attempts'];
                $error = 'Incorrect code.' . ($remaining > 0 ? " $remaining attempt(s) remaining." : ' Use <strong>Resend Code</strong>.');
            } else {
                // ✅ Code is correct — activate account
                $db->query(
                    "UPDATE users SET is_verified = 1, verification_code = NULL, code_expiry = NULL WHERE user_id = $userId"
                );
                unset($_SESSION['pending_verification'], $_SESSION['otp_attempts']);
                header('Location: login.php?registered=1');
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="stylesheet" href="../assets/css/auth-verify.css">
</head>
<body>
<div class="container">
    <div class="verify-container">
        <div class="card">
            <div class="card-header text-center">
                <div class="verify-icon mb-3">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                         xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6Z"
                              stroke="#00aeef" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 6L12 13L2 6" stroke="#00aeef" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h4>Check Your Email</h4>
                <p class="mb-0">
                    We sent a 6-digit code to<br>
                    <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>
                </p>
            </div>

            <div class="card-body p-4">

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($info): ?>
                    <div class="alert alert-info"><?php echo $info; ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="otpForm" autocomplete="off">
                    <p class="otp-label">Enter verification code</p>

                    <div class="otp-group mb-4" id="otpGroup">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" name="d<?php echo $i; ?>"
                                   id="d<?php echo $i; ?>"
                                   class="otp-box form-control"
                                   maxlength="1" inputmode="numeric"
                                   pattern="[0-9]" required
                                   autocomplete="off">
                        <?php endfor; ?>
                    </div>

                    <button type="submit" class="btn btn-verify w-100">Verify Email</button>
                </form>

                <div class="text-center mt-3 resend-section">
                    <p class="text-muted mb-1" style="font-size:.875rem;">
                        Didn't receive the code?
                    </p>
                    <a href="resend-code.php" id="resendLink" class="resend-link">
                        Resend Code
                    </a>
                    <span id="resendTimer" class="text-muted" style="font-size:.8rem;display:none;">
                        (wait <span id="timerCount">60</span>s)
                    </span>
                </div>

                <div class="text-center mt-3">
                    <a href="register.php" class="back-link">← Back to Register</a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Auto-advance OTP inputs
(function () {
    const boxes = document.querySelectorAll('.otp-box');

    boxes.forEach((box, idx) => {
        box.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(-1);
            if (this.value && idx < boxes.length - 1) {
                boxes[idx + 1].focus();
            }
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                boxes[idx - 1].focus();
            }
            // Allow Ctrl+V paste on first box
            if ((e.ctrlKey || e.metaKey) && e.key === 'v' && idx === 0) {
                return;
            }
        });

        box.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            boxes.forEach((b, i) => { b.value = pasted[i] || ''; });
            const lastFilled = Math.min(pasted.length, boxes.length) - 1;
            if (lastFilled >= 0) boxes[lastFilled].focus();
        });
    });

    // Focus first empty box on load
    const first = Array.from(boxes).find(b => !b.value);
    if (first) first.focus();
})();

// Resend cooldown (60s after page load)
(function () {
    const link  = document.getElementById('resendLink');
    const timer = document.getElementById('resendTimer');
    const count = document.getElementById('timerCount');

    <?php if (isset($_GET['resent'])): ?>
    let seconds = 60;
    link.style.pointerEvents = 'none';
    link.style.opacity = '0.4';
    timer.style.display = 'inline';

    const iv = setInterval(() => {
        count.textContent = --seconds;
        if (seconds <= 0) {
            clearInterval(iv);
            link.style.pointerEvents = '';
            link.style.opacity = '';
            timer.style.display = 'none';
        }
    }, 1000);
    <?php endif; ?>
})();
</script>
</body>
</html>
