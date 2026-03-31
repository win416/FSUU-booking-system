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
        return ['icon' => 'bi-calendar-plus-fill', 'color' => 'dark', 'bg' => 'bg-dark'];
    }
    return ['icon' => 'bi-bell-fill', 'color' => 'secondary', 'bg' => 'bg-secondary'];
}

/**
 * Returns a date-group label for a notification's created_at timestamp.
 */
function getDateGroup(string $dateStr): string {
    $ts    = strtotime($dateStr);
    $today = strtotime('today');
    $diff  = $today - strtotime(date('Y-m-d', $ts));
    if ($diff === 0) return 'Today';
    if ($diff === 86400) return 'Yesterday';
    if ($diff < 7 * 86400) return 'This Week';
    if ($diff < 30 * 86400) return 'This Month';
    return 'Older';
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
    <link href="../assets/css/patient-notifications.css?v=2" rel="stylesheet">
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
        <div class="sidebar-nav-wrap">
        <div class="sidebar-section-label">Menu</div>
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
            <div style="max-width:1100px;">

            <!-- Page Header -->
            <div class="notif-page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bi bi-bell me-2"></i>Notifications</h4>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search -->
                    <div class="notif-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="notifSearch" placeholder="Search notifications…" autocomplete="off">
                    </div>
                    <button id="markAllRead" class="btn btn-sm btn-outline-dark <?php echo $unread_count > 0 ? '' : 'd-none'; ?>">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                    <button id="clearAll" class="btn btn-sm btn-outline-danger <?php echo $total_count > 0 ? '' : 'd-none'; ?>">
                        <i class="bi bi-trash3 me-1"></i>Clear All
                    </button>
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
                        Unread <span class="badge bg-dark ms-1" id="tab-count-unread"><?php echo $unread_count; ?></span>
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
                <?php if ($total_count > 0):
                    $lastGroup = '';
                    foreach ($all_notifications as $notif):
                        $style    = getNotifStyle($notif);
                        $isUnread = !$notif['is_read'];
                        $group    = getDateGroup($notif['created_at']);
                        if ($group !== $lastGroup):
                            $lastGroup = $group;
                ?>
                    <div class="notif-date-group" data-group="<?php echo htmlspecialchars($group); ?>">
                        <span><?php echo htmlspecialchars($group); ?></span>
                    </div>
                <?php endif; ?>
                    <div class="notif-card notif-<?php echo $style['color']; ?> <?php echo $isUnread ? 'unread' : ''; ?>"
                         data-id="<?php echo $notif['notification_id']; ?>"
                         data-read="<?php echo $notif['is_read'] ? '1' : '0'; ?>"
                         data-group="<?php echo htmlspecialchars($group); ?>"
                         data-subject="<?php echo strtolower(htmlspecialchars($notif['subject'])); ?>"
                         data-body="<?php echo strtolower(htmlspecialchars($notif['message'])); ?>">
                        <div class="card-body">
                            <div class="d-flex gap-3 align-items-start">

                                <!-- Unread dot -->
                                <?php if ($isUnread): ?>
                                <div class="unread-dot"></div>
                                <?php else: ?>
                                <div style="width:8px;min-width:8px;"></div>
                                <?php endif; ?>

                                <?php
                                    $iconClass = match($style['color']) {
                                        'success'   => 'notif-icon-success',
                                        'danger'    => 'notif-icon-danger',
                                        'warning'   => 'notif-icon-warning',
                                        'dark'      => 'notif-icon-dark',
                                        default     => 'notif-icon-secondary',
                                    };
                                ?>
                                <!-- Icon -->
                                <div class="notif-icon <?= $iconClass ?>">
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
                                                <span class="badge-new badge bg-dark">New</span>
                                            <?php endif; ?>
                                            <!-- Actions (visible on hover) -->
                                            <div class="notif-actions">
                                                <?php if ($isUnread): ?>
                                                <button class="btn btn-sm btn-link p-0 text-success btn-mark-read"
                                                        title="Mark as read">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-link p-0 text-danger btn-delete-notif"
                                                        title="Delete">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="notif-body mb-1 <?php echo $isUnread ? '' : 'text-muted'; ?>">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </p>
                                    <span class="notif-time"
                                          data-ts="<?php echo $notif['created_at']; ?>"
                                          title="<?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?>">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                    </span>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>

                <!-- Empty State -->
                <div id="emptyState" class="notif-empty <?php echo $total_count > 0 ? 'd-none' : ''; ?>">
                    <i class="bi bi-bell-slash notif-empty-icon"></i>
                    <p class="fw-semibold" id="emptyStateMsg">You have no notifications yet.</p>
                    <p class="small">When you receive notifications about your appointments, they will appear here.</p>
                </div>
            </div>

        </div>
        </div><!-- end max-width wrapper -->
    </div>
</div>

<!-- Toast -->
<div id="notifToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
        <div class="toast-body" id="notifToastBody">Done.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Toast helper ─────────────────────────────────────────────────────
const toastEl  = document.getElementById('notifToast');
const toastObj = new bootstrap.Toast(toastEl, { delay: 2500 });
function showToast(msg, type = 'success') {
    toastEl.className = `toast align-items-center text-white border-0 bg-${type}`;
    document.getElementById('notifToastBody').textContent = msg;
    toastObj.show();
}

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
        $(this).html('<i class="bi bi-clock"></i> ' + timeAgo($(this).data('ts')));
    });
}
refreshTimestamps();
setInterval(refreshTimestamps, 60000);

