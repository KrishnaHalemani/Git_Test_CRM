<?php
session_start();
include 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get ALL leads from database
$allLeads = getLeads();

// Initialize data arrays
$leadStatusData = ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'hot' => 0, 'converted' => 0, 'lost' => 0];
$leadSourceData = ['website' => 0, 'social-media' => 0, 'referral' => 0, 'advertisement' => 0, 'manual' => 0, 'other' => 0];
$dailyStats = [];

// Initialize last 15 days data
for($i = 14; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyStats[$date] = ['leads' => 0, 'followups' => 0, 'conversions' => 0, 'walkins' => 0];
}

// Process leads based on role
$filteredLeads = [];
foreach($allLeads as $lead) {
    if($role === 'superadmin') {
        // Super Admin sees all
        $filteredLeads[] = $lead;
    } elseif($role === 'admin') {
        // Admin sees only their assigned leads
        if($lead['assigned_to'] == $user_id || $lead['created_by'] == $user_id) {
            $filteredLeads[] = $lead;
        }
    } elseif($role === 'user') {
        // User sees only their own leads
        if($lead['assigned_to'] == $user_id || $lead['created_by'] == $user_id) {
            $filteredLeads[] = $lead;
        }
    }
}

// Calculate statistics from actual leads
foreach($filteredLeads as $lead) {
    // Status distribution
    if(isset($leadStatusData[$lead['status']])) {
        $leadStatusData[$lead['status']]++;
    }
    
    // Source distribution
    if(isset($leadSourceData[$lead['source']])) {
        $leadSourceData[$lead['source']]++;
    }
    
    // Daily stats for last 15 days
    $leadDate = date('Y-m-d', strtotime($lead['created_at']));
    if(isset($dailyStats[$leadDate])) {
        $dailyStats[$leadDate]['leads']++;
    }
}

// Merge Daily Metrics from Manual Entry (Follow-ups, Conversions, Walk-ins)
$metricsHistory = getDailyMetricsHistory($user_id, $role, 15);

foreach ($metricsHistory as $date => $metrics) {
    if (isset($dailyStats[$date])) {
        $dailyStats[$date]['followups'] = (int)$metrics['followups'];
        $dailyStats[$date]['conversions'] = (int)$metrics['conversions'];
        $dailyStats[$date]['walkins'] = (int)$metrics['walkins'];
    }
}

// Extract daily data for charts
$dailyLeads = [];
$dailyFollowups = [];
$dailyConversions = [];
$dailyWalkins = []; // Moved here
$dayLabels = [];

for($i = 14; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabels[] = date('M d', strtotime($date));
    
    if(isset($dailyStats[$date])) {
        $dailyLeads[] = $dailyStats[$date]['leads'];
        $dailyFollowups[] = $dailyStats[$date]['followups'];
        $dailyConversions[] = $dailyStats[$date]['conversions'];
        $dailyWalkins[] = $dailyStats[$date]['walkins'];
    } else {
        $dailyLeads[] = 0;
        $dailyFollowups[] = 0;
        $dailyConversions[] = 0;
        $dailyWalkins[] = 0;
    }
}

// Calculate totals
$totalLeads = count($filteredLeads);
$totalConversions = array_sum($dailyConversions); // Use manual conversions sum
$conversionRate = ($totalLeads > 0) ? round(($totalConversions / $totalLeads) * 100, 1) : 0;
$totalFollowups = array_sum($dailyFollowups);

// Set dashboard title and scope based on role
if($role === 'superadmin') {
    $dashboardTitle = "CRM Analytics Dashboard - Super Admin";
    $dataScope = "All Organization Data";
} elseif($role === 'admin') {
    $dashboardTitle = "CRM Analytics Dashboard - Team Admin";
    $dataScope = "Team Data Only";
} else {
    $dashboardTitle = "CRM Analytics Dashboard - Your Performance";
    $dataScope = "Personal Data Only";
}

// Prepare data for charts
$statusLabels = array_map(function($key) { 
    return ucfirst(str_replace('-', ' ', $key)); 
}, array_keys($leadStatusData));
$statusValues = array_values($leadStatusData);

$sourceLabels = array_map(function($key) { 
    return ucfirst(str_replace('-', ' ', $key)); 
}, array_keys($leadSourceData));
$sourceValues = array_values($leadSourceData);

