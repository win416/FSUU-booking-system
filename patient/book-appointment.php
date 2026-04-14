<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Unread notifications count for sidebar badge
$unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user['user_id']);
$unread_stmt->execute();
$unread_count = (int) $unread_stmt->get_result()->fetch_assoc()['count'];

// Active services
$services_result = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY service_name");
$services = $services_result->fetch_all(MYSQLI_ASSOC);

// Medical info
$med_stmt = $db->prepare("SELECT * FROM medical_info WHERE user_id = ?");
$med_stmt->bind_param("i", $user['user_id']);
$med_stmt->execute();
$medical_info = $med_stmt->get_result()->fetch_assoc() ?? [];

// Clinic hours display (auto-sync from admin settings)
$clinicHours = [
    'weekday_start' => '08:00',
    'weekday_end' => '21:00',
    'wednesday_start' => '08:00',
    'wednesday_end' => '17:00',
    'saturday_start' => '08:00',
    'saturday_end' => '16:00',
];
$clinicStmt = $db->prepare("
    SELECT setting_key, setting_value
    FROM system_settings
    WHERE setting_key IN (
        'weekday_start', 'weekday_end',
        'wednesday_start', 'wednesday_end',
        'saturday_start', 'saturday_end'
    )
");
if ($clinicStmt) {
    $clinicStmt->execute();
    $clinicRes = $clinicStmt->get_result();
    while ($row = $clinicRes->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $clinicHours)) {
            $clinicHours[$row['setting_key']] = substr((string)$row['setting_value'], 0, 5);
        }
    }
}

$formatClock = static function($time24) {
    $dt = DateTime::createFromFormat('H:i', substr((string)$time24, 0, 5));
    return $dt ? $dt->format('g:i A') : strtoupper((string)$time24);
};
$clinicHoursNotice = 'M/TH, T/F: ' . $formatClock($clinicHours['weekday_start']) . ' - ' . $formatClock($clinicHours['weekday_end'])
    . ' | Wednesday: ' . $formatClock($clinicHours['wednesday_start']) . ' - ' . $formatClock($clinicHours['wednesday_end'])
    . ' | Saturday: ' . $formatClock($clinicHours['saturday_start']) . ' - ' . $formatClock($clinicHours['saturday_end'])
    . ' | Sunday: Closed';

/**
 * Map service name keywords to Bootstrap icons.
 */
function serviceIcon(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'clean') || str_contains($n, 'prophyl'))  return 'bi-stars';
    if (str_contains($n, 'extract') || str_contains($n, 'remov'))  return 'bi-scissors';
    if (str_contains($n, 'fill') || str_contains($n, 'restor'))    return 'bi-patch-plus';
    if (str_contains($n, 'ortho') || str_contains($n, 'brace'))    return 'bi-align-center';
    if (str_contains($n, 'root') || str_contains($n, 'canal'))     return 'bi-activity';
    if (str_contains($n, 'crown') || str_contains($n, 'cap'))      return 'bi-gem';
    if (str_contains($n, 'whiten') || str_contains($n, 'bleach'))  return 'bi-brightness-high';
    if (str_contains($n, 'consult') || str_contains($n, 'exam'))   return 'bi-clipboard2-pulse';
    if (str_contains($n, 'x-ray') || str_contains($n, 'xray'))    return 'bi-camera';
    if (str_contains($n, 'implant'))                                return 'bi-plus-circle-dotted';
    return 'bi-hospital';
}

/**
 * Map service name keywords to background image paths.
 */
