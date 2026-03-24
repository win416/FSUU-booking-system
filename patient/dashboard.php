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
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <style>
    /* Hero text always white */
    .fac-hero-text h2,
    .fac-hero-text p,
    .fac-hero-text strong {
        color: #fff !important;
        text-shadow: 0 2px 10px rgba(0,0,0,0.6) !important;
    }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental
            </div>
            <div class="sidebar-nav-wrap">
            <ul class="sidebar-nav">
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
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span>
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
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>

        <!-- Hero Slideshow -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="fac-slideshow fac-hero">
                    <div class="fac-slide active">
                        <img src="../img/insidefsuudental.jpg" alt="Inside FSUU Dental">
                    </div>
                    <div class="fac-slide">
                        <img src="../img/counter1.jpg" alt="Clinic Counter">
                    </div>
                    <div class="fac-slide">
                        <img src="../img/outside.jpg" alt="Clinic Outside">
                    </div>
                    <!-- Dark gradient overlay -->
                    <div class="fac-hero-overlay"></div>
                    <!-- Title & Welcome text -->
                    <div class="fac-hero-text">
                        <h2>User Dashboard</h2>
                        <p>Welcome back, <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>!</p>
                    </div>
                    <!-- Arrows -->
                    <button class="fac-arrow fac-prev" onclick="facSlide(-1)"><i class="bi bi-chevron-left"></i></button>
                    <button class="fac-arrow fac-next" onclick="facSlide(1)"><i class="bi bi-chevron-right"></i></button>
                    <!-- Dots -->
                    <div class="fac-dots">
                        <span class="fac-dot active" onclick="facGoTo(0)"></span>
                        <span class="fac-dot" onclick="facGoTo(1)"></span>
                        <span class="fac-dot" onclick="facGoTo(2)"></span>
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

                <!-- Clinic Schedule -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Clinic Schedule</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><strong>M/TH, T/F:</strong> 8:00 AM - 9:00 PM</li>
                            <li><strong>WEDNESDAY:</strong> 8:00 AM - 5:00 PM</li>
                            <li><strong>SATURDAY:</strong> 8:00 AM - 4:00 PM</li>
                        </ul>
                    </div>
                </div>
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

    <script>
    // Facilities Slideshow
    let facIdx = 0;
    const facSlides = document.querySelectorAll('.fac-slide');
    const facDots   = document.querySelectorAll('.fac-dot');
    function facGoTo(n) {
        facSlides[facIdx].classList.remove('active');
        facDots[facIdx].classList.remove('active');
        facIdx = (n + facSlides.length) % facSlides.length;
        facSlides[facIdx].classList.add('active');
        facDots[facIdx].classList.add('active');
    }
    function facSlide(dir) { facGoTo(facIdx + dir); }
    setInterval(() => facSlide(1), 5000);
    </script>
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