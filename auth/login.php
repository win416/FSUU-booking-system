<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

// Auto-migrate: add verification columns if they don't exist yet
(function () {
    $db = getDB();
    $db->query("ALTER TABLE `users` ADD COLUMN `is_verified`       TINYINT(1)   NOT NULL DEFAULT 0    AFTER `role`");
    $db->query("ALTER TABLE `users` ADD COLUMN `verification_code` VARCHAR(255) NULL     DEFAULT NULL AFTER `is_verified`");
    $db->query("ALTER TABLE `users` ADD COLUMN `code_expiry`       DATETIME     NULL     DEFAULT NULL AFTER `verification_code`");
    $db->query("UPDATE `users` SET `is_verified` = 1 WHERE `is_verified` = 0 AND `user_id` > 0");
})();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT user_id, fsuu_id, email, password, first_name, last_name, role, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {

                // Block unverified student accounts
                if (empty($user['is_verified']) && $user['role'] === 'student') {
                    // Re-issue a fresh OTP so they can verify
                    require_once '../includes/email_helper.php';
                    $code       = (string) random_int(100000, 999999);
                    $codeHash   = password_hash($code, PASSWORD_DEFAULT);
                    $codeExpiry = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
                    $upd = $db->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE user_id = ?");
                    $upd->bind_param("ssi", $codeHash, $codeExpiry, $user['user_id']);
                    $upd->execute();

                    $_SESSION['pending_verification'] = [
                        'user_id'    => $user['user_id'],
                        'email'      => $user['email'],
                        'first_name' => $user['first_name'],
                        'expires'    => time() + OTP_EXPIRY_MINUTES * 60,
                    ];
                    $_SESSION['otp_attempts'] = 0;

                    $emailError = '';
                    sendVerificationEmail($user['email'], $user['first_name'], $code, $emailError);

                    header('Location: verify-email.php');
                    exit();
                }

                SessionManager::setUser($user);
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header('Location: ' . SITE_URL . '/admin/dashboard.php');
                } else {
                    header('Location: ' . SITE_URL . '/patient/dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="stylesheet" href="../assets/css/auth-login.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header text-center">
                    <h4>FSUU Dental Clinic Login</h4>
                    <p class="mb-0">Access your account</p>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">
                            ✅ Email verified! Your account is active — you can now log in.
                        </div>
                    <?php elseif (isset($_GET['verified'])): ?>
                        <div class="alert alert-success">
                            Account already verified. Please log in.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-login w-100">Login</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>