<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . SITE_URL . '/patient/notifications.php');
    }
    exit();
}

$user = SessionManager::getUser();
$db = getDB();
$unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user['user_id']);
$unread_stmt->execute();
$unread_count = (int)$unread_stmt->get_result()->fetch_assoc()['count'];

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$all_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_count = count($all_notifications);
$read_count = $total_count - $unread_count;

function dentistNotifStyle(array $notif): array {
    $subject = strtolower($notif['subject'] ?? '');
    $type = strtolower($notif['type'] ?? '');
    if (strpos($subject, 'new appointment assigned') !== false || strpos($subject, 'assigned') !== false) {
        return ['icon' => 'bi-calendar-check-fill', 'color' => 'primary', 'bg' => 'bg-primary'];
    }
    if (strpos($subject, 'approved') !== false || strpos($subject, 'confirmed') !== false || strpos($subject, 'completed') !== false) {
        return ['icon' => 'bi-check-circle-fill', 'color' => 'success', 'bg' => 'bg-success'];
    }
    if (strpos($subject, 'declined') !== false || strpos($subject, 'rejected') !== false || strpos($subject, 'cancel') !== false) {
        return ['icon' => 'bi-x-circle-fill', 'color' => 'danger', 'bg' => 'bg-danger'];
    }
    if ($type === 'reminder' || strpos($subject, 'reminder') !== false) {
        return ['icon' => 'bi-alarm-fill', 'color' => 'warning', 'bg' => 'bg-warning'];
    }
    return ['icon' => 'bi-bell-fill', 'color' => 'secondary', 'bg' => 'bg-secondary'];
}
function dentistDateGroup(string $dateStr): string {
    $ts = strtotime($dateStr);
    $today = strtotime('today');
    $diff = $today - strtotime(date('Y-m-d', $ts));
    if ($diff === 0) return 'Today';
    if ($diff === 86400) return 'Yesterday';
    if ($diff < 7 * 86400) return 'This Week';
    if ($diff < 30 * 86400) return 'This Month';
    return 'Older';
}

