<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include 'db.php';
require_role(['superadmin']);

$counts = getDashboardCounts();
$recent = getRecentActivities(15);
$admins = getAdmins();
$users = getAllUsers(500);

$branches = [];
try {
    if (isset($db_type) && $db_type === 'pdo') {
        $stmt = $pdo->query("SELECT * FROM companies ORDER BY name ASC");
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query("SELECT * FROM companies ORDER BY name ASC");
        while ($row = $res->fetch_assoc()) { $branches[] = $row; }
    }
} catch (Exception $e) { }

$stats = getDashboardStats();
$monthlyData = $stats['monthly_data'];
$monthLabels = array_map(function($m) { return date('M Y', strtotime($m['month'] . '-01')); }, $monthlyData);
$monthlyLeads = array_map(function($m) { return $m['created']; }, $monthlyData);
$monthlyConverted = array_map(function($m) { return $m['converted']; }, $monthlyData);
$revenueData = array_map(function($m) { return $m['revenue']; }, $monthlyData);

$sourceData = [];
if (isset($db_type) && $db_type === 'pdo') {
    $stmt = $pdo->query("SELECT source, COUNT(*) as c FROM leads GROUP BY source");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceData[$row['source']] = (int)$row['c'];
    }
} else {
    $res = $conn->query("SELECT source, COUNT(*) as c FROM leads GROUP BY source");
    while($row = $res->fetch_assoc()) {
        $sourceData[$row['source']] = (int)$row['c'];
    }
}
$sourceLabels = array_map(function($k){return ucfirst($k);}, array_keys($sourceData));
$sourceDataArr = array_values($sourceData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Infinite Vision CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .chart-container { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; height: 400px; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background-color: #343a40;
            padding-top: 1rem;
            transition: all 0.3s;
            z-index: 1030;
        }
        #sidebar.collapsed {
            margin-left: -250px;
        }
        #sidebar .nav-link {
            color: #adb5bd;
            font-size: 1.1rem;
            padding: 0.75rem 1.5rem;
            border-left: 3px solid transparent;
        }
        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
            border-left-color: #0d6efd;
        }
        #sidebar .nav-link .fa {
            margin-right: 10px;
        }
        #content {
            margin-left: 250px;
            transition: margin-left 0.3s;
            padding: 20px;
        }
        #content.expanded {
            margin-left: 0;
        }
        #sidebar-toggler {
            position: fixed;
            top: 10px;
            left: 260px;
            z-index: 1031;
            transition: all 0.3s;
        }
         #sidebar-toggler.expanded {
            left: 10px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav id="sidebar">
        <div class="sidebar-header text-center py-4">
            <h4 class="text-white">Infinite Vision CRM</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#">
                    <i class="fa fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                    <i class="fa fa-file-import"></i>
                    Import Leads
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="leads_advanced.php">
                    <i class="fa fa-users"></i>
                    Lead Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="analytics_dashboard.php">
                    <i class="fa fa-chart-line"></i>
                    Leads Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="lead_analysis_report.php">
                    <i class="fa fa-file-invoice"></i>
                    Analysis Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="daily_activity_log.php">
                    <i class="fa fa-list-alt"></i>
                    Daily Activity
                </a>
            </li>
            <li class="nav-item">
                    <a class="nav-link" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php">
                    <i class="fa fa-tasks"></i>
                    Task Manager
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="campaign_manager.php">
                    <i class="fa fa-bullhorn"></i>
                    Campaign Manager
                </a>
            </li>
        </ul>
    </nav>
    <div id="content">
        <!-- <button id="sidebar-toggler" class="btn btn-primary"><i class="fa fa-bars"></i></button> -->
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-chart-pie me-2"></i>Super Admin Dashboard</h1>
                <div>
                    <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Refresh</button>
                    <a href="logout.php" class="btn btn-outline-danger ms-2"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3"><div class="stat-card"><h6 class="text-muted">Total Leads</h6><h3 class="text-primary"><?php echo $stats['total_leads'] ?? 0; ?></h3></div></div>
                <div class="col-md-3"><div class="stat-card"><h6 class="text-muted">New Leads (Status)</h6><h3 class="text-success"><?php echo $stats['new_leads'] ?? 0; ?></h3></div></div>
                <div class="col-md-3"><div class="stat-card"><h6 class="text-muted">Hot Leads</h6><h3 class="text-danger"><?php echo $stats['hot_leads'] ?? 0; ?></h3></div></div>
                <div class="col-md-3"><div class="stat-card"><h6 class="text-muted">Converted</h6><h3 class="text-info"><?php echo $stats['converted_leads'] ?? 0; ?></h3></div></div>
            </div>

            <section id="analytics">
                <div class="row mb-4">
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5>Lead Trends</h5>
                            <canvas id="leadsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5>Lead Sources</h5>
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5>Revenue Projection</h5>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

        <section id="task-manager" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="fas fa-tasks me-2"></i>Task Manager</h3>
                    <a href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php" class="btn btn-primary">
                    <i class="fas fa-external-link-alt me-2"></i>Open Task Manager
                </a>
            </div>
        </section>






        <section id="lead-management" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Lead Management</h3>
                <div>
                    <a href="leads_advanced.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-list me-2"></i>View All Leads
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                        <i class="fas fa-file-import me-2"></i>Import Leads
                    </button>
                </div>
            </div>
             <?php if (isset($_SESSION['import_status'])): ?>
                <div class="alert alert-<?php echo $_SESSION['import_status']['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['import_status']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['import_status']); ?>
            <?php endif; ?>

        </section>

        <section id="recent" class="mb-5">
            <h3><i class="fas fa-history me-2"></i>Recent Activities</h3>
            <div class="list-group">
                <?php foreach($recent as $act): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div><strong><?php echo htmlspecialchars($act['user_name'] ?? 'System'); ?></strong> <span class="text-muted"><?php echo htmlspecialchars($act['activity_type'] ?? ''); ?></span><div><?php echo htmlspecialchars($act['title'] ?? ''); ?></div></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($act['activity_date']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="admins" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Admin Management</h3>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAdminModal"><i class="fas fa-plus me-2"></i>Add Admin</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($admins as $a): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($a['full_name'] ?? $a['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['email']); ?></td>
                            <td><?php echo htmlspecialchars($a['branch'] ?? 'Main'); ?></td>
                            <td><span class="badge <?php echo ($a['status']==='active') ? 'bg-success' : 'bg-warning'; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                            <td><button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editAdminModal" onclick="editAdmin(<?php echo htmlspecialchars(json_encode($a)); ?>)"><i class="fas fa-edit"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="users" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>User Management</h3>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-user-plus me-2"></i>Add User</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['full_name'] ?? $u['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><span class="badge <?php echo ($u['status']==='active') ? 'bg-success' : 'bg-warning'; ?>><?php echo ucfirst($u['status']); ?></span></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick='editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)'><i class="fas fa-edit"></i></button>
                                    <form method="POST" action="admin_actions.php" style="display:inline;margin:0;padding:0;">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Toggle status"><i class="fas fa-toggle-on"></i></button>
                                    </form>
                                    <form method="POST" action="admin_actions.php" style="display:inline;margin:0;padding:0;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($u['username']); ?>?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="admin_actions.php">
                <input type="hidden" name="action" value="create_admin">
                <div class="modal-header bg-success text-white"><h5 class="modal-title">Add New Admin</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" placeholder="e.g. John Doe"></div>
                        <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
                        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div>
                        <div class="mb-3"><label class="form-label">Password</label><input class="form-control" name="password" type="password" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Create</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="editAdminModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="admin_actions.php">
                <input type="hidden" name="action" value="update_admin">
                <input type="hidden" name="id" id="editAdminId">
                <div class="modal-header bg-info text-white"><h5 class="modal-title">Edit Admin</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" id="editAdminFullName"></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" id="editAdminEmail"></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" id="editAdminPhone"></div>
                    <div class="mb-3"><label class="form-label">Branch</label><input class="form-control" name="branch" id="editAdminBranch"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-info">Update</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="admin_actions.php">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header bg-success text-white"><h5 class="modal-title">Add New User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" placeholder="e.g. Jane Doe"></div>
                    <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" type="text" placeholder="optional"></div>
                    <div class="mb-3"><label class="form-label">Branch</label><input class="form-control" name="branch" type="text" placeholder="optional"></div>
                    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" name="password" type="password" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Create</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="admin_actions.php">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-header bg-info text-white"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" id="editUserFullName"></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" name="email" type="email" id="editUserEmail"></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" id="editUserPhone"></div>
                    <div class="mb-3"><label class="form-label">Branch</label><input class="form-control" name="branch" id="editUserBranch"></div>
                    <div class="mb-3"><label class="form-label">Password (leave blank to keep)</label><input class="form-control" name="password" type="password" id="editUserPassword"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-info">Update</button></div>
            </form>
        </div></div>
    </div>

    <?php include 'components/import_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const monthLabels = <?php echo json_encode($monthLabels); ?>;
        const monthlyLeads = <?php echo json_encode($monthlyLeads); ?>;
        const monthlyConverted = <?php echo json_encode($monthlyConverted); ?>;
        const revenueData = <?php echo json_encode($revenueData); ?>;
        const sourceLabels = <?php echo json_encode($sourceLabels); ?>;
        const sourceDataArr = <?php echo json_encode($sourceDataArr); ?>;

        new Chart(document.getElementById('leadsChart'), {
            type: 'line', data: {labels: monthLabels, datasets: [{label: 'Created', data: monthlyLeads, borderColor: '#667eea', backgroundColor: 'rgba(102, 126, 234, 0.1)', borderWidth: 2, fill: true, tension: 0.4}, {label: 'Converted', data: monthlyConverted, borderColor: '#48bb78', backgroundColor: 'rgba(72, 187, 120, 0.1)', borderWidth: 2, fill: true, tension: 0.4}]},
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}, scales: {y: {beginAtZero: true}}}
        });

        new Chart(document.getElementById('revenueChart'), {
            type: 'bar', data: {labels: monthLabels, datasets: [{label: 'Revenue (₹)', data: revenueData, backgroundColor: 'rgba(72, 187, 120, 0.3)', borderColor: '#48bb78', borderWidth: 2}]},
            options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: true, position: 'bottom'}}, scales: {y: {beginAtZero: true}}}
        });

        new Chart(document.getElementById('sourceChart'), {
            type: 'doughnut', data: {labels: sourceLabels, datasets: [{data: sourceDataArr, backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fed7aa']}]},
            options: {responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: {legend: {position: 'bottom'}}}
        });

        function editAdmin(admin) {
            document.getElementById('editAdminId').value = admin.id;
            document.getElementById('editAdminFullName').value = admin.full_name || '';
            document.getElementById('editAdminEmail').value = admin.email || '';
                    document.getElementById('editAdminPhone').value = admin.phone || '';
                    document.getElementById('editAdminBranch').value = admin.branch || '';
        }

        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserFullName').value = user.full_name || '';
            document.getElementById('editUserEmail').value = user.email || '';
            document.getElementById('editUserPhone').value = user.phone || '';
            document.getElementById('editUserBranch').value = user.branch || '';
            document.getElementById('editUserPassword').value = '';
        }
    </script>
</body>
</html>
