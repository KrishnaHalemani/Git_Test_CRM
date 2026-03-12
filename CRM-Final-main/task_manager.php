<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Get filters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search_filter = $_GET['search'] ?? '';
$assigned_to_filter = $_GET['assigned_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'due_date';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Build filters array
$filters = [
    'status' => $status_filter,
    'priority' => $priority_filter,
    'search' => $search_filter,
    'assigned_to' => $assigned_to_filter,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'limit' => 100,
    'offset' => 0
];

// Get tasks based on role
$tasks = getTasksByRole($user_id, $role, $filters);
$stats = getTaskStats($user_id, $role);

// Safety check: Ensure stats has all required keys
if (empty($stats) || !isset($stats['total'])) {
    $stats = [
        'total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 
        'overdue' => 0, 'due_today' => 0, 
        'assigned_by_priority' => ['high' => 0, 'medium' => 0, 'low' => 0]
    ];
}

// Get list of users for assignment (admin and superadmin can see all users)
$users = [];
if ($role === 'superadmin' || $role === 'admin') {
    $users = getAllUsers(); // Need to add this function
}

// Pagination data
$total_tasks = $stats['total'];
$per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = ceil($total_tasks / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --danger: #e74c3c;
            --warning: #f6c23e;
            --info: #36b9cc;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #224abe 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-custom .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }

        .navbar-custom .nav-link:hover {
            color: rgba(255,255,255,0.8) !important;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, #224abe 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .dashboard-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        }

        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.in-progress { border-left-color: var(--info); }
        .stat-card.completed { border-left-color: var(--success); }
        .stat-card.overdue { border-left-color: var(--danger); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        .task-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .task-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-left: 4px solid #ddd;
            transition: all 0.3s;
        }

        .task-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
            border-left-color: var(--primary);
        }

        .task-card.pending { border-left-color: var(--warning); }
        .task-card.in-progress { border-left-color: var(--info); }
        .task-card.completed { border-left-color: var(--success); }
        .task-card.cancelled { border-left-color: #999; }

        .task-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .task-title.completed {
            text-decoration: line-through;
            color: #999;
        }

        .task-description {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            word-break: break-word;
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .priority-high { color: var(--danger); font-weight: 600; }
        .priority-medium { color: var(--warning); font-weight: 600; }
        .priority-low { color: var(--success); }

        .badge-status {
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-in-progress { background-color: #d1ecf1; color: #0c5460; }
        .badge-completed { background-color: #d4edda; color: #155724; }
        .badge-cancelled { background-color: #f8d7da; color: #721c24; }

        .btn-task-action {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }

        .task-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .due-date-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            background-color: #f0f0f0;
            border-radius: 0.25rem;
            font-size: 0.9rem;
        }

        .due-date-badge.overdue {
            background-color: #ffe5e5;
            color: #c00;
        }

        .due-date-badge.due-today {
            background-color: #fff3cd;
            color: #856404;
        }

        .no-tasks {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-tasks i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #224abe 100%);
            color: white;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #224abe;
            border-color: #224abe;
        }

        .pagination {
            margin-top: 2rem;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: var(--primary);
            color: white;
            border-radius: 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .empty-state {
            background: white;
            border-radius: 0.5rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #999;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #bbb;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tasks"></i> CRM Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                    <a class="nav-link active" href="https://infinite-vision.co.in/task_final/Task_ManagerIV-main/html/backend/login.php">
                            <i class="fas fa-tasks"></i> Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php 
                            if ($role === 'superadmin') echo 'superadmin_dashboard.php';
                            elseif ($role === 'admin') echo 'dashboard_advanced.php';
                            else echo 'user_dashboard.php';
                        ?>">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <h1><i class="fas fa-tasks"></i> Task Manager</h1>
            <p>Welcome back, <strong><?php echo htmlspecialchars($username); ?></strong>
                <span class="role-badge"><?php echo ucfirst($role); ?></span>
            </p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card in-progress">
                    <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card overdue">
                    <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['due_today']; ?></div>
                    <div class="stat-label">Due Today</div>
                </div>
            </div>
        </div>

        <!-- Create Task Button (for Admin and SuperAdmin) -->
        <?php if ($role === 'admin' || $role === 'superadmin'): ?>
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    <i class="fas fa-plus"></i> Create New Task
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="task-filters">
            <form method="GET" action="task_manager.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search Tasks</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by title..." 
                           value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort_by" class="form-select">
                        <option value="due_date" <?php echo $sort_by === 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                        <option value="priority" <?php echo $sort_by === 'priority' ? 'selected' : ''; ?>>Priority</option>
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Created</option>
                        <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="task_manager.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Tasks List -->
        <div class="row">
            <div class="col-12">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h4>No tasks found</h4>
                        <p><?php echo $role === 'user' ? 'No tasks have been assigned to you yet.' : 'Create your first task to get started!'; ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        $is_overdue = false;
                        $is_due_today = false;
                        
                        if ($task['due_date']) {
                            $due_date = strtotime($task['due_date']);
                            $today = strtotime(date('Y-m-d'));
                            $is_overdue = ($due_date < $today && $task['status'] !== 'completed');
                            $is_due_today = (date('Y-m-d', $due_date) === date('Y-m-d'));
                        }
                    ?>
                    <div class="task-card <?php echo $task['status']; ?>">
                        <div class="task-title <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>">
                            <i class="fas fa-<?php echo $task['status'] === 'completed' ? 'check-circle' : 'circle'; ?>"></i>
                            <?php echo htmlspecialchars($task['title']); ?>
                            <span class="badge-status badge-<?php echo $task['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($task['description']): ?>
                        <div class="task-description">
                            <?php echo htmlspecialchars(substr($task['description'], 0, 150)); 
                                  if (strlen($task['description']) > 150) echo '...'; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="task-meta">
                            <div class="task-meta-item">
                                <i class="fas fa-flag"></i>
                                <span class="priority-<?php echo $task['priority']; ?>">
                                    <?php echo ucfirst($task['priority']); ?> Priority
                                </span>
                            </div>
                            
                            <?php if ($task['due_date']): ?>
                            <div class="task-meta-item">
                                <i class="fas fa-calendar"></i>
                                <span class="due-date-badge <?php echo $is_overdue ? 'overdue' : ($is_due_today ? 'due-today' : ''); ?>">
                                    <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                    <?php if ($is_overdue): ?><i class="fas fa-exclamation-circle"></i><?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="task-meta-item">
                                <i class="fas fa-user"></i>
                                <small><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned'); ?></small>
                            </div>
                            
                            <?php if ($task['related_type'] !== 'general'): ?>
                            <div class="task-meta-item">
                                <i class="fas fa-link"></i>
                                <small><?php echo ucfirst($task['related_type']); ?> #<?php echo $task['related_id']; ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="task-actions">
                            <a href="task_view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info btn-task-action">
                                <i class="fas fa-eye"></i> View
                            </a>
                            
                            <?php if ($role === 'superadmin' || $role === 'admin' || $task['assigned_to'] == $user_id): ?>
                            <button class="btn btn-sm btn-warning btn-task-action" onclick="updateTaskStatus(<?php echo $task['id']; ?>, event)">
                                <i class="fas fa-hourglass-half"></i> Update
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($role === 'superadmin' || $task['created_by'] == $user_id): ?>
                            <button class="btn btn-sm btn-secondary btn-task-action" data-bs-toggle="modal" 
                                    data-bs-target="#editTaskModal" onclick="loadTaskForEdit(<?php echo $task['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger btn-task-action" onclick="deleteTask(<?php echo $task['id']; ?>, event)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Task Modal (Admin & SuperAdmin only) -->
    <?php if ($role === 'admin' || $role === 'superadmin'): ?>
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createTaskForm">
                        <div class="mb-3">
                            <label class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter task title">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Enter task details..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assign To <span class="text-danger">*</span></label>
                                <select name="assigned_to" class="form-select" required>
                                    <option value="">-- Select User --</option>
                                    <?php foreach (getAllUsers() as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo ucfirst($user['role']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" name="due_date" class="form-control">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Related To</label>
                                <select name="related_type" class="form-select">
                                    <option value="general" selected>General</option>
                                    <option value="lead">Lead</option>
                                    <option value="contact">Contact</option>
                                    <option value="company">Company</option>
                                    <option value="deal">Deal</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createTask()">
                        <i class="fas fa-plus"></i> Create Task
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTaskForm">
                        <input type="hidden" id="edit_task_id" name="task_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text" id="edit_title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="edit_description" name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select id="edit_priority" name="priority" class="form-select">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" id="edit_due_date" name="due_date" class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateTaskForm()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createTask() {
            const form = document.getElementById('createTaskForm');
            const formData = new FormData(form);
            formData.append('action', 'create_task');

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task created successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the task');
            });
        }

        function loadTaskForEdit(taskId) {
            fetch('task_actions.php?action=get_task&task_id=' + taskId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const task = data.task;
                    document.getElementById('edit_task_id').value = task.id;
                    document.getElementById('edit_title').value = task.title;
                    document.getElementById('edit_description').value = task.description || '';
                    document.getElementById('edit_priority').value = task.priority;
                    if (task.due_date) {
                        document.getElementById('edit_due_date').value = task.due_date.replace(' ', 'T').slice(0, 16);
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        function updateTaskForm() {
            const form = document.getElementById('editTaskForm');
            const formData = new FormData(form);
            formData.append('action', 'update_task');

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task updated successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the task');
            });
        }

        function updateTaskStatus(taskId, event) {
            const status = prompt('Enter new status:\npending\nin_progress\ncompleted\ncancelled', 'in_progress');
            if (!status) return;

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('task_id', taskId);
            formData.append('status', status);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task status updated!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function deleteTask(taskId, event) {
            if (!confirm('Are you sure you want to delete this task?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', taskId);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task deleted successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
