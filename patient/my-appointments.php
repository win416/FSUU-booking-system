<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Get all appointments
$stmt = $db->prepare("
    SELECT a.*, s.service_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - FSUU Dental Clinic</title>
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
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="book-appointment.php">
                        <i class="bi bi-calendar-plus"></i> Book Appointment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my-appointments.php">
                        <i class="bi bi-calendar-check"></i> My Appointments
                    </a>
                </li>
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
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="history.php">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                </li>
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Appointments</h2>
                    <a href="book-appointment.php" class="btn btn-primary">
                        <i class="bi bi-calendar-plus me-2"></i> Book New Appointment
                    </a>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Service</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($appointments->num_rows > 0): ?>
                                        <?php while($appt = $appointments->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4"><strong><?php echo htmlspecialchars($appt['service_name']); ?></strong></td>
                                                <td><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                                <td>
                                                    <?php
                                                    $badge = match($appt['status']) {
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'completed' => 'bg-info',
                                                        'cancelled' => 'bg-danger',
                                                        'declined' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($appt['status']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if($appt['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-danger cancel-appt" data-id="<?php echo $appt['appointment_id']; ?>">Cancel</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No appointments found.</td>
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

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <textarea id="reason" class="form-control" placeholder="Optional: Reason for cancellation"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cancelId = null;
        $('.cancel-appt').click(function() {
            cancelId = $(this).data('id');
            $('#cancelModal').modal('show');
        });

        $('#confirmCancel').click(function() {
            if (cancelId) {
                $.post('../api/cancel-appointment.php', {
                    appointment_id: cancelId,
                    reason: $('#reason').val()
                }, function(res) {
                    if (res.success) location.reload();
                    else alert(res.message);
                });
            }
        });
    </script>
</body>
</html>
