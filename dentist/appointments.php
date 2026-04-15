<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/appointments.php');
    } else {
        header('Location: ' . SITE_URL . '/patient/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-appointments.css" rel="stylesheet">
    <link href="../assets/css/dentist-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
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
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="my-schedule.php"><i class="bi bi-clock"></i> My Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="my-patients.php"><i class="bi bi-people"></i> My Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="notifications.php"><i class="bi bi-bell"></i> Notifications <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <?php include '../includes/dentist-topbar.php'; ?>
        <div class="container-fluid my-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h2>Manage Appointments</h2>
                <div class="filter-tabs">
                    <a href="#" class="filter-tab active" data-filter="all">All</a>
                    <a href="#" class="filter-tab" data-filter="pending">Pending</a>
                    <a href="#" class="filter-tab" data-filter="approved">Approved</a>
                    <a href="#" class="filter-tab" data-filter="completed">Completed</a>
                    <a href="#" class="filter-tab" data-filter="cancelled">Cancelled</a>
                </div>
            </div>

            <div id="alertContainer" class="mb-3"></div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>FSUU ID</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="appointmentsBody">
                                <tr><td colspan="6" class="text-center py-4 text-muted">Loading appointments...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_appointment_id" name="appointment_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Date</label>
                        <input type="date" class="form-control" id="edit_date" name="appointment_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Time</label>
                        <select class="form-select" id="edit_time" name="appointment_time" required>
                            <option value="">Select time...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Reschedule (Optional)</label>
                        <textarea class="form-control" id="edit_reason" name="reason" rows="2" placeholder="e.g., Patient requested new time"></textarea>
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
let currentFilter = 'all';
const params = new URLSearchParams(window.location.search);
const focusAppointmentId = parseInt(params.get('appointment_id') || '0', 10);
const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
const editModal = new bootstrap.Modal(document.getElementById('editModal'));
let dentistWorkingHours = null;

function showAlert(type, message) {
    $('#alertContainer').html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
}

function badgeClass(status) {
    const s = (status || '').toString().trim().toLowerCase();
    if (s === 'pending') return 'bg-warning';
    if (s === 'approved') return 'bg-success';
    if (s === 'completed') return 'bg-info';
    return 'bg-danger';
}

function statusLabel(status) {
    const s = (status || '').toString().trim().toLowerCase();
    if (['cancelled','canceled','declined','no_show'].includes(s)) return 'Cancelled';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function fmtDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
}

function fmtTime(t) {
    const dt = new Date('1970-01-01T' + t);
    return dt.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', hour12:true });
}

function actionButtons(a) {
    const s = (a.status || '').toString().trim().toLowerCase();
    const checkedIn = !!a.checked_in_at;
    const completed = !!a.completed_at || s === 'completed';
    const canReschedule = ['pending', 'approved'].includes(s) && !checkedIn && !completed;
    const editBtn = `<button type="button" class="btn btn-sm btn-outline-primary row-action-btn edit-btn" data-id="${a.appointment_id}" data-date="${a.appointment_date}" data-time="${a.appointment_time}" title="Reschedule"><i class="bi bi-pencil"></i></button>`;

    if (s === 'pending') {
        return `
            <div class="d-inline-flex gap-1">
                <button type="button" class="btn btn-sm btn-success row-action-btn approve-btn" data-id="${a.appointment_id}" title="Approve"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-sm btn-danger row-action-btn decline-btn" data-id="${a.appointment_id}" title="Decline"><i class="bi bi-x-lg"></i></button>
                ${editBtn}
            </div>
        `;
    }
    if (s === 'approved') {
        return `
            <div class="d-inline-flex gap-1">
                ${canReschedule ? editBtn : ''}
                <button type="button" class="btn btn-sm btn-outline-primary row-action-btn checkin-btn" data-id="${a.appointment_id}" ${checkedIn ? 'disabled' : ''} title="Check-in"><i class="bi bi-check2-circle"></i></button>
                <button type="button" class="btn btn-sm btn-dark row-action-btn complete-btn" data-id="${a.appointment_id}" ${(!checkedIn || completed) ? 'disabled' : ''} title="Complete"><i class="bi bi-check-all"></i></button>
            </div>
        `;
    }
    return '';
}

function renderRows(items) {
    const body = $('#appointmentsBody');
    if (!items || items.length === 0) {
        body.html('<tr><td colspan="6" class="text-center py-5 text-muted">No appointments found for this filter.</td></tr>');
        return;
    }
    let html = '';
    items.forEach(a => {
        html += `
            <tr class="clickable-row" data-id="${a.appointment_id}">
                <td><strong>${fmtDate(a.appointment_date)}</strong><br><small class="text-muted">${fmtTime(a.appointment_time)}</small></td>
                <td>${a.first_name} ${a.last_name}</td>
                <td><span class="text-muted" style="font-size:0.82rem;">${a.fsuu_id || ''}</span></td>
                <td>${a.service_name}</td>
                <td><span class="badge ${badgeClass(a.status)}">${statusLabel(a.status)}</span></td>
                <td class="action-buttons">${actionButtons(a)}</td>
            </tr>
        `;
    });
    body.html(html);

    if (focusAppointmentId > 0) {
        const targetRow = body.find(`tr[data-id="${focusAppointmentId}"]`);
        if (targetRow.length) {
            targetRow.addClass('table-info');
            $('html, body').animate({ scrollTop: Math.max(targetRow.offset().top - 140, 0) }, 250);
            setTimeout(() => targetRow.removeClass('table-info'), 2500);
        }
    }
}

function loadAppointments() {
    $.get('../api/dentist-appointments.php', { action: 'list', status: currentFilter }, function(res) {
        if (!res.success) {
            showAlert('danger', res.message || 'Failed to load appointments.');
            return;
        }
        renderRows(res.assigned || []);
    }, 'json').fail(() => showAlert('danger', 'Server error while loading appointments.'));
}

function refreshAfter(res, fallback) {
    if (res.success) {
        showAlert('success', '<i class="bi bi-check-circle me-1"></i>' + res.message);
        loadAppointments();
    } else {
        showAlert('danger', res.message || fallback);
    }
}

function fetchDentistWorkingHours() {
    if (dentistWorkingHours) {
        return Promise.resolve(dentistWorkingHours);
    }
    return $.get('../api/dentist-working-hours.php')
        .then(res => {
            if (!res || !res.success || !res.settings) {
                throw new Error('Failed to load dentist working hours.');
            }
            dentistWorkingHours = res.settings;
            return dentistWorkingHours;
        });
}

function loadTimeSlots(date, selectedTime = null) {
    const timeSelect = $('#edit_time');
    timeSelect.html('<option value="">Loading...</option>');

    const dayOfWeek = new Date(date).getDay();
    if (dayOfWeek === 0) {
        timeSelect.html('<option value="">Clinic closed on Sundays</option>');
        return;
    }
    fetchDentistWorkingHours()
        .then(settings => {
            const start = dayOfWeek === 6 ? (settings.saturday_start || '09:00') : (settings.weekday_start || '08:00');
            const end = dayOfWeek === 6 ? (settings.saturday_end || '12:00') : (settings.weekday_end || '12:00');
            const toMinutes = (hhmm) => {
                const [h, m] = String(hhmm).split(':').map(v => parseInt(v, 10));
                if (!Number.isFinite(h) || !Number.isFinite(m)) return NaN;
                return (h * 60) + m;
            };
            const startMin = toMinutes(start);
            const endMin = toMinutes(end);

            if (!Number.isFinite(startMin) || !Number.isFinite(endMin) || startMin >= endMin) {
                timeSelect.html('<option value="">No available schedule for this day</option>');
                return;
            }

            let options = '<option value="">Select time...</option>';
            for (let t = startMin; t < endMin; t += 30) {
                const hh = String(Math.floor(t / 60)).padStart(2, '0');
                const mm = String(t % 60).padStart(2, '0');
                const time24 = `${hh}:${mm}:00`;
                const hour = parseInt(hh, 10);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                const label = `${hour12}:${mm} ${ampm}`;
                const selected = (selectedTime && time24 === selectedTime) ? 'selected' : '';
                options += `<option value="${time24}" ${selected}>${label}</option>`;
            }
            timeSelect.html(options);
        })
        .catch(() => {
            timeSelect.html('<option value="">Unable to load available times</option>');
        });
}

$(document).on('click', '.edit-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const id = $(this).data('id');
    const date = $(this).data('date');
    const today = new Date().toISOString().split('T')[0];

    $('#edit_appointment_id').val(id);
    $('#edit_date').attr('min', today).val(date);
    $('#edit_reason').val('');
    loadTimeSlots(date);
    editModal.show();
});

$('#edit_date').on('change', function() {
    const date = $(this).val();
    if (date) {
        loadTimeSlots(date);
    }
});

$('#saveReschedule').on('click', function() {
    const id = $('#edit_appointment_id').val();
    const date = $('#edit_date').val();
    const time = $('#edit_time').val();
    const reason = $('#edit_reason').val().trim();
    const saveBtn = $(this);

    if (!date || !time) {
        showAlert('warning', 'Please select both date and time.');
        return;
    }

    saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
    $.post('../api/dentist-appointments.php', {
        action: 'reschedule',
        appointment_id: id,
        appointment_date: date,
        appointment_time: time,
        reason: reason
    }, function(res) {
        if (res.success) {
            editModal.hide();
            showAlert('success', '<i class="bi bi-check-circle me-1"></i>' + res.message);
            loadAppointments();
        } else {
            showAlert('danger', res.message || 'Failed to reschedule appointment.');
        }
    }, 'json').fail(() => showAlert('danger', 'Server error during reschedule.'))
      .always(() => saveBtn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save Changes'));
});

$(document).on('click', '.approve-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    $.post('../api/dentist-appointments.php', { action: 'approve', appointment_id: $(this).data('id') }, res => refreshAfter(res, 'Approval failed.'), 'json')
        .fail(() => showAlert('danger', 'Server error during approval.'));
});

$(document).on('click', '.decline-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    const reason = prompt('Reason for declining this appointment:');
    if (reason === null) return;
    $.post('../api/dentist-appointments.php', { action: 'decline', appointment_id: $(this).data('id'), reason: reason }, res => refreshAfter(res, 'Decline failed.'), 'json')
        .fail(() => showAlert('danger', 'Server error during decline.'));
});

