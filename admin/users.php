<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Handle search and filter
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$success_msg = '';
$error_msg = '';

// Build query conditions
$conditions = "u.role IN ('admin', 'dentist', 'staff')";
if (!empty($search)) {
    $s = $db->real_escape_string($search);
    $conditions .= " AND (u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.fsuu_id LIKE '%$s%' OR u.email LIKE '%$s%')";
}
if (!empty($filter_role) && in_array($filter_role, ['admin', 'dentist', 'staff'])) {
    $r = $db->real_escape_string($filter_role);
    $conditions = "u.role = '$r'";
    if (!empty($search)) {
        $s = $db->real_escape_string($search);
        $conditions .= " AND (u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.fsuu_id LIKE '%$s%' OR u.email LIKE '%$s%')";
    }
}

$users_result = $db->query("
    SELECT u.user_id, u.fsuu_id, u.first_name, u.last_name, u.email, u.contact_number, u.role, u.is_active
    FROM users u
    WHERE $conditions
    ORDER BY u.role ASC, u.last_name ASC
");

// Role badge colors
$role_colors = ['admin' => 'danger', 'dentist' => 'primary', 'staff' => 'success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
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
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
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
                    <h2>User Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Add User
                    </button>
                </div>

                <!-- Alerts -->
                <div id="alertContainer"></div>

                <!-- Search & Filter -->
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <form class="row g-2 align-items-center" method="GET" action="">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search by name, ID, or email..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                                    <?php if (!empty($search) || !empty($filter_role)): ?>
                                        <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="role" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="dentist" <?php echo $filter_role === 'dentist' ? 'selected' : ''; ?>>Dentist</option>
                                    <option value="staff" <?php echo $filter_role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
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
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr id="user-row-<?php echo $user['user_id']; ?>">
                                            <td><code><?php echo htmlspecialchars($user['fsuu_id']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($user['last_name'] . ', ' . $user['first_name']); ?></strong></td>
                                            <td><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                            <td><?php echo htmlspecialchars($user['contact_number'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $role_colors[$user['role']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-user-btn"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-fsuu="<?php echo htmlspecialchars($user['fsuu_id']); ?>"
                                                    data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                    data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-contact="<?php echo htmlspecialchars($user['contact_number']); ?>"
                                                    data-role="<?php echo $user['role']; ?>"
                                                    title="Edit User">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning reset-password-btn"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                    title="Reset Password">
                                                    <i class="bi bi-key-fill"></i>
                                                </button>
                                                <button class="btn btn-sm <?php echo $user['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?> toggle-status-btn"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-active="<?php echo $user['is_active']; ?>"
                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="bi bi-<?php echo $user['is_active'] ? 'person-x-fill' : 'person-check-fill'; ?>"></i>
                                                </button>
                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-danger delete-user-btn"
                                                    data-id="<?php echo $user['user_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                    title="Delete User">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">No users found.</td>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addUserAlert"></div>
                    <form id="addUserForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">FSUU ID <span class="text-danger">*</span></label>
                                <input type="text" name="fsuu_id" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="dentist">Dentist</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAddUser">
                        <i class="bi bi-check-lg me-1"></i>Save User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editUserAlert"></div>
                    <form id="editUserForm">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">FSUU ID <span class="text-danger">*</span></label>
                                <input type="text" name="fsuu_id" id="edit_fsuu_id" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" id="edit_contact" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select name="role" id="edit_role" class="form-select" required>
                                    <option value="admin">Admin</option>
                                    <option value="dentist">Dentist</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditUser">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key-fill me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="resetPasswordAlert"></div>
                    <p>Reset password for: <strong id="resetPasswordName"></strong></p>
                    <form id="resetPasswordForm">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-control" minlength="8" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="8" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="saveResetPassword">
                        <i class="bi bi-key-fill me-1"></i>Reset Password
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function showAlert(container, type, message) {
        $(container).html('<div class="alert alert-' + type + ' alert-dismissible fade show">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    // Add User
    $('#saveAddUser').click(function() {
        const formData = $('#addUserForm').serialize() + '&action=add_user';
        $.post('../api/users.php', formData, function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#addUserModal').modal('hide');
                $('#addUserForm')[0].reset();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('#addUserAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#addUserAlert', 'danger', 'Request failed. Try again.'));
    });

    // Edit User - populate modal
    $('.edit-user-btn').click(function() {
        const btn = $(this);
        $('#edit_user_id').val(btn.data('id'));
        $('#edit_first_name').val(btn.data('firstname'));
        $('#edit_last_name').val(btn.data('lastname'));
        $('#edit_fsuu_id').val(btn.data('fsuu'));
        $('#edit_email').val(btn.data('email'));
        $('#edit_contact').val(btn.data('contact'));
        $('#edit_role').val(btn.data('role'));
        $('#editUserAlert').html('');
        $('#editUserModal').modal('show');
    });

    // Save Edit
    $('#saveEditUser').click(function() {
        const formData = $('#editUserForm').serialize() + '&action=edit_user';
        $.post('../api/users.php', formData, function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#editUserModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('#editUserAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#editUserAlert', 'danger', 'Request failed. Try again.'));
    });

    // Reset Password - populate modal
    $('.reset-password-btn').click(function() {
        $('#reset_user_id').val($(this).data('id'));
        $('#resetPasswordName').text($(this).data('name'));
        $('#resetPasswordForm')[0].reset();
        $('#resetPasswordAlert').html('');
        $('#resetPasswordModal').modal('show');
    });

    // Save Reset Password
    $('#saveResetPassword').click(function() {
        const newPwd = $('#new_password').val();
        const confirmPwd = $('#confirm_password').val();
        if (newPwd !== confirmPwd) {
            showAlert('#resetPasswordAlert', 'danger', 'Passwords do not match.');
            return;
        }
        const formData = $('#resetPasswordForm').serialize() + '&action=reset_password';
        $.post('../api/users.php', formData, function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#resetPasswordModal').modal('hide');
            } else {
                showAlert('#resetPasswordAlert', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#resetPasswordAlert', 'danger', 'Request failed. Try again.'));
    });

    // Toggle Status
    $('.toggle-status-btn').click(function() {
        const btn = $(this);
        const userId = btn.data('id');
        const isActive = parseInt(btn.data('active'));
        const action = isActive ? 'Deactivate' : 'Activate';
        if (!confirm(action + ' this user?')) return;
        $.post('../api/users.php', { action: 'toggle_status', user_id: userId }, function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showAlert('#alertContainer', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed. Try again.'));
    });

    // Delete User
    $('.delete-user-btn').click(function() {
        const btn = $(this);
        const userId = btn.data('id');
        const name = btn.data('name');
        if (!confirm('Delete user "' + name + '"? This action cannot be undone.')) return;
        $.post('../api/users.php', { action: 'delete_user', user_id: userId }, function(res) {
            if (res.success) {
                showAlert('#alertContainer', 'success', res.message);
                $('#user-row-' + userId).fadeOut(400, function() { $(this).remove(); });
            } else {
                showAlert('#alertContainer', 'danger', res.message);
            }
        }, 'json').fail(() => showAlert('#alertContainer', 'danger', 'Request failed. Try again.'));
    });
    </script>
</body>
</html>
