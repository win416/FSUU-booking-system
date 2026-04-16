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

// Get pending appointments (read-only; approval is handled by assigned dentist)
$pending = $db->query("
    SELECT a.*, u.first_name, u.last_name, u.fsuu_id, s.service_name,
           d.first_name AS dentist_first_name, d.last_name AS dentist_last_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN dentist_appointment_assignments da ON da.appointment_id = a.appointment_id
    LEFT JOIN users d ON d.user_id = da.dentist_id AND d.role = 'dentist'
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

// Total patients
$total_patients = $db->query("
    SELECT COUNT(*) as count FROM users WHERE role = 'student'
")->fetch_assoc()['count'] ?? 0;

// This month's appointments
$this_month = $db->query("
    SELECT COUNT(*) as count FROM appointments 
    WHERE MONTH(appointment_date) = MONTH(CURDATE()) 
    AND YEAR(appointment_date) = YEAR(CURDATE())
")->fetch_assoc()['count'] ?? 0;

$stats = [
    'total_today'     => $stats_today['total_today']    ?? 0,
    'pending_count'   => $stats_pending['pending_count'] ?? 0,
    'approved_count'  => $stats_today['approved_count']  ?? 0,
    'completed_count' => $stats_today['completed_count'] ?? 0,
    'total_patients'  => $total_patients,
    'this_month'      => $this_month,
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

                    <div class="fac-dots">
                        <span class="fac-dot active" onclick="facGoTo(0)"></span>
                        <span class="fac-dot" onclick="facGoTo(1)"></span>
                        <span class="fac-dot" onclick="facGoTo(2)"></span>
                    </div>
                </div>
            </div>
        </div>


        <div class="row align-items-stretch">
            <!-- Today's Schedule -->
            <div class="col-md-6 d-flex">
                <div class="card mb-4 w-100">
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

            <!-- Pending Appointments -->
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
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Assigned Dentist</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($pending->num_rows > 0): ?>
                                        <?php while($appt = $pending->fetch_assoc()): ?>
                                        <tr class="pending-clickable-row" data-id="<?php echo (int)$appt['appointment_id']; ?>" role="button" tabindex="0">
                                            <td>
                                                <div class="fw-semibold"><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?></td>
                                            <td><span class="badge bg-light text-dark"><?php echo $appt['service_name']; ?></span></td>
                                            <?php $dentist_name = trim((string)(($appt['dentist_first_name'] ?? '') . ' ' . ($appt['dentist_last_name'] ?? ''))); ?>
                                            <td><span class="text-muted"><?php echo $dentist_name !== '' ? 'Dr. ' . htmlspecialchars($dentist_name) : 'No dentist assigned yet'; ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="bi bi-check-circle text-success fs-3"></i>
                                                <p class="mb-0 mt-2 text-muted">All caught up! No pending appointments.</p>
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

        <div class="row align-items-stretch">
            <!-- Weekly Chart -->
            <div class="col-md-8 d-flex">
                <div class="card w-100 dashboard-card">
                    <div class="card-header d-flex align-items-center justify-content-between dashboard-card-header">
                        <div>
                            <h5 class="mb-0 dashboard-card-title">
                                <i class="bi bi-bar-chart-line me-2 dashboard-card-title-icon"></i>Weekly Appointments
                            </h5>
                            <small class="text-muted dashboard-card-subtitle">Last 7 days overview</small>
                        </div>
                        <span class="badge dashboard-week-badge">
                            This Week
                        </span>
                    </div>
                    <div class="card-body dashboard-card-body-compact">
                        <canvas id="weeklyChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4 d-flex">
                <div class="card w-100 dashboard-card">
                    <div class="card-header d-flex align-items-center dashboard-card-header">
                        <h5 class="mb-0 dashboard-card-title">
                            <i class="bi bi-lightning me-2 dashboard-card-title-icon"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body dashboard-card-body">
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
        </div> <!-- closing row -->

    </div> <!-- closing main-content -->
</div> <!-- closing dashboard-wrapper -->

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Reschedule Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rescheduleForm">
                        <input type="hidden" id="reschedule_id" name="appointment_id">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Date</label>
                            <input type="date" class="form-control" id="reschedule_date" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Time</label>
                            <select class="form-select" id="reschedule_time" name="appointment_time" required>
                                <option value="">Select time...</option>
                            </select>
                            <small class="text-muted">Mon-Fri: 1:00 PM - 3:30 PM | Sat: 9:00 AM - 12:00 PM</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason (Optional)</label>
                            <textarea class="form-control" id="reschedule_reason" name="reason" rows="2" placeholder="e.g., Patient requested new time"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveReschedule">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Weekly Chart
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    const weeklyData = <?php echo json_encode(['dates' => $chart_labels, 'counts' => $chart_values]); ?>;

    const gradient = ctx.createLinearGradient(0, 0, 0, 280);
    gradient.addColorStop(0, 'rgba(41, 171, 226, 0.25)');
    gradient.addColorStop(1, 'rgba(41, 171, 226, 0.0)');

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

    // Reschedule button
    $('.edit-pending-btn').click(function() {
        const id = $(this).data('id');
        const date = $(this).data('date');
        const time = $(this).data('time');
        
        $('#reschedule_id').val(id);
        $('#reschedule_date').val(date);
        $('#reschedule_reason').val('');
        
        loadTimeSlots(date, time);
        
        const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
        modal.show();
    });

    // When date changes, reload time slots
    $('#reschedule_date').change(function() {
        const date = $(this).val();
        if (date) {
            loadTimeSlots(date);
        }
    });

    function loadTimeSlots(date, selectedTime = null) {
        const timeSelect = $('#reschedule_time');
        timeSelect.html('<option value="">Loading...</option>');
        
        const dayOfWeek = new Date(date).getDay();
        let slots = [];
        
        if (dayOfWeek === 0) {
            timeSelect.html('<option value="">Clinic closed on Sundays</option>');
            return;
        } else if (dayOfWeek === 6) {
            slots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'];
        } else {
            slots = ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];
        }
        
        let options = '<option value="">Select time...</option>';
        slots.forEach(slot => {
            const time24 = slot + ':00';
            const hour = parseInt(slot.split(':')[0]);
            const min = slot.split(':')[1];
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
            const label = `${hour12}:${min} ${ampm}`;
            const selected = (selectedTime && time24 === selectedTime) ? 'selected' : '';
            options += `<option value="${time24}" ${selected}>${label}</option>`;
        });
        
        timeSelect.html(options);
    }

    // Save reschedule
    $('#saveReschedule').click(function() {
        const id = $('#reschedule_id').val();
        const date = $('#reschedule_date').val();
        const time = $('#reschedule_time').val();
        const reason = $('#reschedule_reason').val();
        
        if (!date || !time) {
            alert('Please select both date and time');
            return;
        }
        
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
        
        $.ajax({
            url: '../api/update-appointment.php',
            method: 'POST',
            data: {
                appointment_id: id,
                action: 'reschedule',
                appointment_date: date,
                appointment_time: time,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error updating appointment');
                    $('#saveReschedule').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Changes');
                }
            },
            error: function() {
                alert('Server error occurred.');
                $('#saveReschedule').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Changes');
            }
        });
    });

    // Make pending appointment rows clickable
    $('.pending-clickable-row').on('click keydown', function(e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        if (e.type === 'keydown') {
            e.preventDefault();
        }
        $('.pending-clickable-row').removeClass('row-selected');
        $(this).addClass('row-selected');
        const appointmentId = $(this).data('id');
        window.location.href = `appointments.php?status=pending&appointment_id=${encodeURIComponent(appointmentId)}`;
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
