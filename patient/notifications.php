<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Unread count (for sidebar badge & header)
$unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user['user_id']);
$unread_stmt->execute();
$unread_count = (int) $unread_stmt->get_result()->fetch_assoc()['count'];

// All notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$all_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_count = count($all_notifications);
$read_count  = $total_count - $unread_count;

/**
 * Returns an icon class + color variant based on notification subject/type.
 */
function getNotifStyle(array $notif): array {
    $subject = strtolower($notif['subject'] ?? '');
    $type    = strtolower($notif['type']    ?? '');

    if (strpos($subject, 'approved') !== false || strpos($subject, 'confirmed') !== false || strpos($subject, 'completed') !== false) {
        return ['icon' => 'bi-check-circle-fill', 'color' => 'success', 'bg' => 'bg-success'];
    }
    if (strpos($subject, 'declined') !== false || strpos($subject, 'rejected') !== false) {
        return ['icon' => 'bi-x-circle-fill', 'color' => 'danger', 'bg' => 'bg-danger'];
    }
    if (strpos($subject, 'cancel') !== false) {
        return ['icon' => 'bi-calendar-x-fill', 'color' => 'danger', 'bg' => 'bg-danger'];
    }
    if ($type === 'reminder' || strpos($subject, 'reminder') !== false) {
        return ['icon' => 'bi-alarm-fill', 'color' => 'warning', 'bg' => 'bg-warning'];
    }
    if (strpos($subject, 'submit') !== false || strpos($subject, 'book') !== false ||
        strpos($subject, 'request') !== false || strpos($subject, 'new') !== false) {
        return ['icon' => 'bi-calendar-plus-fill', 'color' => 'primary', 'bg' => 'bg-primary'];
    }
    return ['icon' => 'bi-bell-fill', 'color' => 'secondary', 'bg' => 'bg-secondary'];
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
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-notifications.css" rel="stylesheet">
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
            <li class="nav-item"><a class="nav-link" href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a></li>
            <li class="nav-item"><a class="nav-link" href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a></li>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-1 fw-bold"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h4>
                    <div class="notif-summary d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark border">
                            <span id="count-all"><?php echo $total_count; ?></span> Total
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                            <span id="count-unread"><?php echo $unread_count; ?></span> Unread
                        </span>
                        <span class="badge bg-light text-secondary border">
                            <span id="count-read"><?php echo $read_count; ?></span> Read
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unread_count > 0): ?>
                    <button id="markAllRead" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                    <?php else: ?>
                    <button id="markAllRead" class="btn btn-sm btn-outline-primary" style="display:none">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Tabs -->
            <ul class="nav notif-filter-tabs">
                <li class="nav-item">
                    <button class="nav-link active" data-filter="all">
                        All <span class="badge bg-secondary ms-1" id="tab-count-all"><?php echo $total_count; ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-filter="unread">
                        Unread <span class="badge bg-primary ms-1" id="tab-count-unread"><?php echo $unread_count; ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-filter="read">
                        Read <span class="badge bg-light text-dark border ms-1" id="tab-count-read"><?php echo $read_count; ?></span>
                    </button>
                </li>
            </ul>

            <!-- Notifications List -->
            <div id="notifList">
                <?php if ($total_count > 0): ?>
                    <?php foreach ($all_notifications as $notif):
                        $style   = getNotifStyle($notif);
                        $isUnread = !$notif['is_read'];
                    ?>
                    <div class="notif-card notif-<?php echo $style['color']; ?> <?php echo $isUnread ? 'unread' : ''; ?>"
                         data-id="<?php echo $notif['notification_id']; ?>"
                         data-read="<?php echo $notif['is_read'] ? '1' : '0'; ?>">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex gap-3 align-items-start">

                                <!-- Icon -->
                                <div class="notif-icon <?php echo $style['bg']; ?> bg-opacity-15 text-<?php echo $style['color']; ?>">
                                    <i class="bi <?php echo $style['icon']; ?>"></i>
                                </div>

                                <!-- Content -->
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <h6 class="notif-subject mb-1 <?php echo $isUnread ? 'fw-bold' : 'fw-semibold text-muted'; ?>">
                                            <?php echo htmlspecialchars($notif['subject']); ?>
                                        </h6>
                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                            <?php if ($isUnread): ?>
                                                <span class="badge-new badge bg-primary" style="font-size:0.68rem">New</span>
                                            <?php endif; ?>
                                            <!-- Actions (visible on hover) -->
                                            <div class="notif-actions">
                                                <?php if ($isUnread): ?>
                                                <button class="btn btn-sm btn-link p-0 text-success btn-mark-read"
                                                        title="Mark as read" style="line-height:1">
                                                    <i class="bi bi-check2-circle fs-5"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-link p-0 text-danger btn-delete-notif"
                                                        title="Delete" style="line-height:1">
                                                    <i class="bi bi-trash3 fs-5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mb-1 <?php echo $isUnread ? '' : 'text-muted'; ?>" style="font-size:0.9rem">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </p>
                                    <span class="notif-time"
                                          data-ts="<?php echo $notif['created_at']; ?>"
                                          title="<?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?>">
                                        <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                    </span>
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Empty State -->
                <div id="emptyState" class="card notif-empty <?php echo $total_count > 0 ? 'd-none' : ''; ?>">
                    <i class="bi bi-bell-slash notif-empty-icon"></i>
                    <p class="mb-0 fw-semibold" id="emptyStateMsg">You have no notifications yet.</p>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// ── Relative timestamps ──────────────────────────────────────────────
