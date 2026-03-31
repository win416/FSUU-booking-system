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

<!-- Sidebar overlay backdrop (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-topbar no-print">
    <!-- Hamburger toggle (mobile only) -->
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle navigation">
        <i class="bi bi-list"></i>
    </button>
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

        <!-- User Profile -->
        <?php
            $adminUser     = SessionManager::getUser();
            $adminFullName = htmlspecialchars(($adminUser['first_name'] ?? '') . ' ' . ($adminUser['last_name'] ?? ''));
            $adminAvatar   = !empty($adminUser['profile_picture'])
                ? '../' . htmlspecialchars($adminUser['profile_picture'])
                : null;
        ?>
        <div class="topbar-user-wrapper" id="topbarUserWrapper">
            <button class="topbar-user-btn" id="topbarUserBtn" aria-label="User menu">
                <?php if ($adminAvatar): ?>
                    <img src="<?= $adminAvatar ?>?v=<?= time() ?>" alt="Avatar" class="topbar-user-avatar">
                <?php else: ?>
                    <span class="topbar-user-initials">
                        <?= strtoupper(substr($adminUser['first_name'] ?? 'A', 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <span class="topbar-user-name"><?= $adminFullName ?></span>
                <i class="bi bi-chevron-down topbar-user-chevron"></i>
            </button>
            <div class="topbar-user-dropdown" id="topbarUserDropdown">
                <a href="settings.php" class="topbar-user-item">
                    <i class="bi bi-person-circle"></i> My Profile
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

<script>
(function () {
    var toggleBtn = document.getElementById('sidebarToggleBtn');
    var overlay   = document.getElementById('sidebarOverlay');

    function openSidebar()  { document.body.classList.add('sidebar-open'); }
    function closeSidebar() { document.body.classList.remove('sidebar-open'); }

    // Inject X close button into sidebar brand area
    var brand = document.querySelector('.sidebar .brand');
    if (brand && !document.getElementById('sidebarCloseBtn')) {
        var closeBtn = document.createElement('button');
        closeBtn.id = 'sidebarCloseBtn';
        closeBtn.className = 'sidebar-close-btn';
        closeBtn.setAttribute('aria-label', 'Close navigation');
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.addEventListener('click', closeSidebar);
        brand.appendChild(closeBtn);
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
        });
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when a nav link is clicked
    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
})();
</script>

<script>
(function () {
    var userBtn      = document.getElementById('topbarUserBtn');
    var userDropdown = document.getElementById('topbarUserDropdown');

    if (!userBtn) return;

    userBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close other dropdowns
        document.getElementById('notifDropdown').classList.remove('open');
        document.getElementById('msgDropdown').classList.remove('open');
        userDropdown.classList.toggle('open');
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('topbarUserWrapper').contains(e.target)) {
            userDropdown.classList.remove('open');
        }
    });
})();
</script>
