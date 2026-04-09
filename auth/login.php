<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

$error = '';
$unverified_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT user_id, fsuu_id, email, password, first_name, last_name, role, contact_number, profile_picture, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Account was created via Google (no password set)
            if (empty($user['password'])) {
                $error = 'This account uses Google Sign-in. <a href="google_auth.php?action=redirect">Sign in with Google</a>, then set a password in your Profile to enable manual login.';
            } elseif (!$user['is_verified']) {
                $unverified_email = $email;
            } elseif (password_verify($password, $user['password'])) {
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

                    <?php if($unverified_email): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-3" style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px 16px;">
                            <span style="font-size:1.5rem;line-height:1;">⚠️</span>
                            <div>
                                <div style="font-weight:600;color:#7b5800;margin-bottom:2px;">Email Not Verified</div>
                                <div style="font-size:0.875rem;color:#5d4037;">
                                    Please verify your email address before logging in.
                                </div>
                                <a href="verify.php?email=<?php echo urlencode($unverified_email); ?>"
                                   class="btn btn-sm mt-2"
                                   style="background:#f59e0b;color:#fff;border:none;border-radius:6px;padding:5px 16px;font-weight:500;font-size:0.82rem;">
                                    ✉️ Verify My Email
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['verified'])): ?>
                        <div class="alert alert-success">✅ Email verified! You can now log in.</div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Password</label>
                            <div class="position-relative">
                                <input type="password" id="password" name="password" class="form-control" style="padding-right: 2.5rem;" required>
                                <i class="bi bi-eye toggle-password" data-target="password" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6c757d;font-size:1.1rem;"></i>
                            </div>
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