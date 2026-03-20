<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
$user = SessionManager::getUser();
$db = getDB();

// Ensure profile_picture column exists (safe on MySQL 8+ and MariaDB 10.3+)
$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");

// Fetch fresh profile_picture from DB
$profilePic = null;
$picStmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
if ($picStmt) {
    $picStmt->bind_param("i", $user['user_id']);
    $picStmt->execute();
    $picRow = $picStmt->get_result()->fetch_assoc();
    $profilePic = $picRow['profile_picture'] ?? null;
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
                FSUU Dental
            </div>
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
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a>
                </li>
            </ul>
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
                                <div class="mt-2">
                                    <label for="picUploadInput" class="btn btn-sm btn-outline-primary">
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
                                <h5 class="mb-0 text-primary">Personal Information</h5>
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
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0 text-primary">Security</h5>
                            </div>
                            <div class="card-body">
                                <form id="securityForm">
                                    <input type="hidden" name="action" value="update_password">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning">Update Password</button>
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
