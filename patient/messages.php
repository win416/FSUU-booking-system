<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db   = getDB();

// Unread count for sidebar badge
$unread_stmt = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user['user_id']);
$unread_stmt->execute();
$unread_notif = (int)$unread_stmt->get_result()->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/patient-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-messages.css" rel="stylesheet">
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
                <a class="nav-link" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                    <?php if ($unread_notif > 0): ?>
                        <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2"><?php echo $unread_notif; ?></span>
                    <?php else: ?>
                        <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link active" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
            <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
            <li class="nav-item logout-nav-item">
                <a class="nav-link text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <div class="main-content" style="padding: 56px 0 0 0 !important;">
        <?php include '../includes/patient-topbar.php'; ?>
        <div class="msg-layout">

            <!-- ── Inbox panel ─────────────────────────────────────────── -->
            <div class="inbox-panel">
                <div class="inbox-header">
                    <span class="inbox-title"><i class="bi bi-envelope me-2"></i>Messages</span>
                    <button class="compose-btn" id="composeBtn">
                        <i class="bi bi-pencil-square"></i> Compose
                    </button>
                </div>
                <div class="inbox-tabs">
                    <button class="inbox-tab active" id="tabInbox" onclick="switchTab('inbox')">
                        <i class="bi bi-inbox me-1"></i>Inbox
                    </button>
                    <button class="inbox-tab" id="tabSent" onclick="switchTab('sent')">
                        <i class="bi bi-send me-1"></i>Sent
                    </button>
                </div>
                <div class="inbox-list" id="inboxList">
                    <div class="inbox-empty">
                        <i class="bi bi-hourglass-split d-block mb-1" style="font-size:1.4rem"></i>
                        Loading…
                    </div>
                </div>
            </div>

            <!-- ── Right panel ─────────────────────────────────────────── -->
            <div class="right-panel" id="rightPanel">

                <!-- Empty state (default) -->
                <div class="empty-state" id="emptyState">
                    <i class="bi bi-chat-square-dots"></i>
                    <p>Select a conversation or compose a new message</p>
                    <button class="compose-btn" onclick="showCompose()">
                        <i class="bi bi-pencil-square"></i> Compose new message
                    </button>
                </div>

                <!-- Thread view (hidden by default) -->
                <div id="threadView" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
                    <div class="thread-header" id="threadHeader">
                        <div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">?</div>
                        <div>
                            <div class="thread-header-name" id="threadName">—</div>
                            <div class="thread-header-sub" id="threadSub"></div>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <div class="text-center text-muted py-4" style="font-size:0.85rem">Loading…</div>
                    </div>
                    <div class="reply-bar">
                        <textarea id="replyInput" rows="1" placeholder="Type a reply… (Enter to send)"></textarea>
                        <button class="reply-send-btn" id="replySendBtn" title="Send">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>

                <!-- Compose view (hidden by default) -->
                <div id="composeView" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
                    <div class="compose-view-header">
                        <span><i class="bi bi-pencil-square me-2"></i>New Message</span>
                        <button class="discard-btn" onclick="showEmpty()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="compose-view-body">
                        <div class="compose-fields">
                            <div class="compose-field-row">
                                <span class="compose-label">From:</span>
                                <span class="compose-value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="compose-field-row" style="position:relative;overflow:visible;">
                                <label class="compose-label" for="msgTo">To:</label>
                                <div class="to-field-wrapper">
                                    <div class="to-row">
                                        <span class="to-chip" id="toChip" style="display:none">
                                            <span id="toChipName"></span>
                                            <button class="chip-remove" id="toChipRemove">&#x2715;</button>
                                        </span>
                                        <input type="text" id="msgTo" class="compose-input"
                                               placeholder="Type a name to search…" autocomplete="off">
                                    </div>
                                    <div class="to-suggestions" id="toSuggestions"></div>
                                </div>
                            </div>
                            <div class="compose-field-row">
                                <label class="compose-label" for="msgSubject">Subject:</label>
                                <input type="text" id="msgSubject" class="compose-input" placeholder="Subject…">
                            </div>
                            <div class="compose-field-row compose-msg-row">
                                <textarea id="chatInput" placeholder="Write your message here…"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="compose-view-footer">
                        <button class="discard-btn" onclick="showEmpty()">Discard</button>
                        <button class="send-btn" id="chatSendBtn">
                            <i class="bi bi-send-fill"></i> Send
                        </button>
                    </div>
                </div>

            </div><!-- /right-panel -->
        </div>
    </div>
