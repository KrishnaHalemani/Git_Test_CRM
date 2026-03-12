# Task Manager API Reference

**Endpoint:** `/task_actions.php`  
**Protocol:** HTTP POST/GET  
**Content-Type:** JSON  
**Authentication:** Session-based (requires `$_SESSION['user_id']` and `$_SESSION['role']`)  
**Response:** JSON with `success` boolean and optional `message`, `task_id`, `task`, or `tasks` fields

---

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Action completed successfully",
    "task_id": 123,
    "task": { ... },
    "tasks": [ ... ],
    "stats": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description here"
}
```

---

## HTTP Status Codes

| Code | Meaning | When |
|------|---------|------|
| 200 | OK | Action successful |
| 400 | Bad Request | Missing required fields |
| 401 | Unauthorized | Not logged in |
| 403 | Forbidden | No permission for action |
| 404 | Not Found | Task doesn't exist |
| 500 | Server Error | Database error |

---

## API Actions

### 1. Create Task

**Method:** POST  
**Action:** `create_task`  
**Permission:** Admin, SuperAdmin only

**Required Fields:**
- `title` (string) - Task title, max 255 chars
- `assigned_to` (integer) - User ID to assign to

**Optional Fields:**
- `description` (string) - Task details
- `priority` (string) - 'low', 'medium', 'high' (default: 'medium')
- `due_date` (datetime) - 'YYYY-MM-DD HH:MM:SS'
- `related_type` (string) - 'lead', 'contact', 'company', 'deal', 'general' (default: 'general')
- `related_id` (integer) - ID of related entity

**Request:**
```php
// Via form
$_POST['action'] = 'create_task';
$_POST['title'] = 'Follow up with ABC Corp';
$_POST['description'] = 'Call about proposal status';
$_POST['assigned_to'] = 2;
$_POST['priority'] = 'high';
$_POST['due_date'] = '2025-12-15 14:00:00';
$_POST['related_type'] = 'lead';
$_POST['related_id'] = 123;

