<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();
$currentUser = SessionManager::getUser();

// Always fetch fresh user data from DB
$freshStmt = $db->prepare("SELECT first_name, last_name, contact_number, email FROM users WHERE user_id = ?");
if ($freshStmt) {
    $freshStmt->bind_param("i", $currentUser['user_id']);
    $freshStmt->execute();
    $freshRow = $freshStmt->get_result()->fetch_assoc();
    if ($freshRow) {
        $currentUser['first_name']     = $freshRow['first_name'];
        $currentUser['last_name']      = $freshRow['last_name'];
        $currentUser['contact_number'] = $freshRow['contact_number'];
        $currentUser['email']          = $freshRow['email'];
        // Sync session so topbar always reflects the latest name/email
        SessionManager::setUser($currentUser);
    }
}

// Load system settings
$sys_settings = [];
$res = $db->query("SELECT setting_key, setting_value FROM system_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sys_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Load services
$services_result = $db->query("SELECT * FROM services ORDER BY service_name ASC");

// Default values if settings don't exist
$max_bookings    = $sys_settings['max_bookings_per_day'] ?? 20;
$reminder_hours  = $sys_settings['reminder_hours'] ?? 24;
$wday_start      = '08:00';
$wday_end        = '21:00';
$wed_start       = '08:00';
$wed_end         = '17:00';
$sat_start       = '08:00';
$sat_end         = '16:00';
$clinic_name     = $sys_settings['clinic_name'] ?? 'FSUU Dental Clinic';
$clinic_email    = $sys_settings['clinic_email'] ?? '';
$clinic_phone    = $sys_settings['clinic_phone'] ?? '';
$clinic_address  = $sys_settings['clinic_address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-settings.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
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
                <li class="nav-item"><a class="nav-link" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link active" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
            </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">
                <h2>System Settings</h2>

                <!-- Global alert -->
                <div id="alertContainer" class="mt-2"></div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mt-3" id="settingsTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-booking">
                            <i class="bi bi-calendar-check me-1"></i> Booking & Hours
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-clinic">
                            <i class="bi bi-hospital me-1"></i> Clinic Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-services">
                            <i class="bi bi-tooth me-1"></i> Dental Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-account">
                            <i class="bi bi-person-circle me-1"></i> My Account
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-4">

                    <!-- ===== TAB 1: BOOKING & HOURS ===== -->
                    <div class="tab-pane fade show active" id="tab-booking">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">Booking Rules</h5></div>
                                    <div class="card-body">
                                        <form id="bookingSettingsForm">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Max Bookings Per Time Slot</label>
                                                <input type="number" name="max_bookings_per_day" class="form-control" min="1" max="100" value="<?php echo htmlspecialchars($max_bookings); ?>" required>
                                                <small class="text-muted">Maximum number of patients that can book the same time slot.</small>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Appointment Reminder (hours before)</label>
                                                <input type="number" name="reminder_hours" class="form-control" min="1" max="72" value="<?php echo htmlspecialchars($reminder_hours); ?>" required>
                                                <small class="text-muted">How many hours before the appointment to send a reminder email.</small>
                                            </div>

                                            <h6 class="fw-bold border-bottom pb-2 mb-3">Clinic Hours</h6>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Monday / Tuesday / Thursday / Friday</label>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">Start Time</label>
                                                        <input type="time" name="weekday_start" class="form-control" value="<?php echo htmlspecialchars($wday_start); ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">End Time</label>
                                                        <input type="time" name="weekday_end" class="form-control" value="<?php echo htmlspecialchars($wday_end); ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Wednesday</label>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">Start Time</label>
                                                        <input type="time" name="wednesday_start" class="form-control" value="<?php echo htmlspecialchars($wed_start); ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">End Time</label>
                                                        <input type="time" name="wednesday_end" class="form-control" value="<?php echo htmlspecialchars($wed_end); ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Saturday</label>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">Start Time</label>
                                                        <input type="time" name="saturday_start" class="form-control" value="<?php echo htmlspecialchars($sat_start); ?>" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small text-muted">End Time</label>
                                                        <input type="time" name="saturday_end" class="form-control" value="<?php echo htmlspecialchars($sat_end); ?>" required>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Sunday is always closed.</small>
                                            </div>

                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i> Save Booking Settings
                                            </button>
                                            <small class="d-block text-muted mt-2">Clinic default hours: M/Th & T/F: 8:00 AM - 9:00 PM | Wednesday: 8:00 AM - 5:00 PM | Saturday: 8:00 AM - 4:00 PM</small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="card info-card">
                                    <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Info</h6></div>
                                    <div class="card-body small text-muted">
                                        <ul class="mb-0 ps-3">
                                            <li>Time slots are generated every <strong>30 minutes</strong> within the clinic hours.</li>
                                            <li>Default clinic hours: <strong>M/T/Th/F: 8:00 AM - 9:00 PM</strong>, <strong>Wednesday: 8:00 AM - 5:00 PM</strong>, <strong>Saturday: 8:00 AM - 4:00 PM</strong>.</li>
                                            <li>Blocked schedules (managed in the <a href="schedule.php">Schedule</a> tab) take priority over these hours.</li>
                                            <li>The clinic is always closed on <strong>Sundays</strong>.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 2: CLINIC INFO ===== -->
                    <div class="tab-pane fade" id="tab-clinic">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">Clinic Information</h5></div>
                                    <div class="card-body">
                                        <form id="clinicInfoForm">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Clinic Name</label>
                                                <input type="text" name="clinic_name" class="form-control" value="<?php echo htmlspecialchars($clinic_name); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Contact Email</label>
                                                <input type="email" name="clinic_email" class="form-control" value="<?php echo htmlspecialchars($clinic_email); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Contact Phone</label>
                                                <input type="text" name="clinic_phone" class="form-control" value="<?php echo htmlspecialchars($clinic_phone); ?>">
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Address</label>
                                                <textarea name="clinic_address" class="form-control" rows="3"><?php echo htmlspecialchars($clinic_address); ?></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i> Save Clinic Info
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 3: SERVICES ===== -->
                    <div class="tab-pane fade" id="tab-services">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Dental Services</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Service
                            </button>
                        </div>
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service Name</th>
                                                <th>Description</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="servicesTableBody">
                                            <?php if ($services_result && $services_result->num_rows > 0): ?>
                                                <?php while ($svc = $services_result->fetch_assoc()): ?>
                                                <tr id="service-row-<?php echo $svc['service_id']; ?>" 
                                                    class="service-row-clickable"
                                                    data-id="<?php echo $svc['service_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($svc['service_name']); ?>"
                                                    data-desc="<?php echo htmlspecialchars($svc['description'] ?? ''); ?>"
                                                    data-duration="<?php echo $svc['duration_minutes'] ?? 30; ?>"
                                                    style="cursor: pointer;">
                                                    <td><strong><?php echo htmlspecialchars($svc['service_name']); ?></strong></td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></small></td>
                                                    <td><?php echo htmlspecialchars($svc['duration_minutes'] ?? ''); ?> min</td>
                                                    <td>
                                                        <?php if ($svc['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="action-buttons" onclick="event.stopPropagation();">
                                                        <button class="btn btn-sm btn-outline-warning toggle-service-btn"
                                                            data-id="<?php echo $svc['service_id']; ?>"
                                                            data-active="<?php echo $svc['is_active']; ?>"
                                                            title="<?php echo $svc['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="bi bi-<?php echo $svc['is_active'] ? 'eye-slash' : 'eye'; ?>-fill"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-service-btn"
                                                            data-id="<?php echo $svc['service_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($svc['service_name']); ?>"
                                                            title="Delete">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No services found. Add one above.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 4: MY ACCOUNT ===== -->
                    <div class="tab-pane fade" id="tab-account">
                        <div class="row g-4">
                            <!-- Profile Picture -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-image me-2"></i>Profile Picture</h5></div>
                                    <div class="card-body">
                                        <div id="avatarUploadAlert"></div>
                                        <div class="d-flex align-items-center gap-4 flex-wrap">
                                            <?php
                                                $pic = $currentUser['profile_picture'] ?? null;
                                                $initials = strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1));
                                            ?>
                                            <!-- Clickable avatar -->
                                            <div id="avatarPreviewWrap" style="position:relative;width:90px;height:90px;flex-shrink:0;cursor:pointer;" title="Click to change photo">
                                                <label for="avatarFileInput" style="display:block;width:90px;height:90px;border-radius:50%;overflow:hidden;cursor:pointer;margin:0;">
                                                    <?php if ($pic): ?>
                                                        <img id="avatarPreviewImg" src="../<?= htmlspecialchars($pic) ?>?v=<?= time() ?>"
                                                             alt="Profile" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #E0E0E0;">
                                                    <?php else: ?>
                                                        <div id="avatarPreviewImg" style="width:90px;height:90px;border-radius:50%;background:#29ABE2;color:#fff;font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;border:3px solid #E0E0E0;">
                                                            <?= $initials ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="avatar-cam-overlay"><i class="bi bi-camera-fill"></i></div>
                                                </label>
                                                <input type="file" id="avatarFileInput" accept="image/*" style="display:none;">
                                            </div>
                                            <!-- Name / role / hint -->
                                            <div>
                                                <div class="mb-1" style="font-size:1.1rem;font-weight:700;color:#1A1A1A;">
                                                    <?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span style="display:inline-flex;align-items:center;gap:0.35rem;background:#29ABE2;color:#fff;font-size:0.75rem;font-weight:600;padding:0.2rem 0.65rem;border-radius:20px;text-transform:uppercase;letter-spacing:0.05em;">
                                                        <i class="bi bi-shield-fill-check"></i>
                                                        <?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'Admin')) ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted mb-2" style="font-size:0.8rem;">Click photo to change · JPG, PNG, WEBP or GIF · Max 2 MB</p>
                                                <button type="button" class="btn btn-primary btn-sm d-none" id="avatarUploadBtn">
                                                    <i class="bi bi-save me-1"></i> Upload
                                                </button>
                                                <span id="avatarFileName" class="text-muted ms-1" style="font-size:0.85rem;"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Personal Info -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Personal Information</h5></div>
                                    <div class="card-body">
                                        <div id="personalInfoAlert"></div>
                                        <form id="personalInfoForm">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">First Name</label>
                                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Last Name</label>
                                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Contact Number</label>
                                                <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($currentUser['contact_number']); ?>">
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i> Save Changes
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <!-- Change Password -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Change Password</h5></div>
                                    <div class="card-body">
                                        <div id="passwordAlert"></div>
                                        <form id="changePasswordForm">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Current Password</label>
                                                <input type="password" name="current_password" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">New Password</label>
                                                <input type="password" name="new_password" class="form-control" minlength="8" required>
                                                <small class="text-muted">Minimum 8 characters</small>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Confirm New Password</label>
                                                <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                            </div>
                                            <button type="submit" class="btn btn-warning">
                                                <i class="bi bi-key-fill me-1"></i> Update Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- end tab-content -->
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Dental Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addServiceAlert"></div>
                    <form id="addServiceForm">
                        <div class="mb-3">
                            <label class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" name="service_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_minutes" class="form-control" min="5" max="480" value="30" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAddService">
                        <i class="bi bi-check-lg me-1"></i> Save Service
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit Dental Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editServiceAlert"></div>
                    <form id="editServiceForm">
                        <input type="hidden" name="service_id" id="edit_service_id">
                        <div class="mb-3">
                            <label class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_service_desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_minutes" id="edit_service_duration" class="form-control" min="5" max="480" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditService">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    // ---- Booking Settings ----
    $('#bookingSettingsForm').submit(function(e) {
        e.preventDefault();
        $.post('../api/settings.php', $(this).serialize() + '&action=save_booking_settings', function(res) {
            showAlert('#alertContainer', res.success ? 'success' : 'danger', res.message);
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed.'));
    });

    // ---- Clinic Info ----
    $('#clinicInfoForm').submit(function(e) {
        e.preventDefault();
        $.post('../api/settings.php', $(this).serialize() + '&action=save_clinic_info', function(res) {
            showAlert('#alertContainer', res.success ? 'success' : 'danger', res.message);
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed.'));
    });

    // ---- Services ----
    $('#saveAddService').click(function() {
        $.post('../api/settings.php', $('#addServiceForm').serialize() + '&action=add_service', function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#addServiceModal').modal('hide');
                $('#addServiceForm')[0].reset();
                setTimeout(() => location.reload(), 800);
            } else {
                showAlert('#addServiceAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#addServiceAlert', 'danger', 'Request failed.'));
    });

    // Make service rows clickable to edit
    $('.service-row-clickable').click(function() {
        const row = $(this);
        $('#edit_service_id').val(row.data('id'));
        $('#edit_service_name').val(row.data('name'));
        $('#edit_service_desc').val(row.data('desc'));
        $('#edit_service_duration').val(row.data('duration'));
        $('#editServiceAlert').html('');
        $('#editServiceModal').modal('show');
    });
    
    // Add hover effect for clickable rows
    $('.service-row-clickable').hover(
        function() { $(this).addClass('table-active'); },
        function() { $(this).removeClass('table-active'); }
    );

    $('#saveEditService').click(function() {
        $.post('../api/settings.php', $('#editServiceForm').serialize() + '&action=edit_service', function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#editServiceModal').modal('hide');
                setTimeout(() => location.reload(), 800);
            } else {
                showAlert('#editServiceAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#editServiceAlert', 'danger', 'Request failed.'));
    });

    $('.toggle-service-btn').click(function() {
        const id = $(this).data('id');
        $.post('../api/settings.php', { action: 'toggle_service', service_id: id }, function(res) {
            showAlert('#alertContainer', res.success ? 'success' : 'danger', res.message);
            if (res.success) setTimeout(() => location.reload(), 800);
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed.'));
    });

    $('.delete-service-btn').click(function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (!confirm('Delete service "' + name + '"? This cannot be undone.')) return;
        $.post('../api/settings.php', { action: 'delete_service', service_id: id }, function(res) {
            showAlert('#alertContainer', res.success ? 'success' : 'danger', res.message);
            if (res.success) $('#service-row-' + id).fadeOut(400, function() { $(this).remove(); });
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed.'));
    });

    // ---- My Account: Profile Picture Upload ----
    document.getElementById('avatarFileInput').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        document.getElementById('avatarFileName').textContent = file.name;
        document.getElementById('avatarUploadBtn').classList.remove('d-none');
        // Preview
        const reader = new FileReader();
        reader.onload = function (e) {
            const wrap = document.getElementById('avatarPreviewWrap');
            let img = document.getElementById('avatarPreviewImg');
            if (img.tagName === 'DIV') {
                // Replace initials div with img
                const newImg = document.createElement('img');
                newImg.id = 'avatarPreviewImg';
                newImg.alt = 'Preview';
                newImg.style.cssText = 'width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #E0E0E0;';
                wrap.replaceChild(newImg, img);
                img = newImg;
            }
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('avatarUploadBtn').addEventListener('click', function () {
        const file = document.getElementById('avatarFileInput').files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('profile_picture', file);
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading…';
        fetch('../api/upload-profile-picture.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Upload';
                if (res.success) {
                    showAlert('#avatarUploadAlert', 'success', 'Profile picture updated successfully!');
                    // Update topbar avatar immediately
                    const topbarAvatar = document.querySelector('.topbar-user-avatar');
                    const topbarInitials = document.querySelector('.topbar-user-initials');
                    if (topbarAvatar) {
                        topbarAvatar.src = '../' + res.path;
                    } else if (topbarInitials) {
                        const newImg = document.createElement('img');
                        newImg.className = 'topbar-user-avatar';
                        newImg.alt = 'Avatar';
                        newImg.src = '../' + res.path;
                        topbarInitials.replaceWith(newImg);
                    }
                    document.getElementById('avatarUploadBtn').classList.add('d-none');
                    document.getElementById('avatarFileName').textContent = '';
                } else {
                    showAlert('#avatarUploadAlert', 'danger', res.message || 'Upload failed.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-1"></i> Upload';
                showAlert('#avatarUploadAlert', 'danger', 'Request failed.');
            });
    });

    // ---- My Account: Personal Info ----
    $('#personalInfoForm').submit(function(e) {
        e.preventDefault();
        $.post('../api/update-profile.php', $(this).serialize() + '&action=update_personal', function(res) {
            showAlert('#personalInfoAlert', res.success ? 'success' : 'danger', res.message);
            if (res.success) {
                setTimeout(() => location.reload(), 800);
            }
        }, 'json').fail(() => showAlert('#personalInfoAlert', 'danger', 'Request failed.'));
    });

    // ---- My Account: Change Password ----
    $('#changePasswordForm').submit(function(e) {
        e.preventDefault();
        const newPwd = $('input[name="new_password"]', this).val();
        const confirmPwd = $('input[name="confirm_password"]', this).val();
        if (newPwd !== confirmPwd) {
            showAlert('#passwordAlert', 'danger', 'New passwords do not match.');
            return;
        }
        $.post('../api/update-profile.php', $(this).serialize() + '&action=update_password', function(res) {
            showAlert('#passwordAlert', res.success ? 'success' : 'danger', res.message);
            if (res.success) $('#changePasswordForm')[0].reset();
        }, 'json').fail(() => showAlert('#passwordAlert', 'danger', 'Request failed.'));
    });
    </script>
</body>
</html>
