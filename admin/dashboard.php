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
            <div class="sidebar-nav-wrap">
            <div class="sidebar-section-label">Menu</div>
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
            </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>

        <!-- Hero Slideshow -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="fac-slideshow">
                    <div class="fac-slide active">
                        <img src="../img/insidefsuudental.jpg" alt="Inside FSUU Dental">
                    </div>
                    <div class="fac-slide">
                        <img src="../img/counter1.jpg" alt="Clinic Counter">
                    </div>
                    <div class="fac-slide">
                        <img src="../img/outside.jpg" alt="Clinic Outside">
                    </div>
                    <div class="fac-hero-overlay"></div>
                    <div class="fac-hero-text">
                        <h2>Admin Dashboard</h2>
                        <p>Welcome back, <strong><?php echo htmlspecialchars(SessionManager::getUser()['first_name']); ?></strong>!</p>
                    </div>
                    <button class="fac-arrow fac-prev" onclick="facSlide(-1)"><i class="bi bi-chevron-left"></i></button>
                    <button class="fac-arrow fac-next" onclick="facSlide(1)"><i class="bi bi-chevron-right"></i></button>
                    <div class="fac-dots">
                        <span class="fac-dot active" onclick="facGoTo(0)"></span>
                        <span class="fac-dot" onclick="facGoTo(1)"></span>
                        <span class="fac-dot" onclick="facGoTo(2)"></span>
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
                <div class="card" style="border-radius:14px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.07);">
                    <div class="card-header d-flex align-items-center justify-content-between" style="background:#fff; border-bottom:1px solid #E0E0E0; border-radius:14px 14px 0 0; padding:1rem 1.5rem;">
                        <div>
                            <h5 class="mb-0" style="font-weight:700; color:#1A1A1A; font-size:1rem;">
                                <i class="bi bi-bar-chart-line me-2" style="color:#1A1A1A;"></i>Weekly Appointments
                            </h5>
                            <small class="text-muted" style="font-size:0.75rem;">Last 7 days overview</small>
                        </div>
                        <span class="badge" style="background:#F8F8F8; color:#1A1A1A; font-size:0.75rem; font-weight:600; border-radius:20px; padding:0.35rem 0.85rem; border:1px solid #E0E0E0;">
                            This Week
                        </span>
                    </div>
                    <div class="card-body" style="padding:1.25rem 1.5rem 1rem;">
                        <canvas id="weeklyChart" height="100"></canvas>
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
        </div> <!-- closing Quick Actions card-body -->
            </div> <!-- closing Quick Actions card -->
            </div> <!-- closing Quick Actions col -->
        </div> <!-- closing row -->

    </div> <!-- closing main-content -->
</div> <!-- closing dashboard-wrapper -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Weekly Chart
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const weeklyData = <?php echo json_encode(['dates' => $chart_labels, 'counts' => $chart_values]); ?>;

    const gradient = ctx.createLinearGradient(0, 0, 0, 280);
    gradient.addColorStop(0, 'rgba(26, 26, 26, 0.15)');
    gradient.addColorStop(1, 'rgba(26, 26, 26, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: weeklyData.dates,
            datasets: [{
                label: 'Appointments',
                data: weeklyData.counts,
                borderColor: '#1A1A1A',
                backgroundColor: gradient,
                fill: true,
                tension: 0.42,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#1A1A1A',
                pointBorderWidth: 2.5,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#1A1A1A',
                pointHoverBorderColor: '#fff',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A1A1A',
                    titleColor: '#E0E0E0',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        title: ctx => ctx[0].label,
                        label: ctx => `  ${ctx.parsed.y} appointment${ctx.parsed.y !== 1 ? 's' : ''}`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#4D4D4D', font: { size: 12 } }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#4D4D4D',
                        font: { size: 12 },
                        padding: 8
                    },
                    grid: { color: '#F8F8F8', drawBorder: false },
                    border: { display: false, dash: [4, 4] }
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

    <script>
    // Hero Facilities Slideshow
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

</body>
</html>