</div>

<script>
const ME         = <?php echo $user['user_id']; ?>;
const MY_EMAIL   = <?php echo json_encode($user['email']); ?>;

let RECIPIENT_ID   = null;
let _threadTimer   = null;

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function timeAgo(dateStr) {
    const d    = new Date(dateStr.replace(' ','T'));
    const now  = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60)          return 'just now';
    if (diff < 3600)        return Math.floor(diff/60) + 'm ago';
    if (diff < 86400)       return d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
    if (diff < 604800)      return d.toLocaleDateString('en-US',{weekday:'short'});
    return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
}

// ── Panel switching ──────────────────────────────────────────────────────────
function showEmpty() {
    document.getElementById('emptyState').style.display  = 'flex';
    document.getElementById('threadView').style.display  = 'none';
    document.getElementById('composeView').style.display = 'none';
    clearInterval(_threadTimer);
    document.querySelectorAll('.thread-item').forEach(el => el.classList.remove('active'));
}
function showCompose() {
    document.getElementById('emptyState').style.display  = 'none';
    document.getElementById('threadView').style.display  = 'none';
    document.getElementById('composeView').style.display = 'flex';
    clearInterval(_threadTimer);
    document.querySelectorAll('.thread-item').forEach(el => el.classList.remove('active'));
    RECIPIENT_ID = null;
    resetComposeForm();
    document.getElementById('msgTo').focus();
}
function showThread(userId, name, avatarHtml, sub) {
    document.getElementById('emptyState').style.display  = 'none';
    document.getElementById('composeView').style.display = 'none';
    document.getElementById('threadView').style.display  = 'flex';
    document.getElementById('threadName').textContent    = name;
    document.getElementById('threadSub').textContent     = sub || '';
    document.getElementById('threadAvatar').outerHTML    = avatarHtml;
    RECIPIENT_ID = userId;
    clearInterval(_threadTimer);
    loadThread(false);
    _threadTimer = setInterval(() => loadThread(true), 5000);
}

// ── Inbox ────────────────────────────────────────────────────────────────────
function loadInbox() {
    fetch('../api/messages.php?action=get_inbox')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('inboxList');
            if (!res.success || !res.threads.length) {
                list.innerHTML = '<div class="inbox-empty"><i class="bi bi-inbox" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;"></i>No messages yet</div>';
                return;
            }
            list.innerHTML = res.threads.map(t => {
                const ini  = (t.first_name[0] + t.last_name[0]).toUpperCase();
                const av   = t.profile_picture
                    ? `<div class="ti-avatar"><img src="../${escHtml(t.profile_picture)}" alt=""></div>`
                    : `<div class="ti-avatar">${ini}</div>`;
                const unreadCls = parseInt(t.unread) > 0 ? 'unread' : '';
                const badge    = parseInt(t.unread) > 0
                    ? `<span class="ti-badge">${t.unread}</span>` : '';
                const preview  = t.last_msg ? escHtml(t.last_msg.substring(0,60)) : '<em>No messages</em>';
                const subject  = t.subject ? escHtml(t.subject) : '(No subject)';
                return `<div class="thread-item ${unreadCls}"
                              data-id="${t.user_id}"
                              data-name="${escHtml(t.first_name + ' ' + t.last_name)}"
                              data-ini="${ini}"
                              data-pic="${escHtml(t.profile_picture||'')}">
                    ${av}
                    <div class="ti-body">
                        <div class="ti-top">
                            <span class="ti-name">${subject}</span>
                            <span class="ti-time">${timeAgo(t.last_at)}</span>
                        </div>
                        <div class="ti-preview">${preview}</div>
                    </div>
                    ${badge}
                </div>`;
            }).join('');

            list.querySelectorAll('.thread-item').forEach(el => {
                el.addEventListener('click', function() {
                    list.querySelectorAll('.thread-item').forEach(x => x.classList.remove('active'));
                    this.classList.add('active');
                    this.classList.remove('unread');
                    this.querySelector('.ti-badge') && this.querySelector('.ti-badge').remove();

                    const uid  = parseInt(this.dataset.id);
                    const name = this.dataset.name;
                    const ini  = this.dataset.ini;
                    const pic  = this.dataset.pic;
                    const avHtml = pic
                        ? `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;flex-shrink:0;border-radius:50%;overflow:hidden;padding:0;background:transparent;"><img src="../${escHtml(pic)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>`
                        : `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">${ini}</div>`;
                    showThread(uid, name, avHtml, '');
                });
            });
        });
}

