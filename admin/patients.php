<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Handle search
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $search_safe = $db->real_escape_string($search);
    $search_query = " AND (u.first_name LIKE '%$search_safe%' OR u.last_name LIKE '%$search_safe%' OR u.fsuu_id LIKE '%$search_safe%' OR u.email LIKE '%$search_safe%')";
}

// Fetch patients (student and staff roles)
$query = "
    SELECT u.user_id, u.fsuu_id, u.first_name, u.last_name, u.email, u.contact_number, u.role, u.is_active,
           (SELECT COUNT(*) FROM appointments WHERE user_id = u.user_id) as appointment_count
    FROM users u
    WHERE u.role IN ('student', 'staff') $search_query
    ORDER BY u.last_name ASC
";

$result = $db->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin-patients.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
            </div>
            <div class="sidebar-nav-wrap">
            <div class="sidebar-section-label">Menu</div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="appointments.php">
                        <i class="bi bi-calendar-check"></i> Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="patients.php">
                        <i class="bi bi-people"></i> Patients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="schedule.php">
                        <i class="bi bi-clock"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="messages.php">
                        <i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="bi bi-person-badge"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
            </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Patients</h2>
                    <form class="d-flex patient-search-form" action="" method="GET">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if(!empty($search)): ?>
                                <a href="patients.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>FSUU ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Type</th>
                                        <th>Appointments</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($result->num_rows > 0): ?>
                                        <?php while($patient = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($patient['fsuu_id']); ?></code></td>
                                            <td><a href="patient-profile.php?id=<?php echo $patient['user_id']; ?>" class="patient-name-link"><?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?></a></td>
                                            <td><small><?php echo htmlspecialchars($patient['email']); ?></small></td>
                                            <td><?php echo htmlspecialchars($patient['contact_number'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo ucfirst($patient['role']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?php echo $patient['appointment_count']; ?></td>
                                            <td>
                                                <?php if($patient['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="patient-profile.php?id=<?php echo $patient['user_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Profile">
                                                    <i class="bi bi-person-lines-fill"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">No patients found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
