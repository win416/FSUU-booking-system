<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();
if (!SessionManager::isDentist()) {
    if (SessionManager::isAdmin()) {
        header('Location: ' . SITE_URL . '/admin/messages.php');
    } else {
        header('Location: ' . SITE_URL . '/patient/messages.php');
    }
    exit();
}

$user = SessionManager::getUser();
$db = getDB();
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
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/patient-messages.css?v=10" rel="stylesheet">
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
        </ul>
        </div>
    </nav>

    <div class="main-content" style="padding: 56px 0 0 0 !important;">
        <?php include '../includes/dentist-topbar.php'; ?>
        <div class="msg-layout">
            <div class="inbox-panel">
                <div class="inbox-header">
                    <span class="inbox-title"><i class="bi bi-envelope me-2"></i>Messages</span>
                    <button class="compose-btn" id="composeBtn"><i class="bi bi-pencil-square"></i> Compose</button>
                </div>
                <div class="inbox-tabs">
                    <button class="inbox-tab active" id="tabInbox" onclick="switchTab('inbox')"><i class="bi bi-inbox me-1"></i>Inbox</button>
                    <button class="inbox-tab" id="tabSent" onclick="switchTab('sent')"><i class="bi bi-send me-1"></i>Sent</button>
                </div>
                <div class="inbox-list" id="inboxList">
                    <div class="inbox-empty"><i class="bi bi-hourglass-split d-block mb-1" style="font-size:1.4rem"></i>Loading…</div>
                </div>
            </div>

            <div class="right-panel" id="rightPanel">
                <div class="empty-state" id="emptyState">
                    <i class="bi bi-chat-square-dots"></i>
                    <p>Select a conversation to start messaging</p>
                </div>
                <div id="threadView" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
                    <div class="thread-header" id="threadHeader">
                        <button class="mobile-back-btn" id="mobileBackBtn" title="Back to inbox"><i class="bi bi-arrow-left"></i></button>
                        <div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">?</div>
                        <div>
                            <div class="thread-header-name" id="threadName">—</div>
                            <div class="thread-header-sub" id="threadSub"></div>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages"><div class="text-center text-muted py-4" style="font-size:0.85rem">Loading…</div></div>
                    <div class="reply-bar">
                        <textarea id="replyInput" rows="1" placeholder="Type a reply… (Enter to send)"></textarea>
                        <button class="reply-send-btn" id="replySendBtn" title="Send"><i class="bi bi-send-fill"></i></button>
                    </div>
                </div>

                <div id="composeView" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
                    <div class="compose-view-header">
                        <span><i class="bi bi-pencil-square me-2"></i>New Message</span>
                        <button class="discard-btn" onclick="showEmpty()"><i class="bi bi-x-lg"></i></button>
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
                                        <input type="text" id="msgTo" class="compose-input" placeholder="Type assigned patient name…" autocomplete="off">
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
                        <button class="send-btn" id="chatSendBtn"><i class="bi bi-send-fill"></i> Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ME = <?php echo $user['user_id']; ?>;
const MY_EMAIL = <?php echo json_encode($user['email']); ?>;
let RECIPIENT_ID = null;
let CURRENT_SUBJECT = '';
let _threadTimer = null;
let _allThreads = [];

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function timeAgo(dateStr) {
    const d = new Date(dateStr.replace(' ','T'));
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
    if (diff < 604800) return d.toLocaleDateString('en-US',{weekday:'short'});
    return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
}

