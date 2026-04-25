<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();
$patient_id = intval($_GET['id'] ?? 0);

if (!$patient_id) {
    header('Location: patients.php');
    exit();
}

// Ensure profile_picture column exists
$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");

// Fetch patient + medical info
$stmt = $db->prepare("
    SELECT u.user_id, u.fsuu_id, u.first_name, u.last_name, u.email,
           u.contact_number, u.program, u.role, u.is_active, u.created_at,
           u.profile_picture,
           m.allergies, m.medical_conditions, m.medications,
           m.emergency_contact_name, m.emergency_contact_number
    FROM users u
    LEFT JOIN medical_info m ON m.user_id = u.user_id
    WHERE u.user_id = ? AND u.role IN ('student','staff')
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Appointment stats
$cnt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='approved') as approved,
        SUM(status='completed') as completed,
        SUM(status='cancelled') as cancelled
    FROM appointments WHERE user_id = ?
");
$cnt->bind_param("i", $patient_id);
$cnt->execute();
$stats = $cnt->get_result()->fetch_assoc();

// Recent appointments
$appts = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           a.status, a.notes, s.service_name
    FROM appointments a
    JOIN services s ON s.service_id = a.service_id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 20
");
$appts->bind_param("i", $patient_id);
$appts->execute();
$appointments = $appts->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> – Patient Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link href="../assets/css/admin-patient-profile.css" rel="stylesheet">
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
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
            <li class="nav-item"><a class="nav-link active" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
            <li class="nav-item"><a class="nav-link" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
            <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
        </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <?php include '../includes/admin-topbar.php'; ?>
        <div class="container-fluid my-4">

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></li>
                </ol>
            </nav>

            <!-- Alert placeholder -->
            <div id="alertBox"></div>

            <!-- Profile Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <?php
                        $picPath = !empty($patient['profile_picture'])
                            ? '../' . htmlspecialchars($patient['profile_picture'])
                            : null;
                        $initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
                        ?>
                        <div class="profile-pic-wrapper" title="Click to change photo">
                            <label for="picUploadInput" class="profile-pic-label">
                                <?php if ($picPath): ?>
                                    <img id="profilePicImg" src="<?php echo $picPath; ?>?v=<?php echo time(); ?>" alt="Profile Picture" class="profile-pic-img">
                                <?php else: ?>
                                    <div id="profilePicInitials" class="profile-avatar">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="profile-pic-overlay"><i class="bi bi-camera-fill"></i></div>
                            </label>
                            <input type="file" id="picUploadInput" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                            <div class="d-flex gap-3 flex-wrap text-muted small">
                                <span><i class="bi bi-card-text me-1"></i><?php echo htmlspecialchars($patient['fsuu_id']); ?></span>
                                <span><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($patient['email']); ?></span>
                                <span><i class="bi bi-phone me-1"></i><?php echo htmlspecialchars($patient['contact_number'] ?: 'N/A'); ?></span>
                                <span><i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($patient['program'] ?: 'N/A'); ?></span>
                                <span><i class="bi bi-person-badge me-1"></i><?php echo ucfirst($patient['role']); ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <span id="statusBadge" class="badge badge-status <?php echo $patient['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $patient['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPersonalModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="toggleStatusBtn">
                                <i class="bi bi-toggle-<?php echo $patient['is_active'] ? 'on' : 'off'; ?>"></i>
                                <?php echo $patient['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                <i class="bi bi-key"></i> Reset Password
                            </button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePatientModal">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Medical Information -->
                <div class="col-md-5">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <div class="section-header">
                                <h5 class="mb-0 text-primary"><i class="bi bi-heart-pulse me-2"></i>Medical Information</h5>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMedicalModal">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <div class="info-label">Allergies</div>
                                <div class="info-value" id="dispAllergies"><?php echo nl2br(htmlspecialchars($patient['allergies'] ?: '—')); ?></div>
                            </div>
                            <hr>
                            <div class="info-row">
                                <div class="info-label">Medical Conditions</div>
                                <div class="info-value" id="dispConditions"><?php echo nl2br(htmlspecialchars($patient['medical_conditions'] ?: '—')); ?></div>
                            </div>
                            <hr>
                            <div class="info-row">
                                <div class="info-label">Current Medications</div>
                                <div class="info-value" id="dispMedications"><?php echo nl2br(htmlspecialchars($patient['medications'] ?: '—')); ?></div>
                            </div>
                            <hr>
                            <div class="info-row">
                                <div class="info-label">Emergency Contact</div>
                                <div class="info-value" id="dispEmergencyName"><?php echo htmlspecialchars($patient['emergency_contact_name'] ?: '—'); ?></div>
                                <div class="info-value text-muted" id="dispEmergencyNumber"><?php echo htmlspecialchars($patient['emergency_contact_number'] ?: ''); ?></div>
                            </div>
                            <hr>
                            <div class="info-row">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($patient['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointment History -->
                <div class="col-md-7">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <div class="section-header">
                                <h5 class="mb-0 text-primary"><i class="bi bi-calendar2-week me-2"></i>Appointment History</h5>
                                <a href="appointments.php?search=<?php echo urlencode($patient['fsuu_id']); ?>" class="btn btn-sm btn-outline-secondary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($appointments->num_rows > 0): ?>
                                            <?php while ($appt = $appointments->fetch_assoc()):
                                                $badge = match($appt['status']) {
                                                    'pending'   => 'bg-warning text-dark',
                                                    'approved'  => 'bg-info text-dark',
                                                    'completed' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    default     => 'bg-secondary'
                                                };
                                            ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                                <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($appt['status']); ?></span></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No appointments found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /row -->

        </div><!-- /container -->
    </div><!-- /main-content -->
</div><!-- /dashboard-wrapper -->

<!-- ===== Edit Personal Info Modal ===== -->
<div class="modal fade" id="editPersonalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Personal Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="personalForm">
                <input type="hidden" name="action" value="update_personal">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">FSUU ID <span class="text-danger">*</span></label>
                            <input type="text" name="fsuu_id" class="form-control" value="<?php echo htmlspecialchars($patient['fsuu_id']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($patient['contact_number']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($patient['email']); ?>" disabled>
                            <small class="text-muted">Email cannot be changed.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Edit Medical Info Modal ===== -->
<div class="modal fade" id="editMedicalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Medical Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="medicalForm">
                <input type="hidden" name="action" value="update_medical">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Allergies</label>
                            <textarea name="allergies" class="form-control" rows="3"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Medical Conditions</label>
                            <textarea name="medical_conditions" class="form-control" rows="3"><?php echo htmlspecialchars($patient['medical_conditions'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Current Medications</label>
                            <textarea name="medications" class="form-control" rows="2"><?php echo htmlspecialchars($patient['medications'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" class="form-control" value="<?php echo htmlspecialchars($patient['emergency_contact_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Reset Password Modal ===== -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Patient Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resetPwForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" id="newPwInput" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" id="confirmPwInput" class="form-control" minlength="8" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Delete Patient Modal ===== -->
<div class="modal fade" id="deletePatientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Patient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold mb-3">⚠️ This action cannot be undone!</p>
                <p class="mb-2">Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>?</p>
                <p class="text-muted small mb-3">All associated data including medical information, appointments, and records will be removed from the system.</p>
                <div class="mb-3">
                    <label class="form-label">Type the patient's name to confirm:</label>
                    <input type="text" id="deleteConfirmName" class="form-control" placeholder="Enter: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    <i class="bi bi-trash me-1"></i>Delete Patient
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const API = '../api/admin-patient.php';
const patientId = <?php echo $patient_id; ?>;

function showAlert(msg, type = 'success') {
    $('#alertBox').html(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`);
    $('html, body').animate({ scrollTop: 0 }, 300);
}

// Generic AJAX form submit
function handleForm(formId, modalId, onSuccess) {
    $(formId).on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('[type=submit]');
        btn.prop('disabled', true).text('Saving…');
        $.post(API, $(this).serialize(), function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.querySelector(modalId)).hide();
                showAlert(res.message);
                if (onSuccess) onSuccess(res);
            } else {
                showAlert(res.message, 'danger');
            }
        }, 'json').fail(function() {
            showAlert('Server error. Please try again.', 'danger');
        }).always(function() {
            btn.prop('disabled', false).text('Save Changes');
        });
    });
}

// Personal info
handleForm('#personalForm', '#editPersonalModal', function() { location.reload(); });

// Medical info – update display values without reload
$('#medicalForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('[type=submit]');
    btn.prop('disabled', true).text('Saving…');
    $.post(API, $(this).serialize(), function(res) {
        if (res.success) {
            bootstrap.Modal.getInstance(document.querySelector('#editMedicalModal')).hide();
            showAlert(res.message);
            const f = $('#medicalForm');
            $('#dispAllergies').text(f.find('[name=allergies]').val() || '—');
            $('#dispConditions').text(f.find('[name=medical_conditions]').val() || '—');
            $('#dispMedications').text(f.find('[name=medications]').val() || '—');
            $('#dispEmergencyName').text(f.find('[name=emergency_contact_name]').val() || '—');
            $('#dispEmergencyNumber').text(f.find('[name=emergency_contact_number]').val() || '');
        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json').fail(function() {
        showAlert('Server error. Please try again.', 'danger');
    }).always(function() { btn.prop('disabled', false).text('Save Changes'); });
});

// Reset password
$('#resetPwForm').on('submit', function(e) {
    e.preventDefault();
    if ($('#newPwInput').val() !== $('#confirmPwInput').val()) {
        showAlert('Passwords do not match', 'danger'); return;
    }
    const btn = $(this).find('[type=submit]');
    btn.prop('disabled', true).text('Resetting…');
    $.post(API, $(this).serialize(), function(res) {
        if (res.success) {
            bootstrap.Modal.getInstance(document.querySelector('#resetPasswordModal')).hide();
            showAlert(res.message);
            $('#resetPwForm')[0].reset();
        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json').fail(function() {
        showAlert('Server error. Please try again.', 'danger');
    }).always(function() { btn.prop('disabled', false).text('Reset Password'); });
});

// Toggle status
$('#toggleStatusBtn').on('click', function() {
    const btn = $(this);
    btn.prop('disabled', true);
    $.post(API, { action: 'toggle_status', patient_id: patientId }, function(res) {
        if (res.success) {
            const active = res.is_active;
            $('#statusBadge')
                .removeClass('bg-success bg-danger')
                .addClass(active ? 'bg-success' : 'bg-danger')
                .text(active ? 'Active' : 'Inactive');
            btn.html(`<i class="bi bi-toggle-${active ? 'on' : 'off'}"></i> ${active ? 'Deactivate' : 'Activate'}`);
            showAlert(res.message);
        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json').fail(function() {
        showAlert('Server error. Please try again.', 'danger');
    }).always(function() { btn.prop('disabled', false); });
});

// Profile picture upload
$('#picUploadInput').on('change', function() {
    const file = this.files[0];
    if (!file) return;
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showAlert('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.', 'danger'); return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showAlert('File too large. Maximum 2 MB.', 'danger'); return;
    }
    const fd = new FormData();
    fd.append('profile_picture', file);
    fd.append('patient_id', patientId);
    $.ajax({
        url: '../api/upload-profile-picture.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                showAlert(res.message);
                const wrapper = $('.profile-pic-wrapper');
                wrapper.find('#profilePicInitials').remove();
                let img = wrapper.find('#profilePicImg');
                if (img.length) {
                    img.attr('src', '../' + res.path);
                } else {
                    wrapper.find('.profile-pic-label').prepend(
                        `<img id="profilePicImg" src="../${res.path}" alt="Profile Picture" class="profile-pic-img">`
                    );
                }
            } else {
                showAlert(res.message, 'danger');
            }
        },
        error: function() { showAlert('Upload failed. Please try again.', 'danger'); }
    });
});

// Delete patient confirmation
const patientFullName = '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>';
$('#deleteConfirmName').on('input', function() {
    $('#confirmDeleteBtn').prop('disabled', $(this).val().trim() !== patientFullName);
});

$('#confirmDeleteBtn').on('click', function() {
    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Deleting…');
    $.post(API, { action: 'delete_patient', patient_id: patientId }, function(res) {
        if (res.success) {
            showAlert(res.message, 'success');
            setTimeout(() => { window.location.href = 'patients.php'; }, 1500);
        } else {
            showAlert(res.message, 'danger');
            btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Delete Patient');
        }
    }, 'json').fail(function() {
        showAlert('Server error. Please try again.', 'danger');
        btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Delete Patient');
    });
});
</script>
</body>
</html>
