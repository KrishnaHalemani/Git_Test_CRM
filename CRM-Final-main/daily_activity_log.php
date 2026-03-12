<?php
session_start();
include 'db.php';
require_role(['superadmin']);

// Get filter date (default to today)
$filter_date = $_GET['date'] ?? '';

// Fetch activities
$activities = [];
try {
    $sql = "SELECT sa.*, u.username, u.role 
            FROM system_activities sa 
            LEFT JOIN users u ON sa.user_id = u.id 
            WHERE 1=1";
    
    $params = [];
    if (!empty($filter_date)) {
        $sql .= " AND DATE(sa.created_at) = ?";
        $params[] = $filter_date;
    }
    
    $sql .= " ORDER BY sa.created_at DESC LIMIT 100";
            
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $activities = $stmt->fetchAll();
    } else {
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param("s", $params[0]);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $activities[] = $row;
    }
} catch (Exception $e) {
    // Table might not exist yet if no activity logged
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Activity Log - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        #sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 250px;
            background-color: #343a40; padding-top: 1rem; transition: all 0.3s; z-index: 1030;
        }
        #sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1.5rem; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active { color: #fff; background-color: #495057; }
        #sidebar .nav-link .fa { margin-right: 10px; }
        #content { margin-left: 250px; padding: 20px; }
        .activity-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .timeline-time { font-size: 0.85rem; color: #6c757d; font-weight: 600; }
        .timeline-date { font-size: 0.75rem; color: #adb5bd; }
        .badge-action { font-size: 0.75rem; text-transform: uppercase; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header text-center py-4">
            <h4 class="text-white">CRM Pro</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="superadmin_dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="leads_advanced.php"><i class="fa fa-users"></i> Lead Management</a></li>
            <li class="nav-item"><a class="nav-link" href="analytics_dashboard.php"><i class="fa fa-chart-line"></i> Leads Analytics</a></li>
            <li class="nav-item"><a class="nav-link" href="lead_analysis_report.php"><i class="fa fa-file-invoice"></i> Analysis Report</a></li>
            <li class="nav-item"><a class="nav-link active" href="daily_activity_log.php"><i class="fa fa-list-alt"></i> Daily Activity</a></li>
            <li class="nav-item"><a class="nav-link" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php"><i class="fa fa-tasks"></i> Task Manager</a></li>
        </ul>
    </nav>

    <!-- Content -->
    <div id="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-history me-2"></i>Daily Activity Log</h2>
                    <p class="text-muted mb-0">Track all operations across the CRM system</p>
                </div>
                <div>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                        <a href="superadmin_dashboard.php" class="btn btn-outline-secondary">Back</a>
                    </form>
                </div>
            </div>

            <div class="activity-card p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">Time</th>
                                <th width="15%">User</th>
                                <th width="15%">Action</th>
                                <th>Description</th>
                                <th width="10%">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No activities recorded.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $act): 
                                    $time = date('h:i A', strtotime($act['created_at']));
                                    $fullDate = date('M d, Y', strtotime($act['created_at']));
                                    $badgeClass = 'bg-secondary';
                                    if (strpos($act['action'], 'create') !== false) $badgeClass = 'bg-success';
                                    elseif (strpos($act['action'], 'delete') !== false) $badgeClass = 'bg-danger';
                                    elseif (strpos($act['action'], 'update') !== false) $badgeClass = 'bg-warning text-dark';
                                    elseif (strpos($act['action'], 'export') !== false) $badgeClass = 'bg-info text-dark';
                                ?>
                                <tr>
                                    <td>
                                        <div class="timeline-time"><?php echo $time; ?></div>
                                        <div class="timeline-date"><?php echo $fullDate; ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($act['username'] ?? 'Unknown'); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($act['role'] ?? 'System'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?> badge-action">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $act['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($act['description']); ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($act['ip_address']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
