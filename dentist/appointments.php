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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentFilter = 'all';
const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

function showAlert(type, message) {
    $('#alertContainer').html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
}

function badgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'pending') return 'bg-warning';
    if (s === 'approved') return 'bg-success';
    if (s === 'completed') return 'bg-info';
    return 'bg-danger';
}

function statusLabel(status) {
    const s = (status || '').toLowerCase();
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
    const s = (a.status || '').toLowerCase();
    const checkedIn = !!a.checked_in_at;
    const completed = !!a.completed_at || s === 'completed';

    if (s === 'pending') {
        return `
            <div class="d-inline-flex gap-1">
                <button type="button" class="btn btn-sm btn-success row-action-btn approve-btn" data-id="${a.appointment_id}" title="Approve"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-sm btn-danger row-action-btn decline-btn" data-id="${a.appointment_id}" title="Decline"><i class="bi bi-x-lg"></i></button>
            </div>
        `;
    }
    if (s === 'approved') {
        return `
            <div class="d-inline-flex gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary row-action-btn checkin-btn" data-id="${a.appointment_id}" ${checkedIn ? 'disabled' : ''} title="Check-in"><i class="bi bi-check2-circle"></i></button>
                <button type="button" class="btn btn-sm btn-dark row-action-btn complete-btn" data-id="${a.appointment_id}" ${(!checkedIn || completed) ? 'disabled' : ''} title="Complete"><i class="bi bi-check-all"></i></button>
            </div>
        `;
    }
    return '<span class="text-muted small">—</span>';
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

