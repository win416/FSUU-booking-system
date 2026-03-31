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
            <div class="sidebar-nav-wrap">
            <div class="sidebar-section-label">Menu</div>
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
            </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>
            <div class="container-fluid my-4">
            <div style="max-width:1100px;width:100%;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h2 class="fw-bold mb-0" style="font-size:1.6rem;">My Appointments</h2>
                        <p class="text-muted mb-0" style="font-size:0.875rem;">View and manage your dental appointments</p>
                    </div>
                    <a href="book-appointment.php" class="btn rounded-pill px-4 fw-semibold" style="background:#29ABE2;color:#fff;border:none;">
                        <i class="bi bi-plus-lg me-1"></i> Book New Appointment
                    </a>
                </div>

                <div class="card border-0 shadow-sm" style="border-radius:12px;overflow:hidden;">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" style="font-size:0.9rem;">
                                <thead style="background:#f9fafb;border-bottom:1px solid #E0E0E0;">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Service</th>
                                        <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Date</th>
                                        <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Time</th>
                                        <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Status</th>
                                        <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($appointments->num_rows > 0): ?>
                                        <?php while($appt = $appointments->fetch_assoc()): ?>
                                            <tr style="border-bottom:1px solid #f3f4f6;">
                                                <td class="ps-4 py-3 fw-semibold" style="color:#111827;"><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                                <td class="py-3"><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                                <td class="py-3"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                                <td class="py-3">
                                                    <?php
                                                    $badgeStyle = match($appt['status']) {
                                                        'pending'   => 'background:#fef3c7;color:#92400e;',
                                                        'approved'  => 'background:#dcfce7;color:#166534;',
                                                        'completed' => 'background:#dbeafe;color:#1e40af;',
                                                        'cancelled' => 'background:#fee2e2;color:#991b1b;',
                                                        'declined'  => 'background:#fee2e2;color:#991b1b;',
                                                        default     => 'background:#f3f4f6;color:#374151;'
                                                    };
                                                    ?>
                                                    <span style="<?php echo $badgeStyle; ?> font-size:0.75rem;font-weight:600;padding:0.25em 0.75em;border-radius:999px;display:inline-block;">
                                                        <?php echo ucfirst($appt['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <?php if($appt['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-danger rounded-pill cancel-appt" data-id="<?php echo $appt['appointment_id']; ?>">Cancel</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3;"></i>
                                                No appointments found.
                                            </td>
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
