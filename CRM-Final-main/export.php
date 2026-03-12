<?php
session_start();
include 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Require admin or superadmin role for export
require_role(['admin', 'superadmin']);

$role = $_SESSION['role'];

// Handle different export formats and filters
$format = $_GET['format'] ?? 'csv';
$filter = $_GET['filter'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// If this is a direct download request
if (isset($_GET['download'])) {
    $user_id = $_SESSION['user_id'];
    $leads = getLeads($user_id, $role);

    // Apply filters
    if ($filter !== 'all') {
        $leads = array_filter($leads, function($lead) use ($filter) {
            return ($lead['status'] ?? 'new') === $filter;
        });
    }

    if ($date_from && $date_to) {
        $leads = array_filter($leads, function($lead) use ($date_from, $date_to) {
            $created = $lead['created_at'] ?? date('Y-m-d');
            return $created >= $date_from && $created <= $date_to;
        });
    }

    logSystemActivity($user_id, 'export_data', "Downloaded leads export (Format: $format, Filter: $filter)");

    if ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="crm_leads_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen("php://output", "w");

        // Add CSV headers
        fputcsv($output, [
            'ID', 'Name', 'Email', 'Phone', 'Company', 'Service',
            'Status', 'Source', 'Assigned To', 'Notes', 'Created At'
        ]);

        // Add lead data
        foreach($leads as $lead) {
            fputcsv($output, [
                $lead['id'] ?? '',
                $lead['name'] ?? '',
                $lead['email'] ?? '',
                $lead['phone'] ?? '',
                $lead['company'] ?? '',
                $lead['service'] ?? '',
                $lead['status'] ?? 'new',
                $lead['source'] ?? 'manual',
                $lead['assigned_to_name'] ?? '',
                $lead['notes'] ?? '',
                $lead['created_at'] ?? ''
            ]);
        }

        fclose($output);
        exit();
    } elseif ($format === 'json') {
        // JSON Export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="crm_leads_' . date('Y-m-d_H-i-s') . '.json"');

        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($leads),
            'filter_applied' => $filter,
            'leads' => $leads
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// If we reach here, show the export interface
$user_id = $_SESSION['user_id'];
$leads = getLeads($user_id, $role);
$totalLeads = count($leads);
$statusCounts = [];
foreach(['new', 'contacted', 'qualified', 'hot', 'converted', 'lost'] as $status) {
    $statusCounts[$status] = count(array_filter($leads, function($lead) use ($status) {
        return ($lead['status'] ?? 'new') === $status;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Pro - Advanced Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);

            --primary-color: #667eea;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --sidebar-width: 280px;

            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-primary);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
            color: white;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .sidebar-brand i {
            margin-right: 0.75rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(8px);
        }

        .nav-link i {
            width: 24px;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .top-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }

        .content-area {
            padding: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .export-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .export-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .export-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .export-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .export-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .option-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .option-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0.05;
            transition: left 0.3s ease;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .option-card:hover::before {
            left: 0;
        }

        .option-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .option-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .option-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .filter-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .filter-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .form-control,
        .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .export-options {
                grid-template-columns: 1fr;
            }

            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard_advanced.php" class="sidebar-brand">
                <i class="fas fa-rocket"></i>CRM Pro
            </a>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard_advanced.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="leads_advanced.php" class="nav-link">
                    <i class="fas fa-users"></i>Leads
                </a>
            </div>
            <?php if($role == 'admin' || $role == 'superadmin'): ?>
            <div class="nav-item">
                <a href="analytics_advanced.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>Analytics
                </a>
            </div>
            <div class="nav-item">
                <a href="reports_advanced.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>Reports
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="export.php" class="nav-link active">
                    <i class="fas fa-download"></i>Export Data
                </a>
            </div>
            <div class="nav-item">
                <a href="profile_advanced.php" class="nav-link">
                    <i class="fas fa-user"></i>Profile
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <h1 class="page-title">Export Data</h1>
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="export-container animate__animated animate__fadeInUp">
                <div class="export-header">
                    <h1 class="export-title">Export Your Data</h1>
                    <p class="export-subtitle">Choose your preferred format and filters to export your CRM data</p>
                </div>

                <!-- Statistics Overview -->
                <div class="stats-overview">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalLeads; ?></div>
                        <div class="stat-label">Total Leads</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statusCounts['new']; ?></div>
                        <div class="stat-label">New Leads</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statusCounts['hot']; ?></div>
                        <div class="stat-label">Hot Leads</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statusCounts['converted']; ?></div>
                        <div class="stat-label">Converted</div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="export-options">
                    <!-- CSV Export -->
                    <div class="option-card" onclick="showExportModal('csv')">
                        <div class="option-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <h3 class="option-title">CSV Export</h3>
                        <p class="option-description">
                            Export your data in CSV format, perfect for Excel and other spreadsheet applications.
                        </p>
                        <button class="btn btn-primary-modern w-100">
                            <i class="fas fa-download me-2"></i>Export as CSV
                        </button>

                        <div class="filter-section">
                            <div class="filter-title">Quick Filters</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" id="csv-status">
                                        <option value="all">All Status</option>
                                        <option value="new">New</option>
                                        <option value="hot">Hot</option>
                                        <option value="converted">Converted</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="quickExport('csv')">
                                        <i class="fas fa-bolt me-1"></i>Quick Export
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- JSON Export -->
                    <div class="option-card" onclick="showExportModal('json')">
                        <div class="option-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <h3 class="option-title">JSON Export</h3>
                        <p class="option-description">
                            Export in JSON format for developers and API integrations with full data structure.
                        </p>
                        <button class="btn btn-primary-modern w-100">
                            <i class="fas fa-download me-2"></i>Export as JSON
                        </button>

                        <div class="filter-section">
                            <div class="filter-title">Developer Options</div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="json-pretty" checked>
                                <label class="form-check-label" for="json-pretty">
                                    Pretty Print JSON
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="json-metadata" checked>
                                <label class="form-check-label" for="json-metadata">
                                    Include Metadata
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Excel Export -->
                    <div class="option-card" onclick="showExportModal('excel')">
                        <div class="option-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <h3 class="option-title">Excel Export</h3>
                        <p class="option-description">
                            Export as Excel file with formatting, charts, and multiple sheets for comprehensive analysis.
                        </p>
                        <button class="btn btn-primary-modern w-100">
                            <i class="fas fa-download me-2"></i>Export as Excel
                        </button>

                        <div class="filter-section">
                            <div class="filter-title">Excel Features</div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="excel-charts" checked>
                                <label class="form-check-label" for="excel-charts">
                                    Include Charts
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="excel-formatting" checked>
                                <label class="form-check-label" for="excel-formatting">
                                    Apply Formatting
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- PDF Report -->
                    <div class="option-card" onclick="showExportModal('pdf')">
                        <div class="option-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h3 class="option-title">PDF Report</h3>
                        <p class="option-description">
                            Generate a professional PDF report with charts, summaries, and detailed lead information.
                        </p>
                        <button class="btn btn-primary-modern w-100">
                            <i class="fas fa-download me-2"></i>Generate PDF
                        </button>

                        <div class="filter-section">
                            <div class="filter-title">Report Options</div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pdf-summary" checked>
                                <label class="form-check-label" for="pdf-summary">
                                    Executive Summary
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pdf-charts" checked>
                                <label class="form-check-label" for="pdf-charts">
                                    Include Charts
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Advanced Export Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm">
                        <input type="hidden" id="export-format" name="format">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Filter by Status</label>
                                    <select class="form-select" name="filter">
                                        <option value="all">All Leads</option>
                                        <option value="new">New Leads</option>
                                        <option value="contacted">Contacted</option>
                                        <option value="qualified">Qualified</option>
                                        <option value="hot">Hot Leads</option>
                                        <option value="converted">Converted</option>
                                        <option value="lost">Lost</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date Range</label>
                                    <select class="form-select" id="date-range">
                                        <option value="all">All Time</option>
                                        <option value="today">Today</option>
                                        <option value="week">This Week</option>
                                        <option value="month">This Month</option>
                                        <option value="quarter">This Quarter</option>
                                        <option value="year">This Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="custom-date-range" style="display: none;">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="date_from">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="date_to">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Fields to Export</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-name" checked>
                                        <label class="form-check-label" for="field-name">Name</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-email" checked>
                                        <label class="form-check-label" for="field-email">Email</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-phone" checked>
                                        <label class="form-check-label" for="field-phone">Phone</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-company" checked>
                                        <label class="form-check-label" for="field-company">Company</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-service" checked>
                                        <label class="form-check-label" for="field-service">Service</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-status" checked>
                                        <label class="form-check-label" for="field-status">Status</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-source" checked>
                                        <label class="form-check-label" for="field-source">Source</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="field-notes">
                                        <label class="form-check-label" for="field-notes">Notes</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary-modern" onclick="executeExport()">
                        <i class="fas fa-download me-2"></i>Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showExportModal(format) {
            document.getElementById('export-format').value = format;
            document.querySelector('#exportModal .modal-title').textContent =
                `Advanced ${format.toUpperCase()} Export Options`;

            const modal = new bootstrap.Modal(document.getElementById('exportModal'));
            modal.show();
        }

        function quickExport(format) {
            const status = document.getElementById(`${format}-status`).value;
            const url = `export.php?download=1&format=${format}&filter=${status}`;

            // Show loading state
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
            btn.disabled = true;

            // Create download link
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Reset button after delay
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        }

        function executeExport() {
            const form = document.getElementById('exportForm');
            const formData = new FormData(form);
            formData.append('download', '1');

            // Build URL with parameters
            const params = new URLSearchParams();
            for (let [key, value] of formData.entries()) {
                params.append(key, value);
            }

            const url = `export.php?${params.toString()}`;

            // Show loading state
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
            btn.disabled = true;

            // Create download link
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Close modal and reset button
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
            }, 2000);
        }

        // Date range handling
        document.getElementById('date-range').addEventListener('change', function() {
            const customRange = document.getElementById('custom-date-range');
            if (this.value === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';

                // Set predefined date ranges
                const today = new Date();
                const fromInput = document.querySelector('input[name="date_from"]');
                const toInput = document.querySelector('input[name="date_to"]');

                switch(this.value) {
                    case 'today':
                        fromInput.value = today.toISOString().split('T')[0];
                        toInput.value = today.toISOString().split('T')[0];
                        break;
                    case 'week':
                        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                        fromInput.value = weekAgo.toISOString().split('T')[0];
                        toInput.value = today.toISOString().split('T')[0];
                        break;
                    case 'month':
                        const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                        fromInput.value = monthAgo.toISOString().split('T')[0];
                        toInput.value = today.toISOString().split('T')[0];
                        break;
                    default:
                        fromInput.value = '';
                        toInput.value = '';
                }
            }
        });

        // Add hover effects to option cards
        document.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animate stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            const stats = document.querySelectorAll('.stat-value');
            stats.forEach((stat, index) => {
                const finalValue = parseInt(stat.textContent);
                let currentValue = 0;
                const increment = finalValue / 50;

                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        stat.textContent = finalValue;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(currentValue);
                    }
                }, 30);
            });
        });
    </script>
</body>
</html>