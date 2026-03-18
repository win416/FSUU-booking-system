<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Get all notifications for user
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();
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
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <style>
        .notification-item {
            border-left: 4px solid var(--border-color);
            transition: all 0.2s ease;
        }
        .notification-item.unread {
            border-left-color: var(--primary-color);
            background-color: var(--accent-color);
        }
        .notification-item:hover {
            background-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Dental
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="book-appointment.php"><i class="bi bi-calendar-plus"></i> Book Appointment</a></li>
                <li class="nav-item"><a class="nav-link" href="my-appointments.php"><i class="bi bi-calendar-check"></i> My Appointments</a></li>
                <li class="nav-item"><a class="nav-link active" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                    <?php
                    $unread_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                    $unread_stmt->bind_param("i", $user['user_id']);
                    $unread_stmt->execute();
                    $unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
                    if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-2"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history"></i> History</a></li>
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right text-danger"></i> Logout</a>
                </li>
            </ul>
        </nav>

        <div class="main-content">
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Notifications</h2>
                    <?php if ($unread_count > 0): ?>
                        <button id="markAllRead" class="btn btn-sm btn-outline-primary">Mark all as read</button>
                    <?php endif; ?>
                </div>

                <div class="notifications-list">
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while($notif = $notifications->fetch_assoc()): ?>
                            <div class="card mb-3 notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-title mb-1 <?php echo $notif['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                                <?php echo htmlspecialchars($notif['subject']); ?>
                                            </h6>
                                            <p class="card-text mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge bg-warning text-dark">New</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card py-5 text-center">
                            <div class="card-body">
                                <i class="bi bi-bell-slash display-4 text-muted mb-3"></i>
                                <p class="text-muted">You have no notifications yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mark all as read
            $('#markAllRead').click(function() {
                $.post('../api/mark-notifications-read.php', { all: true }, function(res) {
                    if (res.success) location.reload();
                });
            });

            // Mark single as read on click if unread
            $('.notification-item.unread').click(function() {
                const id = $(this).data('id');
                $.post('../api/mark-notifications-read.php', { notification_id: id }, function(res) {
                    if (res.success) location.reload();
                });
            });
        });
    </script>
</body>
</html>
