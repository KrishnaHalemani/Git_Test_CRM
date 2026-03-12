<?php
session_start();
include 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get action planner statistics
$actions = getActionPlannerStats($user_id);

// Handle action category update if posted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? null;
    $category = $_POST['category'] ?? null;
    
    if ($lead_id && $category) {
        $result = updateLeadActionCategory($lead_id, $category);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Planner - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --info: #4299e1;
            --secondary: #f7fafc;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 0;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(8px);
        }
        
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .action-card.yet-to-call { border-left-color: var(--warning); }
        .action-card.call-back { border-left-color: var(--info); }
        .action-card.walk-in { border-left-color: var(--success); }
        
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--secondary);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 1rem;
        }
        
        .action-card.yet-to-call .card-icon { background: var(--warning); }
        .action-card.call-back .card-icon { background: var(--info); }
        .action-card.walk-in .card-icon { background: var(--success); }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }
        
        .lead-count {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            background: var(--secondary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .lead-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .lead-item {
            padding: 1rem;
            background: var(--secondary);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .lead-item:hover {
            background: #edf2f7;
        }
        
        .lead-name {
            font-weight: 600;
            color: #2d3748;
            margin: 0 0 0.25rem 0;
        }
        
        .lead-contact {
            font-size: 0.85rem;
            color: #718096;
            margin: 0;
        }
        
        .lead-btn-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .btn-call {
            background: var(--warning);
            color: white;
        }
        
        .btn-call:hover {
            background: #d97706;
            transform: scale(1.05);
        }
        
        .btn-callback {
            background: var(--info);
            color: white;
        }
        
        .btn-callback:hover {
            background: #2563eb;
            transform: scale(1.05);
        }
        
        .btn-walkthrough {
            background: var(--success);
            color: white;
        }
        
        .btn-walkthrough:hover {
            background: #22c55e;
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #718096;
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard_advanced.php" class="sidebar-brand">
                <i class="fas fa-rocket"></i> CRM Pro
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_advanced.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="leads_advanced.php" class="nav-link">
                <i class="fas fa-users"></i> Leads
            </a>
            <a href="action_planner.php" class="nav-link active">
                <i class="fas fa-calendar-check"></i> Action Planner
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i> Action Planner
            </h1>
            <p class="text-muted">Organize and track your follow-up actions</p>
        </div>
        
        <!-- Action Categories -->
        <div class="action-grid">
            <!-- Yet to Call -->
            <div class="action-card yet-to-call">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h2 class="card-title">Yet to Call</h2>
                    <div class="lead-count"><?php echo count($actions['yet_to_call']); ?></div>
                </div>
                <?php if (empty($actions['yet_to_call'])): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                        <p>No leads to call. Great work!</p>
                    </div>
                <?php else: ?>
                    <ul class="lead-list">
                        <?php foreach ($actions['yet_to_call'] as $lead): ?>
                            <li class="lead-item">
                                <div>
                                    <p class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></p>
                                    <p class="lead-contact">
                                        <?php if ($lead['email']): ?><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lead['email']); ?><?php endif; ?>
                                        <?php if ($lead['phone']): ?><br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($lead['phone']); ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="lead-btn-group">
                                    <button class="btn-action btn-callback" onclick="updateAction(<?php echo $lead['id']; ?>, 'call_back')">
                                        <i class="fas fa-undo"></i> Called
                                    </button>
                                    <a href="tel:<?php echo htmlspecialchars($lead['phone'] ?? ''); ?>" class="btn-action btn-call" style="text-decoration:none;">
                                        <i class="fas fa-phone"></i> Call
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Call Back -->
            <div class="action-card call-back">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-sync"></i>
                    </div>
                    <h2 class="card-title">Call Back</h2>
                    <div class="lead-count"><?php echo count($actions['call_back']); ?></div>
                </div>
                <?php if (empty($actions['call_back'])): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                        <p>No callbacks pending. Excellent!</p>
                    </div>
                <?php else: ?>
                    <ul class="lead-list">
                        <?php foreach ($actions['call_back'] as $lead): ?>
                            <li class="lead-item">
                                <div>
                                    <p class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></p>
                                    <p class="lead-contact">
                                        <?php if ($lead['email']): ?><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lead['email']); ?><?php endif; ?>
                                        <?php if ($lead['phone']): ?><br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($lead['phone']); ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="lead-btn-group">
                                    <button class="btn-action btn-walkthrough" onclick="updateAction(<?php echo $lead['id']; ?>, 'walk_in')">
                                        <i class="fas fa-user-tie"></i> Visit
                                    </button>
                                    <a href="tel:<?php echo htmlspecialchars($lead['phone'] ?? ''); ?>" class="btn-action btn-callback" style="text-decoration:none;">
                                        <i class="fas fa-phone"></i> Call Back
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Walk-In -->
            <div class="action-card walk-in">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h2 class="card-title">Walk-In</h2>
                    <div class="lead-count"><?php echo count($actions['walk_in']); ?></div>
                </div>
                <?php if (empty($actions['walk_in'])): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle"></i></div>
                        <p>No walk-ins scheduled.</p>
                    </div>
                <?php else: ?>
                    <ul class="lead-list">
                        <?php foreach ($actions['walk_in'] as $lead): ?>
                            <li class="lead-item">
                                <div>
                                    <p class="lead-name"><?php echo htmlspecialchars($lead['name']); ?></p>
                                    <p class="lead-contact">
                                        <?php if ($lead['email']): ?><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lead['email']); ?><?php endif; ?>
                                        <?php if ($lead['phone']): ?><br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($lead['phone']); ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="lead-btn-group">
                                    <button class="btn-action btn-call" onclick="updateAction(<?php echo $lead['id']; ?>, 'yet_to_call')">
                                        <i class="fas fa-phone"></i> Call
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateAction(leadId, category) {
            const formData = new FormData();
            formData.append('lead_id', leadId);
            formData.append('category', category);
            
            fetch('action_planner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update action');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
