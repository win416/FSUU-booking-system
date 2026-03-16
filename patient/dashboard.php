<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Get upcoming appointments
$upcoming = $db->prepare("
    SELECT a.*, s.service_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    WHERE a.user_id = ? AND a.appointment_date >= CURDATE() 
    AND a.status IN ('pending', 'approved')
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$upcoming->bind_param("i", $user['user_id']);
$upcoming->execute();
$upcoming_appointments = $upcoming->get_result();

// Get appointment statistics
$stats = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
    FROM appointments
    WHERE user_id = ?
");
$stats->bind_param("i", $user['user_id']);
$stats->execute();
$appointment_stats = $stats->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">FSUU Dental Clinic</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="book-appointment.php">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-appointments.php">
                            <i class="bi bi-calendar-check"></i> My Appointments
                        </a>
                    </li>
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
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    Welcome back, <strong><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></strong>!
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending</h5>
                        <h2><?php echo $appointment_stats['pending_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Approved</h5>
                        <h2><?php echo $appointment_stats['approved_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Completed</h5>
                        <h2><?php echo $appointment_stats['completed_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Total</h5>
                        <h2><?php echo array_sum($appointment_stats); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Upcoming Appointments -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Upcoming Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if($upcoming_appointments->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($appt = $upcoming_appointments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                            <td><?php echo $appt['service_name']; ?></td>
                                            <td>
                                                <?php
                                                $badge_class = match($appt['status']) {
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($appt['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($appt['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-danger cancel-appointment" 
                                                        data-id="<?php echo $appt['appointment_id']; ?>">
                                                    Cancel
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No upcoming appointments.</p>
                            <a href="book-appointment.php" class="btn btn-primary">Book Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="book-appointment.php" class="btn btn-primary">
                                <i class="bi bi-calendar-plus"></i> Book Appointment
                            </a>
                            <a href="profile.php" class="btn btn-outline-primary">
                                <i class="bi bi-person"></i> Update Profile
                            </a>
                            <a href="history.php" class="btn btn-outline-primary">
                                <i class="bi bi-clock-history"></i> View History
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Clinic Hours -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Clinic Hours</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><strong>Monday - Friday:</strong> 8:00 AM - 5:00 PM</li>
                            <li><strong>Saturday:</strong> 8:00 AM - 12:00 PM</li>
                            <li><strong>Sunday:</strong> Closed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <textarea id="cancelReason" class="form-control" placeholder="Reason for cancellation (optional)"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Cancel Appointment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        let appointmentId = null;
        
        $('.cancel-appointment').click(function() {
            appointmentId = $(this).data('id');
            $('#cancelModal').modal('show');
        });
        
        $('#confirmCancel').click(function() {
            if(appointmentId) {
                $.ajax({
                    url: '../api/cancel-appointment.php',
                    method: 'POST',
                    data: {
                        appointment_id: appointmentId,
                        reason: $('#cancelReason').val()
                    },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    }
                });
            }
        });
    });
    </script>
</body>
</html>