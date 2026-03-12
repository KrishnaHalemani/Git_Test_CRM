<?php
// Task Manager Backend Handler
// Handles all task CRUD operations with role-based access control

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        // ============== CREATE TASK ==============
        case 'create_task':
            // SuperAdmin and Admin can create tasks
            if ($role !== 'superadmin' && $role !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to create tasks']);
                exit;
            }
            
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            $due_date = $_POST['due_date'] ?? null;
            $priority = $_POST['priority'] ?? 'medium';
            $related_type = $_POST['related_type'] ?? 'general';
            $related_id = (int)($_POST['related_id'] ?? 0) ?: null;
            
            if (!$title || !$assigned_to) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Title and assigned user are required']);
                exit;
            }
            
            // Validate priority
            $valid_priorities = ['low', 'medium', 'high'];
            if (!in_array($priority, $valid_priorities)) {
                $priority = 'medium';
            }
            
            // Validate related type
            $valid_types = ['lead', 'contact', 'company', 'deal', 'general'];
            if (!in_array($related_type, $valid_types)) {
                $related_type = 'general';
            }
            
            $task_id = createTask($title, $description, $user_id, $assigned_to, $due_date, $priority, $related_type, $related_id);
            
            if ($task_id) {
                // Log activity
                logTaskActivity($task_id, $user_id, 'created', null, null, "Task created by " . $_SESSION['username']);
                echo json_encode(['success' => true, 'message' => 'Task created successfully', 'task_id' => $task_id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create task']);
            }
            break;

        // ============== UPDATE TASK ==============
        case 'update_task':
            $task_id = (int)($_POST['task_id'] ?? 0);
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID is required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Check permission: only creator, assigned user, or superadmin can update
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id && $task['assigned_to'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task']);
                exit;
            }
            
            $update_data = [];
            if (isset($_POST['title'])) {
                $update_data['title'] = trim($_POST['title']);
            }
            if (isset($_POST['description'])) {
                $update_data['description'] = trim($_POST['description']);
            }
            if (isset($_POST['priority'])) {
                $priority = $_POST['priority'];
                if (in_array($priority, ['low', 'medium', 'high'])) {
                    $update_data['priority'] = $priority;
                }
            }
            if (isset($_POST['due_date'])) {
                $update_data['due_date'] = $_POST['due_date'];
            }
            if (isset($_POST['start_date'])) {
                $update_data['start_date'] = $_POST['start_date'];
            }
            
            if (!empty($update_data)) {
                if (updateTask($task_id, $update_data)) {
                    logTaskActivity($task_id, $user_id, 'updated', null, null, "Task updated by " . $_SESSION['username']);
                    echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update task']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'No changes made']);
            }
            break;

        // ============== UPDATE TASK STATUS ==============
        case 'update_status':
            $task_id = (int)($_POST['task_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            if (!$task_id || !$status) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID and status are required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Check permission: only creator, assigned user, or superadmin can update status
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id && $task['assigned_to'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task']);
                exit;
            }
            
            $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($status, $valid_statuses)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            if (updateTaskStatus($task_id, $status)) {
                logTaskActivity($task_id, $user_id, 'status_changed', $task['status'], $status, "Status changed to $status by " . $_SESSION['username']);
                echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            break;

        // ============== ASSIGN TASK ==============
        case 'assign_task':
            $task_id = (int)($_POST['task_id'] ?? 0);
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            
            if (!$task_id || !$assigned_to) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID and assigned user are required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Only creator or superadmin can reassign tasks
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only task creator or SuperAdmin can assign tasks']);
                exit;
            }
            
            // Verify assigned user exists
            $assigned_user = authenticateUser($assigned_to, ''); // Just check if user exists
            if (!$assigned_user) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Assigned user does not exist']);
                exit;
            }
            
            if (assignTask($task_id, $assigned_to)) {
                logTaskActivity($task_id, $user_id, 'assigned', $task['assigned_to'], $assigned_to, "Reassigned by " . $_SESSION['username']);
                echo json_encode(['success' => true, 'message' => 'Task reassigned successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to assign task']);
            }
            break;

        // ============== DELETE TASK ==============
        case 'delete_task':
            $task_id = (int)($_POST['task_id'] ?? 0);
            
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID is required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Only creator or superadmin can delete tasks
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this task']);
                exit;
            }
            
            if (deleteTask($task_id)) {
                logTaskActivity($task_id, $user_id, 'deleted', null, null, "Task deleted by " . $_SESSION['username']);
                echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
            }
            break;

        // ============== ADD COMMENT ==============
        case 'add_comment':
            $task_id = (int)($_POST['task_id'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            
            if (!$task_id || !$comment) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID and comment are required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Check if user has access to task
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id && $task['assigned_to'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to comment on this task']);
                exit;
            }
            
            $comment_id = addTaskComment($task_id, $user_id, $comment);
            if ($comment_id) {
                logTaskActivity($task_id, $user_id, 'commented', null, null, "Comment added by " . $_SESSION['username']);
                echo json_encode(['success' => true, 'message' => 'Comment added successfully', 'comment_id' => $comment_id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
            }
            break;

        // ============== SET REMINDER ==============
        case 'set_reminder':
            $task_id = (int)($_POST['task_id'] ?? 0);
            $reminder_time = $_POST['reminder_time'] ?? null;
            $reminder_type = $_POST['reminder_type'] ?? 'custom';
            
            if (!$task_id || !$reminder_time) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID and reminder time are required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            $reminder_id = setTaskReminder($task_id, $user_id, $reminder_time, $reminder_type);
            if ($reminder_id) {
                echo json_encode(['success' => true, 'message' => 'Reminder set successfully', 'reminder_id' => $reminder_id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to set reminder']);
            }
            break;

        // ============== GET TASK ==============
        case 'get_task':
            $task_id = (int)($_POST['task_id'] ?? $_GET['task_id'] ?? 0);
            
            if (!$task_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID is required']);
                exit;
            }
            
            $task = getTaskById($task_id);
            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Check access
            if ($role !== 'superadmin' && $task['created_by'] !== $user_id && $task['assigned_to'] !== $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to view this task']);
                exit;
            }
            
            $task['comments'] = getTaskComments($task_id);
            echo json_encode(['success' => true, 'task' => $task]);
            break;

        // ============== GET TASK LIST ==============
        case 'get_tasks':
            $filters = [
                'status' => $_GET['status'] ?? '',
                'priority' => $_GET['priority'] ?? '',
                'assigned_to' => $_GET['assigned_to'] ?? '',
                'search' => $_GET['search'] ?? '',
                'sort_by' => $_GET['sort_by'] ?? 'due_date',
                'sort_order' => $_GET['sort_order'] ?? 'ASC',
                'limit' => (int)($_GET['limit'] ?? 50),
                'offset' => (int)($_GET['offset'] ?? 0)
            ];
            
            $tasks = getTasksByRole($user_id, $role, $filters);
            $stats = getTaskStats($user_id, $role);
            
            echo json_encode(['success' => true, 'tasks' => $tasks, 'stats' => $stats]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Log task activity for audit trail
 */
function logTaskActivity($task_id, $user_id, $action, $old_value = null, $new_value = null, $description = null) {
    global $conn, $db_type;
    
    // 1. Log to System Activity (Global Audit) - Prioritize this
    if (function_exists('logSystemActivity')) {
        logSystemActivity($user_id, 'task_' . $action, $description . " (Task ID: $task_id)");
    }

    try {
        if ($db_type === 'pdo') {
            $sql = "INSERT INTO task_activity_log (task_id, user_id, action, old_value, new_value, description)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([$task_id, $user_id, $action, $old_value, $new_value, $description]);
        } else {
            $sql = "INSERT INTO task_activity_log (task_id, user_id, action, old_value, new_value, description)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) return false;
            $stmt->bind_param("iissss", $task_id, $user_id, $action, $old_value, $new_value, $description);
            return $stmt->execute();
        }
    } catch (Throwable $e) {
        return false;
    }
}
?>
