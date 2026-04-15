<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/schedule.php');
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

$hours = [
    'weekday_start' => '08:00',
    'weekday_end' => '12:00',
    'saturday_start' => '09:00',
    'saturday_end' => '12:00',
];

$hourKeys = [
    "dentist_{$dentist_id}_weekday_start",
    "dentist_{$dentist_id}_weekday_end",
    "dentist_{$dentist_id}_saturday_start",
    "dentist_{$dentist_id}_saturday_end",
];

$in = "'" . implode("','", array_map([$db, 'real_escape_string'], $hourKeys)) . "'";
$hoursRes = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
while ($row = $hoursRes->fetch_assoc()) {
    if ($row['setting_key'] === "dentist_{$dentist_id}_weekday_start") $hours['weekday_start'] = substr($row['setting_value'], 0, 5);
    if ($row['setting_key'] === "dentist_{$dentist_id}_weekday_end") $hours['weekday_end'] = substr($row['setting_value'], 0, 5);
    if ($row['setting_key'] === "dentist_{$dentist_id}_saturday_start") $hours['saturday_start'] = substr($row['setting_value'], 0, 5);
    if ($row['setting_key'] === "dentist_{$dentist_id}_saturday_end") $hours['saturday_end'] = substr($row['setting_value'], 0, 5);
}

