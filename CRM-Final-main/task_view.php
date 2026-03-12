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

// Get task ID from URL
$task_id = (int)($_GET['id'] ?? 0);
if (!$task_id) {
    header('Location: task_manager.php');
    exit;
}

// Get task details
$task = getTaskById($task_id);
if (!$task) {
    header('Location: task_manager.php');
    exit;
}

// Check access permission
if ($role !== 'superadmin' && $task['created_by'] !== $user_id && $task['assigned_to'] !== $user_id) {
    header('Location: task_manager.php');
    exit;
}

// Get task comments
$comments = getTaskComments($task_id);

// Check if task is overdue
$is_overdue = false;
$is_due_today = false;
if ($task['due_date']) {
    $due_date = strtotime($task['due_date']);
    $today = strtotime(date('Y-m-d'));
    $is_overdue = ($due_date < $today && $task['status'] !== 'completed');
    $is_due_today = (date('Y-m-d', $due_date) === date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['title']); ?> - Task Manager</title>
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
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: #224abe;
        }

        .task-header {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .task-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #333;
        }

        .task-title.completed {
            text-decoration: line-through;
            color: #999;
        }

        .task-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-label {
            font-weight: 600;
            color: #666;
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-in-progress { background-color: #d1ecf1; color: #0c5460; }
        .badge-completed { background-color: #d4edda; color: #155724; }
        .badge-cancelled { background-color: #f8d7da; color: #721c24; }

        .content-card {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .content-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.75rem;
        }

        .description-box {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            border-left: 4px solid var(--primary);
            line-height: 1.6;
            color: #333;
        }

        .task-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            border-left: 3px solid var(--primary);
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            color: #333;
            font-size: 1rem;
        }

        .priority-high { color: var(--danger); font-weight: 600; }
        .priority-medium { color: var(--warning); font-weight: 600; }
        .priority-low { color: var(--success); }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .comment {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--primary);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .comment-author {
            font-weight: 600;
            color: #333;
        }

        .comment-date {
            font-size: 0.85rem;
            color: #999;
        }

        .comment-body {
            color: #555;
            line-height: 1.5;
            word-break: break-word;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #224abe 100%);
            color: white;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #224abe;
            border-color: #224abe;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
        }

        .empty-comments {
            text-align: center;
            padding: 2rem;
            color: #999;
        }

        .empty-comments i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--primary) 0%, #224abe 100%);">
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
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-4" style="padding: 0 2rem;">
        <a href="task_manager.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Tasks
        </a>

        <!-- Task Header -->
        <div class="task-header">
            <h1 class="task-title <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>">
                <i class="fas fa-<?php echo $task['status'] === 'completed' ? 'check-circle' : 'circle'; ?>"></i>
                <?php echo htmlspecialchars($task['title']); ?>
            </h1>

            <div class="task-meta">
                <div class="meta-item">
                    <span class="meta-label">Status:</span>
                    <span class="badge-status badge-<?php echo $task['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                    </span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Priority:</span>
                    <span class="priority-<?php echo $task['priority']; ?>">
                        <i class="fas fa-flag"></i> <?php echo ucfirst($task['priority']); ?>
                    </span>
                </div>

                <?php if ($task['due_date']): ?>
                <div class="meta-item">
                    <span class="meta-label">Due:</span>
                    <span class="due-date-badge <?php echo $is_overdue ? 'overdue' : ($is_due_today ? 'due-today' : ''); ?>">
                        <?php echo date('M d, Y H:i', strtotime($task['due_date'])); ?>
                        <?php if ($is_overdue): ?><i class="fas fa-exclamation-circle"></i> Overdue<?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($role === 'superadmin' || $task['created_by'] == $user_id || $task['assigned_to'] == $user_id): ?>
                    <button class="btn btn-sm btn-warning action-btn" onclick="updateStatus()">
                        <i class="fas fa-hourglass-half"></i> Update Status
                    </button>
                    <button class="btn btn-sm btn-secondary action-btn" data-bs-toggle="modal" data-bs-target="#editTaskModal">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                <?php endif; ?>

                <?php if ($role === 'superadmin' || $task['created_by'] == $user_id): ?>
                    <button class="btn btn-sm btn-danger action-btn" onclick="deleteTask()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Task Details -->
            <div class="col-lg-8">
                <!-- Description -->
                <div class="content-card">
                    <h3 class="content-title">Description</h3>
                    <?php if ($task['description']): ?>
                        <div class="description-box">
                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No description provided</p>
                    <?php endif; ?>
                </div>

                <!-- Details Grid -->
                <div class="content-card">
                    <h3 class="content-title">Task Details</h3>
                    <div class="task-details-grid">
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-user"></i> Assigned To</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned'); ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-user-tie"></i> Created By</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($task['created_by_name'] ?? 'Unknown'); ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-calendar"></i> Created</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y H:i', strtotime($task['created_at'])); ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-calendar"></i> Updated</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y H:i', strtotime($task['updated_at'])); ?>
                            </div>
                        </div>

                        <?php if ($task['start_date']): ?>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-play"></i> Start Date</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y H:i', strtotime($task['start_date'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($task['completed_at']): ?>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-check"></i> Completed</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y H:i', strtotime($task['completed_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($task['related_type'] !== 'general'): ?>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-link"></i> Related To</div>
                            <div class="detail-value">
                                <?php echo ucfirst($task['related_type']); ?> #<?php echo $task['related_id']; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="content-card">
                    <h3 class="content-title">Comments (<?php echo count($comments); ?>)</h3>

                    <!-- Add Comment Form -->
                    <form id="addCommentForm" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Add a Comment</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Type your comment here..." required></textarea>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addComment()">
                            <i class="fas fa-comment"></i> Post Comment
                        </button>
                    </form>

                    <!-- Comments List -->
                    <div id="commentsList">
                        <?php if (empty($comments)): ?>
                            <div class="empty-comments">
                                <i class="fas fa-comments"></i>
                                <p>No comments yet. Be the first to comment!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <span class="comment-author">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?>
                                    </span>
                                    <span class="comment-date">
                                        <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="comment-body">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions -->
            <div class="col-lg-4">
                <!-- Status Change Quick Card -->
                <div class="content-card">
                    <h3 class="content-title">Change Status</h3>
                    <div class="btn-group-vertical w-100" role="group">
                        <button type="button" class="btn btn-outline-warning text-start" onclick="changeStatus('pending')">
                            <i class="fas fa-circle"></i> Pending
                        </button>
                        <button type="button" class="btn btn-outline-info text-start" onclick="changeStatus('in_progress')">
                            <i class="fas fa-hourglass-half"></i> In Progress
                        </button>
                        <button type="button" class="btn btn-outline-success text-start" onclick="changeStatus('completed')">
                            <i class="fas fa-check-circle"></i> Completed
                        </button>
                        <button type="button" class="btn btn-outline-secondary text-start" onclick="changeStatus('cancelled')">
                            <i class="fas fa-times-circle"></i> Cancelled
                        </button>
                    </div>
                </div>

                <!-- Set Reminder Card -->
                <div class="content-card">
                    <h3 class="content-title">Set Reminder</h3>
                    <form id="reminderForm">
                        <div class="mb-3">
                            <label class="form-label">Remind Me At</label>
                            <input type="datetime-local" name="reminder_time" class="form-control">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm w-100" onclick="setReminder()">
                            <i class="fas fa-bell"></i> Set Reminder
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" class="form-control" 
                                   value="<?php echo $task['due_date'] ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : ''; ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTask()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeStatus(status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('task_id', <?php echo $task['id']; ?>);
            formData.append('status', status);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function updateStatus() {
            const status = prompt('Enter new status:\npending\nin_progress\ncompleted\ncancelled');
            if (!status) return;
            changeStatus(status);
        }

        function saveTask() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            formData.append('action', 'update_task');

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task updated!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function deleteTask() {
            if (!confirm('Are you sure you want to delete this task?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', <?php echo $task['id']; ?>);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task deleted!');
                    window.location.href = 'task_manager.php';
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function addComment() {
            const form = document.getElementById('addCommentForm');
            const comment = form.querySelector('[name="comment"]').value.trim();
            
            if (!comment) {
                alert('Please enter a comment');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('task_id', <?php echo $task['id']; ?>);
            formData.append('comment', comment);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function setReminder() {
            const form = document.getElementById('reminderForm');
            const reminderTime = form.querySelector('[name="reminder_time"]').value;
            
            if (!reminderTime) {
                alert('Please select a reminder time');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'set_reminder');
            formData.append('task_id', <?php echo $task['id']; ?>);
            formData.append('reminder_time', reminderTime);

            fetch('task_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reminder set successfully!');
                    form.reset();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