// ── Thread ───────────────────────────────────────────────────────────────────
function loadThread(silent) {
    if (!RECIPIENT_ID) return;
    fetch(`../api/messages.php?action=get_thread&with=${RECIPIENT_ID}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const container = document.getElementById('chatMessages');
            const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;

            let html = '', lastDay = '';
            res.messages.forEach(m => {
                const day = m.created_at.substring(0,10);
                if (day !== lastDay) {
                    const today = new Date().toISOString().substring(0,10);
                    const label = day === today ? 'Today' : day;
                    html += `<div class="day-divider"><span>${label}</span></div>`;
                    lastDay = day;
                }
                const mine    = m.sender_id == ME;
                const ini     = (m.first_name[0] + m.last_name[0]).toUpperCase();
                const av      = mine ? '' : `<div class="msg-avatar-initials">${ini}</div>`;
                const timeStr = new Date(m.created_at.replace(' ','T')).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
                html += `<div class="msg-row ${mine ? 'mine' : 'theirs'}">
                    ${av}
                    <div>
                        <div class="msg-bubble">${escHtml(m.message_text)}</div>
                        <div class="msg-time ${mine ? 'text-end' : ''}">${timeStr}</div>
                    </div>
                </div>`;
            });
            container.innerHTML = html || '<div class="text-center text-muted py-5" style="font-size:0.85rem">No messages yet. Send the first one! 👋</div>';
            if (!silent || wasAtBottom) container.scrollTop = container.scrollHeight;

            // Refresh inbox to clear unread badge
            if (!silent) loadInbox();
        })
        .catch(() => {
            if (!silent) document.getElementById('chatMessages').innerHTML =
                '<div class="text-center text-muted py-4" style="font-size:0.85rem"><i class="bi bi-exclamation-circle me-1"></i>Could not load. <a href="#" onclick="loadThread(false);return false;">Retry</a></div>';
        });
}

// ── Reply ────────────────────────────────────────────────────────────────────
function sendReply() {
    if (!RECIPIENT_ID) return;
    const input = document.getElementById('replyInput');
    const msg   = input.value.trim();
    if (!msg) return;

    const btn = document.getElementById('replySendBtn');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver_id', RECIPIENT_ID);
    fd.append('message', msg);
    fd.append('subject', '');
    fetch('../api/messages.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                input.value = '';
                input.style.height = '';
                loadThread(false);
            } else {
                alert('Failed: ' + (res.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error. Please try again.'))
        .finally(() => { btn.disabled = false; });
}

document.getElementById('replySendBtn').addEventListener('click', sendReply);
document.getElementById('replyInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
});
document.getElementById('replyInput').addEventListener('input', function() {
    this.style.height = '';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// ── Compose – autocomplete ───────────────────────────────────────────────────
const msgToInput    = document.getElementById('msgTo');
const toSuggestions = document.getElementById('toSuggestions');
const toChip        = document.getElementById('toChip');
const toChipName    = document.getElementById('toChipName');

function setRecipient(id, name) {
    RECIPIENT_ID              = id;
    toChipName.textContent    = name;
    toChip.style.display      = 'inline-flex';
    msgToInput.style.display  = 'none';
    msgToInput.value          = '';
    toSuggestions.style.display = 'none';
}
document.getElementById('toChipRemove').addEventListener('click', () => {
    RECIPIENT_ID             = null;
    toChip.style.display     = 'none';
    msgToInput.style.display = '';
    msgToInput.value         = '';
    msgToInput.focus();
});
let _st = null;
msgToInput.addEventListener('input', function() {
    clearTimeout(_st);
    const q = this.value.trim();
    if (!q) { toSuggestions.style.display = 'none'; return; }
    _st = setTimeout(() => {
        fetch(`../api/messages.php?action=search_recipients&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.results.length) {
                    toSuggestions.innerHTML = '<div style="padding:0.55rem 0.85rem;font-size:0.82rem;color:#94a3b8">No results</div>';
                    toSuggestions.style.display = 'block'; return;
                }
                toSuggestions.innerHTML = res.results.map(u => {
                    const ini = (u.first_name[0]+u.last_name[0]).toUpperCase();
                    const av  = u.profile_picture
                        ? `<div class="si-av"><img src="../${escHtml(u.profile_picture)}" alt=""></div>`
                        : `<div class="si-av">${ini}</div>`;
                    return `<div class="si-item" data-id="${u.user_id}" data-name="${escHtml(u.first_name+' '+u.last_name)}">
                        ${av}<div><div class="si-name">${escHtml(u.first_name+' '+u.last_name)}</div><div class="si-email">${escHtml(u.email)}</div></div></div>`;
                }).join('');
                toSuggestions.style.display = 'block';
                toSuggestions.querySelectorAll('.si-item').forEach(el => {
                    el.addEventListener('click', function() { setRecipient(parseInt(this.dataset.id), this.dataset.name); });
                });
            });
    }, 220);
});
msgToInput.addEventListener('blur', () => setTimeout(() => { toSuggestions.style.display = 'none'; }, 200));