function showEmpty() {
    document.getElementById('emptyState').style.display = 'flex';
    document.getElementById('threadView').style.display = 'none';
    document.getElementById('composeView').style.display = 'none';
    clearInterval(_threadTimer);
    RECIPIENT_ID = null; CURRENT_SUBJECT = '';
    document.querySelectorAll('.thread-item').forEach(el => el.classList.remove('active'));
    document.querySelector('.msg-layout').classList.remove('chat-active', 'thread-mode', 'compose-mode');
}
function showCompose() {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('threadView').style.display = 'none';
    document.getElementById('composeView').style.display = 'flex';
    clearInterval(_threadTimer);
    document.querySelectorAll('.thread-item').forEach(el => el.classList.remove('active'));
    RECIPIENT_ID = null;
    resetComposeForm();
    document.getElementById('msgTo').focus();
    const layout = document.querySelector('.msg-layout');
    layout.classList.add('chat-active', 'compose-mode');
    layout.classList.remove('thread-mode');
}
function showThread(userId, name, avatarHtml, subject) {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('composeView').style.display = 'none';
    document.getElementById('threadView').style.display = 'flex';
    document.getElementById('threadName').textContent = subject || '(No subject)';
    document.getElementById('threadSub').textContent = name;
    document.getElementById('threadAvatar').outerHTML = avatarHtml;
    RECIPIENT_ID = parseInt(userId, 10);
    CURRENT_SUBJECT = subject || '';
    loadThread();
    const layout = document.querySelector('.msg-layout');
    layout.classList.add('chat-active', 'thread-mode');
    layout.classList.remove('compose-mode');
    clearInterval(_threadTimer);
    _threadTimer = setInterval(loadThread, 8000);
}
function switchTab(tab) {
    const inb = document.getElementById('tabInbox');
    const sent = document.getElementById('tabSent');
    if (tab === 'inbox') {
        inb.classList.add('active'); sent.classList.remove('active'); loadInbox();
    } else {
        sent.classList.add('active'); inb.classList.remove('active'); loadSent();
    }
    showEmpty();
}
function resetComposeForm() {
    const to = document.getElementById('msgTo');
    const toChip = document.getElementById('toChip');
    const toChipName = document.getElementById('toChipName');
    const sugg = document.getElementById('toSuggestions');
    RECIPIENT_ID = null;
    to.value = ''; to.style.display = '';
    toChip.style.display = 'none'; toChipName.textContent = '';
    sugg.innerHTML = '';
    document.getElementById('msgSubject').value = '';
    document.getElementById('chatInput').value = '';
}
function avatarHtmlFrom(u) {
    const n = ((u.first_name || '') + ' ' + (u.last_name || '')).trim();
    const initials = n.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() || '?';
    if (u.profile_picture) {
        return `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;flex-shrink:0;border-radius:50%;overflow:hidden;padding:0;background:transparent;"><img src="../${escHtml(u.profile_picture)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>`;
    }
    return `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">${initials}</div>`;
}
function renderThreads(rows, mode='inbox') {
    const list = document.getElementById('inboxList');
    if (!rows || !rows.length) {
        list.innerHTML = `<div class="inbox-empty"><i class="bi ${mode === 'sent' ? 'bi-send' : 'bi-inbox'}" style="font-size:1.8rem;display:block;margin-bottom:0.5rem;"></i>${mode === 'sent' ? 'No sent messages' : 'No messages yet'}</div>`;
        return;
    }
    const html = rows.map(t => {
        const fullName = `${t.first_name || ''} ${t.last_name || ''}`.trim() || 'Unknown';
        const initials = ((t.first_name?.[0] || '') + (t.last_name?.[0] || '')).toUpperCase() || '?';
        const av = t.profile_picture
            ? `<div class="ti-avatar"><img src="../${escHtml(t.profile_picture)}" alt=""></div>`
            : `<div class="ti-avatar">${initials}</div>`;
        const unread = parseInt(t.unread || 0, 10);
        const unreadCls = mode === 'inbox' && unread > 0 ? 'unread' : '';
        const badge = mode === 'inbox' && unread > 0 ? `<span class="ti-badge">${unread > 99 ? '99+' : unread}</span>` : '';
        const preview = t.last_msg ? escHtml(t.last_msg.substring(0, 60)) : '<em>No messages</em>';
        const subjectLabel = t.subject ? escHtml(t.subject) : '(No subject)';
        const subLine = mode === 'sent'
            ? `To: ${escHtml(fullName)}`
            : `${escHtml(fullName)}${t.fsuu_id ? ' · ' + escHtml(t.fsuu_id) : ''}`;
        return `<div class="thread-item ${unreadCls} ${t.user_id == RECIPIENT_ID && (t.subject || '') == CURRENT_SUBJECT ? 'active' : ''}"
                     data-user="${t.user_id}"
                     data-name="${escHtml(fullName)}"
                     data-subject="${escHtml(t.subject || '')}"
                     data-ini="${initials}"
                     data-pic="${escHtml(t.profile_picture || '')}">
            ${av}
            <div class="ti-body">
                <div class="ti-top">
                    <span class="ti-name">${subjectLabel}</span>
                    <span class="ti-time">${t.last_at ? timeAgo(t.last_at) : ''}</span>
                </div>
                <div class="ti-sub">${subLine}</div>
                <div class="ti-preview">${preview}</div>
            </div>
            ${badge}
        </div>`;
    }).join('');
    list.innerHTML = html;
    list.querySelectorAll('.thread-item').forEach(el => {
        el.onclick = () => {
            list.querySelectorAll('.thread-item').forEach(x => x.classList.remove('active'));
            el.classList.add('active');
            el.classList.remove('unread');
            el.querySelector('.ti-badge') && el.querySelector('.ti-badge').remove();
            const uid = el.getAttribute('data-user');
            const name = el.getAttribute('data-name');
            const subject = el.getAttribute('data-subject');
            const ini = el.getAttribute('data-ini') || '?';
            const pic = el.getAttribute('data-pic') || '';
            const avHtml = pic
                ? `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;flex-shrink:0;border-radius:50%;overflow:hidden;padding:0;background:transparent;"><img src="../${escHtml(pic)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>`
                : `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">${ini}</div>`;
            showThread(uid, name, avHtml, subject);
        };
    });
}
function loadInbox() {
    fetch('../api/messages.php?action=get_inbox')
        .then(r => r.json()).then(res => {
            if (!res.success) return;
            _allThreads = res.threads || [];
            renderThreads(_allThreads, 'inbox');
        }).catch(() => {});
}
function loadSent() {
    fetch('../api/messages.php?action=get_sent')
        .then(r => r.json()).then(res => {
            if (!res.success) return;
            _allThreads = res.sent || [];
            renderThreads(_allThreads, 'sent');
        }).catch(() => {});
}
function loadThread() {
    if (!RECIPIENT_ID) return;
    fetch(`../api/messages.php?action=get_thread&with=${RECIPIENT_ID}&subject=${encodeURIComponent(CURRENT_SUBJECT)}`)
        .then(r => r.json()).then(res => {
            if (!res.success) return;
            const box = document.getElementById('chatMessages');
            if (!res.messages || !res.messages.length) {
                box.innerHTML = '<div class="text-center text-muted py-5" style="font-size:0.85rem">No messages yet. Say hello! 👋</div>';
                return;
            }

            let html = '';
            let lastDay = '';
            res.messages.forEach(m => {
                const day = (m.created_at || '').substring(0, 10);
                if (day && day !== lastDay) {
                    const today = new Date().toISOString().substring(0, 10);
                    html += `<div class="day-divider"><span>${day === today ? 'Today' : day}</span></div>`;
                    lastDay = day;
                }

                const mine = parseInt(m.sender_id, 10) === ME;
                const initials = ((m.first_name?.[0] || '') + (m.last_name?.[0] || '')).toUpperCase() || '?';
                const avatar = mine
                    ? ''
                    : (m.profile_picture
                        ? `<img src="../${escHtml(m.profile_picture)}" class="msg-avatar" alt="">`
                        : `<div class="msg-avatar-initials">${initials}</div>`);
                const timeStr = new Date((m.created_at || '').replace(' ', 'T')).toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
                html += `<div class="msg-row ${mine ? 'mine' : 'theirs'}">
                    ${avatar}
                    <div class="msg-content">
                        <div class="msg-bubble">${escHtml(m.message_text || '')}</div>
                        <div class="msg-time ${mine ? 'text-end' : ''}">${timeStr}</div>
                    </div>
                </div>`;
            });

            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;
            loadInbox();
        }).catch(() => {});
}
function sendReply() {
    const ta = document.getElementById('replyInput');
    const msg = ta.value.trim();
    if (!msg || !RECIPIENT_ID) return;
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver_id', RECIPIENT_ID);
    fd.append('subject', CURRENT_SUBJECT);
    fd.append('message', msg);
    fetch('../api/messages.php', { method:'POST', body:fd })
        .then(r => r.json()).then(res => {
            if (!res.success) return;
            ta.value = '';
            loadThread();
        }).catch(() => {});
}
function sendCompose() {
    const sendBtn = document.getElementById('chatSendBtn');
    const toChipName = document.getElementById('toChipName');
    const msg = document.getElementById('chatInput').value.trim();
    const subj = document.getElementById('msgSubject').value.trim();
    if (!RECIPIENT_ID) { document.getElementById('msgTo').focus(); return; }
    if (!msg) { document.getElementById('chatInput').focus(); return; }

    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending…';

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver_id', RECIPIENT_ID);
    fd.append('subject', subj);
    fd.append('message', msg);
    fetch('../api/messages.php', { method:'POST', body:fd })
        .then(r => r.json()).then(res => {
            if (!res.success) {
                alert('Failed: ' + (res.message || 'Unknown error'));
                return;
            }
            const sentId = RECIPIENT_ID;
            const sentName = toChipName.textContent || 'Recipient';
            const sentSubject = subj;
            const sentIni = sentName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() || '?';
            resetComposeForm();
            loadInbox();
            const avHtml = `<div class="msg-avatar-initials" id="threadAvatar" style="width:36px;height:36px;font-size:0.85rem;flex-shrink:0;">${sentIni}</div>`;
            showThread(sentId, sentName, avHtml, sentSubject);
            if (!res.email_sent) {
                alert('Message sent. Email notification could not be delivered' + (res.email_error ? ': ' + res.email_error : '.'));
            }
        }).catch(() => {
            alert('Network error.');
        }).finally(() => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send-fill"></i> Send';
        });
}
function attachRecipientSearch() {
    const to = document.getElementById('msgTo');
    const sugg = document.getElementById('toSuggestions');
    const toChip = document.getElementById('toChip');
    const toChipName = document.getElementById('toChipName');
    const toChipRemove = document.getElementById('toChipRemove');
    let t = null;
    to.addEventListener('input', () => {
        const q = to.value.trim();
        clearTimeout(t);
        if (!q) { sugg.innerHTML = ''; sugg.style.display = 'none'; return; }
        t = setTimeout(() => {
            fetch(`../api/messages.php?action=search_recipients&q=${encodeURIComponent(q)}`)
                .then(r => r.json()).then(res => {
                    if (!res.success) { sugg.innerHTML = ''; sugg.style.display = 'none'; return; }
                    const rows = res.results || [];
                    if (!rows.length) {
                        sugg.innerHTML = '<div style="padding:0.55rem 0.85rem;font-size:0.82rem;color:#94a3b8">No results</div>';
                        sugg.style.display = 'block';
                        return;
                    }
                    sugg.innerHTML = rows.map(u => {
                        const n = `${u.first_name || ''} ${u.last_name || ''}`.trim();
                        const initials = `${(u.first_name || '')[0] || ''}${(u.last_name || '')[0] || ''}`.toUpperCase() || '?';
                        const av = u.profile_picture
                            ? `<div class="si-av"><img src="../${escHtml(u.profile_picture)}" alt=""></div>`
                            : `<div class="si-av">${initials}</div>`;
                        return `<div class="si-item" data-id="${u.user_id}" data-name="${escHtml(n)}">
                            ${av}
                            <div>
                                <div class="si-name">${escHtml(n)}</div>
                                <div class="si-email">${escHtml(u.email || '')}</div>
                            </div>
                        </div>`;
                    }).join('');
                    sugg.style.display = 'block';
                    sugg.querySelectorAll('.si-item').forEach(el => {
                        el.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            RECIPIENT_ID = parseInt(el.getAttribute('data-id'), 10);
                            const nm = el.getAttribute('data-name');
                            toChipName.textContent = nm;
                            toChip.style.display = 'inline-flex';
                            to.value = '';
                            to.style.display = 'none';
                            sugg.innerHTML = '';
                            sugg.style.display = 'none';
                        });
                    });
                }).catch(() => { sugg.innerHTML = ''; sugg.style.display = 'none'; });
        }, 200);
    });
    toChipRemove.onclick = () => {
        RECIPIENT_ID = null;
        toChip.style.display = 'none';
        to.style.display = '';
        sugg.innerHTML = '';
        sugg.style.display = 'none';
        to.focus();
    };
    document.addEventListener('click', (e) => {
        if (!document.querySelector('.to-field-wrapper').contains(e.target)) {
            sugg.innerHTML = '';
            sugg.style.display = 'none';
        }
    });
}

function prefillComposeFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const composeTo = params.get('compose_to');
    const composeName = params.get('compose_name');
    const composeSubject = params.get('compose_subject');
    if (!composeTo || !composeName) return;

    showCompose();
    RECIPIENT_ID = parseInt(composeTo, 10);
    const to = document.getElementById('msgTo');
    const toChip = document.getElementById('toChip');
    const toChipName = document.getElementById('toChipName');
    toChipName.textContent = composeName;
    toChip.style.display = 'inline-flex';
    to.value = '';
    to.style.display = 'none';
    if (composeSubject) {
        document.getElementById('msgSubject').value = composeSubject;
    }
}

document.getElementById('composeBtn').onclick = showCompose;
document.getElementById('chatSendBtn').onclick = sendCompose;
document.getElementById('replySendBtn').onclick = sendReply;
document.getElementById('replyInput').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); } });
document.getElementById('mobileBackBtn').onclick = showEmpty;
attachRecipientSearch();
switchTab('inbox');
prefillComposeFromQuery();
</script>
</body>
</html>
