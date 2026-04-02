<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
$user = SessionManager::getUser();
$db = getDB();

// Ensure profile_picture column exists (safe on MySQL 8+ and MariaDB 10.3+)
$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");

// Always fetch fresh user data from DB (not stale session)
$profilePic = null;
$hasPassword = false;
$freshStmt = $db->prepare("SELECT first_name, last_name, contact_number, email, role, profile_picture, password FROM users WHERE user_id = ?");
if ($freshStmt) {
    $freshStmt->bind_param("i", $user['user_id']);
    $freshStmt->execute();
    $freshRow = $freshStmt->get_result()->fetch_assoc();
    // Merge fresh DB data into $user so the form fields always reflect DB truth
    $user['first_name']      = $freshRow['first_name']      ?? $user['first_name'];
    $user['last_name']       = $freshRow['last_name']       ?? $user['last_name'];
    $user['contact_number']  = $freshRow['contact_number']  ?? $user['contact_number'];
    $user['email']           = $freshRow['email']           ?? $user['email'];
    $user['role']            = $freshRow['role']            ?? $user['role'];
    $profilePic  = $freshRow['profile_picture'] ?? null;
    $hasPassword = !empty($freshRow['password']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-profile.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental Clinic
            </div>
            <div class="sidebar-nav-wrap">
            <div class="sidebar-section-label">Menu</div>
            <ul class="sidebar-nav">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a></li>
                <li class="nav-item"><a class="nav-link" href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php
                        $unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $unread_stmt->bind_param("i", $user['user_id']);
                        $unread_stmt->execute();
                        $unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
                        if ($unread_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
            </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>
            <div class="container-fluid my-4">
                <h2 class="mb-4">Profile Settings</h2>

                <!-- Profile Picture Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <?php
                            $initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1));
                            $picSrc   = !empty($profilePic) ? '../' . htmlspecialchars($profilePic) : null;
                            ?>
                            <div class="profile-pic-wrapper" title="Click to change photo">
                                <label for="picUploadInput" class="profile-pic-label">
                                    <?php if ($picSrc): ?>
                                        <img id="profilePicImg" src="<?php echo $picSrc; ?>?v=<?php echo time(); ?>" alt="Profile Picture" class="profile-pic-img">
                                    <?php else: ?>
                                        <div id="profilePicInitials" class="profile-avatar-initials">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="profile-pic-overlay"><i class="bi bi-camera-fill"></i></div>
                                </label>
                                <input type="file" id="picUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                                <div class="my-2">
                                    <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#29ABE2;color:#fff;font-size:0.75rem;font-weight:600;padding:0.2rem 0.65rem;border-radius:20px;text-transform:uppercase;letter-spacing:0.05em;">
                                        <i class="bi bi-person-fill"></i>
                                        <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Student')); ?>
                                    </span>
                                </div>
                                <div class="mt-1">
                                    <label for="picUploadInput" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-upload"></i> Upload Photo
                                    </label>
                                    <small class="text-muted ms-2">JPG, PNG, GIF or WEBP · Max 2 MB</small>
                                </div>
                                <div id="uploadAlert" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0 fw-bold">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form id="personalInfoForm">
                                    <input type="hidden" name="action" value="update_personal">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">First Name</label>
                                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly disabled>
                                        <small class="text-muted">Email cannot be changed.</small>
                                    </div>
                                    <button type="submit" class="btn" style="background:#29ABE2;color:#fff;border-color:#1C9DD6;">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0 fw-bold">Security</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$hasPassword): ?>
                                    <div class="alert alert-info py-2">
                                        <i class="bi bi-google me-1"></i> Your account uses Google Sign-in. Set a password below to also enable manual login.
                                    </div>
                                <?php endif; ?>
                                <form id="securityForm">
                                    <input type="hidden" name="action" value="update_password">
                                    <?php if ($hasPassword): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <?php else: ?>
                                    <input type="hidden" name="current_password" value="">
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $hasPassword ? 'New Password' : 'Set Password'; ?></label>
                                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm <?php echo $hasPassword ? 'New ' : ''; ?>Password</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                    </div>
                                    <button type="submit" class="btn" style="background:#29ABE2;color:#fff;border-color:#1C9DD6;"><?php echo $hasPassword ? 'Update Password' : 'Set Password'; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {

        // Profile picture upload
        $('#picUploadInput').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowed.includes(file.type)) {
                showUploadAlert('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.', 'danger'); return;
            }
            if (file.size > 2 * 1024 * 1024) {
                showUploadAlert('File too large. Maximum 2 MB.', 'danger'); return;
            }
            const fd = new FormData();
            fd.append('profile_picture', file);
            $.ajax({
                url: '../api/upload-profile-picture.php',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        showUploadAlert(res.message, 'success');
                        $('#profilePicInitials').remove();
                        let img = $('#profilePicImg');
                        if (img.length) {
                            img.attr('src', '../' + res.path);
                        } else {
                            $('.profile-pic-label').prepend(
                                `<img id="profilePicImg" src="../${res.path}" alt="Profile Picture" class="profile-pic-img">`
                            );
                        }
                    } else {
                        showUploadAlert(res.message, 'danger');
                    }
                },
                error: function() { showUploadAlert('Upload failed. Please try again.', 'danger'); }
            });
        });

        function showUploadAlert(msg, type) {
            $('#uploadAlert').html(
                `<div class="alert alert-${type} alert-dismissible py-1 px-2 mb-0 small">
                    ${msg}
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                </div>`
            );
        }

        // Existing form submit handler
        $('form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnText = submitBtn.text();

            if (form.attr('id') === 'securityForm') {
                if ($('input[name="new_password"]').val() !== $('input[name="confirm_password"]').val()) {
                    alert('New passwords do not match');
                    return;
                }
            }

            submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: '../api/update-profile.php',
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message || 'Updated successfully!');
                        if (form.attr('id') === 'securityForm') form[0].reset();
                        if (response.refresh) location.reload();
                    } else {
                        alert(response.message || 'An error occurred.');
                    }
                },
                error: function() {
                    alert('Server error occurred. Please try again.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(originalBtnText);
                }
            });
        });
    });
    </script>
</body>
</html>
