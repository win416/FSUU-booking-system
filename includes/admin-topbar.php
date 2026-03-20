<?php /* Admin Top Bar — include inside .main-content, before container-fluid */ ?>
<div class="admin-topbar no-print">
    <div class="admin-topbar-inner">
        <!-- Message Icon -->
        <div class="notif-wrapper" id="msgWrapper">
            <button class="notif-btn" id="msgBtn" title="Messages" aria-label="Messages">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="notif-badge d-none" id="msgBadge">0</span>
            </button>
            <div class="notif-dropdown" id="msgDropdown">
                <div class="notif-header">
                    <span><i class="bi bi-chat-dots me-1"></i>Messages</span>
                    <a href="messages.php" class="notif-view-all">Open Inbox</a>
                </div>
                <ul class="notif-list" id="msgList">
                    <li class="notif-empty">Loading…</li>
                </ul>
                <div class="notif-footer">
                    <a href="messages.php"><i class="bi bi-chat-dots me-1"></i>View All Messages</a>
                </div>
            </div>
        </div>

        <!-- Notification Bell -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-btn" id="notifBtn" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell-fill"></i>
                <span class="notif-badge d-none" id="notifBadge">0</span>
            </button>

            <!-- Dropdown panel -->
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span><i class="bi bi-bell me-1"></i>New Bookings</span>
                    <a href="appointments.php?status=pending" class="notif-view-all">View All</a>
                </div>
                <ul class="notif-list" id="notifList">
                    <li class="notif-empty">Loading…</li>
                </ul>
                <div class="notif-footer">
                    <a href="appointments.php?status=pending">
                        <i class="bi bi-calendar-check me-1"></i>Manage Pending Appointments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const btn      = document.getElementById('notifBtn');
    const dropdown = document.getElementById('notifDropdown');
    const badge    = document.getElementById('notifBadge');
    const list     = document.getElementById('notifList');

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)   return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400)return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function fetchNotifications() {
        fetch('../api/notifications.php')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;

                // Badge
                if (res.count > 0) {
                    badge.textContent = res.count > 99 ? '99+' : res.count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }

                // List
                if (res.notifications.length === 0) {
                    list.innerHTML = '<li class="notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No pending bookings</li>';
                    return;
                }

                list.innerHTML = res.notifications.map(n => `
                    <li class="notif-item">
                        <a href="appointments.php?status=pending" class="notif-link">
                            <div class="notif-icon"><i class="bi bi-calendar-plus"></i></div>
                            <div class="notif-body">
                                <div class="notif-name">${n.patient}</div>
                                <div class="notif-detail">${n.service} &mdash; ${n.date}</div>
                                <div class="notif-time">${timeAgo(n.created_at)}</div>
                            </div>
                        </a>
                    </li>`).join('');
            })
            .catch(() => {});
    }

    // Toggle dropdown
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) fetchNotifications();
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!document.getElementById('notifWrapper').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Initial fetch + poll every 30s
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
})();
</script>

<script>
(function () {
    const btn      = document.getElementById('msgBtn');
    const dropdown = document.getElementById('msgDropdown');
    const badge    = document.getElementById('msgBadge');
    const list     = document.getElementById('msgList');

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr.replace(' ','T'))) / 1000);
        if (diff < 60)    return diff + 's ago';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function fetchMessages() {
        fetch('../api/messages.php?action=unread_count')
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;

                // Badge
                if (res.count > 0) {
                    badge.textContent = res.count > 99 ? '99+' : res.count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }

                // Sidebar badge
                const sb = document.getElementById('sidebarMsgBadge');
                if (sb) { sb.textContent = res.count; sb.style.display = res.count > 0 ? '' : 'none'; }

                if (!dropdown.classList.contains('open')) return;

                if (!res.previews || res.previews.length === 0) {
                    list.innerHTML = '<li class="notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No unread messages</li>';
                    return;
                }
                list.innerHTML = res.previews.map(p => `
                    <li class="notif-item">
                        <a href="messages.php?with=${p.user_id}" class="notif-link">
                            <div class="notif-icon"><i class="bi bi-person-circle"></i></div>
                            <div class="notif-body">
                                <div class="notif-name">${p.name}</div>
                                <div class="notif-detail">${p.last_msg ? p.last_msg.substring(0,55) + (p.last_msg.length>55?'…':'') : ''}</div>
                                <div class="notif-time">${timeAgo(p.last_at)}</div>
                            </div>
                        </a>
                    </li>`).join('');
            })
            .catch(() => {});
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close notif dropdown if open
        document.getElementById('notifDropdown').classList.remove('open');
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) fetchMessages();
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('msgWrapper').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    fetchMessages();
    setInterval(fetchMessages, 30000);
})();
</script>