// JavaScript fetch
fetch('task_actions.php', {
    method: 'POST',
    body: new FormData(document.getElementById('createForm'))
});
```

**Response:**
```json
{
    "success": true,
    "message": "Task created successfully",
    "task_id": 456
}
```

**Error Cases:**
- `400` - Missing title or assigned_to
- `403` - User role is 'user' (insufficient permission)

---

### 2. Update Task

**Method:** POST  
**Action:** `update_task`  
**Permission:** Creator, Assigned user, or SuperAdmin

**Required Fields:**
- `task_id` (integer) - Task to update

**Optional Fields:**
- `title` (string) - New title
- `description` (string) - New description
- `priority` (string) - 'low', 'medium', 'high'
- `due_date` (datetime) - New due date
- `start_date` (datetime) - When work started

**Request:**
```php
$_POST['action'] = 'update_task';
$_POST['task_id'] = 456;
$_POST['title'] = 'Updated: Follow up with ABC Corp';
$_POST['priority'] = 'medium';
```

**Response:**
```json
{
    "success": true,
    "message": "Task updated successfully"
}
```

**Error Cases:**
- `400` - Missing task_id
- `403` - No permission to update
- `404` - Task not found

---

### 3. Update Task Status

**Method:** POST  
**Action:** `update_status`  
**Permission:** Creator, Assigned user, or SuperAdmin

**Required Fields:**
- `task_id` (integer) - Task to update
- `status` (string) - 'pending', 'in_progress', 'completed', 'cancelled'

**Request:**
```php
$_POST['action'] = 'update_status';
$_POST['task_id'] = 456;
$_POST['status'] = 'in_progress';
```

**Response:**
```json
{
    "success": true,
    "message": "Task status updated successfully"
}
```

**Notes:**
- Setting status to 'completed' automatically sets `completed_at` timestamp
- Other statuses do not auto-set any timestamps

**Error Cases:**
- `400` - Missing task_id or status, or invalid status
- `403` - No permission
- `404` - Task not found

---

### 4. Assign Task

**Method:** POST  
**Action:** `assign_task`  
**Permission:** Task Creator or SuperAdmin only

**Required Fields:**
- `task_id` (integer) - Task to reassign
- `assigned_to` (integer) - User ID to assign to

**Request:**
```php
$_POST['action'] = 'assign_task';
$_POST['task_id'] = 456;
$_POST['assigned_to'] = 3;
```

**Response:**
```json
{
    "success": true,
    "message": "Task reassigned successfully"
}
```

**Error Cases:**
- `400` - Missing task_id or assigned_to
- `400` - assigned_to user doesn't exist
- `403` - Only creator can reassign (unless SuperAdmin)
- `404` - Task not found

---

### 5. Delete Task

**Method:** POST  
**Action:** `delete_task`  
**Permission:** Task Creator or SuperAdmin only

**Required Fields:**
- `task_id` (integer) - Task to delete

**Request:**
```php
$_POST['action'] = 'delete_task';
$_POST['task_id'] = 456;
```

**Response:**
```json
{
    "success": true,
    "message": "Task deleted successfully"
}
```

**Notes:**
- Cascading deletion removes: comments, reminders, activity logs
- Non-reversible operation

**Error Cases:**
- `400` - Missing task_id
- `403` - Only creator can delete (unless SuperAdmin)
- `404` - Task not found

---

### 6. Add Comment

**Method:** POST  
**Action:** `add_comment`  
**Permission:** Any authenticated user (with access to task)

**Required Fields:**
- `task_id` (integer) - Task to comment on
- `comment` (string) - Comment text

**Request:**
```php
$_POST['action'] = 'add_comment';
$_POST['task_id'] = 456;
$_POST['comment'] = 'Just spoke with client, they approved the proposal!';
```

**Response:**
```json
{
    "success": true,
    "message": "Comment added successfully",
    "comment_id": 789
}
```

**Notes:**
- HTML is escaped on storage (XSS-safe)
- Timestamps auto-set to current time
- User automatically set from session

**Error Cases:**
- `400` - Missing task_id or comment
- `403` - No access to task
- `404` - Task not found

---

### 7. Set Reminder

**Method:** POST  
**Action:** `set_reminder`  
**Permission:** Any authenticated user

**Required Fields:**
- `task_id` (integer) - Task to remind about
- `reminder_time` (datetime) - When to remind (YYYY-MM-DD HH:MM:SS)

**Optional Fields:**
- `reminder_type` (string) - 'custom', 'due_today', 'overdue', 'daily_summary' (default: 'custom')

**Request:**
```php
$_POST['action'] = 'set_reminder';
$_POST['task_id'] = 456;
$_POST['reminder_time'] = '2025-12-15 13:00:00';
$_POST['reminder_type'] = 'custom';
```

**Response:**
```json
{
    "success": true,
    "message": "Reminder set successfully",
    "reminder_id": 101
}
```

**Notes:**
- Reminder is sent to current user only
- Use `getPendingReminders()` in scheduled script to process
- Set `is_sent = true` after sending

**Error Cases:**
- `400` - Missing task_id or reminder_time
- `404` - Task not found

---

### 8. Get Task Details

**Method:** GET or POST  
**Action:** `get_task`  
**Permission:** Creator, Assigned user, or SuperAdmin

**Required Fields:**
- `task_id` (integer) - Task to retrieve

**Request:**
```php
// GET request
$url = 'task_actions.php?action=get_task&task_id=456';

// POST request
$_POST['action'] = 'get_task';
$_POST['task_id'] = 456;
```

**Response:**
```json
{
    "success": true,
    "task": {
        "id": 456,
        "title": "Follow up with ABC Corp",
        "description": "Call about proposal status",
        "status": "pending",
        "priority": "high",
        "created_by": 1,
        "created_by_name": "admin",
        "assigned_to": 2,
        "assigned_to_name": "john",
        "due_date": "2025-12-15 14:00:00",
        "start_date": null,
        "completed_at": null,
        "related_type": "lead",
        "related_id": 123,
        "created_at": "2025-12-02 10:30:00",
        "updated_at": "2025-12-02 10:30:00",
        "comments": [
            {
                "id": 789,
                "comment": "Client approved!",
                "username": "john",
                "email": "john@example.com",
                "created_at": "2025-12-02 11:00:00"
            }
        ]
    }
}
```

**Error Cases:**
- `400` - Missing task_id
- `403` - No permission to view task
- `404` - Task not found

---

### 9. Get Task List

**Method:** GET  
**Action:** `get_tasks`  
**Permission:** Any authenticated user (role-filtered)

**Optional Query Parameters:**
- `status` - Filter by 'pending', 'in_progress', 'completed', 'cancelled'
- `priority` - Filter by 'low', 'medium', 'high'
- `assigned_to` - Filter by assignee user_id
- `search` - Search in title and description
- `sort_by` - Sort by 'due_date', 'priority', 'created_at', 'status'
- `sort_order` - 'ASC' or 'DESC' (default: 'ASC')
- `limit` - Results per page (default: 50)
- `offset` - Pagination offset (default: 0)

**Request:**
```php
// Fetch all pending tasks sorted by due date
$url = 'task_actions.php?action=get_tasks&status=pending&sort_by=due_date&limit=20';

// Fetch tasks assigned to user 3, high priority
$url = 'task_actions.php?action=get_tasks&assigned_to=3&priority=high';

