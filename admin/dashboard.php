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
$stats_today = $db->query("
    SELECT 
        COUNT(*) as total_today,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM appointments
    WHERE appointment_date = CURDATE()
")->fetch_assoc();

// Pending = ALL pending regardless of date
$stats_pending = $db->query("
    SELECT COUNT(*) as pending_count FROM appointments WHERE status = 'pending'
")->fetch_assoc();

$stats = [
    'total_today'     => $stats_today['total_today']    ?? 0,
    'pending_count'   => $stats_pending['pending_count'] ?? 0,
    'approved_count'  => $stats_today['approved_count']  ?? 0,
    'completed_count' => $stats_today['completed_count'] ?? 0,
];

// Weekly stats
$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekly_data[$date] = 0;
}

$weekly_query = $db->query("
    SELECT 
        DATE(appointment_date) as date,
        COUNT(*) as total
    FROM appointments
    WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
    GROUP BY DATE(appointment_date)
");

while ($row = $weekly_query->fetch_assoc()) {
    $weekly_data[$row['date']] = (int)$row['total'];
}

$chart_labels = [];
$chart_values = [];
foreach ($weekly_data as $date => $count) {
    $chart_labels[] = date('M d', strtotime($date));
    $chart_values[] = $count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
            </div>
            <ul class="sidebar-nav">
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
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Admin Dashboard</h2>
                <p>Welcome, <?php echo SessionManager::getUser()['first_name']; ?></p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <?php
            $total_today    = $stats['total_today']     ?? 0;
            $pending_count  = $stats['pending_count']   ?? 0;
            $approved_count = $stats['approved_count']  ?? 0;
            $completed_count= $stats['completed_count'] ?? 0;
            $max_stat = max($total_today, $pending_count, $approved_count, $completed_count, 1);
            function adminBarWidth($val, $max) {
                return $max > 0 ? round(($val / $max) * 100) : 0;
            }
            ?>
            <div class="col-md-3">
                <div class="card card-stats h-100">
                    <div class="card-body">
                        <h6>Today's Appointments</h6>
                        <h2><?php echo $total_today; ?></h2>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo adminBarWidth($total_today, $max_stat); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stats h-100">
                    <div class="card-body">
                        <h6>Pending Approval</h6>
                        <h2><?php echo $pending_count; ?></h2>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: <?php echo adminBarWidth($pending_count, $max_stat); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stats h-100">
                    <div class="card-body">
                        <h6>Approved Today</h6>
                        <h2><?php echo $approved_count; ?></h2>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?php echo adminBarWidth($approved_count, $max_stat); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stats h-100">
                    <div class="card-body">
                        <h6>Completed Today</h6>
                        <h2><?php echo $completed_count; ?></h2>
                        <div class="progress">
                            <div class="progress-bar bg-info" style="width: <?php echo adminBarWidth($completed_count, $max_stat); ?>%"></div>
                        </div>
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
                </div> <!-- closing Quick Actions -->
            </div> <!-- closing Quick Actions col -->
        </div> <!-- closing row -->

    </div> <!-- closing container-fluid -->
</div> <!-- closing main-content -->
</div> <!-- closing dashboard-wrapper -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Weekly Chart
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const weeklyData = <?php echo json_encode(['dates' => $chart_labels, 'counts' => $chart_values]); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: weeklyData.dates,
            datasets: [{
                label: 'Appointments',
                data: weeklyData.counts,
                borderColor: '#00aeef',
                backgroundColor: 'rgba(0, 174, 239, 0.1)',
                fill: true,
                tension: 0.3
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