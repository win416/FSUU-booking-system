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
    <link href="../assets/css/admin-reports.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <style>
        @media print {
            .sidebar, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .print-header { display: block !important; }
            .card { break-inside: avoid; }
        }
        .print-header { display: none; }
        .export-badge { font-size: 0.7rem; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <nav class="sidebar no-print">
        <nav class="sidebar no-print">
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

                <!-- Print Header (only visible when printing) -->
                <div class="print-header mb-3">
                    <h3>FSUU Dental Clinic — Appointments Report</h3>
                    <p id="print-range-label" class="text-muted mb-0"></p>
                    <hr>
                </div>

                <!-- Top Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h2>Reports & Analytics</h2>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <input type="date" id="start_date" class="form-control report-date-input" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="date" id="end_date" class="form-control report-date-input" value="<?php echo date('Y-m-d'); ?>">
                        <button id="update-reports" class="btn btn-primary">
                            <i class="bi bi-funnel-fill me-1"></i>Filter
                        </button>
                        <!-- Export Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" id="export-csv">
                                        <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Export as CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="export-pdf">
                                        <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export as PDF / Print
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Total Appointments</h6>
                                <h2 id="stat-total">—</h2>
                                <div class="progress">
                                    <div class="progress-bar w-100"></div>
                                </div>
                                <small class="text-info d-block mt-1" id="stat-monthly-diff"></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Completed</h6>
                                <h2 id="stat-completed">—</h2>
                                <div class="progress">
                                    <div class="progress-bar bg-success" id="progress-completed"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>Pending Approval</h6>
                                <h2 id="stat-pending">—</h2>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" id="progress-pending"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats h-100">
                            <div class="card-body">
                                <h6>New Patients</h6>
                                <h2 id="stat-new-patients">—</h2>
                                <div class="progress">
                                    <div class="progress-bar bg-info w-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Appointment Trends</h5>
                                <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Status Distribution</h5>
                                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Most Popular Services</h5>
                                <div class="chart-container"><canvas id="servicesChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Service Breakdown</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="services-table">
                                        <thead class="table-light">
                                            <tr><th>Service</th><th>Appointments</th><th>Share</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Appointments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Appointment Details</h5>
                        <span class="badge bg-secondary" id="detail-count">0 records</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="detail-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>FSUU ID</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="detail-tbody">
                                    <tr><td colspan="8" class="text-center py-4 text-muted">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /container -->
        </div><!-- /main-content -->
    </div><!-- /dashboard-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        let trendsChart, statusChart, servicesChart;
        let currentData = null;

        const statusColors = {
            pending: '#ffc107', approved: '#0dcaf0',
            completed: '#198754', cancelled: '#dc3545', declined: '#6c757d'
        };

        function getDateRange() {
            return {
                start: document.getElementById('start_date').value,
                end: document.getElementById('end_date').value
            };
        }

        // ── Fetch Summary + Charts ───────────────────────────────────────────
        function fetchData() {
            const { start, end } = getDateRange();
            document.getElementById('print-range-label').textContent = `Period: ${start} to ${end}`;

            fetch(`../api/admin-reports.php?start_date=${start}&end_date=${end}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) { currentData = result.data; renderReports(result.data); }
                    else alert('Error: ' + result.message);
                })
                .catch(e => console.error(e));

            fetchDetails(start, end);
        }

        // ── Fetch Detailed Appointments Table ────────────────────────────────
        function fetchDetails(start, end) {
            document.getElementById('detail-tbody').innerHTML =
                '<tr><td colspan="8" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</td></tr>';

            fetch(`../api/export-report.php?start_date=${start}&end_date=${end}&format=json`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) renderDetailTable(result.data);
                    else document.getElementById('detail-tbody').innerHTML =
                        '<tr><td colspan="8" class="text-center py-4 text-muted">No data available.</td></tr>';
                })
                .catch(() => document.getElementById('detail-tbody').innerHTML =
                    '<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load details.</td></tr>');
        }

        function renderDetailTable(rows) {
            document.getElementById('detail-count').textContent = rows.length + ' record' + (rows.length !== 1 ? 's' : '');
            const tbody = document.getElementById('detail-tbody');
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No appointments in this period.</td></tr>';
                return;
            }
            const badge = { pending:'warning', approved:'info', completed:'success', cancelled:'danger', declined:'secondary' };
            tbody.innerHTML = rows.map((r, i) => `
                <tr>
                    <td class="text-muted">${i + 1}</td>
                    <td>${r.appointment_date}</td>
                    <td>${r.appointment_time}</td>
                    <td>${escHtml(r.patient_name)}</td>
                    <td><code>${escHtml(r.fsuu_id)}</code></td>
                    <td>${escHtml(r.service_name)}</td>
                    <td><span class="badge bg-${badge[r.status] || 'secondary'}">${r.status}</span></td>
                    <td class="text-muted"><small>${escHtml(r.notes || '—')}</small></td>
                </tr>`).join('');
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Render Charts & Summary Cards ────────────────────────────────────
        function renderReports(data) {
            document.getElementById('stat-total').textContent = data.total_appointments;
            document.getElementById('stat-completed').textContent = data.status_breakdown.completed || 0;
            document.getElementById('stat-pending').textContent = data.status_breakdown.pending || 0;
            document.getElementById('stat-new-patients').textContent = data.new_patients;

            const diff = data.current_month_count - data.last_month_count;
            const pct = data.last_month_count > 0 ? (diff / data.last_month_count * 100).toFixed(1) : (diff > 0 ? 100 : 0);
            document.getElementById('stat-monthly-diff').textContent =
                `${diff >= 0 ? '+' : ''}${diff} vs last month (${pct}%)`;

            // Trends
            if (trendsChart) trendsChart.destroy();
            trendsChart = new Chart(document.getElementById('trendsChart'), {
                type: 'line',
                data: {
                    labels: data.trends.map(t => t.appointment_date),
                    datasets: [{ label: 'Appointments', data: data.trends.map(t => t.count),
                        borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.4 }]
                },
                options: { responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });

            // Status Doughnut
            const sLabels = Object.keys(data.status_breakdown);
            if (statusChart) statusChart.destroy();
            statusChart = new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: { labels: sLabels, datasets: [{
                    data: Object.values(data.status_breakdown),
                    backgroundColor: sLabels.map(l => statusColors[l] || '#adb5bd')
                }]},
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            // Services Bar
            if (servicesChart) servicesChart.destroy();
            servicesChart = new Chart(document.getElementById('servicesChart'), {
                type: 'bar',
                data: { labels: data.services.map(s => s.service_name), datasets: [{
                    label: 'Bookings', data: data.services.map(s => s.count), backgroundColor: '#0d6efd'
                }]},
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
            });

            // Services Table
            const total = data.services.reduce((acc, s) => acc + parseInt(s.count), 0);
            document.querySelector('#services-table tbody').innerHTML = data.services.map(s => {
                const pct = total > 0 ? (parseInt(s.count) / total * 100).toFixed(1) : 0;
                return `<tr>
                    <td>${escHtml(s.service_name)}</td>
                    <td>${s.count}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1 service-progress">
                                <div class="progress-bar bg-primary" style="width:${pct}%"></div>
                            </div>
                            <small>${pct}%</small>
                        </div>
                    </td></tr>`;
            }).join('');
        }

        // ── CSV Export ───────────────────────────────────────────────────────
        document.getElementById('export-csv').addEventListener('click', function (e) {
            e.preventDefault();
            const { start, end } = getDateRange();
            window.location.href = `../api/export-report.php?start_date=${start}&end_date=${end}&format=csv`;
        });

        // ── PDF / Print Export ───────────────────────────────────────────────
        document.getElementById('export-pdf').addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });

        // ── Filter Button ────────────────────────────────────────────────────
        document.getElementById('update-reports').addEventListener('click', fetchData);

        // Initial load
        fetchData();
    });
    </script>
</body>
</html>
