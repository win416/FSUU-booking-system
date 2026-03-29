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
                <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="bi bi-graph-up"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="messages.php"><i class="bi bi-chat-dots"></i> Messages <span id="sidebarMsgBadge" class="badge bg-danger rounded-pill ms-2" style="display:none">0</span></a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-person-badge"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
            </ul>
            </div>
            <div class="logout-nav-item">
                <a class="nav-link text-danger" href="../auth/logout.php">
                    <i class="bi bi-box-arrow-right text-danger"></i> Logout
                </a>
            </div>
        </nav>


        <div class="main-content">
            <?php include '../includes/admin-topbar.php'; ?>
            <div class="container-fluid my-4">

                <!-- Print Header (only visible when printing) -->
                <div class="print-header mb-3">
                    <h3>FSUU Dental Clinic — Appointments Report</h3>
                    <p id="print-range-label" class="text-muted mb-0"></p>
                    <hr>
                </div>

                <!-- Top Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-3">
                        <div>
                            <h2 class="mb-0">
                                Reports & Analytics
                            </h2>
                            <small class="text-muted" style="font-size:0.8rem;">Appointment statistics and trends</small>
                        </div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <div class="d-flex align-items-center gap-1 bg-white border rounded-3 px-2 py-1" style="border-color:#e2e8f0!important;">
                            <i class="bi bi-calendar3 text-muted" style="font-size:0.8rem;"></i>
                            <input type="date" id="start_date" class="report-date-input border-0 p-0 ps-1" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                            <span class="text-muted" style="font-size:0.8rem;">—</span>
                            <input type="date" id="end_date" class="report-date-input border-0 p-0" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button id="update-reports" class="btn btn-primary btn-sm px-3" style="border-radius:8px;">
                            <i class="bi bi-funnel-fill me-1"></i>Filter
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle px-3" data-bs-toggle="dropdown" style="border-radius:8px;">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius:12px;">
                                <li>
                                    <a class="dropdown-item rounded-2" href="#" id="export-csv">
                                        <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Export as CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded-2" href="#" id="export-pdf">
                                        <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export as PDF / Print
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats stat-blue h-100">
                            <div class="card-body">
                                <div class="stat-top">
                                    <div>
                                        <h6>Total Appointments</h6>
                                        <h2 id="stat-total">—</h2>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-calendar2-check"></i></div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progress-total"></div>
                                </div>
                                <small id="stat-monthly-diff"></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats stat-green h-100">
                            <div class="card-body">
                                <div class="stat-top">
                                    <div>
                                        <h6>Completed</h6>
                                        <h2 id="stat-completed">—</h2>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progress-completed"></div>
                                </div>
                                <small>Successfully completed visits</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats stat-amber h-100">
                            <div class="card-body">
                                <div class="stat-top">
                                    <div>
                                        <h6>Pending Approval</h6>
                                        <h2 id="stat-pending">—</h2>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progress-pending"></div>
                                </div>
                                <small>Awaiting confirmation</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats stat-violet h-100">
                            <div class="card-body">
                                <div class="stat-top">
                                    <div>
                                        <h6>New Patients</h6>
                                        <h2 id="stat-new-patients">—</h2>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progress-new-patients"></div>
                                </div>
                                <small>First-time bookings</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card chart-card mb-4">
                            <div class="card-body">
                                <div class="card-title">Appointment Trends</div>
                                <span class="chart-sub">Daily bookings over the selected period</span>
                                <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card chart-card mb-4">
                            <div class="card-body">
                                <div class="card-title">Status Distribution</div>
                                <span class="chart-sub">Breakdown by appointment status</span>
                                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card chart-card">
                            <div class="card-body">
                                <div class="card-title">Most Popular Services</div>
                                <span class="chart-sub">Top services by number of bookings</span>
                                <div class="chart-container"><canvas id="servicesChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card chart-card">
                            <div class="card-body">
                                <div class="card-title">Service Breakdown</div>
                                <span class="chart-sub">Appointments per service with share %</span>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="services-table">
                                        <thead>
                                            <tr><th>Service</th><th>Count</th><th>Share</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Appointments Table -->
                <div class="card detail-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-table me-2" style="color:#00aeef;"></i>Appointment Details</h5>
                        <span id="detail-count">0 records</span>
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
            const total      = data.total_appointments || 0;
            const completed  = data.status_breakdown.completed || 0;
            const pending    = data.status_breakdown.pending || 0;
            const newPts     = data.new_patients || 0;
            const maxStat    = Math.max(total, completed, pending, newPts, 1);
            const pctOf = v  => Math.round((v / maxStat) * 100);

            document.getElementById('stat-total').textContent        = total;
            document.getElementById('stat-completed').textContent    = completed;
            document.getElementById('stat-pending').textContent      = pending;
            document.getElementById('stat-new-patients').textContent = newPts;

            document.getElementById('progress-total').style.width        = pctOf(total)     + '%';
            document.getElementById('progress-completed').style.width    = pctOf(completed) + '%';
            document.getElementById('progress-pending').style.width      = pctOf(pending)   + '%';
            document.getElementById('progress-new-patients').style.width = pctOf(newPts)    + '%';

            const diff = data.current_month_count - data.last_month_count;
            const pct = data.last_month_count > 0 ? (diff / data.last_month_count * 100).toFixed(1) : (diff > 0 ? 100 : 0);
            const diffEl = document.getElementById('stat-monthly-diff');
            diffEl.textContent = `${diff >= 0 ? '▲' : '▼'} ${Math.abs(diff)} vs last month (${Math.abs(pct)}%)`;
            diffEl.style.color = diff >= 0 ? '#16a34a' : '#dc3545';

            // ── Trends Chart ──────────────────────────────────────────────────
            if (trendsChart) trendsChart.destroy();
            const tCanvas = document.getElementById('trendsChart');
            const tCtx = tCanvas.getContext('2d');
            const tGrad = tCtx.createLinearGradient(0, 0, 0, 220);
            tGrad.addColorStop(0, 'rgba(0,174,239,0.25)');
            tGrad.addColorStop(1, 'rgba(0,174,239,0.0)');
            trendsChart = new Chart(tCanvas, {
                type: 'line',
                data: {
                    labels: data.trends.map(t => t.appointment_date),
                    datasets: [{
                        label: 'Appointments',
                        data: data.trends.map(t => t.count),
                        borderColor: '#00aeef',
                        backgroundColor: tGrad,
                        fill: true,
                        tension: 0.42,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#00aeef',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            bodyFont: { weight: 'bold', size: 13 },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: ctx => ` ${ctx.parsed.y} appointment${ctx.parsed.y !== 1 ? 's' : ''}`
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } }, border: { display: false } },
                        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } }, grid: { color: '#f1f5f9' }, border: { display: false } }
                    }
                }
            });

            // ── Status Doughnut ───────────────────────────────────────────────
            const sLabels = Object.keys(data.status_breakdown);
            const sValues = Object.values(data.status_breakdown);
            const sTotal  = sValues.reduce((a, b) => a + b, 0);
            if (statusChart) statusChart.destroy();
            statusChart = new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: sLabels,
                    datasets: [{
                        data: sValues,
                        backgroundColor: sLabels.map(l => statusColors[l] || '#adb5bd'),
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10, boxHeight: 10,
                                borderRadius: 3,
                                padding: 12,
                                font: { size: 11 },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed} (${sTotal > 0 ? ((ctx.parsed / sTotal) * 100).toFixed(1) : 0}%)`
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw(chart) {
                        const { ctx, chartArea: { top, bottom, left, right } } = chart;
                        const cx = (left + right) / 2, cy = (top + bottom) / 2;
                        ctx.save();
                        ctx.font = 'bold 24px sans-serif'; ctx.fillStyle = '#1e293b';
                        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                        ctx.fillText(sTotal, cx, cy - 8);
                        ctx.font = '11px sans-serif'; ctx.fillStyle = '#94a3b8';
                        ctx.fillText('total', cx, cy + 14);
                        ctx.restore();
                    }
                }]
            });

            // ── Services Bar ──────────────────────────────────────────────────
            if (servicesChart) servicesChart.destroy();
            const barColors = data.services.map((_, i) => {
                const blues = ['#00aeef','#0095cc','#007aaa','#005f87','#004a6a'];
                return blues[i % blues.length];
            });
            servicesChart = new Chart(document.getElementById('servicesChart'), {
                type: 'bar',
                data: {
                    labels: data.services.map(s => s.service_name),
                    datasets: [{
                        label: 'Bookings',
                        data: data.services.map(s => s.count),
                        backgroundColor: barColors,
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: ctx => ` ${ctx.parsed.x} booking${ctx.parsed.x !== 1 ? 's' : ''}`
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 11 } }, grid: { color: '#f1f5f9' }, border: { display: false } },
                        y: { grid: { display: false }, ticks: { color: '#475569', font: { size: 11 } }, border: { display: false } }
                    }
                }
            });

            // ── Services Table ────────────────────────────────────────────────
            const servicesTotal = data.services.reduce((acc, s) => acc + parseInt(s.count), 0);
            document.querySelector('#services-table tbody').innerHTML = data.services.map(s => {
                const pct = servicesTotal > 0 ? (parseInt(s.count) / servicesTotal * 100).toFixed(1) : 0;
                return `<tr>
                    <td>${escHtml(s.service_name)}</td>
                    <td><strong>${s.count}</strong></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress service-progress flex-grow-1">
                                <div class="progress-bar" style="width:${pct}%"></div>
                            </div>
                            <small class="text-muted" style="min-width:32px;">${pct}%</small>
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
