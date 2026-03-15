<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fsuu_id = trim($_POST['fsuu_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact = trim($_POST['contact_number']);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $db = getDB();
        
        // Check if FSUU ID or email already exists
        $check = $db->prepare("SELECT user_id FROM users WHERE fsuu_id = ? OR email = ?");
        $check->bind_param("ss", $fsuu_id, $email);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'FSUU ID or Email already registered';
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert = $db->prepare("INSERT INTO users (fsuu_id, email, password, first_name, last_name, contact_number, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
            $insert->bind_param("ssssss", $fsuu_id, $email, $hashed_password, $first_name, $last_name, $contact);
            
            if ($insert->execute()) {
                $user_id = $db->insert_id;
                
                // Create empty medical info
                $medical = $db->prepare("INSERT INTO medical_info (user_id) VALUES (?)");
                $medical->bind_param("i", $user_id);
                $medical->execute();
                
                $success = 'Registration successful! You can now login.';
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
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .register-container { max-width: 500px; margin: 50px auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; padding: 20px; }
        .btn-register { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px; }
        .btn-register:hover { opacity: 0.9; color: white; }
    </style>
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
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
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
                            <small class="text-muted">Use your FSUU email</small>
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