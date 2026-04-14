<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/settings.php');
    } else {
        header('Location: ' . SITE_URL . '/patient/profile.php');
    }
    exit();
}

$db = getDB();
$user = SessionManager::getUser();
$dentist_id = (int)$user['user_id'];

$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_profiles (
        dentist_id INT(11) NOT NULL,
        specialization VARCHAR(150) DEFAULT NULL,
        digital_signature_path VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (dentist_id),
        CONSTRAINT fk_dentist_profile_user FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$stmt = $db->prepare("SELECT specialization FROM dentist_profiles WHERE dentist_id = ?");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: ['specialization' => ''];

$freshStmt = $db->prepare("SELECT first_name, last_name, contact_number, email, profile_picture, password FROM users WHERE user_id = ?");
$freshStmt->bind_param("i", $dentist_id);
$freshStmt->execute();
$fresh = $freshStmt->get_result()->fetch_assoc() ?: [];
$fullName = trim(($fresh['first_name'] ?? $user['first_name'] ?? '') . ' ' . ($fresh['last_name'] ?? $user['last_name'] ?? ''));
$profilePic = $fresh['profile_picture'] ?? ($user['profile_picture'] ?? null);
$initials = strtoupper(substr($fresh['first_name'] ?? $user['first_name'] ?? 'D', 0, 1) . substr($fresh['last_name'] ?? $user['last_name'] ?? 'D', 0, 1));
$hasPassword = !empty($fresh['password']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Profile - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-settings.css" rel="stylesheet">
    <link href="../assets/css/dentist-dashboard.css" rel="stylesheet">
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
            <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li class="nav-item"><a class="nav-link" href="my-schedule.php"><i class="bi bi-clock"></i> My Schedule</a></li>
            <li class="nav-item"><a class="nav-link" href="my-patients.php"><i class="bi bi-people"></i> My Patients</a></li>
            <li class="nav-item"><a class="nav-link" href="notifications.php"><i class="bi bi-bell"></i> Notifications <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link active" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
        </ul>
        </div>
    </nav>

    <div class="main-content">
        <?php include '../includes/dentist-topbar.php'; ?>
        <div class="container-fluid my-4">
            <h2 class="mb-4">Profile Settings</h2>
            <div id="alertContainer" class="mb-3"></div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="profile-pic-wrapper" title="Click to change photo">
                            <label for="picUploadInput" class="profile-pic-label">
                                <?php if (!empty($profilePic)): ?>
                                    <img id="profilePicImg" src="../<?php echo htmlspecialchars($profilePic); ?>?v=<?php echo time(); ?>" alt="Profile Picture" class="profile-pic-img">
                                <?php else: ?>
                                    <div id="profilePicInitials" class="profile-avatar-initials"><?php echo $initials; ?></div>
                                <?php endif; ?>
                                <div class="profile-pic-overlay"><i class="bi bi-camera-fill"></i></div>
                            </label>
                            <input type="file" id="picUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($fullName); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($fresh['email'] ?? $user['email'] ?? ''); ?></small>
                            <div class="my-2">
                                <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#29ABE2;color:#fff;font-size:0.75rem;font-weight:600;padding:0.2rem 0.65rem;border-radius:20px;text-transform:uppercase;letter-spacing:0.05em;">
                                    <i class="bi bi-person-fill"></i> Dentist
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

            <div class="row g-4 mb-1">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div id="personalInfoAlert"></div>
                            <form id="personalInfoForm">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($fresh['first_name'] ?? $user['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($fresh['last_name'] ?? $user['last_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($fresh['contact_number'] ?? $user['contact_number'] ?? ''); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <div id="passwordAlert"></div>
                            <?php if (!$hasPassword): ?>
                                <div class="alert alert-info py-2">
                                    <i class="bi bi-google me-1"></i> Your account uses Google Sign-in. Set a password below to also enable manual login.
                                </div>
                            <?php endif; ?>
                            <form id="changePasswordForm">
                                <?php if ($hasPassword): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <?php else: ?>
                                    <input type="hidden" name="current_password" value="">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><?php echo $hasPassword ? 'New Password' : 'Set Password'; ?></label>
                                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Confirm <?php echo $hasPassword ? 'New ' : ''; ?>Password</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-key-fill me-1"></i> <?php echo $hasPassword ? 'Update Password' : 'Set Password'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-award me-2"></i>Credentials</h5>
                </div>
                <div class="card-body">
                    <form id="specializationForm" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" id="specializationInput" value="<?php echo htmlspecialchars($profile['specialization'] ?? ''); ?>" placeholder="e.g., Orthodontics, General Dentistry" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Credentials</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function showAlert(type, message) {
    $('#alertContainer').html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
}

$('#picUploadInput').on('change', function() {
    const file = this.files[0];
    if (!file) return;
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showUploadAlert('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.', 'danger');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showUploadAlert('File too large. Maximum 2 MB.', 'danger');
        return;
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
                    img.attr('src', '../' + res.path + '?v=' + Date.now());
                } else {
                    $('.profile-pic-label').prepend(`<img id="profilePicImg" src="../${res.path}?v=${Date.now()}" alt="Profile Picture" class="profile-pic-img">`);
                }
            } else {
                showUploadAlert(res.message || 'Upload failed', 'danger');
            }
        },
        error: function() { showUploadAlert('Upload failed. Please try again.', 'danger'); }
    });
});

function showUploadAlert(msg, type) {
    $('#uploadAlert').html(`<div class="alert alert-${type} alert-dismissible py-1 px-2 mb-0 small">${msg}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`);
}

function showInlineAlert(selector, type, message) {
    $(selector).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
}

$('#personalInfoForm').on('submit', function(e) {
    e.preventDefault();
    $.post('../api/update-profile.php', $(this).serialize() + '&action=update_personal', function(res) {
        if (res.success) {
            showInlineAlert('#personalInfoAlert', 'success', res.message || 'Personal information updated successfully.');
            setTimeout(() => location.reload(), 500);
        } else {
            showInlineAlert('#personalInfoAlert', 'danger', res.message || 'Failed to update personal information.');
        }
    }, 'json').fail(() => showInlineAlert('#personalInfoAlert', 'danger', 'Request failed.'));
});

$('#changePasswordForm').on('submit', function(e) {
    e.preventDefault();
    const newPwd = $('input[name="new_password"]', this).val();
    const confirmPwd = $('input[name="confirm_password"]', this).val();
    if (newPwd !== confirmPwd) {
        showInlineAlert('#passwordAlert', 'danger', 'New passwords do not match.');
        return;
    }
    $.post('../api/update-profile.php', $(this).serialize() + '&action=update_password', function(res) {
        showInlineAlert('#passwordAlert', res.success ? 'success' : 'danger', res.message);
        if (res.success) $('#changePasswordForm')[0].reset();
    }, 'json').fail(() => showInlineAlert('#passwordAlert', 'danger', 'Request failed.'));
});

$('#specializationForm').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'save_specialization');
    fd.append('specialization', $('#specializationInput').val().trim());
    fetch('../api/dentist-profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) showAlert('success', '<i class="bi bi-check-circle me-1"></i>' + res.message);
            else showAlert('danger', res.message || 'Failed to save specialization.');
        })
        .catch(() => showAlert('danger', 'Server error while saving specialization.'));
});

</script>
</body>
</html>
