<?php
session_start();
include 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Only allow 'user' role on this page (admins go to dashboard_advanced)
if($_SESSION['role'] !== 'user') {
    header("Location: dashboard_advanced.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $username;

// Get user's own leads
$leads = getLeads($user_id, 'user');

// Calculate statistics
$totalLeads = count($leads);
$statusCounts = [];
$priorityCounts = [];

foreach(['new', 'contacted', 'qualified', 'hot', 'converted', 'lost'] as $status) {
    $statusCounts[$status] = count(array_filter($leads, function($lead) use ($status) {
        return ($lead['status'] ?? 'new') === $status;
    }));
}

foreach(['low', 'medium', 'high'] as $priority) {
    $priorityCounts[$priority] = count(array_filter($leads, function($lead) use ($priority) {
        return ($lead['priority'] ?? 'medium') === $priority;
    }));
}

// Fallback for formatLeadValue if not in db.php to prevent page crash
if (!function_exists('formatLeadValue')) {
    function formatLeadValue($amount, $type = '') {
        if (empty($amount)) return '₹0.00';
        if (stripos($type, 'franchise') !== false && $amount < 1000 && $amount > 0) {
            return '₹' . number_format((float)$amount, 2) . ' L';
        }
        return '₹' . number_format((float)$amount, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --info-color: #a8edea;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-primary);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
            box-shadow: var(--shadow-lg);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            margin-left: 1rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
        }

        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .main-content {
            padding: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }

        .stat-card .label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }

        .stat-card .icon {
            font-size: 2rem;
            color: var(--primary-color);
            opacity: 0.2;
            margin-bottom: 0.5rem;
        }

        .leads-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            margin-top: 2rem;
        }

        .leads-section h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .lead-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .lead-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .lead-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .lead-details {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .lead-detail {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .lead-detail strong {
            color: var(--text-primary);
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-new { background: #667eea20; color: #667eea; }
        .status-contacted { background: #a8edea20; color: #0891b2; }
        .status-qualified { background: #43e97b20; color: #059669; }
        .status-hot { background: #fa709a20; color: #dc2626; }
        .status-converted { background: #4facfe20; color: #0284c7; }
        .status-lost { background: #71809e20; color: #475569; }

        .priority-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-low { background: #43e97b20; color: #059669; }
        .priority-medium { background: #fbbf2420; color: #d97706; }
        .priority-high { background: #ef444420; color: #dc2626; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }

        .no-leads {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .no-leads i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .success-message {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .error-message {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .leads-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="user_dashboard.php">
                <i class="fas fa-rocket me-2"></i>CRM Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="leads_advanced.php">
                            <i class="fas fa-list me-1"></i>My Leads
                        </a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php">
                            <i class="fas fa-tasks me-1"></i>Tasks
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" >
                            <i class="fas fa-user me-1"></i>Profile
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($username, 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($full_name); ?></span>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="mb-4">
            <!-- <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                <i class="fas fa-upload"></i> Import Leads
            </button> -->
        </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importLeadsModal" style="position:fixed;bottom:20px;right:20px;z-index:1050;">
                <i class="fas fa-file-import me-1"></i>Import Leads
            </button>
        <?php include 'components/import_modal.php'; ?>
        <div class="container-fluid">
            <h1 class="page-title">Welcome, <?php echo htmlspecialchars($full_name); ?>! 👋</h1>

            <!-- Success/Error Messages -->
            <?php if(isset($_GET['success'])): ?>
                <div class="success-message alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php
                    switch($_GET['success']) {
                        case 'lead_created': echo 'Lead created successfully!'; break;
                        case 'lead_updated': echo 'Lead updated successfully!'; break;
                        case 'lead_deleted': echo 'Lead deleted successfully!'; break;
                        default: echo 'Operation completed successfully!';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-list"></i></div>
                        <div class="label">Total Leads</div>
                        <div class="number"><?php echo $totalLeads; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-fire"></i></div>
                        <div class="label">Hot Leads</div>
                        <div class="number"><?php echo $statusCounts['hot'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                        <div class="label">Converted</div>
                        <div class="number"><?php echo $statusCounts['converted'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="label">New Leads</div>
                        <div class="number"><?php echo $statusCounts['new'] ?? 0; ?></div>
                    </div>
                </div>
            </div>

            <!-- Leads Section -->
            <div class="leads-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <h3 class="mb-0">Your Leads</h3>
                        <select class="form-select form-select-sm" style="width: 150px;" id="leadTypeFilter">
                            <option value="">All Types</option>
                            <option value="franchise">Franchise</option>
                            <option value="service">Service</option>
                            <option value="course">Course</option>
                        </select>
                    </div>
                    <a href="leads_advanced.php" class="btn btn-primary btn-small">
                        <i class="fas fa-arrow-right me-1"></i>View All
                    </a>
                </div>

                <?php if(count($leads) > 0): ?>
                    <div class="row">
                        <?php 
                        $displayed = 0;
                        foreach($leads as $lead): 
                            if($displayed >= 5) break;
                            $displayed++;
                        ?>
                        <div class="col-md-6 col-lg-4 lead-item" data-source="<?php echo strtolower($lead['source'] ?? ''); ?>" data-service="<?php echo strtolower($lead['service'] ?? ''); ?>">
                            <div class="lead-card">
                                <div class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></div>
                                
                                <div class="lead-details">
                                    <div class="lead-detail">
                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($lead['email'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="lead-detail">
                                        <strong>Phone:</strong><br>
                                        <?php echo htmlspecialchars($lead['phone'] ?? 'N/A'); ?>
                                    </div>
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <span class="status-badge status-<?php echo $lead['status'] ?? 'new'; ?>">
                                        <?php echo ucfirst($lead['status'] ?? 'new'); ?>
                                    </span>
                                    <span class="priority-badge priority-<?php echo $lead['priority'] ?? 'medium'; ?>" style="margin-left: 0.5rem;">
                                        <?php echo ucfirst($lead['priority'] ?? 'medium'); ?>
                                    </span>
                                </div>

                                <div class="text-muted small">
                                                Value: <strong><?php echo formatLeadValue($lead['estimated_value'] ?? 0, $lead['service'] ?? ''); ?></strong>
                                            </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if(count($leads) > 5): ?>
                        <div class="text-center mt-3">
                            <p class="text-muted">Showing 5 of <?php echo count($leads); ?> leads</p>
                            <a href="leads_advanced.php" class="btn btn-outline-primary">View All Leads</a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-leads">
                        <i class="fas fa-inbox"></i>
                        <h5>No Leads Yet</h5>
                        <p>You don't have any leads assigned to you yet.</p>
                        <a href="leads_advanced.php" class="btn btn-primary">Create Your First Lead</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple filter for the dashboard cards
        document.getElementById('leadTypeFilter').addEventListener('change', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('.lead-item');
            
            items.forEach(item => {
                const source = (item.dataset.source || '').toLowerCase();
                const service = (item.dataset.service || '').toLowerCase();
                
                if (!filter || source.includes(filter) || service.includes(filter)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
