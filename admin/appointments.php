<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Get filter status
$status_filter = strtolower(trim($_GET['status'] ?? 'all'));
$allowed_filters = ['all', 'pending', 'approved', 'completed', 'cancelled', 'canceled', 'declined', 'no_show'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

// Build query
$query = "
    SELECT a.*, u.first_name, u.last_name, u.fsuu_id, s.service_name,
           d.first_name AS dentist_first_name, d.last_name AS dentist_last_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN dentist_appointment_assignments da ON da.appointment_id = a.appointment_id
    LEFT JOIN users d ON d.user_id = da.dentist_id AND d.role = 'dentist'
";

if ($status_filter !== 'all') {
    if (in_array($status_filter, ['cancelled', 'canceled', 'declined', 'no_show'], true)) {
        $query .= " WHERE LOWER(TRIM(a.status)) IN ('cancelled', 'canceled', 'declined', 'no_show')";
    } else {
        $query .= " WHERE LOWER(TRIM(a.status)) = '" . $db->real_escape_string($status_filter) . "'";
    }
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$result = $db->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-appointments.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
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
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="appointments.php">
                        <i class="bi bi-calendar-check"></i> Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="patients.php">
                        <i class="bi bi-people"></i> Patients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="schedule.php">
                        <i class="bi bi-clock"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="bi bi-person-badge"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
            </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h2>Manage Appointments</h2>
                    <div class="filter-tabs">
                        <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                        <a href="?status=completed" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="?status=cancelled" class="filter-tab <?php echo in_array($status_filter, ['cancelled', 'canceled', 'declined', 'no_show'], true) ? 'active' : ''; ?>">Cancelled</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                         <th>Date & Time</th>
                                        <th>Patient</th>
                                        <th>FSUU ID</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($result->num_rows > 0): ?>
                                        <?php while($appt = $result->fetch_assoc()): ?>
                                        <tr class="clickable-row" data-id="<?php echo $appt['appointment_id']; ?>">
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></td>
                                            <td><span class="text-muted" style="font-size:0.82rem;"><?php echo htmlspecialchars($appt['fsuu_id']); ?></span></td>
                                            <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                            <td>
                                                <?php
                                                $status_key = strtolower(trim((string)($appt['status'] ?? '')));
                                                $dentist_name = trim((string)(($appt['dentist_first_name'] ?? '') . ' ' . ($appt['dentist_last_name'] ?? '')));
                                                $dentist_label = $dentist_name !== '' ? 'Dr. ' . htmlspecialchars($dentist_name) : 'No dentist assigned yet';
                                                $badge_class = match($status_key) {
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'completed' => 'bg-info',
                                                    'cancelled', 'canceled', 'declined', 'no_show' => 'bg-danger',
                                                    default => 'bg-danger'
                                                };
                                                $status_label = match($status_key) {
                                                    'pending' => 'Pending',
                                                    'approved' => 'Approved',
                                                    'completed' => 'Completed',
                                                    'cancelled', 'canceled', 'declined', 'no_show' => 'Cancelled',
                                                    default => 'Cancelled'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $status_label; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($status_key === 'pending'): ?>
                                                    <button class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="<?php echo $appt['appointment_id']; ?>" data-date="<?php echo $appt['appointment_date']; ?>" data-time="<?php echo $appt['appointment_time']; ?>" data-service="<?php echo $appt['service_id']; ?>" title="Reschedule">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <span class="text-muted small"><?php echo $dentist_label; ?> handles approval</span>
                                                <?php elseif($status_key === 'approved'): ?>
                                                    <button class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="<?php echo $appt['appointment_id']; ?>" data-date="<?php echo $appt['appointment_date']; ?>" data-time="<?php echo $appt['appointment_time']; ?>" data-service="<?php echo $appt['service_id']; ?>" title="Reschedule">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <span class="text-muted small"><?php echo $dentist_label; ?> handles completion</span>
                                                <?php elseif(in_array($status_key, ['cancelled', 'canceled', 'declined', 'no_show'], true)): ?>
                                                    <button class="btn btn-sm btn-danger" disabled title="Cancelled">
                                                        <i class="bi bi-x-circle"></i> Cancelled
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">No appointments found for the selected filter.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Loaded via AJAX -->
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit/Reschedule Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_appointment_id" name="appointment_id">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Date</label>
                            <input type="date" class="form-control" id="edit_date" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Time</label>
                            <select class="form-select" id="edit_time" name="appointment_time" required>
                                <option value="">Select time...</option>
                            </select>
                            <small class="text-muted">Mon-Fri: 1:00 PM - 3:30 PM | Sat: 9:00 AM - 12:00 PM</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Reschedule (Optional)</label>
                            <textarea class="form-control" id="edit_reason" name="reason" rows="2" placeholder="e.g., Patient requested new time"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveReschedule">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        const targetAppointmentId = new URLSearchParams(window.location.search).get('appointment_id');
        if (targetAppointmentId) {
            const targetRow = $(`.clickable-row[data-id="${targetAppointmentId}"]`);
            if (targetRow.length) {
                targetRow.addClass('row-focused');
                targetRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Stop action buttons from triggering row click
        $('.edit-btn').click(function(e) {
            e.stopPropagation();
        });

        // Edit/Reschedule button
        $('.edit-btn').click(function() {
            const id = $(this).data('id');
            const date = $(this).data('date');
            const time = $(this).data('time');
            
            $('#edit_appointment_id').val(id);
            $('#edit_date').val(date);
            $('#edit_reason').val('');
            
            // Load time slots for the date
            loadTimeSlots(date, time);
            
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });

        // When date changes, reload time slots
        $('#edit_date').change(function() {
            const date = $(this).val();
            if (date) {
                loadTimeSlots(date);
            }
        });

        function loadTimeSlots(date, selectedTime = null) {
            const timeSelect = $('#edit_time');
            timeSelect.html('<option value="">Loading...</option>');
            
            const dayOfWeek = new Date(date).getDay();
            let slots = [];
            
            if (dayOfWeek === 0) { // Sunday - closed
                timeSelect.html('<option value="">Clinic closed on Sundays</option>');
                return;
            } else if (dayOfWeek === 6) { // Saturday
                slots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'];
            } else { // Mon-Fri
                slots = ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];
            }
            
            let options = '<option value="">Select time...</option>';
            slots.forEach(slot => {
                const time24 = slot + ':00';
                const hour = parseInt(slot.split(':')[0]);
                const min = slot.split(':')[1];
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                const label = `${hour12}:${min} ${ampm}`;
                const selected = (selectedTime && time24 === selectedTime) ? 'selected' : '';
                options += `<option value="${time24}" ${selected}>${label}</option>`;
            });
            
            timeSelect.html(options);
        }

        // Save reschedule
        $('#saveReschedule').click(function() {
            const id = $('#edit_appointment_id').val();
            const date = $('#edit_date').val();
            const time = $('#edit_time').val();
            const reason = $('#edit_reason').val();
            
            if (!date || !time) {
                alert('Please select both date and time');
                return;
            }
            
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
            
            $.ajax({
                url: '../api/update-appointment.php',
                method: 'POST',
                data: {
                    appointment_id: id,
                    action: 'reschedule',
                    appointment_date: date,
                    appointment_time: time,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error updating appointment');
                        $('#saveReschedule').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Changes');
                    }
                },
                error: function() {
                    alert('Server error occurred.');
                    $('#saveReschedule').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Changes');
                }
            });
        });

        // Row click → View Details
        $('.clickable-row').click(function() {
            const id = $(this).data('id');
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            const content = $('#detailsContent');
            
            // Re-show spinner
            content.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');
            modal.show();

            $.get('../api/get-appointment-details.php', { id: id }, function(response) {
                if(response.success) {
                    const data = response.data;
                    const html = `
                        <div class="appointment-details">
                            <div class="row g-3">
                                <!-- Left column: Patient + Appointment -->
                                <div class="col-md-6">
                                    <div class="p-3 rounded" style="background:#f8f9fa;">
                                        <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">PATIENT INFORMATION</h6>
                                        <div class="row g-2">
                                            <div class="col-6"><small class="text-muted d-block">Name</small><strong>${data.first_name} ${data.last_name}</strong></div>
                                            <div class="col-6"><small class="text-muted d-block">FSUU ID</small><span style="font-size:0.82rem;">${data.fsuu_id}</span></div>
                                            <div class="col-12"><small class="text-muted d-block">Email</small><span style="font-size:0.85rem;">${data.email}</span></div>
                                            <div class="col-12"><small class="text-muted d-block">Contact</small><span style="font-size:0.85rem;">${data.contact_number || '<em class="text-muted">N/A</em>'}</span></div>
                                        </div>
                                    </div>
                                    <div class="p-3 rounded mt-3" style="background:#f8f9fa;">
                                        <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">APPOINTMENT INFORMATION</h6>
                                        <div class="row g-2">
                                            <div class="col-6"><small class="text-muted d-block">Service</small><strong>${data.service_name}</strong></div>
                                            <div class="col-6"><small class="text-muted d-block">Date</small>${new Date(data.appointment_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                            <div class="col-6"><small class="text-muted d-block">Time</small>${data.appointment_time}</div>
                                            <div class="col-6"><small class="text-muted d-block">Status</small><span class="badge ${getStatusBadge(data.status)}">${getStatusLabel(data.status)}</span></div>
                                            ${data.cancellation_reason ? `<div class="col-12"><small class="text-muted d-block">Cancellation Reason</small>${data.cancellation_reason}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                                <!-- Right column: Medical -->
                                <div class="col-md-6">
                                    <div class="p-3 rounded h-100" style="background:#f8f9fa;">
                                        <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">MEDICAL INFORMATION</h6>
                                        <div class="row g-2">
                                            <div class="col-12"><small class="text-muted d-block">Allergies</small>${data.allergies || '<span class="text-muted">None</span>'}</div>
                                            <div class="col-12"><small class="text-muted d-block">Conditions</small>${data.medical_conditions || '<span class="text-muted">None</span>'}</div>
                                            <div class="col-12"><small class="text-muted d-block">Medications</small>${data.medications || '<span class="text-muted">None</span>'}</div>
                                            <div class="col-6"><small class="text-muted d-block">Emergency Contact</small>${data.emergency_contact_name || '<span class="text-muted">N/A</span>'}</div>
                                            <div class="col-6"><small class="text-muted d-block">Emergency Number</small>${data.emergency_contact_number || '<span class="text-muted">N/A</span>'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    content.html(html);
                } else {
                    content.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            }).fail(function() {
                content.html('<div class="alert alert-danger">Failed to fetch appointment details.</div>');
            });
        });

        function getStatusBadge(status) {
            const normalized = (status || '').toString().trim().toLowerCase();
            switch(normalized) {
                case 'pending': return 'bg-warning';
                case 'approved': return 'bg-success';
                case 'completed': return 'bg-info';
                case 'cancelled':
                case 'canceled':
                case 'no_show':
                case 'declined': return 'bg-danger';
                default: return 'bg-danger';
            }
        }

        function getStatusLabel(status) {
            const normalized = (status || '').toString().trim().toLowerCase();
            switch(normalized) {
                case 'pending': return 'PENDING';
                case 'approved': return 'APPROVED';
                case 'completed': return 'COMPLETED';
                case 'cancelled':
                case 'canceled':
                case 'no_show':
                case 'declined': return 'CANCELLED';
                default: return 'CANCELLED';
            }
        }
    });
    </script>
</body>
</html>
