<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/patients.php');
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

$db->query("
    CREATE TABLE IF NOT EXISTS dentist_patient_records (
        record_id INT(11) NOT NULL AUTO_INCREMENT,
        dentist_id INT(11) NOT NULL,
        patient_id INT(11) NOT NULL,
        appointment_id INT(11) DEFAULT NULL,
        treatment_notes TEXT NOT NULL,
        prescription TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (record_id),
        KEY idx_dentist_patient (dentist_id, patient_id),
        KEY idx_appointment (appointment_id),
        CONSTRAINT fk_dpr_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_dpr_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT fk_dpr_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$search = trim($_GET['search'] ?? '');
$patientsSql = "
    SELECT DISTINCT u.user_id, u.fsuu_id, u.first_name, u.last_name, u.email, u.contact_number, u.program, u.profile_picture,
        (SELECT COUNT(*) FROM dentist_patient_records dpr WHERE dpr.dentist_id = ? AND dpr.patient_id = u.user_id) AS record_count,
        (SELECT MAX(dpr2.created_at) FROM dentist_patient_records dpr2 WHERE dpr2.dentist_id = ? AND dpr2.patient_id = u.user_id) AS last_record_at
    FROM dentist_appointment_assignments da
    INNER JOIN appointments a ON a.appointment_id = da.appointment_id
    INNER JOIN users u ON u.user_id = a.user_id
    WHERE u.role IN ('student','staff')
      AND da.dentist_id = ?
      AND LOWER(TRIM(a.status)) NOT IN ('cancelled','canceled','declined','no_show')
";

$params = [$dentist_id, $dentist_id, $dentist_id];
$types = "iii";
if ($search !== '') {
    $patientsSql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.fsuu_id LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}
$patientsSql .= " ORDER BY COALESCE(last_record_at, '1970-01-01') DESC, u.last_name ASC, u.first_name ASC";

$patientsStmt = $db->prepare($patientsSql);
$patientsStmt->bind_param($types, ...$params);
$patientsStmt->execute();
$patients = $patientsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="my-schedule.php"><i class="bi bi-clock"></i> My Schedule</a></li>
                    <li class="nav-item"><a class="nav-link active" href="my-patients.php"><i class="bi bi-people"></i> My Patients</a></li>
                    <li class="nav-item"><a class="nav-link" href="notifications.php"><i class="bi bi-bell"></i> Notifications <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/dentist-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">My Patients</h2>
                        <p class="text-muted mb-0">Treatment history and prescriptions for your patient records.</p>
                    </div>
                </div>

                <div id="alertContainer" class="mb-3"></div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" placeholder="Search by name, FSUU ID, or email" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-journal-medical me-2"></i>Patient Records</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($patients->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="padding:0.85rem 1.25rem;">Patient</th>
                                            <th style="padding:0.85rem 1.25rem;">FSUU ID</th>
                                            <th style="padding:0.85rem 1.25rem;">Program</th>
                                            <th style="padding:0.85rem 1.25rem;">Contact</th>
                                            <th style="padding:0.85rem 1.25rem;">Records</th>
                                            <th style="padding:0.85rem 1.25rem;">Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($p = $patients->fetch_assoc()): ?>
                                            <?php $patientName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')); ?>
                                            <tr class="patient-profile-row"
                                                data-id="<?php echo (int)$p['user_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($patientName); ?>"
                                                data-email="<?php echo htmlspecialchars($p['email'] ?? ''); ?>"
                                                data-contact="<?php echo htmlspecialchars($p['contact_number'] ?? ''); ?>"
                                                data-fsuu-id="<?php echo htmlspecialchars($p['fsuu_id'] ?? ''); ?>"
                                                data-program="<?php echo htmlspecialchars($p['program'] ?? ''); ?>"
                                                data-picture="<?php echo htmlspecialchars($p['profile_picture'] ?? ''); ?>"
                                                style="cursor:pointer;">
                                                <td style="padding:0.85rem 1.25rem;">
                                                    <strong><?php echo htmlspecialchars($patientName); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                                                </td>
                                                <td style="padding:0.85rem 1.25rem;"><?php echo htmlspecialchars($p['fsuu_id'] ?: '—'); ?></td>
                                                <td style="padding:0.85rem 1.25rem;"><?php echo htmlspecialchars($p['program'] ?: '—'); ?></td>
                                                <td style="padding:0.85rem 1.25rem;"><?php echo htmlspecialchars($p['contact_number'] ?: '—'); ?></td>
                                                <td style="padding:0.85rem 1.25rem;"><span class="badge bg-primary"><?php echo (int)$p['record_count']; ?></span></td>
                                                <td style="padding:0.85rem 1.25rem;"><?php echo $p['last_record_at'] ? date('M j, Y g:i A', strtotime($p['last_record_at'])) : 'No records yet'; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2"></i>No matching patients found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="patientRecordsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-journal-medical me-2"></i><span id="recordsPatientName">Patient Records</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="recordsAlert"></div>
                    <form id="recordForm" class="card mb-3">
                        <div class="card-body">
                            <input type="hidden" name="patient_id" id="record_patient_id">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Treatment History (Clinical Notes)</label>
                                <textarea class="form-control" name="treatment_notes" id="treatment_notes" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Prescription</label>
                                <textarea class="form-control" name="prescription" id="prescription" rows="3" placeholder="Medication, dosage, instructions"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Record</button>
                        </div>
                    </form>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Treatment History & Prescriptions</h6>
                        </div>
                        <div class="card-body" id="recordsList">
                            <div class="text-center text-muted py-3">Select a patient to load records.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="patientProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Patient Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-4 text-center">
                            <img id="patientProfileImage" src="../img/default-avatar.png" alt="Patient Avatar"
                                 class="rounded-circle border" style="width:120px;height:120px;object-fit:cover;display:none;">
                            <div id="patientProfileInitials" class="rounded-circle border d-inline-flex align-items-center justify-content-center"
                                 style="width:120px;height:120px;font-size:2rem;font-weight:700;color:#29ABE2;background:#eef8ff;display:none;">P</div>
                            <div class="mt-2 text-muted small">Assigned Patient</div>
                        </div>
                        <div class="col-md-8">
                            <h5 class="mb-2" id="patientProfileName">—</h5>
                            <div class="mb-2"><strong>Email:</strong> <span id="patientProfileEmail">—</span></div>
                            <div class="mb-2"><strong>Contact:</strong> <span id="patientProfileContact">—</span></div>
                            <div class="mb-2"><strong>FSUU ID:</strong> <span id="patientProfileFsuu">—</span></div>
                            <div class="mb-2"><strong>Program:</strong> <span id="patientProfileProgram">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="messagePatientBtn" href="messages.php" class="btn btn-primary">
                        <i class="bi bi-chat-dots me-1"></i>Message Patient
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-2">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    function loadPatientRecords(patientId) {
        $('#recordsList').html('<div class="text-center py-3 text-muted">Loading records...</div>');
        $.get('../api/dentist-patient-records.php', { action: 'list', patient_id: patientId }, function(res) {
            if (!res.success) {
                $('#recordsList').html('<div class="text-danger">Failed to load records.</div>');
                return;
            }
            if (!res.records || res.records.length === 0) {
                $('#recordsList').html('<div class="text-center py-3 text-muted">No treatment records yet.</div>');
                return;
            }
            let html = '';
            res.records.forEach(r => {
                html += `<div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="fw-semibold">${r.created_at_display}</div>
                        <span class="badge bg-secondary">${r.appointment_label}</span>
                    </div>
                    <div class="mb-2"><strong>Treatment Notes:</strong><br>${$('<div>').text(r.treatment_notes).html().replace(/\n/g, '<br>')}</div>
                    <div><strong>Prescription:</strong><br>${r.prescription ? $('<div>').text(r.prescription).html().replace(/\n/g, '<br>') : '<span class="text-muted">None</span>'}</div>
                </div>`;
            });
            $('#recordsList').html(html);
        }, 'json').fail(() => {
            $('#recordsList').html('<div class="text-danger">Server error while loading records.</div>');
        });
    }

    $(document).on('click', '.patient-profile-row', function() {
        const patientId = $(this).data('id');
        const name = $(this).data('name') || 'Patient';
        const email = $(this).data('email') || 'Not available';
        const contact = $(this).data('contact') || 'Not available';
        const fsuuId = $(this).data('fsuu-id') || '—';
        const program = $(this).data('program') || '—';
        const picture = $(this).data('picture') || '';

        $('#patientProfileName').text(name);
        $('#patientProfileEmail').text(email);
        $('#patientProfileContact').text(contact);
        $('#patientProfileFsuu').text(fsuuId);
        $('#patientProfileProgram').text(program);

        const initials = name.split(' ').filter(Boolean).map(n => n[0]).join('').slice(0, 2).toUpperCase() || 'P';
        if (picture) {
            $('#patientProfileImage').attr('src', '../' + picture).show();
            $('#patientProfileInitials').hide();
        } else {
            $('#patientProfileImage').hide();
            $('#patientProfileInitials').text(initials).show();
        }

        const msgUrl = 'messages.php?compose_to=' + encodeURIComponent(patientId) +
            '&compose_name=' + encodeURIComponent(name) +
            '&compose_subject=' + encodeURIComponent('Patient Concern');
        $('#messagePatientBtn').attr('href', msgUrl);

        new bootstrap.Modal(document.getElementById('patientProfileModal')).show();
    });

    $('#recordForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../api/dentist-patient-records.php', $(this).serialize() + '&action=save', function(res) {
            if (res.success) {
                showAlert('#recordsAlert', 'success', '<i class="bi bi-check-circle me-1"></i>Record saved successfully.');
                $('#treatment_notes').val('');
                $('#prescription').val('');
                loadPatientRecords($('#record_patient_id').val());
            } else {
                showAlert('#recordsAlert', 'danger', res.message || 'Failed to save record.');
            }
        }, 'json').fail(() => showAlert('#recordsAlert', 'danger', 'Server error while saving record.'));
    });

    </script>
</body>
</html>