function serviceBgImage(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'clean') || str_contains($n, 'prophyl'))  return '../img/cleaning.jpg';
    if (str_contains($n, 'consult') || str_contains($n, 'exam'))   return '../img/consultation.jpg';
    if (str_contains($n, 'extract') || str_contains($n, 'remov'))  return '../img/extractions.jpg';
    if (str_contains($n, 'fill') || str_contains($n, 'restor') || str_contains($n, 'pasta'))    return '../img/filling.jpg';
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/patient-dashboard.css">
    <link rel="stylesheet" href="../assets/css/patient-book-appointment.css?v=3">
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
            <li class="nav-item"><a class="nav-link active" href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a></li>
            <li class="nav-item"><a class="nav-link" href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a></li>
            <li class="nav-item">
                <a class="nav-link" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
        </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <?php include '../includes/patient-topbar.php'; ?>
        <div class="container-fluid my-4">

            <!-- Page Header -->
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Book an Appointment</h4>
                <p class="text-muted mb-0">Follow the steps below to schedule your dental visit.</p>
            </div>

            <!-- Toast Alert -->
            <div id="bookingToast" class="alert d-none mb-3" role="alert" style="display:none!important"></div>

            <!-- Step Progress Bar -->
            <div class="booking-steps mb-4">
                <div class="step active" id="step-dot-1">
                    <div class="step-circle"><i class="bi bi-grid-1x2"></i></div>
                    <span class="step-label">Service</span>
                </div>
                <div class="step-line"></div>
                <div class="step" id="step-dot-2">
                    <div class="step-circle"><i class="bi bi-calendar3"></i></div>
                    <span class="step-label">Date & Time</span>
                </div>
                <div class="step-line"></div>
                <div class="step" id="step-dot-3">
                    <div class="step-circle"><i class="bi bi-clipboard2-check"></i></div>
                    <span class="step-label">Confirm</span>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left: Step Panels -->
                <div class="col-lg-8" id="panelCol">

                    <!-- ── Step 1: Select Service ─────────────────────────────── -->
                    <div class="booking-panel" id="step1">
                        <div class="booking-panel-header">
                            <span class="step-badge">1</span>
                            <h5 class="mb-0">Select a Service</h5>
                        </div>
                        <div class="booking-panel-body">
                            <?php if (empty($services)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-exclamation-circle fs-2 d-block mb-2"></i>
                                    No services are currently available.
                                </div>
                            <?php else: ?>
                            <div class="row g-3" id="servicesGrid">
                                <?php foreach ($services as $svc):
                                    $bgImg = serviceBgImage($svc['service_name']);
                                    $bgStyle = $bgImg ? "style=\"background-image:url('{$bgImg}')\"" : '';
                                    $hasBg = $bgImg ? ' has-bg-image' : '';
                                ?>
                                <div class="col-6 col-md-3">
                                    <div class="service-card<?php echo $hasBg; ?>"
                                         data-service-id="<?php echo $svc['service_id']; ?>"
                                         data-service-name="<?php echo htmlspecialchars($svc['service_name']); ?>"
                                         data-service-duration="<?php echo $svc['duration_minutes']; ?>"
                                         <?php echo $bgStyle; ?>>
                                        <?php if ($bgImg): ?>
                                        <div class="service-card-overlay"></div>
                                        <?php endif; ?>
                                        <div class="service-check"><i class="bi bi-check-circle-fill"></i></div>
                                        <h6 class="service-card-title"><?php echo htmlspecialchars($svc['service_name']); ?></h6>
                                        <p class="service-card-desc"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></p>
                                        <span class="service-card-duration">
                                            <i class="bi bi-clock me-1"></i><?php echo $svc['duration_minutes']; ?> mins
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Step 2: Date & Time ────────────────────────────────── -->
                    <div class="booking-panel d-none" id="step2">
                        <div class="booking-panel-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="step-badge">2</span>
                                <h5 class="mb-0">Choose Date &amp; Time</h5>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" id="changeServiceBtn">
                                <i class="bi bi-arrow-left me-1"></i>Change Service
                            </button>
                        </div>
                        <div class="booking-panel-body">
                            <!-- Operating hours notice -->
                            <div class="clinic-hours-notice mb-3">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Clinic Hours:</strong>
                                <?php echo htmlspecialchars($clinicHoursNotice); ?>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <div id="calendar"></div>
                                </div>
                                <div class="col-md-5">
                                    <div class="time-slots-panel">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-1 text-primary"></i>Available Slots</h6>
                                            <small class="text-muted" id="selectedDateLabel">Pick a date</small>
                                        </div>
                                        <div id="timeSlotsContainer">
                                            <div class="slots-placeholder">
                                                <i class="bi bi-calendar-event"></i>
                                                <span>Select a date on the calendar</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Step 3: Confirm & Medical Info ─────────────────────── -->
                    <div class="booking-panel d-none" id="step3">
                        <div class="booking-panel-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="step-badge">3</span>
                                <h5 class="mb-0">Medical Info &amp; Consent</h5>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" id="changeDateBtn">
                                <i class="bi bi-arrow-left me-1"></i>Change Time
                            </button>
                        </div>
                        <div class="booking-panel-body">
                            <form id="bookingForm" novalidate>

                                <p class="text-muted small mb-3">
                                    Please review and update your medical information below. This helps us provide safe dental care.
                                </p>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Allergies</label>
                                        <textarea class="form-control" name="allergies" rows="2"
                                                  placeholder="e.g. Penicillin, latex..."><?php echo htmlspecialchars($medical_info['allergies'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Medical Conditions</label>
                                        <textarea class="form-control" name="medical_conditions" rows="2"
                                                  placeholder="e.g. Diabetes, hypertension..."><?php echo htmlspecialchars($medical_info['medical_conditions'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Current Medications</label>
                                        <textarea class="form-control" name="medications" rows="2"
                                                  placeholder="List any medications you are taking..."><?php echo htmlspecialchars($medical_info['medications'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Additional Notes <span class="text-muted fw-normal">(optional)</span></label>
                                        <textarea class="form-control" name="notes" rows="2"
                                                  placeholder="Any concerns or special requests?"></textarea>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill me-1 text-primary"></i>Emergency Contact</h6>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Contact Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="emergency_contact_name"
                                               value="<?php echo htmlspecialchars($medical_info['emergency_contact_name'] ?? ''); ?>"
                                               placeholder="Full name" required>
                                        <div class="invalid-feedback">Please provide an emergency contact name.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="emergency_contact_number"
                                               value="<?php echo htmlspecialchars($medical_info['emergency_contact_number'] ?? ''); ?>"
                                               placeholder="e.g. 09xx-xxx-xxxx" required>
                                        <div class="invalid-feedback">Please provide an emergency contact number.</div>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="consent-box mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
                                        <label class="form-check-label" for="consent">
                                            I have read and agree to the FSUU Dental Clinic policies and I consent to the dental procedure. I confirm that the information provided above is accurate.
                                        </label>
                                        <div class="invalid-feedback">You must agree to the clinic policies before proceeding.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-confirm" id="submitBtn">
                                        <span class="btn-text"><i class="bi bi-calendar-check me-1"></i>Confirm Booking</span>
                                        <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Submitting…</span>
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>

                </div><!-- /col-lg-8 -->

                <!-- Right: Booking Summary -->
                <div class="col-lg-4" id="summaryCol" style="display:none">
                    <div class="booking-summary summary-sticky">
                        <div class="booking-summary-header">
                            <i class="bi bi-receipt me-2"></i>Booking Summary
                        </div>
                        <div class="booking-summary-body">
                            <div class="summary-row" id="sum-service">
                                <span class="summary-label"><i class="bi bi-hospital me-1"></i>Service</span>
                                <span class="summary-value text-muted" id="sum-service-val">—</span>
                            </div>
                            <div class="summary-row" id="sum-duration">
                                <span class="summary-label"><i class="bi bi-clock me-1"></i>Duration</span>
                                <span class="summary-value text-muted" id="sum-duration-val">—</span>
                            </div>
                            <div class="summary-row" id="sum-date">
                                <span class="summary-label"><i class="bi bi-calendar3 me-1"></i>Date</span>
                                <span class="summary-value text-muted" id="sum-date-val">—</span>
                            </div>
                            <div class="summary-row" id="sum-time">
                                <span class="summary-label"><i class="bi bi-alarm me-1"></i>Time</span>
                                <span class="summary-value text-muted" id="sum-time-val">—</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label"><i class="bi bi-person me-1"></i>Patient</span>
                                <span class="summary-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                            </div>
                        </div>
                        <div class="booking-summary-footer" id="sum-status-row">
                            <span class="badge bg-warning text-dark w-100 py-2">
                                <i class="bi bi-hourglass-split me-1"></i>Pending Dentist Approval
                            </span>
                        </div>
                    </div>
                </div>

            </div><!-- /row -->

        </div><!-- /container -->
    </div><!-- /main-content -->

</div><!-- /dashboard-wrapper -->

<!-- Sunday Closed Modal -->
<!-- Floating toast notification -->
<div id="floatToastWrap" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;pointer-events:none;"></div>

<div class="modal fade" id="sundayModal" tabindex="-1" aria-labelledby="sundayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center py-4 px-4">
                <div class="mb-3">
                    <span style="font-size:3rem;">🚫</span>
                </div>
                <h5 class="fw-bold mb-2">Clinic Closed on Sundays</h5>
                <p class="text-muted mb-4">We're sorry, the clinic is not available on Sundays. Please select a <strong>Monday – Saturday</strong> date instead.</p>
                <button type="button" class="btn btn-primary px-4 rounded-pill" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>


<script>
const SERVICES = <?php echo json_encode(array_column($services, null, 'service_id')); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────────
let selectedService  = null;
let selectedDate     = null;
let selectedTime     = null;
let calendar         = null;

// ── Toast helper ─────────────────────────────────────────────────────
function showToast(type, msg) {
    // Legacy inline alert — keep hidden
    $('#bookingToast').addClass('d-none');

    const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const colors = { success: '#16a34a', danger: '#dc2626', warning: '#d97706', info: '#0ea5e9' };
    const bgColors = { success: '#f0fdf4', danger: '#fff5f5', warning: '#fffbeb', info: '#eff6ff' };
    const borderColors = { success: '#bbf7d0', danger: '#fecaca', warning: '#fde68a', info: '#bfdbfe' };

    const icon  = icons[type]  || icons.info;
    const color = colors[type] || colors.info;
    const bg    = bgColors[type] || bgColors.info;
    const border= borderColors[type] || borderColors.info;

    const toast = $(`<div style="
        display:flex;align-items:flex-start;gap:0.65rem;
        background:${bg};border:1px solid ${border};border-left:4px solid ${color};
        border-radius:10px;padding:0.75rem 1rem;
        box-shadow:0 4px 20px rgba(0,0,0,0.12);
        min-width:260px;max-width:340px;
        pointer-events:all;
        animation:toastIn 0.25s ease both;
        font-size:0.875rem;color:#1A1A1A;
    ">
        <i class="bi bi-${icon}" style="color:${color};font-size:1rem;flex-shrink:0;margin-top:1px;"></i>
        <span style="flex:1;line-height:1.45;">${msg}</span>
        <button onclick="this.closest('div').remove()" style="background:none;border:none;color:#9ca3af;font-size:1rem;padding:0;cursor:pointer;flex-shrink:0;line-height:1;">&times;</button>
    </div>`);

    $('#floatToastWrap').append(toast);
    const ms = type === 'success' ? 5000 : 4000;
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), ms);
}

// ── Step indicator ────────────────────────────────────────────────────
function setStep(n) {
    [1,2,3].forEach(i => {
        const dot = $('#step-dot-' + i);
        dot.removeClass('active completed');
        if (i < n)  dot.addClass('completed');
        if (i === n) dot.addClass('active');
    });
}

// ── Booking summary ────────────────────────────────────────────────────
function updateSummary() {
    const svc = selectedService ? SERVICES[selectedService] : null;
    $('#sum-service-val').text(svc ? svc.service_name : '—').toggleClass('text-muted', !svc);
    $('#sum-duration-val').text(svc ? svc.duration_minutes + ' mins' : '—').toggleClass('text-muted', !svc);

    const dateStr = selectedDate ? new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US',
        {weekday:'short', month:'short', day:'numeric', year:'numeric'}) : '—';
    $('#sum-date-val').text(dateStr).toggleClass('text-muted', !selectedDate);

    const timeStr = selectedTime ? formatTime(selectedTime) : '—';
    $('#sum-time-val').text(timeStr).toggleClass('text-muted', !selectedTime);

    if (svc) { $('#summaryCol').show(); $('#panelCol').removeClass('col-panel-full'); }
    else     { $('#summaryCol').hide(); $('#panelCol').addClass('col-panel-full'); }
}

// ── Step 1 → 2: Service selection ─────────────────────────────────────
$(document).on('click', '.service-card', function () {
    $('.service-card').removeClass('selected');
    $(this).addClass('selected');
    selectedService = parseInt($(this).data('service-id'));
    selectedDate = null;
    selectedTime = null;

    $('#step1').addClass('d-none');
    $('#step2').removeClass('d-none');
    setStep(2);
    updateSummary();
    $('#bookingToast').addClass('d-none');

    if (!calendar) initCalendar();
    else calendar.render();

    $('#step2')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
});

$('#changeServiceBtn').on('click', function () {
    $('#step2').addClass('d-none');
    $('#step3').addClass('d-none');
    $('#step1').removeClass('d-none');
    selectedDate = null;
    selectedTime = null;
    setStep(1);
    updateSummary();
});

// ── Calendar ──────────────────────────────────────────────────────────

// Shared tooltip element for blocked-date hover
const $calTip = $('<div class="cal-blocked-tooltip"></div>').appendTo('body');
let calTipTimer = null;

function showCalTip(text, x, y) {
    $calTip.text(text).css({ left: x, top: y }).addClass('visible');
}
function hideCalTip() {
    clearTimeout(calTipTimer);
    $calTip.removeClass('visible');
}

function initCalendar() {
    const calEl = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        selectable: true,
        dayCellClassNames: function (arg) {
            const today = new Date(); today.setHours(0,0,0,0);
            const cell  = new Date(arg.date); cell.setHours(0,0,0,0);
            return cell < today ? ['cal-past-day'] : [];
        },
        events: {
            url: '../api/get-blocked-dates.php',
            failure: () => showToast('warning', 'Could not load blocked dates.')
        },
        eventDidMount: function (info) {
            // Only attach tooltip to background (blocked) events
            if (info.event.display !== 'background' || !info.event.extendedProps.isFullDay) return;
            const reason = info.event.extendedProps.reason || 'Management decision';
            const msg    = 'Not available: ' + reason;
            const el     = info.el;

            $(el).on('mouseenter', function (e) {
                const rect = el.getBoundingClientRect();
                const x = rect.left + window.scrollX + rect.width / 2;
                const y = rect.top  + window.scrollY - 8;
                showCalTip(msg, x, y);
                // Reposition tooltip centred above cell after render
                const tw = $calTip.outerWidth();
                $calTip.css('left', x - tw / 2);
            });

            $(el).on('mouseleave', function () {
                calTipTimer = setTimeout(hideCalTip, 120);
            });

            $(el).on('click touchend', function () {
                hideCalTip();
            });
        },
        dateClick: function (info) {
            // Reject past dates
            const clicked = new Date(info.dateStr + 'T00:00:00');
            const today   = new Date(); today.setHours(0,0,0,0);
            if (clicked < today) {
                showToast('warning', 'This date has already passed. Please select a future date.');
                return;
            }

            // Reject Sundays
            const dow = clicked.getDay();
            if (dow === 0) { new bootstrap.Modal(document.getElementById('sundayModal')).show(); return; }

            // Check blocked date and explain why (mobile-friendly feedback)
            const blocked = calendar.getEvents().find(e =>
                e.display === 'background' && e.extendedProps.isFullDay && e.startStr === info.dateStr);
            if (blocked) {
                const reason = blocked.extendedProps.reason || 'Management decision';
                showToast('info', `This date is blocked: ${reason}`);
                return;
            }

            // Highlight selected date
            calendar.getEvents().filter(e => e.extendedProps.isSelected).forEach(e => e.remove());
            calendar.addEvent({
                start: info.dateStr, allDay: true,
                backgroundColor: 'rgba(0,174,239,0.15)', borderColor: 'var(--primary-color)',
                display: 'background',
                extendedProps: { isSelected: true }
            });

            selectedDate = info.dateStr;
            const pretty = new Date(info.dateStr + 'T00:00:00').toLocaleDateString('en-US',
                { weekday: 'long', month: 'long', day: 'numeric' });
            $('#selectedDateLabel').text(pretty);
            loadTimeSlots(info.dateStr);
            updateSummary();
        }
    });
    calendar.render();
}

// ── Time slots ────────────────────────────────────────────────────────
function loadTimeSlots(date) {
    $('#timeSlotsContainer').html(
        '<div class="slots-loading"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading slots…</div>');

    $.ajax({
        url: '../api/get-slots.php',
        method: 'GET',
        data: { date: date, service_id: selectedService },
        dataType: 'json',
        success: function (res) {
            if (res.success) displayTimeSlots(res.slots, res.maxPerDay);
            else showToast('danger', res.message || 'Error loading slots.');
        },
        error: () => showToast('danger', 'Connection error loading slots.')
    });
}

function displayTimeSlots(slots, maxPerDay) {
    if (!slots || slots.length === 0) {
        $('#timeSlotsContainer').html('<div class="slots-placeholder"><i class="bi bi-x-circle text-danger"></i><span>No slots available for this day.</span></div>');
        return;
    }

    const now   = new Date();
    const today = now.toDateString() === new Date(selectedDate + 'T00:00:00').toDateString();

    let availCount = 0;
    let html = '<div class="slots-grid">';

    slots.forEach(slot => {
        const [h, m] = slot.time.split(':');
        const slotDt  = new Date(selectedDate + 'T' + slot.time);
        const isPast  = today && slotDt < now;
        const isFull  = slot.booked >= maxPerDay;
        const isBlock = slot.blocked;
        const disabled = isPast || isFull || isBlock;
        if (!disabled) availCount++;

        let label = '';
        if (isFull || isBlock) label = '<small class="slot-tag">Full</small>';
        else if (isPast)       label = '<small class="slot-tag past">Past</small>';

        html += `<div class="time-slot ${disabled ? 'disabled' : ''}" data-time="${slot.time}">
                    <span class="slot-time">${formatTime(slot.time)}</span>${label}
                 </div>`;
    });
    html += '</div>';

    const countHtml = `<p class="slots-availability-note mb-1">
        <i class="bi bi-check-circle-fill text-success me-1"></i>
        <strong>${availCount}</strong> slot${availCount !== 1 ? 's' : ''} available
    </p>
    <p class="text-muted small mb-2">
        These are the only times available because they match the dentist's schedule.
    </p>`;

    $('#timeSlotsContainer').html(countHtml + html);
}

// ── Step 2 → 3: Select time ────────────────────────────────────────────
$(document).on('click', '.time-slot:not(.disabled)', function () {
    selectedTime = $(this).data('time');
    $('.time-slot').removeClass('selected');
    $(this).addClass('selected');

    $('#step2').addClass('d-none');
    $('#step3').removeClass('d-none');
    setStep(3);
    updateSummary();
    $('#step3')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
});

$('#changeDateBtn').on('click', function () {
    $('#step3').addClass('d-none');
    $('#step2').removeClass('d-none');
    selectedTime = null;
    setStep(2);
    updateSummary();
});

// ── Format time (H:i:s → 12h) ─────────────────────────────────────────
function formatTime(t) {
    const [h, m] = t.split(':');
    const d = new Date(); d.setHours(+h, +m);
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

// ── Form submission ────────────────────────────────────────────────────
$('#bookingForm').on('submit', function (e) {
    e.preventDefault();
    const form = this;

    // Bootstrap validation
    form.classList.add('was-validated');
    if (!form.checkValidity()) {
        showToast('danger', 'Please fill in all required fields.');
        return;
    }
    if (!$('#consent').is(':checked')) {
        showToast('danger', 'You must agree to the clinic policies to proceed.');
        return;
    }
    if (!selectedService || !selectedDate || !selectedTime) {
        showToast('danger', 'Booking is incomplete. Please restart the process.');
        return;
    }

    // Loading state
    $('#submitBtn .btn-text').addClass('d-none');
    $('#submitBtn .btn-loading').removeClass('d-none');
    $('#submitBtn').prop('disabled', true);

    $.ajax({
        url: '../api/book-appointment.php',
        method: 'POST',
        dataType: 'json',
        data: {
            service_id:               selectedService,
            appointment_date:         selectedDate,
            appointment_time:         selectedTime,
            allergies:                $('[name="allergies"]').val(),
            medical_conditions:       $('[name="medical_conditions"]').val(),
            medications:              $('[name="medications"]').val(),
            emergency_contact_name:   $('[name="emergency_contact_name"]').val(),
            emergency_contact_number: $('[name="emergency_contact_number"]').val(),
            notes:                    $('[name="notes"]').val(),
            consent:                  true
        },
        success: function (res) {
            if (res.success) {
                const dentist = res.assigned_dentist || null;
                let msg = '<strong>Appointment submitted!</strong> Pending dentist approval.';
                if (dentist && dentist.user_id) {
                    const dName = dentist.name ? dentist.name : 'Assigned Dentist';
                    const safeName = $('<div>').text(dName).html();
                    msg += `<br><span class="small">Assigned dentist: <strong>Dr. ${safeName}</strong>. You can message them directly from Messages.</span>`;
                    const target = `messages.php?compose_to=${encodeURIComponent(dentist.user_id)}&compose_name=${encodeURIComponent(dName)}&compose_subject=${encodeURIComponent('Appointment Concern')}`;
                    msg += `<br><a href="${target}" class="small fw-semibold text-decoration-underline">Message assigned dentist now</a>`;
                }
                showToast('success', msg);
                setTimeout(() => window.location.href = 'my-appointments.php', 3500);
            } else {
                showToast('danger', res.message || 'Could not book appointment. Please try again.');
                resetSubmitBtn();
            }
        },
        error: () => { showToast('danger', 'A connection error occurred. Please try again.'); resetSubmitBtn(); }
    });
});

function resetSubmitBtn() {
    $('#submitBtn .btn-text').removeClass('d-none');
    $('#submitBtn .btn-loading').addClass('d-none');
    $('#submitBtn').prop('disabled', false);
}
</script>
</body>
</html>