$(document).on('click', '.checkin-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    $.post('../api/dentist-appointments.php', { action: 'check_in', appointment_id: $(this).data('id') }, res => refreshAfter(res, 'Check-in failed.'), 'json')
        .fail(() => showAlert('danger', 'Server error during check-in.'));
});

$(document).on('click', '.complete-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    $.post('../api/dentist-appointments.php', { action: 'complete', appointment_id: $(this).data('id') }, res => refreshAfter(res, 'Completion failed.'), 'json')
        .fail(() => showAlert('danger', 'Server error during completion.'));
});

$(document).on('click', '.clickable-row', function(e) {
    if ($(e.target).closest('.action-buttons, .row-action-btn, button, a, input, textarea, select, label').length) {
        return;
    }
    const id = $(this).data('id');
    $('#detailsContent').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');
    detailsModal.show();

    $.get('../api/dentist-appointment-details.php', { id: id }, function(response) {
        if (!response.success) {
            $('#detailsContent').html('<div class="alert alert-danger">' + (response.message || 'Failed to load details.') + '</div>');
            return;
        }
        const data = response.data;
        const html = `
            <div class="appointment-details">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background:#f8f9fa;">
                            <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">PATIENT INFORMATION</h6>
                            <div class="row g-2">
                                <div class="col-6"><small class="text-muted d-block">Name</small><strong>${data.first_name} ${data.last_name}</strong></div>
                                <div class="col-6"><small class="text-muted d-block">FSUU ID</small><span style="font-size:0.82rem;">${data.fsuu_id || ''}</span></div>
                                <div class="col-12"><small class="text-muted d-block">Email</small><span style="font-size:0.85rem;">${data.email || 'N/A'}</span></div>
                                <div class="col-12"><small class="text-muted d-block">Contact</small><span style="font-size:0.85rem;">${data.contact_number || 'N/A'}</span></div>
                            </div>
                        </div>
                        <div class="p-3 rounded mt-3" style="background:#f8f9fa;">
                            <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">APPOINTMENT INFORMATION</h6>
                            <div class="row g-2">
                                <div class="col-6"><small class="text-muted d-block">Service</small><strong>${data.service_name}</strong></div>
                                <div class="col-6"><small class="text-muted d-block">Date</small>${new Date(data.appointment_date + 'T00:00:00').toLocaleDateString('en-US', { month:'long', day:'numeric', year:'numeric' })}</div>
                                <div class="col-6"><small class="text-muted d-block">Time</small>${fmtTime(data.appointment_time)}</div>
                                <div class="col-6"><small class="text-muted d-block">Status</small><span class="badge ${badgeClass(data.status)}">${statusLabel(data.status)}</span></div>
                                ${data.cancellation_reason ? `<div class="col-12"><small class="text-muted d-block">Cancellation Reason</small>${data.cancellation_reason}</div>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded h-100" style="background:#f8f9fa;">
                            <h6 class="text-primary border-bottom pb-2 mb-3" style="font-size:0.8rem;letter-spacing:.05em;">MEDICAL INFORMATION</h6>
                            <div class="row g-2">
                                <div class="col-12"><small class="text-muted d-block">Allergies</small>${data.allergies || 'None'}</div>
                                <div class="col-12"><small class="text-muted d-block">Conditions</small>${data.medical_conditions || 'None'}</div>
                                <div class="col-12"><small class="text-muted d-block">Medications</small>${data.medications || 'None'}</div>
                                <div class="col-6"><small class="text-muted d-block">Emergency Contact</small>${data.emergency_contact_name || 'N/A'}</div>
                                <div class="col-6"><small class="text-muted d-block">Emergency Number</small>${data.emergency_contact_number || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#detailsContent').html(html);
    }).fail(() => {
        $('#detailsContent').html('<div class="alert alert-danger">Failed to fetch appointment details.</div>');
    });
});

$(document).on('click', '.filter-tab', function(e) {
    e.preventDefault();
    $('.filter-tab').removeClass('active');
    $(this).addClass('active');
    currentFilter = $(this).data('filter');
    loadAppointments();
});

loadAppointments();
</script>
</body>
</html>