// Search for 'client' in tasks
$url = 'task_actions.php?action=get_tasks&search=client';
```

**Response:**
```json
{
    "success": true,
    "tasks": [
        {
            "id": 456,
            "title": "Follow up with ABC Corp",
            "status": "pending",
            "priority": "high",
            "assigned_to": 2,
            "assigned_to_name": "john",
            "created_by": 1,
            "created_by_name": "admin",
            "due_date": "2025-12-15 14:00:00",
            "created_at": "2025-12-02 10:30:00"
        }
    ],
    "stats": {
        "total": 24,
        "pending": 8,
        "in_progress": 10,
        "completed": 5,
        "overdue": 1,
        "due_today": 3,
        "assigned_by_priority": {
            "high": 5,
            "medium": 10,
            "low": 9
        }
    }
}
```

**Role-Based Filtering:**
- **SuperAdmin:** See all tasks
- **Admin:** See own tasks + tasks assigned to their team
- **User:** See only tasks assigned to them

---

## Error Codes & Messages

| HTTP | Error | When |
|------|-------|------|
| 400 | Bad Request | Missing required fields |
| 401 | Unauthorized | Not logged in (no session) |
| 403 | Forbidden | Insufficient permissions for action |
| 404 | Not Found | Task, user, or resource doesn't exist |
| 500 | Server Error | Database error or uncaught exception |

**Example Error Response:**
```json
{
    "success": false,
    "message": "Only task creator or SuperAdmin can assign tasks"
}
```

---

## JavaScript Fetch Examples

### Create Task
```javascript
async function createTask(title, assignedTo, priority) {
    const formData = new FormData();
    formData.append('action', 'create_task');
    formData.append('title', title);
    formData.append('assigned_to', assignedTo);
    formData.append('priority', priority);
    
    const response = await fetch('task_actions.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        console.log('Task created:', data.task_id);
    } else {
        console.error('Error:', data.message);
    }
}
```

### Update Status
```javascript
async function updateTaskStatus(taskId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    
    const response = await fetch('task_actions.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    return data.success;
}
```

### Get Tasks List
```javascript
async function getTasksList(filters = {}) {
    const params = new URLSearchParams({
        action: 'get_tasks',
        ...filters
    });
    
    const response = await fetch(`task_actions.php?${params}`);
    const data = await response.json();
    return data.tasks || [];
}
```

### Add Comment
```javascript
async function addComment(taskId, commentText) {
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('task_id', taskId);
    formData.append('comment', commentText);
    
    const response = await fetch('task_actions.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    return data.success;
}
```

---

## Role-Based Access Control

| Role | Create | Read | Update | Delete | Assign | Comment |
|------|:------:|:----:|:------:|:------:|:------:|:-------:|
| SuperAdmin | ✅ All | ✅ All | ✅ All | ✅ All | ✅ Any | ✅ All |
| Admin | ✅ Own | ✅ Team | ✅ Own | ✅ Own | ✅ Own | ✅ All |
| User | ❌ | ✅ Assigned | ❌ | ❌ | ❌ | ✅ Own |

---

## Rate Limiting

Not implemented in current version. For production, consider:
```php
// Add to task_actions.php after session start
$rate_limit_key = "task_api_" . $_SESSION['user_id'];
$cache = apcu_fetch($rate_limit_key) ?? 0;

if ($cache > 100) { // 100 requests per minute
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
    exit;
}

apcu_store($rate_limit_key, $cache + 1, 60);
```

---

## Best Practices

1. **Always check response.success** before processing data
2. **Handle error messages gracefully** - show to user or log
3. **Use appropriate HTTP methods** - POST for mutations, GET for reads
4. **Include error handling** in fetch promises
5. **Validate user input** on client side before sending
6. **Use session authentication** - don't pass credentials in URL
7. **Implement proper error UI** - show loading states, spinners
8. **Test with all 3 roles** - SuperAdmin, Admin, User
9. **Check browser console** - JavaScript errors logged there
10. **Monitor server logs** - PHP errors in XAMPP error logs

---

## Database Functions Directly Available (in PHP)

Instead of using API, can call functions directly:
```php
<?php
require_once 'db.php';

// Create task
$task_id = createTask('Title', 'Description', 1, 2, '2025-12-15', 'high');

// Get task
$task = getTaskById($task_id);

// Get filtered list
$tasks = getTasksByRole(1, 'admin', ['status' => 'pending']);

// Update status
updateTaskStatus($task_id, 'completed');

// Add comment
addTaskComment($task_id, 1, 'Great work!');

// Get stats
$stats = getTaskStats(1, 'admin');
?>
```

---

**API Documentation Complete**  
Last Updated: December 2025  
Version: 1.0
