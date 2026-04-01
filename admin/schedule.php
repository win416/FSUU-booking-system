<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();
$today = date('Y-m-d');

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

// Calendar events: individual appointments with patient name + service
$appt_query = $db->query("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
           u.first_name, u.last_name,
           s.service_name, s.duration_minutes
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    WHERE a.appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
      AND a.status IN ('pending','approved','completed')
    ORDER BY a.appointment_date, a.appointment_time
");
$appt_events = [];
while ($row = $appt_query->fetch_assoc()) {
    $startDT = $row['appointment_date'] . 'T' . $row['appointment_time'];
    $endDT   = $row['appointment_date'] . 'T' . date('H:i:s', strtotime($row['appointment_time']) + ((int)$row['duration_minutes'] * 60));
    $colors  = ['approved' => '#198754', 'pending' => '#fd7e14', 'completed' => '#6c757d'];
    $color   = $colors[$row['status']] ?? '#1A1A1A';
    $appt_events[] = [
        'id'              => 'appt_' . $row['appointment_id'],
        'title'           => $row['first_name'] . ' ' . $row['last_name'],
        'start'           => $startDT,
        'end'             => $endDT,
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'textColor'       => '#fff',
        'extendedProps'   => [
            'service' => $row['service_name'],
            'status'  => $row['status'],
        ],
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
    <style>
        /* Calendar event styles */
        .fc-event { cursor: pointer; }
        .fc-timegrid-event .fc-event-main { padding: 0; }
        /* Legend dots */
        .legend-dot { display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:4px; }
        .legend-dot--appt-pending  { background:#fd7e14; }
        .legend-dot--appt-approved { background:#198754; }
        .legend-dot--appt-done     { background:#6c757d; }
        .legend-dot--blocked       { background:#dc3545; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
            </div>
            <div class="sidebar-nav-wrap">
            <div class="sidebar-section-label">Menu</div>
            <ul class="sidebar-nav">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
                <li class="nav-item"><a class="nav-link active" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
            </ul>
            </div>
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

                <!-- Calendar -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Clinic Calendar</h5>
                        <div class="d-flex gap-3 small flex-wrap">
                            <span><span class="legend-dot legend-dot--appt-approved"></span>Approved</span>
                            <span><span class="legend-dot legend-dot--appt-pending"></span>Pending</span>
                            <span><span class="legend-dot legend-dot--appt-done"></span>Completed</span>
                            <span><span class="legend-dot legend-dot--blocked"></span>Blocked</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="clinicCalendar"></div>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <!-- Block Schedule Modal -->
    <div class="modal fade" id="blockModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
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
                                    <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                                    <div class="time-slot-picker" id="startTimePicker">
                                        <button type="button" class="time-slot-display" id="startTimeDisplay" onclick="toggleTimePicker('start')">
                                            <i class="bi bi-clock-fill"></i> <span id="startTimeLabel">Select time</span>
                                            <i class="bi bi-chevron-down ms-auto"></i>
                                        </button>
                                        <div class="time-slot-dropdown" id="startTimeDropdown">
                                            <?php
                                            $times = [];
                                            for ($h = 8; $h <= 17; $h++) {
                                                foreach ([0, 30] as $m) {
                                                    $val = sprintf('%02d:%02d', $h, $m);
                                                    $label = date('h:i A', strtotime($val));
                                                    $times[] = ['val' => $val, 'label' => $label];
                                                }
                                            }
                                            foreach ($times as $t): ?>
                                            <button type="button" class="time-slot-btn" data-val="<?php echo $t['val']; ?>" data-target="start">
                                                <?php echo $t['label']; ?>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" name="start_time" id="start_time_hidden">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
                                    <div class="time-slot-picker" id="endTimePicker">
                                        <button type="button" class="time-slot-display" id="endTimeDisplay" onclick="toggleTimePicker('end')">
                                            <i class="bi bi-clock-fill"></i> <span id="endTimeLabel">Select time</span>
                                            <i class="bi bi-chevron-down ms-auto"></i>
                                        </button>
                                        <div class="time-slot-dropdown" id="endTimeDropdown">
                                            <?php foreach ($times as $t): ?>
                                            <button type="button" class="time-slot-btn" data-val="<?php echo $t['val']; ?>" data-target="end">
                                                <?php echo $t['label']; ?>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" name="end_time" id="end_time_hidden">
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
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 20px 60px rgba(0,0,0,0.15);">
                <div class="modal-header" style="border-bottom:1px solid #E0E0E0; padding:1.25rem 1.5rem;">
                    <h5 class="modal-title fw-bold" id="dayModalTitle">
                        <i class="bi bi-calendar3 me-2 text-muted"></i>Schedule for <span id="dayModalDate"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="dayModalBody">
                    <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div> <span class="text-muted ms-2">Loading...</span></div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E0E0E0; padding:1rem 1.5rem;">
                    <button type="button" class="btn btn-danger btn-sm" id="dayModalBlockBtn">
                        <i class="bi bi-calendar-x me-1"></i>Block This Date
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-dismiss="modal">Close</button>
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
            initialView: 'timeGridWeek',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
            height: 'auto',
            dayMaxEvents: 2,
            dayMaxEventRows: 3,
            slotMinTime: '08:00:00',
            slotMaxTime: '18:00:00',
            allDaySlot: true,
            nowIndicator: true,
            events: calendarEvents,
            eventContent: function (arg) {
                const service = arg.event.extendedProps.service;
                if (service) {
                    // Appointment event: show name + service
                    const timeText = arg.timeText ? `<div class="fc-event-time" style="font-size:0.7rem;opacity:0.85">${arg.timeText}</div>` : '';
                    return { html: `<div style="padding:2px 4px;overflow:hidden">
                        ${timeText}
                        <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${arg.event.title}</div>
                        <div style="font-size:0.75rem;opacity:0.9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${service}</div>
                    </div>` };
                }
                // Blocked event: default rendering
                return true;
            },
            eventClick: function (info) {
                const service = info.event.extendedProps.service;
                if (service) {
                    // Appointment clicked — open day modal for that date
                    const dateStr = info.event.startStr.substring(0, 10);
                    openDayModal(dateStr);
                }
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
            const appts = (res.appointments || []).filter(a => a.status !== 'declined' && a.status !== 'cancelled');
            let html = '';
            if (appts.length === 0) {
                html = '<div class="text-center py-5"><i class="bi bi-calendar-x" style="font-size:2.5rem;color:#E0E0E0;"></i><p class="text-muted mt-3 mb-0">No appointments on this date.</p></div>';
            } else {
                const badgeStyle = {
                    pending:   'background:#fff8e1;color:#b45309;border:1px solid #fde68a;',
                    approved:  'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;',
                    completed: 'background:#F8F8F8;color:#4D4D4D;border:1px solid #E0E0E0;',
                    cancelled: 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                    declined:  'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                };
                html = `<div class="table-responsive">
                    <table class="table mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr style="background:#F8F8F8;border-bottom:1px solid #E0E0E0;">
                                <th style="padding:0.75rem 1.25rem;font-weight:600;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#4D4D4D;">Time</th>
                                <th style="padding:0.75rem 1.25rem;font-weight:600;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#4D4D4D;">Patient</th>
                                <th style="padding:0.75rem 1.25rem;font-weight:600;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#4D4D4D;">Service</th>
                                <th style="padding:0.75rem 1.25rem;font-weight:600;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#4D4D4D;">Status</th>
                            </tr>
                        </thead>
                        <tbody>`;
                appts.forEach(a => {
                    const bStyle = badgeStyle[a.status] || badgeStyle.completed;
                    html += `<tr style="border-bottom:1px solid #F8F8F8;">
                        <td style="padding:0.85rem 1.25rem;font-weight:600;">${a.appointment_time || ''}</td>
                        <td style="padding:0.85rem 1.25rem;">${(a.first_name||'')+' '+(a.last_name||'')}</td>
                        <td style="padding:0.85rem 1.25rem;color:#4D4D4D;">${a.service_name || ''}</td>
                        <td style="padding:0.85rem 1.25rem;">
                            <span style="display:inline-block;padding:0.25em 0.75em;border-radius:9999px;font-size:0.75rem;font-weight:600;${bStyle}">
                                ${a.status.charAt(0).toUpperCase()+a.status.slice(1)}
                            </span>
                        </td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
            }
            $('#dayModalBody').html(html);
        }).fail(function() {
            $('#dayModalBody').html('<div class="text-center py-4"><p class="text-danger mb-0"><i class="bi bi-exclamation-circle me-1"></i>Could not load appointments.</p></div>');
        });
    }

    // Pre-fill block modal date from day modal
    $('#dayModalBlockBtn').click(function () {
        const d = $(this).data('date');
        $('#block_date').val(d);
        bootstrap.Modal.getInstance(document.getElementById('dayModal')).hide();
        setTimeout(() => new bootstrap.Modal(document.getElementById('blockModal')).show(), 300);
    });

    // ── Time slot picker ─────────────────────────────────────────────────────
    function toggleTimePicker(target) {
        const dd = document.getElementById(target + 'TimeDropdown');
        const other = target === 'start' ? 'endTimeDropdown' : 'startTimeDropdown';
        document.getElementById(other).classList.remove('open');
        dd.classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.time-slot-picker')) {
            document.querySelectorAll('.time-slot-dropdown').forEach(d => d.classList.remove('open'));
        }
    });
    document.querySelectorAll('.time-slot-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.target;
            const val = this.dataset.val;
            const label = this.textContent.trim();
            document.getElementById(target + '_time_hidden').value = val + ':00';
            document.getElementById(target + 'TimeLabel').textContent = label;
            document.getElementById(target + 'TimeDropdown').classList.remove('open');
            document.getElementById(target + 'TimeDisplay').classList.add('selected');
            document.querySelectorAll(`[data-target="${target}"]`).forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // ── Toggle time fields ───────────────────────────────────────────────────
    $('#is_full_day').change(function () {
        if ($(this).is(':checked')) {
            $('#timeFields').hide();
        } else {
            $('#timeFields').show();
        }
    });

    // ── Save Block ───────────────────────────────────────────────────────────
    $('#saveBlock').click(function () {
        const fullDay = $('#is_full_day').is(':checked');
        if (!fullDay) {
            const st = $('#start_time_hidden').val();
            const et = $('#end_time_hidden').val();
            if (!st || !et) {
                showAlert('#blockModalAlert', 'danger', 'Please select both Start Time and End Time.');
                return;
            }
        }
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
