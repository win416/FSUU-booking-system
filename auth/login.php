<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT user_id, fsuu_id, email, password, first_name, last_name, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Account was created via Google (no password set)
            if (empty($user['password'])) {
                $error = 'This account uses Google Sign-in. <a href="google_auth.php?action=redirect">Sign in with Google</a>, then set a password in your Profile to enable manual login.';
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

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1"><span class="px-2 text-muted small">or</span><hr class="flex-grow-1">
                    </div>

                    <a href="google_auth.php?action=redirect" class="btn w-100 d-flex align-items-center justify-content-center gap-2" style="border:1px solid #dadce0;border-radius:8px;padding:10px;font-weight:500;color:#3c4043;background:#fff;text-decoration:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                        Sign in with Google
                    </a>
                    
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