<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar">
            <div class="brand">
                <img src="../img/fsuu%20dental.jpg" alt="Logo" class="sidebar-logo">
                FSUU Admin
            </div>
            <ul class="sidebar-nav">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="patients.php"><i class="bi bi-people"></i> Patients</a></li>
                <li class="nav-item"><a class="nav-link" href="schedule.php"><i class="bi bi-clock"></i> Schedule</a></li>
                <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                <li class="nav-item logout-nav-item">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
        <div class="main-content">
            <div class="container-fluid my-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Reports & Analytics</h2>
                    <div class="d-flex gap-2">
                        <input type="date" id="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="date" id="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        <button id="update-reports" class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stats-card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Appointments</h6>
                                <h3 id="stat-total" class="mb-0">-</h3>
                                <small class="text-info" id="stat-monthly-diff"></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stats-card border-start border-success border-4">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Completed</h6>
                                <h3 id="stat-completed" class="mb-0 text-success">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stats-card border-start border-warning border-4">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Pending</h6>
                                <h3 id="stat-pending" class="mb-0 text-warning">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm stats-card border-start border-primary border-4">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">New Patients</h6>
                                <h3 id="stat-new-patients" class="mb-0 text-primary">-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Appointment Trends</h5>
                                <div style="height: 300px;">
                                    <canvas id="trendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Status Distribution</h5>
                                <div style="height: 300px;">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Most Popular Services</h5>
                                <div style="height: 300px;">
                                    <canvas id="servicesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Recent Activity Summary</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="services-table">
                                        <thead>
                                            <tr>
                                                <th>Service</th>
                                                <th>Appointments</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data populated via JS -->
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let trendsChart, statusChart, servicesChart;

            function fetchData() {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;

                fetch(`../api/admin-reports.php?start_date=${startDate}&end_date=${endDate}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            renderReports(result.data);
                        } else {
                            alert('Error fetching report data: ' + result.message);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }

            function renderReports(data) {
                // Update Summary Stats
                document.getElementById('stat-total').textContent = data.total_appointments;
                document.getElementById('stat-completed').textContent = data.status_breakdown.completed || 0;
                document.getElementById('stat-pending').textContent = data.status_breakdown.pending || 0;
                document.getElementById('stat-new-patients').textContent = data.new_patients;

                const diff = data.current_month_count - data.last_month_count;
                const diffPct = data.last_month_count > 0 ? (diff / data.last_month_count * 100).toFixed(1) : (diff > 0 ? 100 : 0);
                document.getElementById('stat-monthly-diff').textContent = `${diff >= 0 ? '+' : ''}${diff} this month (${diffPct}%)`;

                // Trends Chart
                const trendsLabels = data.trends.map(t => t.appointment_date);
                const trendsValues = data.trends.map(t => t.count);

                if (trendsChart) trendsChart.destroy();
                trendsChart = new Chart(document.getElementById('trendsChart'), {
                    type: 'line',
                    data: {
                        labels: trendsLabels,
                        datasets: [{
                            label: 'Appointments',
                            data: trendsValues,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });

                // Status Chart
                const statusLabels = Object.keys(data.status_breakdown);
                const statusValues = Object.values(data.status_breakdown);
                const statusColors = {
                    'pending': '#ffc107',
                    'approved': '#0dcaf0',
                    'completed': '#198754',
                    'cancelled': '#dc3545',
                    'declined': '#6c757d'
                };

                if (statusChart) statusChart.destroy();
                statusChart = new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusValues,
                            backgroundColor: statusLabels.map(l => statusColors[l] || '#000')
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });

                // Services Chart
                const serviceLabels = data.services.map(s => s.service_name);
                const serviceValues = data.services.map(s => s.count);

                if (servicesChart) servicesChart.destroy();
                servicesChart = new Chart(document.getElementById('servicesChart'), {
                    type: 'bar',
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            label: 'Bookings',
                            data: serviceValues,
                            backgroundColor: '#0d6efd'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } }
                    }
                });

                // Services Table
                const tableBody = document.querySelector('#services-table tbody');
                tableBody.innerHTML = '';
                const total = data.services.reduce((acc, s) => acc + parseInt(s.count), 0);
                
                data.services.forEach(s => {
                    const pct = total > 0 ? (parseInt(s.count) / total * 100).toFixed(1) : 0;
                    const row = `
                        <tr>
                            <td>${s.service_name}</td>
                            <td>${s.count}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                        <div class="progress-bar bg-primary" style="width: ${pct}%"></div>
                                    </div>
                                    <small>${pct}%</small>
                                </div>
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            }

            document.getElementById('update-reports').addEventListener('click', fetchData);
            
            // Initial load
            fetchData();
        });
    </script>
</body>
</html>
