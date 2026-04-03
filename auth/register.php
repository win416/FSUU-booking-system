<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = getDB(); // Initialize database connection
    
    $fsuu_id = trim($_POST['fsuu_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact = trim($_POST['contact_number']);
    $program = trim($_POST['program'] ?? '');

    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (!str_ends_with($email, '@urios.edu.ph')) {
        $error = 'Only @urios.edu.ph email addresses are allowed';
    } elseif (empty($program)) {
        $error = 'Please select your program';
    } else {
        // Check if FSUU ID or email already exists
        $check = $db->prepare("SELECT user_id FROM users WHERE fsuu_id = ? OR email = ?");
        $check->bind_param("ss", $fsuu_id, $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = 'FSUU ID or Email already registered';
        } else {
            // 1. Setup Data
            $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 2. INSERT User
            $insert = $db->prepare(
                "INSERT INTO users (fsuu_id, email, password, first_name, last_name, contact_number, program, role, verification_code, is_verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, 0)"
            );
            $insert->bind_param("ssssssss", $fsuu_id, $email, $hashed_password, $first_name, $last_name, $contact, $program, $verification_code);

            if ($insert->execute()) {
                $user_id = $db->insert_id;

                // Create empty medical info record
                $medical = $db->prepare("INSERT INTO medical_info (user_id) VALUES (?)");
                $medical->bind_param("i", $user_id);
                $medical->execute();

                // 3. Try Email
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = SMTP_PORT;

                    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
                    $mail->addAddress($email, $first_name . ' ' . $last_name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your FSUU Dental Clinic Verification Code';
                    $mail->Body = "
                        <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;'>
                            <h2 style='color:#2c6fad;'>FSUU Dental Clinic</h2>
                            <p>Hello, <strong>{$first_name}</strong>!</p>
                            <p>Use the verification code below to complete your registration:</p>
                            <div style='font-size:36px;font-weight:bold;letter-spacing:8px;text-align:center;padding:16px 0;color:#2c6fad;'>{$verification_code}</div>
                            <p style='color:#888;font-size:13px;'>This code expires in " . OTP_EXPIRY_MINUTES . " minutes.</p>
                        </div>";
                    
                    $mail->send();
                } catch (Exception $e) {
                    // Silently continue so user can still reach verify.php
                }

                header('Location: verify.php?email=' . urlencode($email));
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Register - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="stylesheet" href="../assets/css/auth-register.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card">
                <div class="card-header text-center">
                    <h4>FSUU Dental Clinic Registration</h4>
                    <p class="mb-0">Create your patient account</p>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>FSUU ID Number</label>
                            <input type="text" name="fsuu_id" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                            <small class="text-muted">Use your URIOS email (@urios.edu.ph)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Program</label>
                            <select name="program" class="form-control" required>
                                <option value="" disabled selected>— Select Program —</option>
                                <option value="AP">AP</option>
                                <option value="ASP">ASP</option>
                                <option value="BAP">BAP</option>
                                <option value="CJEP">CJEP</option>
                                <option value="CSP">CSP</option>
                                <option value="ETP">ETP</option>
                                <option value="GSR">GSR</option>
                                <option value="LAW">LAW</option>
                                <option value="TEP">TEP</option>
                                <option value="THMP">THMP</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label>Password</label>
                            <div class="position-relative">
                                <input type="password" id="password" name="password" class="form-control" style="padding-right: 2.5rem;" required>
                                <i class="bi bi-eye toggle-password" data-target="password" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6c757d;font-size:1.1rem;"></i>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Confirm Password</label>
                            <div class="position-relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" style="padding-right: 2.5rem;" required>
                                <i class="bi bi-eye toggle-password" data-target="confirm_password" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6c757d;font-size:1.1rem;"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-register w-100">Register</button>
                    </form>

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1"><span class="px-2 text-muted small">or</span><hr class="flex-grow-1">
                    </div>

                    <a href="google_auth.php?action=redirect" class="btn w-100 d-flex align-items-center justify-content-center gap-2" style="border:1px solid #dadce0;border-radius:8px;padding:10px;font-weight:500;color:#3c4043;background:#fff;text-decoration:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                        Sign up with Google
                    </a>
                    
                    <div class="text-center mt-3">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.toggle-password').forEach(function(icon) {
            icon.addEventListener('click', function() {
                var input = document.getElementById(this.dataset.target);
                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    this.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });
    </script>
</body>
</html>