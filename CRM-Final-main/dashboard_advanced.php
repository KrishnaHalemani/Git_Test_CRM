<?php
session_start();
include 'db.php';

// Check if user is logged in and has a role
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Superadmins should use their own dashboard
if($_SESSION['role'] === 'superadmin') {
    header("Location: superadmin_dashboard.php");
    exit();
}

// Users should use user dashboard
if($_SESSION['role'] === 'user') {
    header("Location: user_dashboard.php");
    exit();
}

// Require admin role for this dashboard
require_role(['admin']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get leads based on user role
$leads = getLeads($user_id, $role);

// Get dashboard statistics from MySQL
$stats = getDashboardStats();
$totalLeads = $stats['total_leads'];
$newLeads = $stats['recent_leads'] ?? $stats['new_leads']; // Use recent (7 days) for this specific dashboard view
$hotLeads = $stats['hot_leads'];
$convertedLeads = $stats['converted_leads'];

// Conversion rate
$conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

// Monthly data for advanced charts from MySQL
$monthlyData = [];
$revenueData = [];

// Get monthly data from database
if (isset($stats['monthly_data'])) {
    foreach ($stats['monthly_data'] as $count) {
        $monthName = date('M Y', strtotime($count['month'] . '-01'));
        $monthlyData[$monthName] = $count['created'];
        $revenueData[$monthName] = $count['revenue']; // Actual revenue from DB
    }
} else {
    // Fallback data if no database data
    for($i = 11; $i >= 0; $i--) {
        $monthName = date('M Y', strtotime("-$i months"));
        $monthlyData[$monthName] = 0;
        $revenueData[$monthName] = 0;
    }
}

// Lead sources data
$sourceData = [];
$sources = ['website', 'social-media', 'referral', 'advertisement', 'manual'];
foreach($sources as $source) {
    $sourceData[$source] = count(array_filter($leads, function($lead) use ($source) {
        return ($lead['source'] ?? 'manual') === $source;
    }));
}

// Recent activities (mock data for demo)
$recentActivities = [
    ['type' => 'lead_added', 'user' => 'John Admin', 'description' => 'Added new lead: Sarah Johnson', 'time' => '2 minutes ago', 'icon' => 'user-plus', 'color' => 'success'],
    ['type' => 'status_changed', 'user' => 'Jane User', 'description' => 'Changed lead status to Hot', 'time' => '15 minutes ago', 'icon' => 'fire', 'color' => 'warning'],
    ['type' => 'lead_converted', 'user' => 'Mike Admin', 'description' => 'Converted lead to customer', 'time' => '1 hour ago', 'icon' => 'check-circle', 'color' => 'success'],
    ['type' => 'email_sent', 'user' => 'System', 'description' => 'Follow-up email sent to 5 leads', 'time' => '2 hours ago', 'icon' => 'envelope', 'color' => 'info'],
    ['type' => 'report_generated', 'user' => 'Admin', 'description' => 'Monthly report generated', 'time' => '3 hours ago', 'icon' => 'chart-bar', 'color' => 'primary']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Infinite Vision CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #1f2937;
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #9ca3af;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            color: white;
            background: rgba(255,255,255,0.05);
            border-left-color: var(--primary-color);
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
        }

        .nav-group {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6b7280;
            padding: 1rem 1.5rem 0.5rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .welcome-text h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }

        .date-text {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-main);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Charts */
        .chart-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            height: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        /* Tables */
        .table-card {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
        }

        .table th {
            background: #f9fafb;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: var(--text-muted);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="brand">
                <i class="fas fa-bolt text-primary"></i> Infinite Vision CRM
            </a>
        </div>
        <div class="nav-links">
            <div class="nav-group">Overview</div>
            <a href="dashboard_advanced.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <!-- <a href="analytics_advanced.php" class="nav-item">
                <i class="fas fa-chart-line"></i> Analytics
            </a> -->
            <!-- <a href="lead_analysis_report.php" class="nav-item">
                <i class="fas fa-file-invoice"></i> Reports
            </a> -->

            <div class="nav-group">Management</div>
            <a href="leads_advanced.php" class="nav-item">
                <i class="fas fa-users"></i> Leads
            </a>
                    <a href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php" class="nav-item">
                <i class="fas fa-tasks"></i> Tasks
            </a>
            <!-- <a href="#" data-bs-toggle="modal" data-bs-target="#importLeadsModal" class="nav-item">
                <i class="fas fa-file-import"></i> Import
            </a> -->

            <div class="nav-group">Account</div>
            <a href="logout.php" class="nav-item text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Admin Dashboard</h1>
                <div class="date-text"><?php echo date('l, F j, Y'); ?></div>
            </div>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-secondary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-white border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $totalLeads; ?></div>
                <div class="stat-label">Total Leads</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-value"><?php echo $newLeads; ?></div>
                <div class="stat-label">New Leads (7 Days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-value"><?php echo $hotLeads; ?></div>
                <div class="stat-label">Hot Leads</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value"><?php echo $convertedLeads; ?></div>
                <div class="stat-label">Converted Leads</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Lead Growth Trend</h3>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="leadsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Lead Sources</h3>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">Quick Actions</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="leads_advanced.php" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>View All Leads
                            </a>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                                <i class="fas fa-file-import me-2"></i>Import Leads
                            </button>
                    <a href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php" class="btn btn-info text-white">
                                <i class="fas fa-tasks me-2"></i>Manage Tasks
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include 'components/import_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Charts
        const monthLabels = <?php echo json_encode(array_keys($monthlyData)); ?>;
        const monthlyLeads = <?php echo json_encode(array_values($monthlyData)); ?>;
        const sourceLabels = <?php echo json_encode(array_keys($sourceData)); ?>;
        const sourceValues = <?php echo json_encode(array_values($sourceData)); ?>;

        // Leads Trend Chart
        new Chart(document.getElementById('leadsChart'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'New Leads',
                    data: monthlyLeads,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Source Chart
        new Chart(document.getElementById('sourceChart'), {
            type: 'doughnut',
            data: {
                labels: sourceLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: sourceValues,
                    backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>
<?php include 'components/import_modal.php'; ?>
                        <div class="action-title">Email Campaign</div>
                    </a>

                    <a href="automation.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="action-title">Setup Automation</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Advanced Chart Configurations
        Chart.defaults.font.family = 'Inter';
        Chart.defaults.color = '#718096';
        // Charts
        const monthLabels = <?php echo json_encode(array_keys($monthlyData)); ?>;
        const monthlyLeads = <?php echo json_encode(array_values($monthlyData)); ?>;
        const sourceLabels = <?php echo json_encode(array_keys($sourceData)); ?>;
        const sourceValues = <?php echo json_encode(array_values($sourceData)); ?>;

        // Leads Trend Chart
        const ctx = document.getElementById('leadsChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(102, 126, 234, 0.3)');
        gradient.addColorStop(1, 'rgba(102, 126, 234, 0.05)');

        const leadsChart = new Chart(ctx, {
        new Chart(document.getElementById('leadsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthlyData)); ?>,
                labels: monthLabels,
                datasets: [{
                    label: 'Leads',
                    data: <?php echo json_encode(array_values($monthlyData)); ?>,
                    borderColor: '#667eea',
                    backgroundColor: gradient,
                    borderWidth: 4,
                    fill: true,
                    label: 'New Leads',
                    data: monthlyLeads,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false
                    }
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#667eea'
                    }
                    y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueGradient = revenueCtx.createLinearGradient(0, 0, 0, 300);
        revenueGradient.addColorStop(0, 'rgba(72, 187, 120, 0.3)');
        revenueGradient.addColorStop(1, 'rgba(72, 187, 120, 0.05)');

        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
        // Source Chart
        new Chart(document.getElementById('sourceChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($revenueData)); ?>,
                labels: sourceLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_values($revenueData)); ?>,
                    backgroundColor: revenueGradient,
                    borderColor: '#48bb78',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                    data: sourceValues,
                    backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#48bb78',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₹' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            padding: 10,
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
                cutout: '70%'
            }
        });

        // Source Chart (Doughnut)
        const sourceCtx = document.getElementById('sourceChart').getContext('2d');
        const sourceChart = new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Website', 'Social Media', 'Referral', 'Advertisement', 'Manual'],
                datasets: [{
                    data: <?php echo json_encode(array_values($sourceData)); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b',
                        '#fa709a'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        cornerRadius: 8,
                        displayColors: true
                    }
                }
            }
        });

        // Chart Controls
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Here you would typically fetch new data based on the period
                // For demo purposes, we'll just show a loading state
                const spinner = document.createElement('div');
                spinner.className = 'loading-spinner';
                this.appendChild(spinner);

                setTimeout(() => {
                    spinner.remove();
                }, 1000);
            });
        });

        // Search functionality
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            // Implement search logic here
            console.log('Searching for:', query);
        });

        // Notification click
        document.querySelector('.notification-btn').addEventListener('click', function() {
            // Show notifications dropdown
            console.log('Show notifications');
        });

        // Auto-refresh data every 30 seconds
        setInterval(() => {
            // Refresh dashboard data
            console.log('Refreshing dashboard data...');
        }, 30000);

        // Add smooth scrolling to all anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
<?php include 'components/import_modal.php'; ?>
