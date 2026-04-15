<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireLogin();

if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . SITE_URL . '/patient/dashboard.php');
    }
    exit();
}

$db = getDB();
$user = SessionManager::getUser();
$dentist_id = (int)$user['user_id'];

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_appointment_assignments (
        assignment_id INT(11) NOT NULL AUTO_INCREMENT,
        appointment_id INT(11) NOT NULL,
        dentist_id INT(11) NOT NULL,
        checked_in_at DATETIME DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (assignment_id),
        UNIQUE KEY uq_appointment (appointment_id),
        KEY idx_dentist (dentist_id),
        CONSTRAINT fk_daa_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
        CONSTRAINT fk_daa_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$todayAppointmentsStmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
           u.first_name, u.last_name, u.fsuu_id, s.service_name
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    JOIN users u ON u.user_id = a.user_id
    JOIN services s ON s.service_id = a.service_id
    WHERE da.dentist_id = ?
      AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
");
$todayAppointmentsStmt->bind_param("i", $dentist_id);
$todayAppointmentsStmt->execute();
$todayAppointments = $todayAppointmentsStmt->get_result();

$upcomingAppointmentsStmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
           u.first_name, u.last_name, u.fsuu_id, s.service_name
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    JOIN users u ON u.user_id = a.user_id
    JOIN services s ON s.service_id = a.service_id
    WHERE da.dentist_id = ?
      AND a.appointment_date >= CURDATE()
      AND LOWER(TRIM(a.status)) = 'pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 10
");
$upcomingAppointmentsStmt->bind_param("i", $dentist_id);
$upcomingAppointmentsStmt->execute();
$upcomingAppointments = $upcomingAppointmentsStmt->get_result();

$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekly_data[$date] = 0;
}

$weeklyStmt = $db->prepare("
    SELECT DATE(a.appointment_date) AS date, COUNT(*) AS total
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    WHERE da.dentist_id = ?
      AND a.appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
      AND LOWER(TRIM(a.status)) NOT IN ('cancelled', 'canceled', 'declined', 'no_show')
    GROUP BY DATE(a.appointment_date)
");
$weeklyStmt->bind_param("i", $dentist_id);
$weeklyStmt->execute();
$weeklyRes = $weeklyStmt->get_result();
while ($row = $weeklyRes->fetch_assoc()) {
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
    <title>Dentist Dashboard - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/dentist-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental Clinic
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
                        <a class="nav-link" href="my-schedule.php">
                            <i class="bi bi-clock"></i> My Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-patients.php">
                            <i class="bi bi-people"></i> My Patients
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="notifications.php"><i class="bi bi-bell"></i> Notifications <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/dentist-topbar.php'; ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="fac-slideshow fac-hero">
                        <div class="fac-slide active"><img src="../img/insidefsuudental.jpg" alt="Inside FSUU Dental"></div>
                        <div class="fac-slide"><img src="../img/counter1.jpg" alt="Clinic Counter"></div>
                        <div class="fac-slide"><img src="../img/outside.jpg" alt="Clinic Outside"></div>
                        <div class="fac-hero-overlay"></div>
                        <div class="fac-hero-text">
                            <h2>Dentist Dashboard</h2>
                            <p>Welcome back, <strong>Dr. <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>!</p>
                        </div>
                        <div class="fac-dots">
                            <span class="fac-dot active" onclick="facGoTo(0)"></span>
                            <span class="fac-dot" onclick="facGoTo(1)"></span>
                            <span class="fac-dot" onclick="facGoTo(2)"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row align-items-stretch">
                <div class="col-md-6 d-flex">
                    <div class="card mb-4 w-100">
                        <div class="card-header">
                            <h5>Today's Appointments</h5>
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
                                        <?php if ($todayAppointments->num_rows > 0): ?>
                                            <?php while ($appointment = $todayAppointments->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = match($appointment['status']) {
                                                            'pending' => 'bg-warning',
                                                            'approved' => 'bg-success',
                                                            'completed' => 'bg-info',
                                                            'cancelled', 'declined' => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst(htmlspecialchars($appointment['status'])); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center py-4">No appointments today</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 d-flex">
                    <div class="card mb-4 w-100">
                        <div class="card-header">
                            <h5>Pending Appointments</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($upcomingAppointments->num_rows > 0): ?>
                                            <?php while ($appointment = $upcomingAppointments->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="text-nowrap"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                                    <td class="text-nowrap"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                    <td class="text-nowrap"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                                    <td class="text-nowrap"><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                                    <td class="text-nowrap">
                                                        <a href="appointments.php?appointment_id=<?php echo (int)$appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil-square me-1"></i>Manage
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-4">No pending appointments</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row align-items-stretch">
                <div class="col-md-8 d-flex">
                    <div class="card w-100 dashboard-card">
                        <div class="card-header d-flex align-items-center justify-content-between dashboard-card-header">
                            <div>
                                <h5 class="mb-0 dashboard-card-title">
                                    <i class="bi bi-bar-chart-line me-2 dashboard-card-title-icon"></i>Weekly Appointments
                                </h5>
                                <small class="text-muted dashboard-card-subtitle">Last 7 days overview</small>
                            </div>
                            <span class="badge dashboard-week-badge">This Week</span>
                        </div>
                        <div class="card-body dashboard-card-body-compact">
                            <canvas id="weeklyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 d-flex">
                    <div class="card w-100 dashboard-card">
                        <div class="card-header d-flex align-items-center dashboard-card-header">
                            <h5 class="mb-0 dashboard-card-title">
                                <i class="bi bi-lightning me-2 dashboard-card-title-icon"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body dashboard-card-body">
                            <div class="d-grid gap-2">
                                <a href="appointments.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-week"></i> View Appointments
                                </a>
                                <a href="my-schedule.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-x"></i> Manage My Schedule
                                </a>
                                <a href="my-patients.php" class="btn btn-outline-primary">
                                    <i class="bi bi-people"></i> Open My Patients
                                </a>
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person"></i> Update Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    const weeklyCanvas = document.getElementById('weeklyChart');
    if (weeklyCanvas) {
        const ctx = weeklyCanvas.getContext('2d');
        const weeklyData = <?php echo json_encode(['dates' => $chart_labels, 'counts' => $chart_values]); ?>;
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(41, 171, 226, 0.25)');
        gradient.addColorStop(1, 'rgba(41, 171, 226, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: weeklyData.dates,
                datasets: [{
                    label: 'Appointments',
                    data: weeklyData.counts,
                    borderColor: '#29ABE2',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.42,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#29ABE2',
                    pointBorderWidth: 2.5,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#29ABE2',
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
                        backgroundColor: '#29ABE2',
                        titleColor: '#E0E0E0',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            title: context => context[0].label,
                            label: context => `  ${context.parsed.y} appointment${context.parsed.y !== 1 ? 's' : ''}`
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
                        border: { display: false }
                    }
                }
            }
        });
    }

    let facIdx = 0;
    const facSlides = document.querySelectorAll('.fac-slide');
    const facDots = document.querySelectorAll('.fac-dot');
    function facGoTo(n) {
        if (!facSlides.length) return;
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
