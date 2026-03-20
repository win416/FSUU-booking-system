<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Stat counts
$upcoming_blocks = $db->query("SELECT COUNT(*) as cnt FROM blocked_schedules WHERE block_date >= CURDATE()")->fetch_assoc()['cnt'] ?? 0;
$fullday_blocks  = $db->query("SELECT COUNT(*) as cnt FROM blocked_schedules WHERE block_date >= CURDATE() AND is_full_day = 1")->fetch_assoc()['cnt'] ?? 0;
$partial_blocks  = $db->query("SELECT COUNT(*) as cnt FROM blocked_schedules WHERE block_date >= CURDATE() AND is_full_day = 0")->fetch_assoc()['cnt'] ?? 0;

// Today's appointment count
$today = date('Y-m-d');
$today_appts = $db->query("SELECT COUNT(*) as cnt FROM appointments WHERE appointment_date = '$today' AND status IN ('pending','approved')")->fetch_assoc()['cnt'] ?? 0;

// Fetch upcoming blocks
$upcoming = $db->query("
    SELECT bs.*, u.first_name, u.last_name
    FROM blocked_schedules bs
    LEFT JOIN users u ON bs.created_by = u.user_id
    WHERE bs.block_date >= CURDATE()
    ORDER BY bs.block_date ASC, bs.start_time ASC
");

// Fetch past blocks (last 30 days)
$past = $db->query("
    SELECT bs.*, u.first_name, u.last_name
    FROM blocked_schedules bs
    LEFT JOIN users u ON bs.created_by = u.user_id
    WHERE bs.block_date < CURDATE()
    ORDER BY bs.block_date DESC
    LIMIT 30
");

// Calendar events: blocked dates
$calendar_blocks = $db->query("
    SELECT block_date, is_full_day, reason
    FROM blocked_schedules
    WHERE block_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
");
$block_events = [];
while ($row = $calendar_blocks->fetch_assoc()) {
    $block_events[] = [
        'title'           => $row['is_full_day'] ? '🚫 Full Day Blocked' : ('🚫 ' . ($row['reason'] ?: 'Partial Block')),
        'start'           => $row['block_date'],
        'backgroundColor' => '#dc3545',
        'borderColor'     => '#dc3545',
        'textColor'       => '#fff',
        'allDay'          => true,
    ];
}

// Calendar events: appointments per day
$appt_counts = $db->query("
    SELECT appointment_date, COUNT(*) as cnt
    FROM appointments
    WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
      AND status IN ('pending','approved','completed')
    GROUP BY appointment_date
");
$appt_events = [];
while ($row = $appt_counts->fetch_assoc()) {
    $appt_events[] = [
        'title'           => '📅 ' . $row['cnt'] . ' Appointment' . ($row['cnt'] > 1 ? 's' : ''),
        'start'           => $row['appointment_date'],
        'backgroundColor' => '#0d6efd',
        'borderColor'     => '#0d6efd',
        'textColor'       => '#fff',
        'allDay'          => true,
    ];
}

$all_events = array_merge($block_events, $appt_events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-schedule.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
                <li class="nav-item"><a class="nav-link active" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">Schedule Management</h2>
                        <p class="text-muted mb-0">Block dates, view appointments, and manage clinic availability.</p>
                    </div>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#blockModal">
                        <i class="bi bi-calendar-x-fill me-1"></i> Block Schedule
                    </button>
                </div>

                <!-- Alert container -->
                <div id="alertContainer" class="mb-3"></div>

                <!-- Stat Cards -->
                <div class="row mb-4">
                    <?php
                    $maxSched = max($today_appts, $upcoming_blocks, $fullday_blocks, $partial_blocks, 1);
                    function schedBar($v, $m) { return $m > 0 ? round(($v / $m) * 100) : 0; }
                    ?>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Today's Appointments</h6>
                                <h2><?php echo $today_appts; ?></h2>
                                <div class="progress">
                                    <div class="progress-bar" style="width:<?php echo schedBar($today_appts, $maxSched); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Upcoming Blocks</h6>
                                <h2><?php echo $upcoming_blocks; ?></h2>
                                <div class="progress">
                                    <div class="progress-bar" style="width:<?php echo schedBar($upcoming_blocks, $maxSched); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Full Day Blocks</h6>
                                <h2><?php echo $fullday_blocks; ?></h2>
                                <div class="progress">
                                    <div class="progress-bar" style="width:<?php echo schedBar($fullday_blocks, $maxSched); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Partial Blocks</h6>
                                <h2><?php echo $partial_blocks; ?></h2>
                                <div class="progress">
                                    <div class="progress-bar" style="width:<?php echo schedBar($partial_blocks, $maxSched); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Clinic Calendar</h5>
                        <div class="d-flex gap-3 small">
                            <span><span class="legend-dot" class="legend-dot--appt"></span>Appointments</span>
                            <span><span class="legend-dot" class="legend-dot--blocked"></span>Blocked</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="clinicCalendar"></div>
                    </div>
                </div>

                <!-- Blocks Table Tabs -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="blocksTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tab-upcoming">
                                    Upcoming Blocks
                                    <span class="badge bg-danger ms-1"><?php echo $upcoming_blocks; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-past">
                                    Past Blocks <small class="text-muted">(last 30)</small>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content">

                            <!-- Upcoming Blocks -->
                            <div class="tab-pane fade show active" id="tab-upcoming">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th>Time</th>
                                                <th>Reason</th>
                                                <th>Blocked By</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($upcoming->num_rows > 0): ?>
                                                <?php while ($row = $upcoming->fetch_assoc()): ?>
                                                <tr id="block-row-<?php echo $row['block_id']; ?>">
                                                    <td><strong><?php echo date('M d, Y', strtotime($row['block_date'])); ?></strong></td>
                                                    <td><span class="text-muted"><?php echo date('l', strtotime($row['block_date'])); ?></span></td>
                                                    <td>
                                                        <?php if ($row['is_full_day']): ?>
                                                            <span class="badge bg-danger">Full Day</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <?php echo date('h:i A', strtotime($row['start_time'])) . ' – ' . date('h:i A', strtotime($row['end_time'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo $row['first_name'] ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'System'; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger delete-block-btn"
                                                            data-id="<?php echo $row['block_id']; ?>"
                                                            data-date="<?php echo date('M d, Y', strtotime($row['block_date'])); ?>">
                                                            <i class="bi bi-trash-fill me-1"></i>Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-muted">
                                                        <i class="bi bi-calendar-check fs-3 d-block mb-2"></i>
                                                        No upcoming blocked schedules.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Past Blocks -->
                            <div class="tab-pane fade" id="tab-past">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th>Time</th>
                                                <th>Reason</th>
                                                <th>Blocked By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($past->num_rows > 0): ?>
                                                <?php while ($row = $past->fetch_assoc()): ?>
                                                <tr class="text-muted">
                                                    <td><?php echo date('M d, Y', strtotime($row['block_date'])); ?></td>
                                                    <td><?php echo date('l', strtotime($row['block_date'])); ?></td>
                                                    <td>
                                                        <?php if ($row['is_full_day']): ?>
                                                            <span class="badge bg-secondary">Full Day</span>
                                                        <?php else: ?>
                                                            <small><?php echo date('h:i A', strtotime($row['start_time'])) . ' – ' . date('h:i A', strtotime($row['end_time'])); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                                    <td><small><?php echo $row['first_name'] ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'System'; ?></small></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No past blocks found.</td>
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
    </div>

    <!-- Block Schedule Modal -->
    <div class="modal fade" id="blockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-x-fill me-2"></i>Block Schedule</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="blockModalAlert"></div>
                    <form id="blockForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="block_date" id="block_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_full_day" name="is_full_day" checked>
                            <label class="form-check-label fw-semibold" for="is_full_day">Block full day</label>
                        </div>
                        <div id="timeFields" style="display:none;">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" name="reason" placeholder="e.g. Clinic maintenance, Holiday, Staff meeting">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveBlock">
                        <i class="bi bi-calendar-x me-1"></i>Block Date
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Day Detail Modal -->
    <div class="modal fade" id="dayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayModalTitle">Schedule for <span id="dayModalDate"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dayModalBody">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger btn-sm" id="dayModalBlockBtn">
                        <i class="bi bi-calendar-x me-1"></i>Block This Date
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    const calendarEvents = <?php echo json_encode($all_events); ?>;

    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    // ── Full Calendar ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const calEl = document.getElementById('clinicCalendar');
        const calendar = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
            height: 'auto',
            events: calendarEvents,
            eventClick: function (info) {
                // Do nothing on event click, date click handles it
            },
            dateClick: function (info) {
                openDayModal(info.dateStr);
            },
            dayCellClassNames: function (arg) {
                if (arg.date.getDay() === 0) return ['fc-day-sunday'];
            }
        });
        calendar.render();

        // Pre-fill date if passed via URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('block') === '1') {
            new bootstrap.Modal(document.getElementById('blockModal')).show();
        }
    });

    // ── Day Detail Modal ─────────────────────────────────────────────────────
    function openDayModal(dateStr) {
        $('#dayModalDate').text(new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' }));
        $('#dayModalBody').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>');
        $('#dayModalBlockBtn').data('date', dateStr);
        new bootstrap.Modal(document.getElementById('dayModal')).show();

        $.get('../api/get-appointment-details.php', { date: dateStr }, function (res) {
            const appts = res.appointments || [];
            let html = '';
            if (appts.length === 0) {
                html = '<p class="text-muted text-center py-3">No appointments on this date.</p>';
            } else {
                html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr></thead><tbody>';
                appts.forEach(a => {
                    const badge = { pending:'warning', approved:'info', completed:'success', cancelled:'danger', declined:'secondary' };
                    html += `<tr>
                        <td>${a.appointment_time || ''}</td>
                        <td>${a.first_name || ''} ${a.last_name || ''}</td>
                        <td>${a.service_name || ''}</td>
                        <td><span class="badge bg-${badge[a.status]||'secondary'}">${a.status}</span></td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
            }
            $('#dayModalBody').html(html);
        }).fail(function() {
            $('#dayModalBody').html('<p class="text-danger text-center py-3">Could not load appointments.</p>');
        });
    }

    // Pre-fill block modal date from day modal
    $('#dayModalBlockBtn').click(function () {
        const d = $(this).data('date');
        $('#block_date').val(d);
        bootstrap.Modal.getInstance(document.getElementById('dayModal')).hide();
        setTimeout(() => new bootstrap.Modal(document.getElementById('blockModal')).show(), 300);
    });

    // ── Toggle time fields ───────────────────────────────────────────────────
    $('#is_full_day').change(function () {
        if ($(this).is(':checked')) {
            $('#timeFields').hide();
            $('input[name="start_time"], input[name="end_time"]').removeAttr('required');
        } else {
            $('#timeFields').show();
            $('input[name="start_time"], input[name="end_time"]').attr('required', 'required');
        }
    });

    // ── Save Block ───────────────────────────────────────────────────────────
    $('#saveBlock').click(function () {
        const formData = $('#blockForm').serialize();
        $.post('../api/block-schedule.php', formData, function (res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>' + res.message);
                bootstrap.Modal.getInstance(document.getElementById('blockModal')).hide();
                $('#blockForm')[0].reset();
                $('#timeFields').hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('#blockModalAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#blockModalAlert', 'danger', 'Server error. Please try again.'));
    });

    // ── Delete Block ─────────────────────────────────────────────────────────
    $(document).on('click', '.delete-block-btn', function () {
        const id   = $(this).data('id');
        const date = $(this).data('date');
        if (!confirm('Remove block for ' + date + '? Patients will be able to book this date again.')) return;
        $.post('../api/delete-block.php', { block_id: id }, function (res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>Block removed successfully.');
                $('#block-row-' + id).fadeOut(400, function () { $(this).remove(); });
            } else {
                showAlert('#alertContainer', 'danger', res.message || 'Failed to remove block.');
            }
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Server error. Please try again.'));
    });
    </script>
</body>
</html>
