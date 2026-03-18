<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();

$db = getDB();

// Fetch upcoming blocks
$blocks = $db->query("
    SELECT block_id, block_date, start_time, end_time, reason, is_full_day
    FROM blocked_schedules
    WHERE block_date >= CURDATE()
    ORDER BY block_date, start_time
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule blocks - Admin</title>
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
                    <a class="nav-link" href="patients.php">
                        <i class="bi bi-people"></i> Patients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="schedule.php">
                        <i class="bi bi-clock"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
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
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid my-4">
                <h2>Manage Blocked Schedules</h2>
                <p class="text-muted">Block specific dates or times from being booked by patients.</p>

                <div class="row mt-4">
                    <!-- Create Block Form -->
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Add New Block</h5>
                            </div>
                            <div class="card-body">
                                <form id="blockForm">
                                    <div class="mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="block_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_full_day" name="is_full_day" checked>
                                        <label class="form-check-label" for="is_full_day">Full Day Block</label>
                                    </div>
                                    <div id="timeFields" style="display: none;">
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <label class="form-label">Start Time</label>
                                                <input type="time" class="form-control" name="start_time">
                                            </div>
                                            <div class="col-6 mb-3">
                                                <label class="form-label">End Time</label>
                                                <input type="time" class="form-control" name="end_time">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Reason (Optional)</label>
                                        <input type="text" class="form-control" name="reason" placeholder="e.g. Clinic Maintenance">
                                    </div>
                                    <button type="submit" class="btn btn-warning">Block Schedule</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Blocks Table -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Upcoming Blocked Schedules</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Reason</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($blocks->num_rows > 0): ?>
                                                <?php while($row = $blocks->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($row['block_date'])); ?></td>
                                                    <td>
                                                        <?php 
                                                            if($row['is_full_day']) {
                                                                echo '<span class="badge bg-danger">Full Day</span>';
                                                            } else {
                                                                echo date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time']));
                                                            }
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['reason'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $row['block_id']; ?>">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">No upcoming blocks found.</td>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle time inputs based on full day checkbox
            $('#is_full_day').change(function() {
                if($(this).is(':checked')) {
                    $('#timeFields').hide();
                    $('input[name="start_time"]').removeAttr('required');
                    $('input[name="end_time"]').removeAttr('required');
                } else {
                    $('#timeFields').show();
                    $('input[name="start_time"]').attr('required', 'required');
                    $('input[name="end_time"]').attr('required', 'required');
                }
            });

            // Submit new block
            $('#blockForm').submit(function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: '../api/block-schedule.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if(response.success) {
                            alert('Schedule blocked successfully!');
                            location.reload();
                        } else {
                            alert(response.message || 'Error occurred while blocking');
                        }
                    },
                    error: function() {
                        alert('Server error occurred.');
                    }
                });
            });

            // Delete block
            $('.delete-btn').click(function() {
                if(!confirm('Are you sure you want to unblock this schedule? Patients will be able to book it again.')) return;
                
                const id = $(this).data('id');
                
                $.ajax({
                    url: '../api/delete-block.php',
                    method: 'POST',
                    data: { block_id: id },
                    success: function(response) {
                        if(response.success) {
                            location.reload();
                        } else {
                            alert(response.message || 'Error deleting block');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