$upcoming = $db->prepare("
    SELECT bs.*,
           u.first_name AS creator_first_name,
           u.last_name AS creator_last_name,
           u.role AS creator_role
    FROM blocked_schedules bs
    LEFT JOIN users u ON u.user_id = bs.created_by
    WHERE bs.block_date >= CURDATE()
      AND (
            bs.created_by = ?
            OR bs.created_by IS NULL
            OR u.role <> 'dentist'
          )
    ORDER BY bs.block_date ASC, bs.start_time ASC
");
$upcoming->bind_param("i", $dentist_id);
$upcoming->execute();
$upcomingBlocks = $upcoming->get_result();

$calendar_blocks = $db->prepare("
    SELECT bs.block_id, bs.block_date, bs.is_full_day, bs.reason, bs.start_time, bs.end_time, bs.created_by,
           u.role AS creator_role
    FROM blocked_schedules bs
    LEFT JOIN users u ON u.user_id = bs.created_by
    WHERE (
            bs.created_by = ?
            OR bs.created_by IS NULL
            OR u.role <> 'dentist'
          )
      AND bs.block_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
");
$calendar_blocks->bind_param("i", $dentist_id);
$calendar_blocks->execute();
$blocksRes = $calendar_blocks->get_result();

$block_events = [];
$blocked_full_dates = [];
$blocked_dates_info = [];
while ($row = $blocksRes->fetch_assoc()) {
    $isDentistOwned = (int)($row['created_by'] ?? 0) === $dentist_id;
    $blockReason = trim((string)($row['reason'] ?? '')) ?: 'Unavailable';
    $blockTitle = $isDentistOwned ? 'Unavailable' : 'Clinic Blocked';

    if ((int)$row['is_full_day'] === 1) {
        if (!in_array($row['block_date'], $blocked_full_dates, true)) {
            $blocked_full_dates[] = $row['block_date'];
        }
        if (!isset($blocked_dates_info[$row['block_date']])) {
            $blocked_dates_info[$row['block_date']] = $blockReason;
        } elseif (stripos($blocked_dates_info[$row['block_date']], $blockReason) === false) {
            $blocked_dates_info[$row['block_date']] .= ' / ' . $blockReason;
        }
    }

    if ((int)$row['is_full_day'] === 1) {
        $block_events[] = [
            'id' => 'block_' . $row['block_id'],
            'title' => $blockTitle,
            'start' => $row['block_date'],
            'allDay' => true,
            'display' => 'background',
            'backgroundColor' => '#dc3545',
            'borderColor' => '#dc3545',
            'extendedProps' => [
                'isBlocked' => true,
                'reason' => $blockReason,
                'block_id' => $row['block_id'],
                'editable' => $isDentistOwned,
            ],
        ];
    } else {
        $start = $row['block_date'] . 'T' . $row['start_time'];
        $end = $row['block_date'] . 'T' . $row['end_time'];
        $block_events[] = [
            'id' => 'block_' . $row['block_id'],
            'title' => $blockTitle,
            'start' => $start,
            'end' => $end,
            'allDay' => false,
            'backgroundColor' => '#dc3545',
            'borderColor' => '#dc3545',
            'textColor' => '#fff',
            'extendedProps' => [
                'isBlocked' => true,
                'reason' => $blockReason,
                'block_id' => $row['block_id'],
                'editable' => $isDentistOwned,
            ],
        ];
    }
}

$apptStmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, s.service_name, s.duration_minutes, u.first_name, u.last_name
    FROM dentist_appointment_assignments da
    JOIN appointments a ON a.appointment_id = da.appointment_id
    JOIN users u ON u.user_id = a.user_id
    JOIN services s ON s.service_id = a.service_id
    WHERE da.dentist_id = ?
      AND a.appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
      AND a.status IN ('pending','approved','completed')
    ORDER BY a.appointment_date, a.appointment_time
");
$apptStmt->bind_param("i", $dentist_id);
$apptStmt->execute();
$apptsRes = $apptStmt->get_result();
$appt_events = [];
while ($row = $apptsRes->fetch_assoc()) {
    $startDT = $row['appointment_date'] . 'T' . $row['appointment_time'];
    $endDT = $row['appointment_date'] . 'T' . date('H:i:s', strtotime($row['appointment_time']) + ((int)$row['duration_minutes'] * 60));
    $color = $row['status'] === 'completed' ? '#6c757d' : '#29ABE2';
    $appt_events[] = [
        'id' => 'appt_' . $row['appointment_id'],
        'title' => $row['first_name'] . ' ' . $row['last_name'],
        'start' => $startDT,
        'end' => $endDT,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#fff',
        'extendedProps' => [
            'service' => $row['service_name'],
            'status' => $row['status'],
            'isBlocked' => false,
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
    <title>My Schedule - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-schedule.css" rel="stylesheet">
    <link href="../assets/css/dentist-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <style>
        .availability-card {
            border: 1px solid #e8edf3;
            border-radius: 14px;
            overflow: hidden;
        }
        .availability-card .card-header {
            background: linear-gradient(135deg, #f8fcff 0%, #eef8ff 100%);
            border-bottom: 1px solid #e4edf5;
            padding: 0.9rem 1.1rem;
        }
        .availability-day {
            border: 1px solid #e8edf3;
            border-radius: 12px;
            padding: 0.85rem;
            background: #fff;
            height: 100%;
        }
        .availability-day-title {
            font-weight: 700;
            font-size: 0.92rem;
            color: #1f2937;
            margin-bottom: 0.65rem;
        }
        .availability-card .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.35rem;
        }
        .availability-card .form-control[type="time"] {
            border-radius: 10px;
            min-height: 42px;
            background: #fbfdff;
        }
    </style>
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
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-schedule.php"><i class="bi bi-clock"></i> My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-patients.php"><i class="bi bi-people"></i> My Patients</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="notifications.php"><i class="bi bi-bell"></i> Notifications <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/dentist-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">My Schedule</h2>
                        <p class="text-muted mb-0">Set your hours, blocked schedule, and view your daily/weekly calendar.</p>
                    </div>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#blockModal">
                        <i class="bi bi-calendar-x-fill me-1"></i> Blocked Schedule
                    </button>
                </div>

                <div id="alertContainer" class="mb-3"></div>

                <div class="card mb-4 availability-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Set Availability Hours</h5>
                    </div>
                    <div class="card-body">
                        <form id="workingHoursForm" class="row g-3 align-items-stretch justify-content-center">
                            <div class="col-12 col-md-6">
                                <div class="availability-day">
                                    <div class="availability-day-title"><i class="bi bi-calendar-week me-1 text-primary"></i>Monday - Friday</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">Start</label>
                                            <input type="time" class="form-control" name="weekday_start" value="<?php echo htmlspecialchars($hours['weekday_start']); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">End</label>
                                            <input type="time" class="form-control" name="weekday_end" value="<?php echo htmlspecialchars($hours['weekday_end']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="availability-day">
                                    <div class="availability-day-title"><i class="bi bi-sun me-1 text-success"></i>Saturday</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">Start</label>
                                            <input type="time" class="form-control" name="saturday_start" value="<?php echo htmlspecialchars($hours['saturday_start']); ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">End</label>
                                            <input type="time" class="form-control" name="saturday_end" value="<?php echo htmlspecialchars($hours['saturday_end']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-1">
                                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Sunday is closed.</small>
                                <button type="submit" class="btn btn-primary px-3"><i class="bi bi-save me-1"></i>Save Availability Hours</button>
                            </div>
                        </form>
                    </div>
                </div>

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

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Blocked Schedules</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($upcomingBlocks->num_rows > 0): ?>
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
                                        <?php while ($block = $upcomingBlocks->fetch_assoc()): ?>
                                            <?php $isDentistOwned = (int)($block['created_by'] ?? 0) === $dentist_id; ?>
                                            <tr class="<?php echo $isDentistOwned ? 'block-row-clickable' : ''; ?>"
                                                style="cursor:<?php echo $isDentistOwned ? 'pointer' : 'default'; ?>;"
                                                data-id="<?php echo $block['block_id']; ?>"
                                                data-date="<?php echo $block['block_date']; ?>"
                                                data-fullday="<?php echo $block['is_full_day']; ?>"
                                                data-start="<?php echo $block['start_time']; ?>"
                                                data-end="<?php echo $block['end_time']; ?>"
                                                data-reason="<?php echo htmlspecialchars($block['reason'] ?? ''); ?>"
                                                id="block-row-<?php echo $block['block_id']; ?>">
                                                <td style="padding:0.85rem 1.25rem;">
                                                    <strong><?php echo date('M j, Y', strtotime($block['block_date'])); ?></strong><br>
                                                    <small class="text-muted"><?php echo date('l', strtotime($block['block_date'])); ?></small>
                                                </td>
                                                <td style="padding:0.85rem 1.25rem;">
                                                    <?php if ((int)$block['is_full_day'] === 1): ?>
                                                        <span class="badge bg-danger">Full Day</span>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo date('g:i A', strtotime($block['start_time'])); ?> - <?php echo date('g:i A', strtotime($block['end_time'])); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:0.85rem 1.25rem;"><?php echo htmlspecialchars($block['reason'] ?: '—'); ?></td>
                                                <td style="padding:0.85rem 1.25rem;">
                                                    <?php if ($isDentistOwned): ?>
                                                        <span class="badge bg-primary">You</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-dark">Admin / Clinic</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:0.85rem 1.25rem; text-align:right;" onclick="event.stopPropagation();">
                                                    <?php if ($isDentistOwned): ?>
                                                        <button class="btn btn-sm btn-outline-danger delete-block-btn"
                                                            data-id="<?php echo $block['block_id']; ?>"
                                                            data-date="<?php echo date('M j, Y', strtotime($block['block_date'])); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Read-only</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                                No upcoming unavailable blocks
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="blockModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-x-fill me-2"></i><span id="blockModalTitle">Blocked Schedule</span></h5>
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
                            <label class="form-check-label fw-semibold" for="is_full_day">Blocked whole day</label>
                        </div>
                        <div id="timeFields" style="display:none;">
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time" id="start_time">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time" id="end_time">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" name="reason" id="block_reason" placeholder="e.g. Lunch break, Day off, Seminar">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveBlock">
                        <i class="bi bi-calendar-x me-1"></i><span id="saveBlockText">Save</span>
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
    const dentistHours = <?php echo json_encode($hours); ?>;

    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    function toFull(hm) { return hm.length === 5 ? hm + ':00' : hm; }

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
            dayMaxEvents: true,
            dayMaxEventRows: true,
            hiddenDays: [0],
            slotMinTime: '08:00:00',
            slotMaxTime: '21:00:00',
            slotDuration: '00:30:00',
            slotLabelInterval: '00:30:00',
            slotLabelContent: function(arg) {
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
            expandRows: true,
            allDaySlot: true,
            allDayText: '',
            nowIndicator: true,
            businessHours: [
                { daysOfWeek: [1,2,3,4,5], startTime: toFull(dentistHours.weekday_start), endTime: toFull(dentistHours.weekday_end) },
                { daysOfWeek: [6], startTime: toFull(dentistHours.saturday_start), endTime: toFull(dentistHours.saturday_end) }
            ],
            events: calendarEvents,
            eventContent: function(arg) {
                if (arg.event.extendedProps && arg.event.extendedProps.isBlocked) return true;
                const service = arg.event.extendedProps.service || '';
                if (arg.view.type === 'dayGridMonth') {
                    return { html: '<div class="fc-event-content-custom"><div class="fc-event-title-custom">' + (arg.timeText ? (arg.timeText + ' ') : '') + arg.event.title + '</div></div>' };
                }
                return { html: '<div class="fc-event-content-custom"><div class="fc-event-time-custom">' + (arg.timeText || '') + '</div><div class="fc-event-title-custom">' + arg.event.title + '</div><div class="fc-event-service-custom">' + service + '</div></div>' };
            },
            dateClick: function(info) {
                $('#block_date').val(info.dateStr);
                new bootstrap.Modal(document.getElementById('blockModal')).show();
            },
            dayCellClassNames: function (arg) {
                const classes = [];
                const y = arg.date.getFullYear();
                const m = String(arg.date.getMonth() + 1).padStart(2, '0');
                const d = String(arg.date.getDate()).padStart(2, '0');
                const dateStr = `${y}-${m}-${d}`;
                if (blockedFullDates.includes(dateStr)) classes.push('fc-day-blocked');
                return classes;
            },
            datesSet: function () { applyBlockedDayStyling(); },
            viewDidMount: function () { applyBlockedDayStyling(); }
        });
        calendar.render();

        function applyBlockedDayStyling() {
            setTimeout(function () {
                document.querySelectorAll('.blocked-reason-label').forEach(el => el.remove());
                blockedFullDates.forEach(function(dateStr) {
                    const reason = blockedDatesInfo[dateStr] || 'Unavailable';
                    const dayCells = document.querySelectorAll('.fc-daygrid-day[data-date="' + dateStr + '"]');
                    dayCells.forEach(function(cell) {
                        cell.classList.add('fc-day-blocked');
                        const frame = cell.querySelector('.fc-daygrid-day-frame');
                        if (frame && !frame.querySelector('.blocked-reason-label')) {
                            const label = document.createElement('div');
                            label.className = 'blocked-reason-label';
                            label.innerHTML = '<i class="bi bi-slash-circle"></i> ' + reason;
                            frame.appendChild(label);
                        }
                    });
                });
            }, 120);
        }
    });

    $('#workingHoursForm').on('submit', function (e) {
        e.preventDefault();
        $.post('../api/dentist-working-hours.php', $(this).serialize(), function(res){
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>' + res.message + ' Refreshing view...');
                setTimeout(() => location.reload(), 900);
            } else {
                showAlert('#alertContainer', 'danger', res.message || 'Failed to save working hours.');
            }
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Server error while saving hours.'));
    });

    $('#is_full_day').change(function () {
        $('#timeFields').toggle(!$(this).is(':checked'));
    });

    function resetBlockModal() {
        $('#block_id').val('');
        $('#blockForm')[0].reset();
        $('#is_full_day').prop('checked', true);
        $('#timeFields').hide();
        $('#blockModalTitle').text('Blocked Schedule');
        $('#saveBlockText').text('Save');
        $('#blockModalAlert').html('');
    }

    $('[data-bs-target="#blockModal"]').click(function(){ resetBlockModal(); });

    $(document).on('click', '.block-row-clickable', function () {
        resetBlockModal();
        const id = $(this).data('id');
        const date = $(this).data('date');
        const isFullDay = $(this).data('fullday') == 1;
        const startTime = $(this).data('start');
        const endTime = $(this).data('end');
        const reason = $(this).data('reason');

        $('#block_id').val(id);
        $('#block_date').val(date);
        $('#block_reason').val(reason);
        $('#blockModalTitle').text('Edit Blocked Schedule');
        $('#saveBlockText').text('Update');

        if (!isFullDay) {
            $('#is_full_day').prop('checked', false);
            $('#timeFields').show();
            if (startTime) $('#start_time').val(String(startTime).substring(0,5));
            if (endTime) $('#end_time').val(String(endTime).substring(0,5));
        }
        new bootstrap.Modal(document.getElementById('blockModal')).show();
    });

    $('#saveBlock').click(function () {
        const fullDay = $('#is_full_day').is(':checked');
        if (!fullDay) {
            const st = $('#start_time').val();
            const et = $('#end_time').val();
            if (!st || !et) {
                showAlert('#blockModalAlert', 'danger', 'Please provide start and end time for partial blocks.');
                return;
            }
            if (st >= et) {
                showAlert('#blockModalAlert', 'danger', 'Start time must be before end time.');
                return;
            }
        }

        const isEdit = $('#block_id').val() !== '';
        const apiUrl = isEdit ? '../api/update-block.php' : '../api/block-schedule.php';
        $.post(apiUrl, $('#blockForm').serialize(), function(res){
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>' + res.message);
                bootstrap.Modal.getInstance(document.getElementById('blockModal')).hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showAlert('#blockModalAlert', 'danger', res.message || 'Failed to save block.');
            }
        }, 'json').fail(() => showAlert('#blockModalAlert', 'danger', 'Server error.'));
    });

    $(document).on('click', '.delete-block-btn', function () {
        const id = $(this).data('id');
        const date = $(this).data('date');
        if (!confirm('Remove unavailable block for ' + date + '?')) return;
        $.post('../api/delete-block.php', { block_id: id }, function(res){
            if (res.success) {
                showAlert('#alertContainer', 'success', '<i class="bi bi-check-circle me-2"></i>Block removed successfully.');
                $('#block-row-' + id).fadeOut(300, function(){ $(this).remove(); });
            } else {
                showAlert('#alertContainer', 'danger', res.message || 'Failed to delete block.');
            }
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Server error.'));
    });

    </script>
</body>
</html>
