<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Get today's appointments
$today = date('Y-m-d');
$today_appointments = $db->prepare("
    SELECT a.*, u.first_name, u.last_name, u.fsuu_id, s.service_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.appointment_date = ?
    ORDER BY a.appointment_time
");
$today_appointments->bind_param("s", $today);
$today_appointments->execute();
$today_result = $today_appointments->get_result();

// Get pending approvals
$pending = $db->query("
    SELECT a.*, u.first_name, u.last_name, u.fsuu_id, s.service_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.status = 'pending'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 10
");

// Statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_today,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM appointments
    WHERE appointment_date = CURDATE()
")->fetch_assoc();

// Weekly stats
$weekly = $db->query("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total
    FROM appointments
    WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
    GROUP BY DATE(appointment_date)
    ORDER BY date
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">FSUU Dental - Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">
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
                <ul class="navbar-nav">
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
    <div class="container-fluid my-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Admin Dashboard</h2>
                <p>Welcome, <?php echo SessionManager::getUser()['first_name']; ?></p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Today's Appointments</h5>
                        <h2><?php echo $stats['total_today'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending Approval</h5>
                        <h2><?php echo $stats['pending_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Approved Today</h5>
                        <h2><?php echo $stats['approved_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Completed</h5>
                        <h2><?php echo $stats['completed_count'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Today's Schedule -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Today's Schedule (<?php echo date('F d, Y'); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($today_result->num_rows > 0): ?>
                                        <?php while($appt = $today_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                            <td><?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?></td>
                                            <td><?php echo $appt['service_name']; ?></td>
                                            <td>
                                                <?php
                                                $badge_class = match($appt['status']) {
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'completed' => 'bg-info',
                                                    'cancelled' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($appt['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No appointments today</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Pending Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($appt = $pending->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, h:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])); ?></td>
                                        <td><?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?></td>
                                        <td><?php echo $appt['service_name']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success approve-btn" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger decline-btn" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Weekly Chart -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Weekly Appointments</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyChart"></canvas>
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
                            <a href="schedule.php?block=1" class="btn btn-outline-primary">
                                <i class="bi bi-calendar-x"></i> Block Schedule
                            </a>
                            <a href="appointments.php?view=all" class="btn btn-outline-primary">
                                <i class="bi bi-calendar-week"></i> View All Appointments
                            </a>
                            <a href="reports.php" class="btn btn-outline-primary">
                                <i class="bi bi-file-text"></i> Generate Report
                            </a>
                            <a href="users.php?add=1" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus"></i> Add User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Weekly Chart
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const weeklyData = <?php
        $dates = [];
        $counts = [];
        while($row = $weekly->fetch_assoc()) {
            $dates[] = date('M d', strtotime($row['date']));
            $counts[] = $row['total'];
        }
        echo json_encode(['dates' => $dates, 'counts' => $counts]);
    ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: weeklyData.dates,
            datasets: [{
                label: 'Appointments',
                data: weeklyData.counts,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 1
                }
            }
        }
    });

    // Approve appointment
    $('.approve-btn').click(function() {
        const id = $(this).data('id');
        if(confirm('Approve this appointment?')) {
            $.ajax({
                url: '../api/update-appointment.php',
                method: 'POST',
                data: {
                    appointment_id: id,
                    status: 'approved'
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    }
                }
            });
        }
    });

    // Decline appointment
    $('.decline-btn').click(function() {
        const id = $(this).data('id');
        const reason = prompt('Reason for declining:');
        if(reason !== null) {
            $.ajax({
                url: '../api/update-appointment.php',
                method: 'POST',
                data: {
                    appointment_id: id,
                    status: 'declined',
                    reason: reason
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    }
                }
            });
        }
    });
    </script>
</body>
</html>