function timeAgo(dateStr) {
    const date = new Date(dateStr.replace(' ', 'T'));
    const diff = Math.floor((Date.now() - date) / 1000);
    if (diff < 60)     return 'Just now';
    if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function refreshTimestamps() {
    $('[data-ts]').each(function () {
        $(this).text(timeAgo($(this).data('ts')));
    });
}
refreshTimestamps();
setInterval(refreshTimestamps, 60000);

// ── Counts ───────────────────────────────────────────────────────────
function updateCounts() {
    const total   = $('.notif-card').length;
    const unread  = $('.notif-card.unread').length;
    const read    = total - unread;

    $('#count-all').text(total);
    $('#count-unread').text(unread);
    $('#count-read').text(read);
    $('#tab-count-all').text(total);
    $('#tab-count-unread').text(unread);
    $('#tab-count-read').text(read);

    if (unread > 0) {
        $('#sidebarNotifBadge').text(unread).show();
        $('#markAllRead').show();
    } else {
        $('#sidebarNotifBadge').hide();
        $('#markAllRead').hide();
    }
}

// ── Filter tabs ──────────────────────────────────────────────────────
let activeFilter = 'all';

function applyFilter(filter) {
    activeFilter = filter;
    let visible = 0;
    $('.notif-card').each(function () {
        const isRead = $(this).data('read') === 1 || $(this).data('read') === '1';
        let show = (filter === 'all') ||
                   (filter === 'unread' && !isRead) ||
                   (filter === 'read'   && isRead);
        $(this).toggle(show);
        if (show) visible++;
    });

    const emptyMessages = {
        all:    'You have no notifications yet.',
        unread: 'No unread notifications.',
        read:   'No read notifications yet.'
    };
    if (visible === 0) {
        $('#emptyStateMsg').text(emptyMessages[filter]);
        $('#emptyState').removeClass('d-none');
    } else {
        $('#emptyState').addClass('d-none');
    }
}

$(document).on('click', '.notif-filter-tabs button', function () {
    $('.notif-filter-tabs button').removeClass('active');
    $(this).addClass('active');
    applyFilter($(this).data('filter'));
});

// ── Mark single as read ──────────────────────────────────────────────
$(document).on('click', '.btn-mark-read', function (e) {
    e.stopPropagation();
    const card = $(this).closest('.notif-card');
    const id   = card.data('id');
    $.post('../api/mark-notifications-read.php', { notification_id: id }, function (res) {
        if (res.success) markCardAsRead(card);
    }, 'json');
});

function markCardAsRead(card) {
    card.removeClass('unread').attr('data-read', '1');
    card.find('.notif-subject').removeClass('fw-bold').addClass('fw-semibold text-muted');
    card.find('p').addClass('text-muted');
    card.find('.badge-new').remove();
    card.find('.btn-mark-read').remove();
    if (activeFilter === 'unread') {
        card.slideUp(250, function () {
            $(this).remove();
            updateCounts();
            applyFilter(activeFilter);
        });
        return;
    }
    updateCounts();
}

// ── Mark all as read ─────────────────────────────────────────────────
$('#markAllRead').on('click', function () {
    $.post('../api/mark-notifications-read.php', { all: true }, function (res) {
        if (res.success) {
            $('.notif-card.unread').each(function () { markCardAsRead($(this)); });
            updateCounts();
        }
    }, 'json');
});

// ── Delete notification ──────────────────────────────────────────────
$(document).on('click', '.btn-delete-notif', function (e) {
    e.stopPropagation();
    const card = $(this).closest('.notif-card');
    const id   = card.data('id');
    card.addClass('notif-removing');
    setTimeout(function () {
        card.slideUp(200, function () {
            $(this).remove();
            updateCounts();
            applyFilter(activeFilter);
        });
    }, 300);
    $.post('../api/delete-notification.php', { notification_id: id });
});
</script>
</body>
</html>
