<?php /* Dentist Top Bar */ ?>
<link rel="stylesheet" href="../assets/css/admin-topbar.css">
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-topbar no-print">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
    </button>
    <div class="admin-topbar-inner">
        <div class="notif-wrapper" id="dtMsgWrapper">
            <button class="notif-btn" id="dtMsgBtn" title="Messages" aria-label="Messages">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="notif-badge d-none" id="dtMsgBadge">0</span>
            </button>
            <div class="notif-dropdown" id="dtMsgDropdown">
                <div class="notif-header">
                    <span><i class="bi bi-chat-dots me-1"></i>Messages</span>
                    <a href="messages.php" class="notif-view-all">Open Chat</a>
                </div>
                <ul class="notif-list" id="dtMsgList">
                    <li class="notif-empty">Loading…</li>
                </ul>
                <div class="notif-footer">
                    <a href="messages.php"><i class="bi bi-chat-dots me-1"></i>Open Messages</a>
                </div>
            </div>
        </div>

        <div class="notif-wrapper" id="dtNotifWrapper">
            <button class="notif-btn" id="dtNotifBtn" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell-fill"></i>
                <span class="notif-badge d-none" id="dtNotifBadge">0</span>
            </button>
            <div class="notif-dropdown" id="dtNotifDropdown">
                <div class="notif-header">
                    <span><i class="bi bi-bell me-1"></i>Notifications</span>
                    <a href="notifications.php" class="notif-view-all">View All</a>
                </div>
                <ul class="notif-list" id="dtNotifList">
                    <li class="notif-empty">Loading…</li>
                </ul>
                <div class="notif-footer">
                    <a href="notifications.php"><i class="bi bi-bell me-1"></i>Open Notifications</a>
                </div>
            </div>
        </div>

        <?php
            $dtUser = SessionManager::getUser();
            $dtName = 'Dr. ' . htmlspecialchars(($dtUser['first_name'] ?? '') . ' ' . ($dtUser['last_name'] ?? ''));
            $dtAvatar = !empty($dtUser['profile_picture']) ? '../' . htmlspecialchars($dtUser['profile_picture']) : null;
        ?>
        <div class="topbar-user-wrapper" id="dtTopbarUserWrapper">
            <button class="topbar-user-btn" id="dtTopbarUserBtn" aria-label="User menu">
                <?php if ($dtAvatar): ?>
                    <img src="<?= $dtAvatar ?>?v=<?= time() ?>" alt="Avatar" class="topbar-user-avatar">
                <?php else: ?>
                    <span class="topbar-user-initials"><?= strtoupper(substr($dtUser['first_name'] ?? 'D', 0, 1)) ?></span>
                <?php endif; ?>
                <span class="topbar-user-name"><?= $dtName ?></span>
                <i class="bi bi-chevron-down topbar-user-chevron"></i>
            </button>
            <div class="topbar-user-dropdown" id="dtTopbarUserDropdown">
                <a href="profile.php" class="topbar-user-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <div class="topbar-user-divider"></div>
                <a href="../auth/logout.php" class="topbar-user-item topbar-user-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const msgBtn = document.getElementById('dtMsgBtn');
    const msgDrop = document.getElementById('dtMsgDropdown');
    const msgBadge = document.getElementById('dtMsgBadge');
    const msgList = document.getElementById('dtMsgList');
    const notifBtn = document.getElementById('dtNotifBtn');
    const notifDrop = document.getElementById('dtNotifDropdown');
    const notifBadge = document.getElementById('dtNotifBadge');
    const notifList = document.getElementById('dtNotifList');
    const userBtn = document.getElementById('dtTopbarUserBtn');
    const userDrop = document.getElementById('dtTopbarUserDropdown');

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr.replace(' ','T'))) / 1000);
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function fetchMessages() {
        fetch('../api/messages.php?action=unread_count')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                if (res.count > 0) {
                    msgBadge.textContent = res.count > 99 ? '99+' : res.count;
                    msgBadge.classList.remove('d-none');
                } else {
                    msgBadge.classList.add('d-none');
                }
                const sb = document.getElementById('sidebarMsgBadge');
                if (sb) { sb.textContent = res.count; sb.style.display = res.count > 0 ? '' : 'none'; }

                if (!msgDrop.classList.contains('open')) return;
                if (!res.previews || res.previews.length === 0) {
                    msgList.innerHTML = '<li class="notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No unread messages</li>';
                    return;
                }
                msgList.innerHTML = res.previews.map(p => `
                    <li class="notif-item">
                        <a href="messages.php" class="notif-link">
                            <div class="notif-icon"><i class="bi bi-person-circle"></i></div>
                            <div class="notif-body">
                                <div class="notif-name">FSUU Admin</div>
                                <div class="notif-detail">${(p.message || p.last_msg || '').substring(0, 60)}${((p.message || p.last_msg || '').length > 60) ? '…' : ''}</div>
                                <div class="notif-time">${timeAgo(p.created_at || p.last_at || new Date().toISOString())}</div>
                            </div>
                        </a>
                    </li>`).join('');
            })
            .catch(() => {});
    }

    function fetchNotifications() {
        fetch('../api/patient-notifications.php')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                if (res.count > 0) {
                    notifBadge.textContent = res.count > 99 ? '99+' : res.count;
                    notifBadge.classList.remove('d-none');
                } else {
                    notifBadge.classList.add('d-none');
                }
                const sb = document.getElementById('sidebarNotifBadge');
                if (sb) { sb.textContent = res.count; sb.style.display = res.count > 0 ? '' : 'none'; }

                if (!notifDrop.classList.contains('open')) return;
                if (!res.notifications || res.notifications.length === 0) {
                    notifList.innerHTML = '<li class="notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No unread notifications</li>';
                    return;
                }
                notifList.innerHTML = res.notifications.map(n => `
                    <li class="notif-item">
                        <a href="notifications.php" class="notif-link">
                            <div class="notif-icon"><i class="bi bi-bell"></i></div>
                            <div class="notif-body">
                                <div class="notif-name">${n.subject || 'Notification'}</div>
                                <div class="notif-detail">${(n.message || '').substring(0, 60)}${(n.message || '').length > 60 ? '…' : ''}</div>
                                <div class="notif-time">${timeAgo(n.created_at)}</div>
                            </div>
                        </a>
                    </li>`).join('');
            })
            .catch(() => {});
    }

    msgBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        notifDrop.classList.remove('open');
        userDrop.classList.remove('open');
        msgDrop.classList.toggle('open');
        if (msgDrop.classList.contains('open')) fetchMessages();
    });
    notifBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgDrop.classList.remove('open');
        userDrop.classList.remove('open');
        notifDrop.classList.toggle('open');
        if (notifDrop.classList.contains('open')) fetchNotifications();
    });
    userBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgDrop.classList.remove('open');
        notifDrop.classList.remove('open');
        userDrop.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!document.getElementById('dtMsgWrapper').contains(e.target)) msgDrop.classList.remove('open');
        if (!document.getElementById('dtNotifWrapper').contains(e.target)) notifDrop.classList.remove('open');
        if (!document.getElementById('dtTopbarUserWrapper').contains(e.target)) userDrop.classList.remove('open');
    });

    fetchMessages();
    fetchNotifications();
    setInterval(fetchMessages, 30000);
    setInterval(fetchNotifications, 30000);
})();
</script>

<script>
(function () {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const overlay = document.getElementById('sidebarOverlay');
    function openSidebar() { document.body.classList.add('sidebar-open'); }
    function closeSidebar() { document.body.classList.remove('sidebar-open'); }
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
        });
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
})();
</script>
