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
$blocked_full_dates = []; // Track full-day blocked dates for background styling
$blocked_dates_info = []; // Track blocked dates with reasons for display
while ($row = $calendar_blocks->fetch_assoc()) {
    if ($row['is_full_day']) {
        $blocked_full_dates[] = $row['block_date'];
        $blocked_dates_info[$row['block_date']] = $row['reason'] ?: 'Blocked';
    }
    // Build title with reason
    $reasonText = $row['reason'] ?: 'Blocked';
    $title = '🚫 ' . $reasonText;
    
    // We show block info via overlay in the day column, so hide the all-day event
    // but keep it in case the view is list/month where overlay doesn't work
    $block_events[] = [
        'title'           => $title,
        'start'           => $row['block_date'],
        'backgroundColor' => '#dc3545',
        'borderColor'     => '#dc3545',
        'textColor'       => '#fff',
        'allDay'          => true,
        'display'         => 'background',  // Use background display so it doesn't show as event bar
        'extendedProps'   => [
            'reason'    => $row['reason'],
            'isBlocked' => true,
        ],
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
    $colors  = ['approved' => '#198754', 'pending' => '#29ABE2', 'completed' => '#6c757d'];
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
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental Clinic
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

                <!-- Blocked Dates Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Blocked Dates</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($upcoming->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="padding: 0.85rem 1.25rem;">Date</th>
                                        <th style="padding: 0.85rem 1.25rem;">Time</th>
                                        <th style="padding: 0.85rem 1.25rem;">Reason</th>
                                        <th style="padding: 0.85rem 1.25rem;">Created By</th>
                                        <th style="padding: 0.85rem 1.25rem; text-align: right; width: 80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($block = $upcoming->fetch_assoc()): ?>
                                    <tr id="block-row-<?php echo $block['block_id']; ?>" 
                                        class="block-row-clickable"
                                        style="cursor: pointer;"
                                        data-id="<?php echo $block['block_id']; ?>"
                                        data-date="<?php echo $block['block_date']; ?>"
                                        data-fullday="<?php echo $block['is_full_day']; ?>"
                                        data-start="<?php echo $block['start_time']; ?>"
                                        data-end="<?php echo $block['end_time']; ?>"
                                        data-reason="<?php echo htmlspecialchars($block['reason'] ?? ''); ?>">
                                        <td style="padding: 0.85rem 1.25rem;">
                                            <strong><?php echo date('M j, Y', strtotime($block['block_date'])); ?></strong>
                                            <br><small class="text-muted"><?php echo date('l', strtotime($block['block_date'])); ?></small>
                                        </td>
                                        <td style="padding: 0.85rem 1.25rem;">
                                            <?php if ($block['is_full_day']): ?>
                                                <span class="badge bg-danger">Full Day</span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <?php echo date('g:i A', strtotime($block['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($block['end_time'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.85rem 1.25rem;">
                                            <?php echo htmlspecialchars($block['reason'] ?: '—'); ?>
                                        </td>
                                        <td style="padding: 0.85rem 1.25rem;">
                                            <?php echo htmlspecialchars(($block['first_name'] ?? '') . ' ' . ($block['last_name'] ?? '')); ?>
                                        </td>
                                        <td style="padding: 0.85rem 1.25rem; text-align: right;" onclick="event.stopPropagation();">
                                            <button class="btn btn-sm btn-outline-danger delete-block-btn"
                                                data-id="<?php echo $block['block_id']; ?>"
                                                data-date="<?php echo date('M j, Y', strtotime($block['block_date'])); ?>"
                                                title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                            No upcoming blocked dates
                        </div>
                        <?php endif; ?>
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
                    <h5 class="modal-title"><i class="bi bi-calendar-x-fill me-2"></i><span id="blockModalTitle">Block Schedule</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="blockModalAlert"></div>
                    <form id="blockForm">
                        <input type="hidden" name="block_id" id="block_id" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="block_date" id="block_date" required>
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
                            <input type="text" class="form-control" name="reason" id="block_reason" placeholder="e.g. Clinic maintenance, Holiday, Staff meeting">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveBlock">
                        <i class="bi bi-calendar-x me-1"></i><span id="saveBlockText">Block Date</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Day Detail Modal -->
    <div class="modal fade" id="dayModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:8px; border:none; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb; padding:1rem 1.25rem;">
                    <h5 class="modal-title fw-bold" id="dayModalTitle" style="font-size:1rem;">
                        <i class="bi bi-calendar3 me-2"></i>Schedule for <span id="dayModalDate"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="dayModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="text-muted mt-2 mb-0" style="font-size:0.875rem;">Loading...</p>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb; padding:0.75rem 1rem; gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger btn-sm" id="dayModalBlockBtn">
                        <i class="bi bi-calendar-x me-1"></i>Block This Date
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    const calendarEvents = <?php echo json_encode($all_events); ?>;
    const blockedFullDates = <?php echo json_encode($blocked_full_dates); ?>;
    const blockedDatesInfo = <?php echo json_encode($blocked_dates_info); ?>;

    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    // ── Full Calendar ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

        const calEl = document.getElementById('clinicCalendar');
        const isMobile = window.innerWidth < 768;
        const calendar = new FullCalendar.Calendar(calEl, {
            initialView: isMobile ? 'listWeek' : 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            height: 'auto',
            dayMaxEvents: 2,
            dayMaxEventRows: 3,
            hiddenDays: [0],  // Hide Sunday (0 = Sunday)
            slotMinTime: '08:00:00',
            slotMaxTime: '17:00:00',
            slotDuration: '00:30:00',  // 30 minute slots
            slotLabelInterval: '00:30:00',  // Label every 30 minutes
            slotLabelContent: function(arg) {
                // Format as "8:00 - 8:30" (shorter format)
                const startDate = arg.date;
                const startHour = startDate.getHours();
                const startMin = startDate.getMinutes();
                const endMin = startMin + 30;
                const endHour = endMin >= 60 ? startHour + 1 : startHour;
                const endMinutes = endMin >= 60 ? 0 : endMin;
                
                const formatTime = (h, m) => {
                    const hour = h % 12 || 12;
                    return hour + ':' + (m < 10 ? '0' : '') + m;
                };
                const ampm = startHour >= 12 ? 'pm' : 'am';
                return formatTime(startHour, startMin) + '-' + formatTime(endHour, endMinutes) + ampm;
            },
            expandRows: true,  // Expand rows to fill available height
            allDaySlot: true,
            allDayText: '',  // Remove "all-day" text to prevent overlap
            nowIndicator: true,
            businessHours: [
                {
                    daysOfWeek: [1, 2, 3, 4, 5], // Mon-Fri
                    startTime: '13:00',
                    endTime: '15:30'
                },
                {
                    daysOfWeek: [6], // Saturday
                    startTime: '09:00',
                    endTime: '12:00'
                }
            ],
            events: calendarEvents,
            eventContent: function (arg) {
                const service = arg.event.extendedProps.service;
                if (service) {
                    // Appointment event: show name + service with compact layout
                    return { html: `<div class="fc-event-content-custom">
                        <div class="fc-event-time-custom">${arg.timeText || ''}</div>
                        <div class="fc-event-title-custom">${arg.event.title}</div>
                        <div class="fc-event-service-custom">${service}</div>
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
                const classes = [];
                if (arg.date.getDay() === 0) classes.push('fc-day-sunday');
                // Check if this date is a full-day blocked date (use local date to avoid timezone issues)
                const y = arg.date.getFullYear();
                const m = String(arg.date.getMonth() + 1).padStart(2, '0');
                const d = String(arg.date.getDate()).padStart(2, '0');
                const dateStr = `${y}-${m}-${d}`;
                if (blockedFullDates.includes(dateStr)) classes.push('fc-day-blocked');
                return classes;
            },
            viewDidMount: function (arg) {
                // Apply blocked styling to timegrid columns in week/day views
                applyBlockedDayStyling();
            },
            datesSet: function (arg) {
                // Re-apply after date navigation
                applyBlockedDayStyling();
            }
        });
        calendar.render();

        // Helper function to apply blocked day styling to timegrid columns
        function applyBlockedDayStyling() {
            setTimeout(function() {
                // First remove any existing blocked classes and reason overlays/labels to reset state
                document.querySelectorAll('.fc-day-blocked').forEach(function(el) {
                    el.classList.remove('fc-day-blocked');
                });
                document.querySelectorAll('.blocked-reason-label').forEach(function(el) {
                    el.remove();
                });
                
                // Then apply only to specifically blocked dates
                blockedFullDates.forEach(function(dateStr) {
                    const reason = blockedDatesInfo[dateStr] || 'Blocked';
                    
                    // Target timegrid columns (week/day view) - just red background, no overlay
                    const cols = document.querySelectorAll('.fc-timegrid-col[data-date="' + dateStr + '"]');
                    cols.forEach(function(col) {
                        col.classList.add('fc-day-blocked');
                    });
                    
                    // Also target header cells by exact data-date match
                    const headers = document.querySelectorAll('.fc-col-header-cell[data-date="' + dateStr + '"]');
                    headers.forEach(function(cell) {
                        cell.classList.add('fc-day-blocked');
                    });
                    
                    // Month view cells - show reason label
                    const dayCells = document.querySelectorAll('.fc-daygrid-day[data-date="' + dateStr + '"]');
                    dayCells.forEach(function(cell) {
                        cell.classList.add('fc-day-blocked');
                        // Add reason to month view cell
                        const dayFrame = cell.querySelector('.fc-daygrid-day-frame');
                        if (dayFrame && !dayFrame.querySelector('.blocked-reason-label')) {
                            const label = document.createElement('div');
                            label.className = 'blocked-reason-label';
                            label.innerHTML = '<i class="bi bi-slash-circle"></i> ' + reason;
                            dayFrame.appendChild(label);
                        }
                    });
                });
            }, 150);
        }

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
            const blocks = res.blocks || [];
            let html = '';
            
            // Display blocked schedules first if any
            if (blocks.length > 0) {
                // Hide the Block This Date button since date is already blocked
                $('#dayModalBlockBtn').hide();
                
                blocks.forEach((block, idx) => {
                    if (idx > 0) html += '<div style="margin:8px 0;"></div>';
                    html += `<div style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);border-radius:8px;padding:16px;border:1px solid #fecaca;margin:16px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                            <div style="width:32px;height:32px;background:#dc2626;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi bi-slash-circle" style="font-size:1rem;color:#fff;"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:0.95rem;font-weight:700;color:#991b1b;">
                                    <i class="bi bi-calendar-x me-1"></i>Blocked Schedule
                                </div>
                                <div style="font-size:0.75rem;color:#7f1d1d;">This date is unavailable for appointments</div>
                            </div>
                        </div>`;
                    if (block.reason) {
                        html += `<div style="background:#ffffff;border-radius:6px;padding:10px 12px;margin-bottom:8px;border-left:3px solid #dc2626;">
                            <div style="font-size:0.625rem;color:#991b1b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Reason</div>
                            <div style="font-size:0.875rem;color:#1f2937;line-height:1.4;">${block.reason}</div>
                        </div>`;
                    }
                    if (block.is_full_day == 1) {
                        html += `<div style="background:#ffffff;border-radius:6px;padding:10px 12px;border-left:3px solid #dc2626;">
                            <div style="font-size:0.625rem;color:#991b1b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Duration</div>
                            <div style="font-size:0.875rem;color:#1f2937;"><i class="bi bi-clock me-1"></i>Full Day</div>
                        </div>`;
                    } else if (block.start_time || block.end_time) {
                        html += `<div style="background:#ffffff;border-radius:6px;padding:10px 12px;border-left:3px solid #dc2626;">
                            <div style="font-size:0.625rem;color:#991b1b;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Time Range</div>
                            <div style="font-size:0.875rem;color:#1f2937;"><i class="bi bi-clock me-1"></i>${block.start_time || ''} - ${block.end_time || ''}</div>
                        </div>`;
                    }
                    html += `</div>`;
                });
            } else {
                // Show the Block This Date button when no blocks exist
                $('#dayModalBlockBtn').show();
            }
            
            if (appts.length === 0 && blocks.length === 0) {
                html += '<div class="text-center py-4"><i class="bi bi-calendar-x" style="font-size:2rem;color:#d1d5db;"></i><p class="text-muted mt-2 mb-0" style="font-size:0.875rem;">No appointments on this date.</p></div>';
            } else if (appts.length === 0) {
                html += `<div class="text-center py-3">
                    <i class="bi bi-check-circle" style="font-size:1.5rem;color:#9ca3af;"></i>
                    <p class="text-muted mb-0 mt-2" style="font-size:0.875rem;">No appointments scheduled</p>
                </div>`;
            } else {
                const badgeStyle = {
                    pending:   'background:#fff8e1;color:#b45309;border:1px solid #fde68a;',
                    approved:  'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;',
                    completed: 'background:#F8F8F8;color:#4D4D4D;border:1px solid #E0E0E0;',
                    cancelled: 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                    declined:  'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
                };
                html += `<div class="table-responsive">
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
        resetBlockModal();
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

    // ── Reset modal to "Add" mode ────────────────────────────────────────────
    function resetBlockModal() {
        $('#block_id').val('');
        $('#blockForm')[0].reset();
        $('#is_full_day').prop('checked', true);
        $('#timeFields').hide();
        $('#startTimeLabel').text('Select time');
        $('#endTimeLabel').text('Select time');
        $('#startTimeDisplay, #endTimeDisplay').removeClass('selected');
        document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('active'));
        $('#blockModalTitle').text('Block Schedule');
        $('#saveBlockText').text('Block Date');
        $('#blockModalAlert').html('');
    }

    // Reset modal when opened via "Block Schedule" button (add mode)
    $('[data-bs-target="#blockModal"]').click(function() {
        resetBlockModal();
    });

    // ── Clickable Block Row (Edit) ───────────────────────────────────────────
    $(document).on('click', '.block-row-clickable', function () {
        resetBlockModal();
        
        const id = $(this).data('id');
        const date = $(this).data('date');
        const isFullDay = $(this).data('fullday') == 1;
        const startTime = $(this).data('start');
        const endTime = $(this).data('end');
        const reason = $(this).data('reason');

        // Set modal to edit mode
        $('#block_id').val(id);
        $('#block_date').val(date);
        $('#block_reason').val(reason);
        $('#blockModalTitle').text('Edit Block');
        $('#saveBlockText').text('Update Block');

        if (isFullDay) {
            $('#is_full_day').prop('checked', true);
            $('#timeFields').hide();
        } else {
            $('#is_full_day').prop('checked', false);
            $('#timeFields').show();
            
            // Set start time
            if (startTime) {
                const startVal = startTime.substring(0, 5);
                $('#start_time_hidden').val(startTime);
                const startLabel = formatTime(startVal);
                $('#startTimeLabel').text(startLabel);
                $('#startTimeDisplay').addClass('selected');
                document.querySelectorAll('[data-target="start"]').forEach(b => {
                    b.classList.toggle('active', b.dataset.val === startVal);
                });
            }
            
            // Set end time
            if (endTime) {
                const endVal = endTime.substring(0, 5);
                $('#end_time_hidden').val(endTime);
                const endLabel = formatTime(endVal);
                $('#endTimeLabel').text(endLabel);
                $('#endTimeDisplay').addClass('selected');
                document.querySelectorAll('[data-target="end"]').forEach(b => {
                    b.classList.toggle('active', b.dataset.val === endVal);
                });
            }
        }

        new bootstrap.Modal(document.getElementById('blockModal')).show();
    });

    // Helper to format time as "h:mm AM/PM"
    function formatTime(timeStr) {
        const [h, m] = timeStr.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const hour = h % 12 || 12;
        return `${hour}:${m.toString().padStart(2, '0')} ${ampm}`;
    }

    // ── Save Block (Add or Update) ───────────────────────────────────────────
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
        
        const blockId = $('#block_id').val();
        const isEdit = blockId !== '';
        const apiUrl = isEdit ? '../api/update-block.php' : '../api/block-schedule.php';
        const formData = $('#blockForm').serialize();
        
        $.post(apiUrl, formData, function (res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>' + res.message);
                bootstrap.Modal.getInstance(document.getElementById('blockModal')).hide();
                resetBlockModal();
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