function resolveAppointmentIdFromNotification(mysqli $db, int $dentistId, array $notif): ?int {
    $message = trim((string)($notif['message'] ?? ''));
    if ($message === '') return null;

    if (!preg_match_all('/([A-Za-z]+ \d{1,2}, \d{4}) at (\d{1,2}:\d{2} [AP]M)/i', $message, $dateMatches, PREG_SET_ORDER)) {
        return null;
    }

    $lastMatch = end($dateMatches);
    $dt = DateTime::createFromFormat('F j, Y g:i A', $lastMatch[1] . ' ' . $lastMatch[2]);
    if (!$dt) return null;

    $date = $dt->format('Y-m-d');
    $time = $dt->format('H:i:s');

    $fullName = null;
    if (preg_match('/Patient:\s*([A-Za-z.\'\-\s]+)\.?/i', $message, $nameMatch)) {
        $fullName = trim($nameMatch[1]);
    }

    if ($fullName) {
        $stmt = $db->prepare("
            SELECT a.appointment_id
            FROM dentist_appointment_assignments da
            JOIN appointments a ON a.appointment_id = da.appointment_id
            JOIN users u ON u.user_id = a.user_id
            WHERE da.dentist_id = ?
              AND a.appointment_date = ?
              AND a.appointment_time = ?
              AND TRIM(LOWER(CONCAT(u.first_name, ' ', u.last_name))) = TRIM(LOWER(?))
            ORDER BY a.created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("isss", $dentistId, $date, $time, $fullName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) return (int)$row['appointment_id'];
    }

    $stmt = $db->prepare("
        SELECT a.appointment_id
        FROM dentist_appointment_assignments da
        JOIN appointments a ON a.appointment_id = da.appointment_id
        WHERE da.dentist_id = ?
          AND a.appointment_date = ?
          AND a.appointment_time = ?
        ORDER BY a.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("iss", $dentistId, $date, $time);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['appointment_id'] : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-notifications.css?v=2" rel="stylesheet">
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
            <li class="nav-item"><a class="nav-link" href="my-patients.php"><i class="bi bi-people"></i> My Patients</a></li>
            <li class="nav-item">
                <a class="nav-link active" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                    <?php else: ?>
                        <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
        </ul>
        </div>
    </nav>

    <div class="main-content">
        <?php include '../includes/dentist-topbar.php'; ?>
        <div class="container-fluid my-4 d-flex justify-content-center">
            <div class="notif-wrapper">
            <div class="notif-page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div><h4 class="fw-bold mb-1"><i class="bi bi-bell me-2"></i>Notifications</h4></div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="notif-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="notifSearch" placeholder="Search notifications…" autocomplete="off">
                    </div>
                    <button id="markAllRead" class="btn btn-sm btn-outline-dark <?php echo $unread_count > 0 ? '' : 'd-none'; ?>">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                </div>
            </div>

            <ul class="nav notif-filter-tabs">
                <li class="nav-item"><button class="nav-link active" data-filter="all">All <span class="badge bg-secondary ms-1" id="tab-count-all"><?php echo $total_count; ?></span></button></li>
                <li class="nav-item"><button class="nav-link" data-filter="unread">Unread <span class="badge bg-dark ms-1" id="tab-count-unread"><?php echo $unread_count; ?></span></button></li>
                <li class="nav-item"><button class="nav-link" data-filter="read">Read <span class="badge bg-light text-dark border ms-1" id="tab-count-read"><?php echo $read_count; ?></span></button></li>
            </ul>

            <div id="notifList">
                <?php if ($total_count > 0):
                    $lastGroup = '';
                    foreach ($all_notifications as $notif):
                        $style = dentistNotifStyle($notif);
                        $isUnread = !$notif['is_read'];
                        $group = dentistDateGroup($notif['created_at']);
                        $resolvedAppointmentId = resolveAppointmentIdFromNotification($db, (int)$user['user_id'], $notif);
                        if ($group !== $lastGroup):
                            $lastGroup = $group;
                ?>
                    <div class="notif-date-group" data-group="<?php echo htmlspecialchars($group); ?>"><span><?php echo htmlspecialchars($group); ?></span></div>
                <?php endif; ?>
                     <div class="notif-card notif-<?php echo $style['color']; ?> <?php echo $isUnread ? 'unread' : ''; ?>"
                          data-id="<?php echo $notif['notification_id']; ?>"
                          data-read="<?php echo $notif['is_read'] ? '1' : '0'; ?>"
                          data-group="<?php echo htmlspecialchars($group); ?>"
                          data-subject="<?php echo strtolower(htmlspecialchars($notif['subject'])); ?>"
                          data-body="<?php echo strtolower(htmlspecialchars($notif['message'])); ?>"
                          data-appointment-id="<?php echo (int)($resolvedAppointmentId ?? 0); ?>"
                          style="cursor:pointer;">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <h6 class="notif-subject mb-1 <?php echo $isUnread ? 'fw-bold' : 'fw-semibold text-muted'; ?>">
                                            <?php echo htmlspecialchars($notif['subject']); ?>
                                        </h6>
                                        <?php if ($isUnread): ?><span class="badge-new badge bg-dark">New</span><?php endif; ?>
                                    </div>
                                    <p class="notif-body mb-1 <?php echo $isUnread ? '' : 'text-muted'; ?>"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="notif-time" data-ts="<?php echo $notif['created_at']; ?>" title="<?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?>">
                                        <i class="bi bi-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>

                <div id="emptyState" class="notif-empty <?php echo $total_count > 0 ? 'd-none' : ''; ?>">
                    <i class="bi bi-bell-slash notif-empty-icon"></i>
                    <p class="fw-semibold" id="emptyStateMsg">You have no notifications yet.</p>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.notif-filter-tabs .nav-link').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.notif-filter-tabs .nav-link').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('.notif-card').forEach(c => {
            const isRead = c.dataset.read === '1';
            c.style.display =
                (filter === 'all') ||
                (filter === 'unread' && !isRead) ||
                (filter === 'read' && isRead) ? '' : 'none';
        });
    });
});
document.getElementById('notifSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.notif-card').forEach(c => {
        const m = c.dataset.subject.includes(q) || c.dataset.body.includes(q);
        c.style.display = m ? '' : 'none';
    });
});
document.getElementById('markAllRead')?.addEventListener('click', function() {
    fetch('../api/mark-notifications-read.php', {
        method:'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'all=true'
    })
        .then(r => r.json()).then(() => location.reload()).catch(() => {});
});
document.querySelectorAll('.notif-card').forEach(card => {
    card.addEventListener('click', function() {
        const id = this.dataset.id;
        const appointmentId = parseInt(this.dataset.appointmentId || '0', 10);
        const isUnread = this.dataset.read === '0';
        const go = () => {
            window.location.href = appointmentId > 0
                ? ('appointments.php?appointment_id=' + encodeURIComponent(appointmentId))
                : 'appointments.php';
        };

        if (!isUnread) {
            go();
            return;
        }

        fetch('../api/mark-notifications-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'notification_id=' + encodeURIComponent(id)
        }).finally(go);
    });
});
</script>
</body>
</html>
