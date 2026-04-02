<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
$user = SessionManager::getUser();
$db = getDB();

// Fetch medical info
$stmt = $db->prepare("SELECT * FROM medical_info WHERE user_id = ?");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$medical = $stmt->get_result()->fetch_assoc();

// Fetch past completed appointments
$histStmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.notes,
           s.service_name, s.duration_minutes
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    WHERE a.user_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$histStmt->bind_param("i", $user['user_id']);
$histStmt->execute();
$history = $histStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment History & Medical Records - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental
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
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                <li class="nav-item"><a class="nav-link active" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
            </ul>
            </div>
        </nav>
        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>
            <div class="container-fluid my-4">
                <h2 class="mb-4">Appointment History & Medical Records</h2>

                <!-- Medical Information Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">My Medical Records</h5>
                    </div>
                    <div class="card-body">
                        <form id="medicalInfoForm">
                            <input type="hidden" name="action" value="update_medical">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Allergies</label>
                                    <textarea name="allergies" class="form-control" rows="3"><?php echo htmlspecialchars($medical['allergies'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Medical Conditions</label>
                                    <textarea name="medical_conditions" class="form-control" rows="3"><?php echo htmlspecialchars($medical['medical_conditions'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Current Medications</label>
                                    <textarea name="medications" class="form-control" rows="3"><?php echo htmlspecialchars($medical['medications'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Emergency Contact Name</label>
                                    <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($medical['emergency_contact_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Relationship to Patient</label>
                                    <select name="emergency_contact_relationship" class="form-select" required>
                                        <option value="">-- Select Relationship --</option>
                                        <?php
                                        $relationships = ['Father', 'Mother', 'Sibling', 'Spouse', 'Child', 'Grandparent', 'Aunt/Uncle', 'Cousin', 'Guardian', 'Friend', 'Other'];
                                        $selected = $medical['emergency_contact_relationship'] ?? '';
                                        foreach ($relationships as $rel):
                                        ?>
                                        <option value="<?php echo $rel; ?>" <?php echo $selected === $rel ? 'selected' : ''; ?>><?php echo $rel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Emergency Contact Number</label>
                                    <input type="text" name="emergency_contact_number" class="form-control" value="<?php echo htmlspecialchars($medical['emergency_contact_number'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn" style="background:#29ABE2;color:#fff;border-color:#1C9DD6;">Update Medical Info</button>
                        </form>
                    </div>
                </div>

                <!-- Appointment History Section -->
                <div class="card">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-muted"></i>Past Appointments</h5>
                        <span class="badge" style="background:#e8f7fd;color:#29ABE2;font-size:0.8rem;padding:0.4rem 0.75rem;border-radius:20px;">
                            <?php echo $history->num_rows; ?> record<?php echo $history->num_rows !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($history->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="padding:0.85rem 1.25rem;">Date</th>
                                        <th style="padding:0.85rem 1.25rem;">Time</th>
                                        <th style="padding:0.85rem 1.25rem;">Service</th>
                                        <th style="padding:0.85rem 1.25rem;">Duration</th>
                                        <th style="padding:0.85rem 1.25rem;">Status</th>
                                        <th style="padding:0.85rem 1.25rem;">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $history->fetch_assoc()): ?>
                                    <tr class="history-row" style="cursor:pointer;"
                                        data-date="<?php echo date('F d, Y', strtotime($row['appointment_date'])); ?>"
                                        data-day="<?php echo date('l', strtotime($row['appointment_date'])); ?>"
                                        data-time="<?php echo date('h:i A', strtotime($row['appointment_time'])); ?>"
                                        data-service="<?php echo htmlspecialchars($row['service_name']); ?>"
                                        data-duration="<?php echo $row['duration_minutes']; ?>"
                                        data-notes="<?php echo htmlspecialchars($row['notes'] ?? ''); ?>">
                                        <td style="padding:0.85rem 1.25rem;">
                                            <strong><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('l', strtotime($row['appointment_date'])); ?></small>
                                        </td>
                                        <td style="padding:0.85rem 1.25rem;">
                                            <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                                        </td>
                                        <td style="padding:0.85rem 1.25rem;">
                                            <strong><?php echo htmlspecialchars($row['service_name']); ?></strong>
                                        </td>
                                        <td style="padding:0.85rem 1.25rem;">
                                            <span style="background:rgba(41,171,226,0.1);color:#29ABE2;border:1px solid rgba(41,171,226,0.25);border-radius:20px;padding:2px 10px;font-size:0.78rem;font-weight:600;">
                                                <i class="bi bi-clock me-1"></i><?php echo $row['duration_minutes']; ?> mins
                                            </span>
                                        </td>
                                        <td style="padding:0.85rem 1.25rem;">
                                            <span class="badge bg-info">Completed</span>
                                        </td>
                                        <td style="padding:0.85rem 1.25rem;">
                                            <small class="text-muted"><?php echo $row['notes'] ? htmlspecialchars($row['notes']) : '—'; ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x" style="font-size:2.5rem;color:#E0E0E0;"></i>
                            <p class="text-muted mt-3 mb-0">No completed appointments yet.</p>
                            <a href="book-appointment.php" class="btn btn-sm mt-3" style="background:#29ABE2;color:#fff;">Book your first appointment</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Detail Modal -->
    <div class="modal fade" id="appointmentDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:12px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background:#f0f8ff;border-bottom:1px solid #e0e0e0;border-radius:12px 12px 0 0;padding:1rem 1.5rem;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calendar-check me-2" style="color:#29ABE2;"></i>Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:1.5rem;">
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Date</small>
                            <strong id="modal-date"></strong><br>
                            <small class="text-muted" id="modal-day"></small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Time</small>
                            <strong id="modal-time"></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Service</small>
                            <strong id="modal-service"></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Duration</small>
                            <span id="modal-duration" style="background:rgba(41,171,226,0.1);color:#29ABE2;border:1px solid rgba(41,171,226,0.25);border-radius:20px;padding:2px 10px;font-size:0.8rem;font-weight:600;display:inline-block;"></span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Status</small>
                            <span class="badge bg-info">Completed</span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Notes</small>
                            <span id="modal-notes" class="text-muted"></span>
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
        // Clickable history rows
        $(document).on('click', '.history-row', function() {
            $('#modal-date').text($(this).data('date'));
            $('#modal-day').text($(this).data('day'));
            $('#modal-time').text($(this).data('time'));
            $('#modal-service').text($(this).data('service'));
            $('#modal-duration').html('<i class="bi bi-clock me-1"></i>' + $(this).data('duration') + ' mins');
            var notes = $(this).data('notes');
            $('#modal-notes').text(notes ? notes : '—');
            new bootstrap.Modal(document.getElementById('appointmentDetailModal')).show();
        });
        // Hover effect
        $(document).on('mouseenter', '.history-row', function() {
            $(this).css('background','#f0f9ff');
        }).on('mouseleave', '.history-row', function() {
            $(this).css('background','');
        });

        $('#medicalInfoForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnText = submitBtn.text();

            submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: '../api/update-profile.php',
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message || 'Medical records updated successfully!');
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
