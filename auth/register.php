<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/email_helper.php';

// Auto-migrate: add verification columns if they don't exist yet
(function () {
    $db = getDB();
    $migrations = [
        "ALTER TABLE `users` ADD COLUMN `is_verified`       TINYINT(1)   NOT NULL DEFAULT 0    AFTER `role`",
        "ALTER TABLE `users` ADD COLUMN `verification_code` VARCHAR(255) NULL     DEFAULT NULL AFTER `is_verified`",
        "ALTER TABLE `users` ADD COLUMN `code_expiry`       DATETIME     NULL     DEFAULT NULL AFTER `verification_code`",
    ];
    foreach ($migrations as $sql) {
        $db->query($sql); // errno 1060 = duplicate column — silently ignored
    }
    // All pre-existing accounts are treated as already verified
    $db->query("UPDATE `users` SET `is_verified` = 1 WHERE `is_verified` = 0 AND `user_id` > 0");
})();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fsuu_id  = trim($_POST['fsuu_id']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $first    = trim($_POST['first_name']);
    $last     = trim($_POST['last_name']);
    $contact  = trim($_POST['contact_number']);

    // Validation
    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $db = getDB();

        $check = $db->prepare("SELECT user_id FROM users WHERE fsuu_id = ? OR email = ?");
        $check->bind_param("ss", $fsuu_id, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'FSUU ID or Email is already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $db->prepare(
                "INSERT INTO users (fsuu_id, email, password, first_name, last_name, contact_number, role, is_verified)
                 VALUES (?, ?, ?, ?, ?, ?, 'student', 0)"
            );
            $insert->bind_param("ssssss", $fsuu_id, $email, $hashed, $first, $last, $contact);

            if ($insert->execute()) {
                $user_id = $db->insert_id;

                // Create empty medical info record
                $med = $db->prepare("INSERT INTO medical_info (user_id) VALUES (?)");
                $med->bind_param("i", $user_id);
                $med->execute();

                // Generate 6-digit OTP
                $code        = (string) random_int(100000, 999999);
                $codeHash    = password_hash($code, PASSWORD_DEFAULT);
                $codeExpiry  = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

                $upd = $db->prepare(
                    "UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?"
                );
                $upd->bind_param("ssi", $codeHash, $codeExpiry, $user_id);
                $upd->execute();

                // Store pending session
                $_SESSION['pending_verification'] = [
                    'user_id'    => $user_id,
                    'email'      => $email,
                    'first_name' => $first,
                    'expires'    => time() + OTP_EXPIRY_MINUTES * 60,
                ];
                $_SESSION['otp_attempts'] = 0;

                // Send verification email
                $emailError = '';
                $sent = sendVerificationEmail($email, $first, $code, $emailError);

                if (!$sent) {
                    // Email failed, but account is created — user can resend from verify page
                    $_SESSION['email_send_failed'] = true;
                }

                header('Location: verify-email.php');
                exit();
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
                            <small class="text-muted">Use your URIOS/FSUU email (e.g. <em>yourname@urios.edu.ph</em>)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-register w-100">Register</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>