// Create dummy category data (can be enhanced with real categories)
$categoryData = ['Premium' => 0, 'Standard' => 0, 'Budget' => 0, 'Corporate' => 0];
foreach($filteredLeads as $lead) {
    // Distribute leads into categories based on estimated_value
    if($lead['estimated_value'] > 10000) {
        $categoryData['Premium']++;
    } elseif($lead['estimated_value'] > 5000) {
        $categoryData['Standard']++;
    } elseif($lead['estimated_value'] > 1000) {
        $categoryData['Budget']++;
    } else {
        $categoryData['Corporate']++;
    }
}

$categoryLabels = array_keys($categoryData);
$categoryValues = array_values($categoryData);

$dayLabelsJSON = json_encode($dayLabels);
$dailyLeadsJSON = json_encode($dailyLeads);
$dailyFollowupsJSON = json_encode($dailyFollowups);
$dailyConversionsJSON = json_encode($dailyConversions);
$dailyWalkinsJSON = json_encode($dailyWalkins);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashboardTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --info-color: #4299e1;
            --light-bg: #f7fafc;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --card-border: 1px solid #e2e8f0;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .dashboard-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .dashboard-header .badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--primary-color);
        }

        .chart-card {
            background: white;
            border: var(--card-border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
        }

        .chart-card:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .chart-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .stat-box {
            background: white;
            border: var(--card-border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #718096;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 3rem;
            color: var(--primary-color);
            opacity: 0.1;
            margin-bottom: 0.5rem;
        }

        .section-container {
            margin-bottom: 3rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .role-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            font-size: 0.9rem;
            margin-left: 1rem;
        }

        .alert-info-custom {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .color-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .chart-legend {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .legend-item {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        canvas {
            max-height: 300px;
        }

        .chart-container-wrapper {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .chart-container-wrapper {
                height: 250px;
            }
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .nav-back {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Back Navigation -->
        <div class="nav-back">
            <?php if($role === 'superadmin'): ?>
                <a href="superadmin_dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            <?php elseif($role === 'admin'): ?>
                <a href="dashboard_advanced.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            <?php else: ?>
                <a href="user_dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
            <?php endif; ?>
        </div>

        <!-- Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-chart-line me-3"></i><?php echo htmlspecialchars($dashboardTitle); ?></h1>
                    <p class="mt-2 mb-0">Comprehensive CRM Performance Metrics</p>
                </div>
                <div class="text-end">
                    <span class="role-badge"><i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($dataScope); ?></span>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="section-container">
            <h2 class="section-title"><i class="fas fa-tachometer-alt me-2"></i>Key Performance Indicators</h2>
            <div class="metrics-grid">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $totalLeads; ?></div>
                    <div class="stat-label">Total Leads</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $totalConversions; ?></div>
                    <div class="stat-label">Conversions</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                    <div class="stat-value"><?php echo $conversionRate; ?>%</div>
                    <div class="stat-label">Conversion Rate</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value"><?php echo $totalFollowups; ?></div>
                    <div class="stat-label">Followups (Last 15 Days)</div>
                </div>
            </div>
        </div>

        <!-- Pie Charts Section -->
        <div class="section-container">
            <h2 class="section-title"><i class="fas fa-pie-chart me-2"></i>Lead Distribution Analysis</h2>
            
            <div class="row">
                <!-- Lead Status Pie Chart -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-filter me-2"></i>Lead Status Distribution</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="leadStatusChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <?php foreach($statusLabels as $i => $label): ?>
                                <div class="legend-item">
                                    <span class="color-dot" style="background-color: <?php echo ['#667eea', '#764ba2', '#f093fb', '#48bb78', '#f56565', '#fed7aa'][$i % 6]; ?>"></span>
                                    <span><?php echo htmlspecialchars($label); ?>: <?php echo $statusValues[$i]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Lead Source Pie Chart -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-location-dot me-2"></i>Lead Source Distribution</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="leadSourceChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <?php foreach($sourceLabels as $i => $label): ?>
                                <div class="legend-item">
                                    <span class="color-dot" style="background-color: <?php echo ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a'][$i % 6]; ?>"></span>
                                    <span><?php echo htmlspecialchars($label); ?>: <?php echo $sourceValues[$i]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Lead Category Pie Chart -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-tags me-2"></i>Lead Category Distribution</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="leadCategoryChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <?php foreach($categoryLabels as $i => $label): ?>
                                <div class="legend-item">
                                    <span class="color-dot" style="background-color: <?php echo ['#667eea', '#764ba2', '#f093fb', '#4facfe'][$i % 4]; ?>"></span>
                                    <span><?php echo htmlspecialchars($label); ?>: <?php echo $categoryValues[$i]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bar Charts Section -->
        <div class="section-container">
            <h2 class="section-title"><i class="fas fa-bar-chart me-2"></i>Performance Trends (Last 15 Days)</h2>
            
            <div class="row">
                <!-- Daily Leads -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-arrow-up me-2"></i>Leads Created Per Day</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="dailyLeadsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Followups -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-phone me-2"></i>Follow-ups Completed Per Day</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="dailyFollowupsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Conversions -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-thumbs-up me-2"></i>Conversions Per Day</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="dailyConversionsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Walk-ins -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="chart-card-title"><i class="fas fa-person-walking me-2"></i>Walk-in Counts Per Day</h5>
                        <div class="chart-container-wrapper">
                            <canvas id="dailyWalkinsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Summary -->
        <div class="section-container">
            <div class="alert alert-info-custom">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Data Note:</strong> All metrics displayed are <strong>real-time data from your CRM</strong> based on your role permissions. Data is filtered to show only relevant information for your role level.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dynamic data from PHP database
        const statusLabels = <?php echo json_encode($statusLabels); ?>;
        const statusValues = <?php echo json_encode($statusValues); ?>;
        
        const sourceLabels = <?php echo json_encode($sourceLabels); ?>;
        const sourceValues = <?php echo json_encode($sourceValues); ?>;
        
        const categoryLabels = <?php echo json_encode($categoryLabels); ?>;
        const categoryValues = <?php echo json_encode($categoryValues); ?>;
        
        const dayLabels = <?php echo $dayLabelsJSON; ?>;
        const dailyLeads = <?php echo $dailyLeadsJSON; ?>;
        const dailyFollowups = <?php echo $dailyFollowupsJSON; ?>;
        const dailyConversions = <?php echo $dailyConversionsJSON; ?>;
        const dailyWalkins = <?php echo $dailyWalkinsJSON; ?>;

        // Lead Status Pie Chart
        new Chart(document.getElementById('leadStatusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#48bb78', '#f56565', '#fed7aa'],
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, titleFont: { size: 13 }, bodyFont: { size: 12 } }
                }
            }
        });

        // Lead Source Pie Chart
        new Chart(document.getElementById('leadSourceChart'), {
            type: 'doughnut',
            data: {
                labels: sourceLabels,
                datasets: [{
                    data: sourceValues,
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a'],
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, titleFont: { size: 13 }, bodyFont: { size: 12 } }
                }
            }
        });

        // Lead Category Pie Chart
        new Chart(document.getElementById('leadCategoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe'],
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
                    tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, titleFont: { size: 13 }, bodyFont: { size: 12 } }
                }
            }
        });

        // Daily Leads Bar Chart
        new Chart(document.getElementById('dailyLeadsChart'), {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [{
                    label: 'Leads Created',
                    data: dailyLeads,
                    backgroundColor: '#667eea',
                    borderColor: '#667eea',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#764ba2'
                }]
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Daily Follow-ups Bar Chart
        new Chart(document.getElementById('dailyFollowupsChart'), {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [{
                    label: 'Follow-ups Completed',
                    data: dailyFollowups,
                    backgroundColor: '#4299e1',
                    borderColor: '#4299e1',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#2c5aa0'
                }]
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Daily Conversions Bar Chart
        new Chart(document.getElementById('dailyConversionsChart'), {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [{
                    label: 'Conversions',
                    data: dailyConversions,
                    backgroundColor: '#48bb78',
                    borderColor: '#48bb78',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#38a169'
                }]
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Daily Walk-ins Bar Chart
        new Chart(document.getElementById('dailyWalkinsChart'), {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [{
                    label: 'Walk-ins',
                    data: dailyWalkins,
                    backgroundColor: '#ed8936',
                    borderColor: '#ed8936',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#c05621'
                }]
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>
