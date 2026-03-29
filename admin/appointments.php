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
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
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
            <div class="logout-nav-item">
                <a class="nav-link text-danger" href="../auth/logout.php">
                    <i class="bi bi-box-arrow-right text-danger"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Appointments</h2>
                    <div class="btn-group">
                        <a href="?status=all" class="btn btn-outline-primary <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=pending" class="btn btn-outline-primary <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?status=approved" class="btn btn-outline-primary <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
                        <a href="?status=completed" class="btn btn-outline-primary <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="?status=cancelled" class="btn btn-outline-primary <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
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
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($appt['fsuu_id']); ?></code></td>
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
                                                    <button class="btn btn-sm btn-info complete-btn" data-id="<?php echo $appt['appointment_id']; ?>" title="Mark as Completed">
                                                        <i class="bi bi-check-all"></i> Complete
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-secondary view-details" data-id="<?php echo $appt['appointment_id']; ?>" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
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
        <div class="modal-dialog">
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

        // View Details
        $('.view-details').click(function() {
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
                            <div class="section mb-4">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Patient Information</h6>
                                <div class="row">
                                    <div class="col-6 mb-2"><strong>Name:</strong><br>${data.first_name} ${data.last_name}</div>
                                    <div class="col-6 mb-2"><strong>FSUU ID:</strong><br>${data.fsuu_id}</div>
                                    <div class="col-6 mb-2"><strong>Email:</strong><br>${data.email}</div>
                                    <div class="col-6 mb-2"><strong>Contact:</strong><br>${data.contact_number}</div>
                                </div>
                            </div>
                            
                            <div class="section mb-4">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Appointment Information</h6>
                                <div class="row">
                                    <div class="col-6 mb-2"><strong>Service:</strong><br>${data.service_name}</div>
                                    <div class="col-6 mb-2"><strong>Date:</strong><br>${new Date(data.appointment_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                    <div class="col-6 mb-2"><strong>Time:</strong><br>${data.appointment_time}</div>
                                    <div class="col-6 mb-2"><strong>Status:</strong><br><span class="badge ${getStatusBadge(data.status)}">${data.status.toUpperCase()}</span></div>
                                    ${data.cancellation_reason ? `<div class="col-12 mb-2"><strong>Cancellation Reason:</strong><br>${data.cancellation_reason}</div>` : ''}
                                </div>
                            </div>

                            <div class="section">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Medical Information</h6>
                                <div class="row">
                                    <div class="col-12 mb-2"><strong>Allergies:</strong><br>${data.allergies || '<span class="text-muted">None</span>'}</div>
                                    <div class="col-12 mb-2"><strong>Conditions:</strong><br>${data.medical_conditions || '<span class="text-muted">None</span>'}</div>
                                    <div class="col-12 mb-2"><strong>Medications:</strong><br>${data.medications || '<span class="text-muted">None</span>'}</div>
                                    <div class="col-6 mb-2"><strong>Emergency Contact:</strong><br>${data.emergency_contact_name || '<span class="text-muted">N/A</span>'}</div>
                                    <div class="col-6 mb-2"><strong>Emergency Number:</strong><br>${data.emergency_contact_number || '<span class="text-muted">N/A</span>'}</div>
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
