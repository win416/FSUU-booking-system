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
    <link rel="stylesheet" href="../assets/css/patient-book-appointment.css">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="brand">
            <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
            FSUU Dental
        </div>
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
            <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
            <li class="nav-item logout-nav-item">
                <a class="nav-link text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid my-4">

            <!-- Page Header -->
            <div class="mb-4">
                <h4 class="fw-bold mb-1"><i class="bi bi-calendar-plus me-2 text-primary"></i>Book an Appointment</h4>
                <p class="text-muted mb-0">Follow the steps below to schedule your dental visit.</p>
            </div>

            <!-- Toast Alert -->
            <div id="bookingToast" class="alert d-none mb-3" role="alert"></div>

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
                <div class="col-lg-8">

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
                                    $icon = serviceIcon($svc['service_name']);
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="service-card"
                                         data-service-id="<?php echo $svc['service_id']; ?>"
                                         data-service-name="<?php echo htmlspecialchars($svc['service_name']); ?>"
                                         data-service-duration="<?php echo $svc['duration_minutes']; ?>">
                                        <div class="service-card-icon">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </div>
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
                                Mon–Fri: 1:00 PM – 3:30 PM &nbsp;|&nbsp; Saturday: 9:00 AM – 12:00 PM &nbsp;|&nbsp; Sunday: Closed
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
                    <div class="booking-summary sticky-top" style="top: 1.5rem">
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
                                <i class="bi bi-hourglass-split me-1"></i>Pending Admin Approval
                            </span>
                        </div>
                    </div>
                </div>

            </div><!-- /row -->

        </div><!-- /container -->
    </div><!-- /main-content -->

</div><!-- /dashboard-wrapper -->

<!-- PHP data passed to JS -->
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
    const el = $('#bookingToast');
    el.removeClass('d-none alert-success alert-danger alert-warning alert-info')
      .addClass('alert-' + type)
      .html('<i class="bi bi-' + (type==='success'?'check-circle':'exclamation-triangle') + '-fill me-2"></i>' + msg);
    el[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    clearTimeout(el.data('t'));
    if (type === 'success') el.data('t', setTimeout(() => el.addClass('d-none'), 6000));
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

    if (svc) { $('#summaryCol').show(); } else { $('#summaryCol').hide(); }
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
function initCalendar() {
    const calEl = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
        selectable: true,
        validRange: { start: new Date().toISOString().split('T')[0] },
        events: {
            url: '../api/get-blocked-dates.php',
            failure: () => showToast('warning', 'Could not load blocked dates.')
        },
        dateClick: function (info) {
            // Reject Sundays
            const dow = new Date(info.dateStr + 'T00:00:00').getDay();
            if (dow === 0) { showToast('warning', 'The clinic is closed on Sundays.'); return; }

            // Check blocked
            const blocked = calendar.getEvents().find(e =>
                e.display === 'background' && e.extendedProps.isFullDay && e.startStr === info.dateStr);
            if (blocked) {
                showToast('warning', 'This date is unavailable: ' + (blocked.extendedProps.reason || 'Management decision'));
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

    const countHtml = `<p class="slots-availability-note mb-2">
        <i class="bi bi-check-circle-fill text-success me-1"></i>
        <strong>${availCount}</strong> slot${availCount !== 1 ? 's' : ''} available
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
                showToast('success', '<strong>Appointment submitted!</strong> You will be notified once it is approved.');
                setTimeout(() => window.location.href = 'my-appointments.php', 2200);
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