// ── Compose – send ───────────────────────────────────────────────────────────
function resetComposeForm() {
    RECIPIENT_ID              = null;
    toChip.style.display      = 'none';
    msgToInput.style.display  = '';
    msgToInput.value          = '';
    document.getElementById('msgSubject').value = '';
    document.getElementById('chatInput').value  = '';
}

document.getElementById('chatSendBtn').addEventListener('click', function() {
    if (!RECIPIENT_ID) { msgToInput.focus(); return; }
    const msg     = document.getElementById('chatInput').value.trim();
    const subject = document.getElementById('msgSubject').value.trim();
    if (!msg) { document.getElementById('chatInput').focus(); return; }

    this.disabled = true;
    this.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending…';
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver_id', RECIPIENT_ID);
    fd.append('message', msg);
    fd.append('subject', subject);
    fetch('../api/messages.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const sentId   = RECIPIENT_ID;
                const sentName = document.getElementById('toChipName').textContent;
                const ini      = sentName.split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase();
                resetComposeForm();
                loadInbox();
                // Open thread immediately
                const avHtml = `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">${ini}</div>`;
                showThread(sentId, sentName, avHtml, '');
            } else {
                alert('Failed: ' + (res.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error.'))
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-send-fill"></i> Send';
        });
});

document.getElementById('composeBtn').addEventListener('click', showCompose);

// ── Tab switching ─────────────────────────────────────────────────────────────
let _activeTab = 'inbox';
function switchTab(tab) {
    _activeTab = tab;
    document.getElementById('tabInbox').classList.toggle('active', tab === 'inbox');
    document.getElementById('tabSent').classList.toggle('active', tab === 'sent');
    if (tab === 'inbox') loadInbox();
    else loadSent();
}

// ── Sent ──────────────────────────────────────────────────────────────────────
function loadSent() {
    const list = document.getElementById('inboxList');
    list.innerHTML = '<div class="inbox-empty"><i class="bi bi-hourglass-split d-block mb-1" style="font-size:1.4rem"></i>Loading…</div>';
    fetch('../api/messages.php?action=get_sent')
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.sent.length) {
                list.innerHTML = '<div class="inbox-empty"><i class="bi bi-send" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;"></i>No sent messages</div>';
                return;
            }
            list.innerHTML = res.sent.map(m => {
                const preview = escHtml(m.message_text.substring(0, 60));
                const subject = m.subject ? escHtml(m.subject) : '(No subject)';
                const timeStr = timeAgo(m.created_at);
                return `<div class="thread-item sent-item">
                    <div class="ti-avatar"><i class="bi bi-send" style="font-size:0.9rem;"></i></div>
                    <div class="ti-body">
                        <div class="ti-top">
                            <span class="ti-name">${subject}</span>
                            <span class="ti-time">${timeStr}</span>
                        </div>
                        <div class="ti-preview">${preview}</div>
                    </div>
                </div>`;
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<div class="inbox-empty text-danger">Could not load sent messages.</div>';
        });
}

// ── Init ─────────────────────────────────────────────────────────────────────
loadInbox();
setInterval(() => { if (_activeTab === 'inbox') loadInbox(); }, 15000);
</script>
</body>
</html>


