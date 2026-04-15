<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

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
    CREATE TABLE IF NOT EXISTS dentist_profiles (
        dentist_id INT(11) NOT NULL,
        specialization VARCHAR(150) DEFAULT NULL,
        digital_signature_path VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (dentist_id),
        CONSTRAINT fk_dentist_profile_user FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Get all appointments
$stmt = $db->prepare("
    SELECT a.*, s.service_name,
           d.user_id AS dentist_id, d.first_name AS dentist_first_name, d.last_name AS dentist_last_name,
           d.email AS dentist_email, d.contact_number AS dentist_contact, d.profile_picture AS dentist_profile_picture,
           dp.specialization AS dentist_specialization, dp.digital_signature_path AS dentist_signature_path
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN dentist_appointment_assignments da ON da.appointment_id = a.appointment_id
    LEFT JOIN users d ON d.user_id = da.dentist_id AND d.role = 'dentist'
    LEFT JOIN dentist_profiles dp ON dp.dentist_id = d.user_id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$appointments = $stmt->get_result();
$focusAppointmentId = (int)($_GET['appointment_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-my-appointments.css" rel="stylesheet">
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
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="book-appointment.php">
                        <i class="bi bi-calendar-plus"></i> Book Appointment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my-appointments.php">
                        <i class="bi bi-calendar-check"></i> My Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
                        <i class="bi bi-bell"></i> Notifications
                        <?php
                        $unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $unread_stmt->bind_param("i", $user['user_id']);
                        $unread_stmt->execute();
                        $unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
                        if ($unread_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="history.php">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                </li>
            </ul>
            </div>
        </nav>

        <div class="main-content">
            <?php include '../includes/patient-topbar.php'; ?>
            <div class="container-fluid my-4">
            <div style="max-width:1100px;width:100%;margin:0 auto;">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="fw-bold mb-0" style="font-size:1.6rem;">My Appointments</h2>
                            <p class="text-muted mb-0" style="font-size:0.875rem;">View and manage your dental appointments</p>
                        </div>
                        <a href="book-appointment.php" class="btn rounded-pill fw-semibold flex-shrink-0 d-none d-sm-inline-flex align-items-center gap-1" style="background:#29ABE2;color:#fff;border:none;white-space:nowrap;padding:8px 20px;">
                            <i class="bi bi-plus-lg"></i> Book New Appointment
                        </a>
                    </div>
                    <!-- Mobile: full-width button below title -->
                    <a href="book-appointment.php" class="btn rounded-pill fw-semibold w-100 mt-3 d-sm-none" style="background:#29ABE2;color:#fff;border:none;">
                        <i class="bi bi-plus-lg me-1"></i> Book New Appointment
                    </a>
                </div>

                <div class="card border-0 shadow-sm appt-table-wrap" style="border-radius:12px;overflow:hidden;">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" style="font-size:0.9rem;">
                                <thead style="background:#f9fafb;border-bottom:1px solid #E0E0E0;">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Service</th>
                                        <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Date</th>
                                         <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Time</th>
                                         <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Status</th>
                                         <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Dentist</th>
                                         <th class="py-3 fw-semibold text-uppercase" style="font-size:0.72rem;letter-spacing:0.06em;color:#6b7280;">Actions</th>
                                     </tr>
                                 </thead>
                                <tbody>
                                    <?php if($appointments->num_rows > 0): ?>
                                        <?php while($appt = $appointments->fetch_assoc()): ?>
                                            <tr id="appointment-row-<?php echo (int)$appt['appointment_id']; ?>" style="border-bottom:1px solid #f3f4f6;">
                                                <td class="ps-4 py-3 fw-semibold" style="color:#111827;"><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                                <td class="py-3"><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                                <td class="py-3"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                                <td class="py-3">
                                                    <?php
                                                    $status_key = strtolower(trim((string)($appt['status'] ?? '')));
                                                    if ($status_key === '') {
                                                        $status_key = 'declined';
                                                    }
                                                    if ($status_key === 'canceled') {
                                                        $status_key = 'cancelled';
                                                    }
                                                    $status_label = match($status_key) {
                                                        'pending'   => 'Pending',
                                                        'approved'  => 'Approved',
                                                        'completed' => 'Completed',
                                                        'cancelled' => 'Cancelled',
                                                        'declined'  => 'Declined',
                                                        'no_show'   => 'No Show',
                                                        default     => 'Declined'
                                                    };
                                                    $badgeStyle = match($status_key) {
                                                        'pending'   => 'background:#fef3c7;color:#92400e;',
                                                        'approved'  => 'background:#dcfce7;color:#166534;',
                                                        'completed' => 'background:#dbeafe;color:#1e40af;',
                                                        'cancelled' => 'background:#fee2e2;color:#991b1b;',
                                                        'declined'  => 'background:#fee2e2;color:#991b1b;',
                                                        'no_show'   => 'background:#fef2f2;color:#991b1b;',
                                                        default     => 'background:#f3f4f6;color:#374151;'
                                                    };
                                                    ?>
                                                    <span style="<?php echo $badgeStyle; ?> font-size:0.75rem;font-weight:600;padding:0.25em 0.75em;border-radius:999px;display:inline-block;">
                                                        <?php echo $status_label; ?>
                                                    </span>
                                                </td>
                                                 <td class="py-3">
                                                     <?php if (!empty($appt['dentist_id'])): ?>
                                                         <?php
                                                         $dentistName = trim(($appt['dentist_first_name'] ?? '') . ' ' . ($appt['dentist_last_name'] ?? ''));
                                                         $dentistName = $dentistName !== '' ? $dentistName : 'Assigned Dentist';
                                                         ?>
                                                         <button
                                                             type="button"
                                                             class="btn btn-link p-0 text-decoration-underline dentist-profile-btn"
                                                             data-dentist-id="<?php echo (int)$appt['dentist_id']; ?>"
                                                             data-dentist-name="<?php echo htmlspecialchars($dentistName); ?>"
                                                             data-dentist-email="<?php echo htmlspecialchars($appt['dentist_email'] ?? ''); ?>"
                                                             data-dentist-contact="<?php echo htmlspecialchars($appt['dentist_contact'] ?? ''); ?>"
                                                             data-dentist-specialization="<?php echo htmlspecialchars($appt['dentist_specialization'] ?? ''); ?>"
                                                             data-dentist-picture="<?php echo htmlspecialchars($appt['dentist_profile_picture'] ?? ''); ?>"
                                                             data-dentist-signature="<?php echo htmlspecialchars($appt['dentist_signature_path'] ?? ''); ?>"
                                                         >
                                                             Dr. <?php echo htmlspecialchars($dentistName); ?>
                                                         </button>
                                                     <?php else: ?>
                                                         <span class="text-muted small">To be assigned</span>
                                                     <?php endif; ?>
                                                 </td>
                                                 <td class="py-3">
                                                     <?php if($status_key === 'pending'): ?>
                                                         <button class="btn btn-sm btn-outline-danger rounded-pill cancel-appt" data-id="<?php echo $appt['appointment_id']; ?>">Cancel</button>
                                                     <?php else: ?>
                                                         <span class="text-muted small">—</span>
                                                     <?php endif; ?>
                                                 </td>
                                             </tr>
                                         <?php endwhile; ?>
                                     <?php else: ?>
                                         <tr>
                                             <td colspan="6" class="text-center py-5 text-muted">
                                                 <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3;"></i>
                                                 No appointments found.
                                             </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Mobile Card Layout -->
                <div class="appt-card-mobile">
                    <?php
                    // Reset result pointer for mobile cards
                    $appointments->data_seek(0);
                    if ($appointments->num_rows > 0):
                        while ($appt = $appointments->fetch_assoc()):
                            $status_key = strtolower(trim((string)($appt['status'] ?? '')));
                            if ($status_key === '') {
                                $status_key = 'declined';
                            }
                            if ($status_key === 'canceled') {
                                $status_key = 'cancelled';
                            }
                            $status_label = match($status_key) {
                                'pending'   => 'Pending',
                                'approved'  => 'Approved',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'declined'  => 'Declined',
                                'no_show'   => 'No Show',
                                default     => 'Declined'
                            };
                            $badgeStyle = match($status_key) {
                                'pending'   => 'background:#fef3c7;color:#92400e;',
                                'approved'  => 'background:#dcfce7;color:#166534;',
                                'completed' => 'background:#dbeafe;color:#1e40af;',
                                'cancelled' => 'background:#fee2e2;color:#991b1b;',
                                'declined'  => 'background:#fee2e2;color:#991b1b;',
                                'no_show'   => 'background:#fef2f2;color:#991b1b;',
                                default     => 'background:#f3f4f6;color:#374151;'
                            };
                    ?>
                    <div class="appt-item" id="appointment-card-<?php echo (int)$appt['appointment_id']; ?>">
                        <div class="appt-item-top">
                            <div class="appt-service"><?php echo htmlspecialchars($appt['service_name']); ?></div>
                            <span style="<?php echo $badgeStyle; ?> font-size:0.72rem;font-weight:600;padding:0.2em 0.65em;border-radius:999px;white-space:nowrap;">
                                <?php echo $status_label; ?>
                            </span>
                        </div>
                         <div class="appt-meta">
                             <span><i class="bi bi-calendar3"></i><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></span>
                             <span><i class="bi bi-clock"></i><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></span>
                         </div>
                        <div class="appt-meta" style="margin-top:0.3rem;">
                            <span><i class="bi bi-person-badge"></i>
                                <?php if (!empty($appt['dentist_id'])): ?>
                                    <?php $dentistName = trim(($appt['dentist_first_name'] ?? '') . ' ' . ($appt['dentist_last_name'] ?? '')); ?>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 text-decoration-underline dentist-profile-btn"
                                        style="font-size:0.82rem;vertical-align:baseline;"
                                        data-dentist-id="<?php echo (int)$appt['dentist_id']; ?>"
                                        data-dentist-name="<?php echo htmlspecialchars($dentistName); ?>"
                                        data-dentist-email="<?php echo htmlspecialchars($appt['dentist_email'] ?? ''); ?>"
                                        data-dentist-contact="<?php echo htmlspecialchars($appt['dentist_contact'] ?? ''); ?>"
                                        data-dentist-specialization="<?php echo htmlspecialchars($appt['dentist_specialization'] ?? ''); ?>"
                                        data-dentist-picture="<?php echo htmlspecialchars($appt['dentist_profile_picture'] ?? ''); ?>"
                                        data-dentist-signature="<?php echo htmlspecialchars($appt['dentist_signature_path'] ?? ''); ?>"
                                    >
                                        Dr. <?php echo htmlspecialchars($dentistName !== '' ? $dentistName : 'Assigned Dentist'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">To be assigned</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($status_key === 'pending'): ?>
                        <div class="appt-footer">
                            <span class="text-muted" style="font-size:0.75rem;">Awaiting confirmation</span>
                            <button class="btn btn-sm btn-outline-danger rounded-pill cancel-appt" data-id="<?php echo $appt['appointment_id']; ?>" style="font-size:0.78rem;padding:3px 14px;">Cancel</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3;"></i>
                        No appointments found.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <textarea id="reason" class="form-control" placeholder="Optional: Reason for cancellation"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dentistProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Dentist Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-4 text-center">
                            <img id="dentistProfileImage" src="../img/default-avatar.png" alt="Dentist Avatar"
                                 class="rounded-circle border" style="width:120px;height:120px;object-fit:cover;">
                            <div class="mt-2 text-muted small">Assigned Dentist</div>
                        </div>
                        <div class="col-md-8">
                            <h5 class="mb-2" id="dentistProfileName">—</h5>
                            <div class="mb-2"><strong>Email:</strong> <span id="dentistProfileEmail">—</span></div>
                            <div class="mb-2"><strong>Contact:</strong> <span id="dentistProfileContact">—</span></div>
                            <div class="mb-2"><strong>Specialization:</strong> <span id="dentistProfileSpecialization">Not specified</span></div>
                        </div>
                    </div>
                    <div class="mt-3" id="dentistSignatureWrap" style="display:none;">
                        <div class="fw-semibold mb-2">Digital Signature</div>
                        <img id="dentistSignatureImage" src="" alt="Dentist Signature"
                             class="img-fluid border rounded" style="max-height:140px;background:#fff;">
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="dentistMessageBtn" href="messages.php" class="btn btn-primary">
                        <i class="bi bi-chat-dots me-1"></i>Message Dentist
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const focusAppointmentId = <?php echo $focusAppointmentId; ?>;
        if (focusAppointmentId > 0) {
            const desktopRow = document.getElementById('appointment-row-' + focusAppointmentId);
            const mobileCard = document.getElementById('appointment-card-' + focusAppointmentId);
            const target = window.matchMedia('(max-width: 767.98px)').matches ? (mobileCard || desktopRow) : (desktopRow || mobileCard);

            if (target) {
                target.classList.add('table-info');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => target.classList.remove('table-info'), 2500);
            }
        }

        let cancelId = null;
        $('.cancel-appt').click(function() {
            cancelId = $(this).data('id');
            $('#cancelModal').modal('show');
        });

        $('#confirmCancel').click(function() {
            if (cancelId) {
                $.post('../api/cancel-appointment.php', {
                    appointment_id: cancelId,
                    reason: $('#reason').val()
                }, function(res) {
                    if (res.success) location.reload();
                    else alert(res.message);
                });
            }
        });

        $(document).on('click', '.dentist-profile-btn', function() {
            const dentistId = $(this).data('dentist-id');
            const name = $(this).data('dentist-name') || 'Assigned Dentist';
            const email = $(this).data('dentist-email') || 'Not available';
            const contact = $(this).data('dentist-contact') || 'Not available';
            const specialization = $(this).data('dentist-specialization') || 'Not specified';
            const picture = $(this).data('dentist-picture') || '';
            const signature = $(this).data('dentist-signature') || '';

            $('#dentistProfileName').text('Dr. ' + name);
            $('#dentistProfileEmail').text(email);
            $('#dentistProfileContact').text(contact);
            $('#dentistProfileSpecialization').text(specialization);

            if (picture) {
                $('#dentistProfileImage').attr('src', '../' + picture);
            } else {
                $('#dentistProfileImage').attr('src', '../img/default-avatar.png');
            }

            if (signature) {
                $('#dentistSignatureImage').attr('src', '../' + signature);
                $('#dentistSignatureWrap').show();
            } else {
                $('#dentistSignatureWrap').hide();
            }

            const msgUrl = 'messages.php?compose_to=' + encodeURIComponent(dentistId) +
                '&compose_name=' + encodeURIComponent(name) +
                '&compose_subject=' + encodeURIComponent('Appointment Concern');
            $('#dentistMessageBtn').attr('href', msgUrl);

            $('#dentistProfileModal').modal('show');
        });
    </script>
</body>
</html>
