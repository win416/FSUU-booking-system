<!-- Logout Confirmation Modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(2px);" id="logoutOverlay"></div>
    <div style="position:relative;background:#fff;border-radius:16px;padding:2rem 2rem 1.5rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;z-index:1;">
        <div style="width:56px;height:56px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <i class="bi bi-box-arrow-right" style="font-size:1.6rem;color:#ef4444;"></i>
        </div>
        <h5 style="font-weight:700;color:#111827;margin-bottom:0.4rem;">Log Out?</h5>
        <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.5rem;">Are you sure you want to log out of your account?</p>
        <div style="display:flex;gap:0.75rem;justify-content:center;">
            <button id="logoutCancelBtn" style="flex:1;padding:0.6rem 1rem;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#374151;font-weight:500;cursor:pointer;font-size:0.9rem;">Cancel</button>
            <a id="logoutConfirmBtn" href="../auth/logout.php" style="flex:1;padding:0.6rem 1rem;border:none;border-radius:8px;background:#ef4444;color:#fff;font-weight:600;cursor:pointer;font-size:0.9rem;text-decoration:none;display:flex;align-items:center;justify-content:center;">Yes, Log Out</a>
        </div>
    </div>
</div>
<script>
(function () {
    const modal      = document.getElementById('logoutModal');
    const overlay    = document.getElementById('logoutOverlay');
    const cancelBtn  = document.getElementById('logoutCancelBtn');

    function openLogoutModal(e) {
        e.preventDefault();
        modal.style.display = 'flex';
    }
    function closeLogoutModal() {
        modal.style.display = 'none';
    }

    // Attach to all logout links (but not the confirm button itself)
    document.querySelectorAll('a[href*="logout.php"]:not(#logoutConfirmBtn)').forEach(function (el) {
        el.addEventListener('click', openLogoutModal);
    });

    cancelBtn.addEventListener('click', closeLogoutModal);
    overlay.addEventListener('click', closeLogoutModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLogoutModal();
    });
})();
</script>
<?php /* Patient Top Bar — include inside .main-content, before container */ ?>
<div class="patient-topbar">
    <div class="patient-topbar-inner">
        <!-- Message Icon -->
        <div class="pt-notif-wrapper" id="ptMsgWrapper">
            <button class="pt-notif-btn" id="ptMsgBtn" title="Messages" aria-label="Messages">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="pt-notif-badge d-none" id="ptMsgBadge">0</span>
            </button>
            <div class="pt-notif-dropdown" id="ptMsgDropdown">
                <div class="pt-notif-header">
                    <span><i class="bi bi-chat-dots me-1"></i>Messages</span>
                    <a href="messages.php" class="pt-notif-view-all">Open Chat</a>
                </div>
                <ul class="pt-notif-list" id="ptMsgList">
                    <li class="pt-notif-empty">Loading…</li>
                </ul>
                <div class="pt-notif-footer">
                    <a href="messages.php"><i class="bi bi-chat-dots me-1"></i>Open Messages</a>
                </div>
            </div>
        </div>

        <!-- Notification Bell -->
        <div class="pt-notif-wrapper" id="ptNotifWrapper">
            <button class="pt-notif-btn" id="ptNotifBtn" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell-fill"></i>
                <span class="pt-notif-badge d-none" id="ptNotifBadge">0</span>
            </button>

            <!-- Dropdown panel -->
            <div class="pt-notif-dropdown" id="ptNotifDropdown">
                <div class="pt-notif-header">
                    <span><i class="bi bi-bell me-1"></i>Notifications</span>
                    <a href="notifications.php" class="pt-notif-view-all">View All</a>
                </div>
                <ul class="pt-notif-list" id="ptNotifList">
                    <li class="pt-notif-empty">Loading…</li>
                </ul>
                <div class="pt-notif-footer">
                    <a href="notifications.php">
                        <i class="bi bi-bell me-1"></i>See all notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const btn      = document.getElementById('ptNotifBtn');
    const dropdown = document.getElementById('ptNotifDropdown');
    const badge    = document.getElementById('ptNotifBadge');
    const list     = document.getElementById('ptNotifList');

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000);
        if (diff < 60)    return diff + 's ago';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function iconForSubject(subject) {
        const s = (subject || '').toLowerCase();
        if (s.includes('approved') || s.includes('confirmed') || s.includes('completed'))
            return { icon: 'bi-check-circle-fill', color: '#198754' };
        if (s.includes('declined') || s.includes('rejected') || s.includes('cancel'))
            return { icon: 'bi-x-circle-fill', color: '#dc3545' };
        if (s.includes('reminder'))
            return { icon: 'bi-alarm-fill', color: '#ffc107' };
        return { icon: 'bi-calendar-plus-fill', color: '#00aeef' };
    }

    function fetchNotifications() {
        fetch('../api/patient-notifications.php')
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

                // Also update sidebar badge if present
                const sidebarBadge = document.getElementById('sidebarNotifBadge');
                if (sidebarBadge) {
                    if (res.count > 0) {
                        sidebarBadge.textContent = res.count;
                        sidebarBadge.style.display = '';
                    } else {
                        sidebarBadge.style.display = 'none';
                    }
                }

                // List
                if (res.notifications.length === 0) {
                    list.innerHTML = '<li class="pt-notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No unread notifications</li>';
                    return;
                }

                list.innerHTML = res.notifications.map(n => {
                    const { icon, color } = iconForSubject(n.subject);
                    return `<li class="pt-notif-item">
                        <a href="notifications.php" class="pt-notif-link">
                            <div class="pt-notif-icon" style="color:${color}">
                                <i class="bi ${icon}"></i>
                            </div>
                            <div class="pt-notif-body">
                                <div class="pt-notif-name">${n.subject}</div>
                                <div class="pt-notif-detail">${n.message.substring(0, 60)}${n.message.length > 60 ? '…' : ''}</div>
                                <div class="pt-notif-time">${timeAgo(n.created_at)}</div>
                            </div>
                        </a>
                    </li>`;
                }).join('');
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
        if (!document.getElementById('ptNotifWrapper').contains(e.target)) {
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
    const btn      = document.getElementById('ptMsgBtn');
    const dropdown = document.getElementById('ptMsgDropdown');
    const badge    = document.getElementById('ptMsgBadge');
    const list     = document.getElementById('ptMsgList');

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
                    list.innerHTML = '<li class="pt-notif-empty"><i class="bi bi-check-circle text-success me-1"></i>No unread messages</li>';
                    return;
                }
                list.innerHTML = res.previews.map(p => `
                    <li class="pt-notif-item">
                        <a href="messages.php" class="pt-notif-link">
                            <div class="pt-notif-icon" style="color:#00aeef">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="pt-notif-body">
                                <div class="pt-notif-name">FSUU Admin</div>
                                <div class="pt-notif-detail">${p.message ? p.message.substring(0,60)+(p.message.length>60?'…':'') : ''}</div>
                                <div class="pt-notif-time">${timeAgo(p.created_at)}</div>
                            </div>
                        </a>
                    </li>`).join('');
            })
            .catch(() => {});
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close notif dropdown if open
        document.getElementById('ptNotifDropdown').classList.remove('open');
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) fetchMessages();
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('ptMsgWrapper').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    fetchMessages();
    setInterval(fetchMessages, 30000);
})();
</script>
