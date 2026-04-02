<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Get filter status
$status_filter = $_GET['status'] ?? 'all';

// Build query
$query = "
    SELECT a.*, u.first_name, u.last_name, u.fsuu_id, s.service_name 
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
";

if ($status_filter !== 'all') {
    $query .= " WHERE a.status = '" . $db->real_escape_string($status_filter) . "'";
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
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <style>
    .filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
    .filter-tab {
        display: inline-block;
        padding: 6px 20px;
        border-radius: 50px;
        border: 1.5px solid #29ABE2;
        color: #29ABE2;
        background: #fff;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
        white-space: nowrap;
    }
    .filter-tab:hover { background: #e8f7fd; color: #1C9DD6; border-color: #1C9DD6; }
    .filter-tab.active { background: #29ABE2; color: #fff; border-color: #29ABE2; }
    .filter-tab.active:hover { background: #1C9DD6; border-color: #1C9DD6; color: #fff; }
    .btn-dark.complete-btn { background: #29ABE2; border-color: #1C9DD6; }
    .btn-dark.complete-btn:hover { background: #1C9DD6; border-color: #1C9DD6; }
    tbody tr.clickable-row { cursor: pointer; }
    tbody tr.clickable-row:hover { background-color: #f0f8ff; }
    </style>
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
                        <a href="?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
                        <a href="?status=completed" class="filter-tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="?status=cancelled" class="filter-tab <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
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
                                                $badge_class = match($appt['status']) {
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'completed' => 'bg-info',
                                                    'cancelled', 'declined' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($appt['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($appt['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success approve-btn" data-id="<?php echo $appt['appointment_id']; ?>" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger decline-btn" data-id="<?php echo $appt['appointment_id']; ?>" title="Decline">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php elseif($appt['status'] == 'approved'): ?>
                                                    <button class="btn btn-sm btn-dark complete-btn" data-id="<?php echo $appt['appointment_id']; ?>" title="Mark as Completed">
                                                        <i class="bi bi-check-all"></i> Complete
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Approve
        $('.approve-btn').click(function() {
            const id = $(this).data('id');
            if(confirm('Approve this appointment?')) {
                updateStatus(id, 'approved');
            }
        });

        // Decline
        $('.decline-btn').click(function() {
            const id = $(this).data('id');
            const reason = prompt('Reason for declining:');
            if(reason !== null) {
                updateStatus(id, 'declined', reason);
            }
        });

        // Complete
        $('.complete-btn').click(function() {
            const id = $(this).data('id');
            if(confirm('Mark this appointment as completed?')) {
                updateStatus(id, 'completed');
            }
        });

        function updateStatus(id, status, reason = null) {
            $.ajax({
                url: '../api/update-appointment.php',
                method: 'POST',
                data: {
                    appointment_id: id,
                    status: status,
                    reason: reason
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error updating appointment');
                    }
                },
                error: function() {
                    alert('Server error occurred.');
                }
            });
        }

        // Stop action buttons from triggering row click
        $('.approve-btn, .decline-btn, .complete-btn').click(function(e) {
            e.stopPropagation();
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
                                            <div class="col-6"><small class="text-muted d-block">Status</small><span class="badge ${getStatusBadge(data.status)}">${data.status.toUpperCase()}</span></div>
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
            switch(status) {
                case 'pending': return 'bg-warning';
                case 'approved': return 'bg-success';
                case 'completed': return 'bg-info';
                case 'cancelled':
                case 'declined': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
    });
    </script>
</body>
</html>