// ── Counts ───────────────────────────────────────────────────────────
function updateCounts() {
    const total   = $('.notif-card:not([style*="display: none"])').length || $('.notif-card').length;
    const allCards = $('.notif-card');
    const unread  = allCards.filter('.unread').length;
    const read    = allCards.length - unread;

    $('#count-all').text(allCards.length);
    $('#count-unread').text(unread);
    $('#count-read').text(read);
    $('#tab-count-all').text(allCards.length);
    $('#tab-count-unread').text(unread);
    $('#tab-count-read').text(read);

    if (unread > 0) {
        $('#sidebarNotifBadge').text(unread).show();
        $('#markAllRead').removeClass('d-none');
    } else {
        $('#sidebarNotifBadge').hide();
        $('#markAllRead').addClass('d-none');
    }
    if (allCards.length > 0) {
        $('#clearAll').removeClass('d-none');
    } else {
        $('#clearAll').addClass('d-none');
    }
}

// ── Filter tabs ──────────────────────────────────────────────────────
let activeFilter = 'all';
let searchQuery  = '';

function applyFilter() {
    let visible = 0;
    // Hide all date-group dividers first
    $('.notif-date-group').hide();

    $('.notif-card').each(function () {
        const isRead    = $(this).data('read') === 1 || $(this).data('read') === '1';
        const subject   = ($(this).data('subject') || '').toLowerCase();
        const body      = ($(this).data('body') || '').toLowerCase();
        const q         = searchQuery.toLowerCase();
        const matchSearch = !q || subject.includes(q) || body.includes(q);
        const matchFilter = activeFilter === 'all' ||
                            (activeFilter === 'unread' && !isRead) ||
                            (activeFilter === 'read' && isRead);
        const show = matchFilter && matchSearch;
        $(this).toggle(show);
        if (show) {
            visible++;
            // Show the date-group divider for this group
            const group = $(this).data('group');
            $(`.notif-date-group[data-group="${group}"]`).show();
        }
    });

    const emptyMessages = {
        all:    q => q ? 'No notifications match your search.' : 'You have no notifications yet.',
        unread: q => q ? 'No unread notifications match your search.' : 'No unread notifications.',
        read:   q => q ? 'No read notifications match your search.' : 'No read notifications yet.'
    };
    if (visible === 0) {
        $('#emptyStateMsg').text(emptyMessages[activeFilter](searchQuery));
        $('#emptyState').removeClass('d-none');
    } else {
        $('#emptyState').addClass('d-none');
    }
}

$(document).on('click', '.notif-filter-tabs button', function () {
    $('.notif-filter-tabs button').removeClass('active');
    $(this).addClass('active');
    activeFilter = $(this).data('filter');
    applyFilter();
});

// ── Search ───────────────────────────────────────────────────────────
$('#notifSearch').on('input', function () {
    searchQuery = $(this).val().trim();
    applyFilter();
});

// ── Click to expand + auto-mark read ────────────────────────────────
$(document).on('click', '.notif-card', function (e) {
    if ($(e.target).closest('.notif-actions').length) return; // ignore action btns
    const card = $(this);
    card.toggleClass('expanded');
    // Auto-mark as read on click if unread
    if (card.hasClass('unread')) {
        const id = card.data('id');
        $.post('../api/mark-notifications-read.php', { notification_id: id }, function (res) {
            if (res.success) markCardAsRead(card);
        }, 'json');
    }
});

// ── Mark single as read ──────────────────────────────────────────────
$(document).on('click', '.btn-mark-read', function (e) {
    e.stopPropagation();
    const card = $(this).closest('.notif-card');
    const id   = card.data('id');
    $.post('../api/mark-notifications-read.php', { notification_id: id }, function (res) {
        if (res.success) {
            markCardAsRead(card);
            showToast('Marked as read', 'success');
        }
    }, 'json');
});

function markCardAsRead(card) {
    card.removeClass('unread').attr('data-read', '1');
    card.find('.notif-subject').removeClass('fw-bold').addClass('fw-semibold text-muted');
    card.find('.notif-body').addClass('text-muted');
    card.find('.badge-new').remove();
    card.find('.btn-mark-read').remove();
    card.find('.unread-dot').css('background', 'transparent');
    if (activeFilter === 'unread') {
        card.slideUp(250, function () {
            $(this).remove();
            updateCounts();
            applyFilter();
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
            showToast('All notifications marked as read', 'success');
        }
    }, 'json');
});

// ── Delete single notification ───────────────────────────────────────
$(document).on('click', '.btn-delete-notif', function (e) {
    e.stopPropagation();
    const card = $(this).closest('.notif-card');
    const id   = card.data('id');
    card.addClass('notif-removing');
    $.post('../api/delete-notification.php', { notification_id: id });
    setTimeout(function () {
        card.slideUp(200, function () {
            $(this).remove();
            updateCounts();
            applyFilter();
        });
    }, 300);
    showToast('Notification deleted', 'danger');
});

// ── Clear all notifications ──────────────────────────────────────────
$('#clearAll').on('click', function () {
    if (!confirm('Delete all notifications? This cannot be undone.')) return;
    $.post('../api/delete-notification.php', { all: true }, function (res) {
        if (res.success) {
            $('.notif-card, .notif-date-group').fadeOut(200, function () {
                $(this).remove();
                updateCounts();
                applyFilter();
            });
            showToast('All notifications cleared', 'danger');
        }
    }, 'json');
});

// ── Poll for new notifications ───────────────────────────────────────
setInterval(function () {
    $.getJSON('../api/mark-notifications-read.php?check=1', function (res) {
        if (res && res.unread !== undefined) {
            const current = $('.notif-card.unread').length;
            if (res.unread > current) location.reload(); // new notification arrived
        }
    });
}, 30000);
</script>
</body>
</html>
