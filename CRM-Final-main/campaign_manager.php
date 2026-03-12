<?php
session_start();
require_once 'db.php';
require_role('superadmin'); // Only superadmins can access this page

$campaigns = getCampaigns();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Manager - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: #f8f9fa; }
        #sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 250px;
            background-color: #343a40; padding-top: 1rem; transition: all 0.3s; z-index: 1030;
        }
        #sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1.5rem; font-size: 1.1rem; border-left: 3px solid transparent; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active { color: #fff; background-color: #495057; border-left-color: #0d6efd; }
        #sidebar .nav-link .fa { margin-right: 10px; }
        #content { margin-left: 250px; padding: 20px; transition: margin-left 0.3s; }
        @media (max-width: 768px) {
            #sidebar { margin-left: -250px; }
            #content { margin-left: 0; }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-header text-center py-4">
        <h4 class="text-white">Infinite Vision CRM</h4>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="superadmin_dashboard.php">
                <i class="fa fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="leads_advanced.php">
                <i class="fa fa-users"></i> Lead Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="analytics_dashboard.php">
                <i class="fa fa-chart-line"></i> Leads Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="lead_analysis_report.php">
                <i class="fa fa-file-invoice"></i> Analysis Report
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="daily_activity_log.php">
                <i class="fa fa-list-alt"></i> Daily Activity
            </a>
        </li>
        <li class="nav-item">
                    <a class="nav-link" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php">
                <i class="fa fa-tasks"></i> Task Manager
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="campaign_manager.php">
                <i class="fa fa-bullhorn"></i> Campaign Manager
            </a>
        </li>
    </ul>
</nav>

<div id="content">
    <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Campaign Manager</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                        <i class="fas fa-plus"></i> Create New Campaign
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    All Campaigns
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Campaign Name</th>
                                    <th scope="col">Created By</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($campaigns)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No campaigns found. Create one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($campaign['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($campaign['creator_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></td>
                                        <td><span class="badge bg-<?php echo $campaign['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($campaign['status']); ?></span></td>
                                        <td>
                                            <a href="campaign_edit.php?id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Define Fields & Import
                                            </a>
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
</div>

<!-- Create Campaign Modal -->
<div class="modal fade" id="createCampaignModal" tabindex="-1" aria-labelledby="createCampaignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title" id="createCampaignModalLabel">Create New Campaign</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="campaign_actions.php" method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="create_campaign">
            <div class="mb-3">
                <label for="campaignName" class="form-label">Campaign Name</label>
                <input type="text" class="form-control" id="campaignName" name="campaign_name" placeholder="e.g., Summer Sale 2025" required>
                <div class="form-text">This will be the name of your marketing campaign.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Create Campaign</button>
        </div>
        </form>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
