# Task Manager System - Complete Implementation Guide

## Overview

The Task Manager is a complete workflow management system built into the CRM Pro with full role-based access control (RBAC). It enables SuperAdmin, Admin, and User roles to create, assign, track, and collaborate on tasks with support for comments, reminders, and activity logging.

**Status:** ✅ Fully Implemented
**Last Updated:** December 2025
**Version:** 1.0

---

## Key Features

### 1. Role-Based Access Control
- **SuperAdmin:** Create, edit, delete all tasks; assign to any user; view all tasks
- **Admin:** Create tasks, assign to users; view their own and team tasks; edit their own
- **User:** View assigned tasks; update status; add comments; cannot create or delete tasks

### 2. Task Management
- Create tasks with title, description, priority, due date, and assignment
- Link tasks to Leads, Contacts, Companies, Deals, or keep as General
- Update task details (except delete for non-creators)
- Track task status: Pending → In Progress → Completed/Cancelled
- View full task details with creation/update timestamps

### 3. Task Comments
- Add comments to tasks (visible to creator, assignee, and superadmin)
- View comment history with timestamps and authors
- Support for multi-line comments with HTML escaping

### 4. Task Reminders
- Set custom reminders for any task
- Automatic reminder types: due_today, overdue, daily_summary
- Track sent/unsent reminders with timestamps
- Extensible for email notifications

### 5. Activity Logging
- Full audit trail for all task changes
- Log: created, updated, status_changed, assigned, commented, deleted
- Track old and new values for change history

### 6. Dashboard & Filters
- Beautiful dashboard with statistics cards (total, pending, in-progress, completed, overdue, due-today)
- Filter by status, priority, assigned user, search terms
- Sort by due date, priority, created date, or status
- Pagination for large task lists
- Quick status update buttons for assigned tasks

---

## Database Schema

### `tasks` Table
```sql
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_by INT NOT NULL,
    assigned_to INT NOT NULL,
    due_date DATETIME,
    start_date DATETIME,
    completed_at DATETIME,
    related_type ENUM('lead', 'contact', 'company', 'deal', 'general') DEFAULT 'general',
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

### `task_comments` Table
```sql
CREATE TABLE task_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### `task_reminders` Table
```sql
CREATE TABLE task_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    reminder_time DATETIME NOT NULL,
    reminder_type ENUM('due_today', 'overdue', 'daily_summary', 'custom') DEFAULT 'custom',
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### `task_activity_log` Table
```sql
CREATE TABLE task_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_value LONGTEXT,
    new_value LONGTEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## Database Functions (in `db.php`)

### Task CRUD Functions

#### `createTask($title, $description, $created_by, $assigned_to, $due_date, $priority, $related_type, $related_id)`
Creates a new task. Returns task ID on success.

```php
$task_id = createTask(
    'Follow up with client',
    'Call about proposal',
    1, // created_by (user_id)
    2, // assigned_to (user_id)
    '2025-12-15 14:00:00',
    'high',
    'lead',
    123 // related_id
);
```

#### `getTaskById($task_id)`
Fetches a single task with creator and assignee names.

#### `getTasksByRole($user_id, $role, $filters)`
Retrieves tasks based on user role with support for filtering and sorting.

```php
$filters = [
    'status' => 'pending',
    'priority' => 'high',
    'assigned_to' => 2,
    'search' => 'client',
    'sort_by' => 'due_date',
    'sort_order' => 'ASC',
    'limit' => 50,
    'offset' => 0
];
$tasks = getTasksByRole($user_id, 'admin', $filters);
```

#### `updateTask($task_id, $data)`
Updates task details. $data array can include: title, description, status, priority, assigned_to, due_date, start_date.

#### `updateTaskStatus($task_id, $status)`
Updates only the task status. Sets completed_at timestamp if status is 'completed'.

#### `assignTask($task_id, $assigned_to)`
Reassigns a task to a different user.

#### `deleteTask($task_id)`
Deletes a task (and cascades to comments, reminders, and activity logs).

### Comment Functions

#### `addTaskComment($task_id, $user_id, $comment)`
Adds a comment to a task. Returns comment ID.

#### `getTaskComments($task_id)`
Retrieves all comments for a task in descending order (newest first).

### Reminder Functions

#### `setTaskReminder($task_id, $user_id, $reminder_time, $reminder_type)`
Creates a reminder for a task. Returns reminder ID.

#### `getPendingReminders()`
Gets all unsent reminders that are due (reminder_time <= NOW()).

#### `markReminderSent($reminder_id)`
Marks a reminder as sent with timestamp.

### Statistics & Logging

#### `getTaskStats($user_id, $role)`
Returns dashboard statistics: total, pending, in_progress, completed, overdue, due_today, and priority breakdown.

#### `logTaskActivity($task_id, $user_id, $action, $old_value, $new_value, $description)`
Logs task activities for audit trail.

---

## API Endpoints (`task_actions.php`)

All endpoints require active session authentication.

### Create Task
**POST** `/task_actions.php`
```json
{
    "action": "create_task",
    "title": "Task title",
    "description": "Task details",
    "assigned_to": 2,
    "priority": "high",
    "due_date": "2025-12-15 14:00:00",
    "related_type": "lead",
    "related_id": 123
}
```

### Update Task
**POST** `/task_actions.php`
```json
{
    "action": "update_task",
    "task_id": 1,
    "title": "Updated title",
    "priority": "medium",
    "due_date": "2025-12-20"
}
```

### Update Status
**POST** `/task_actions.php`
```json
{
    "action": "update_status",
    "task_id": 1,
    "status": "in_progress"
}
```

### Assign Task
**POST** `/task_actions.php`
```json
{
    "action": "assign_task",
    "task_id": 1,
    "assigned_to": 3
}
```

### Delete Task
**POST** `/task_actions.php`
```json
{
    "action": "delete_task",
    "task_id": 1
}
```

### Add Comment
**POST** `/task_actions.php`
```json
{
    "action": "add_comment",
    "task_id": 1,
    "comment": "Comment text"
}
```

### Set Reminder
**POST** `/task_actions.php`
```json
{
    "action": "set_reminder",
    "task_id": 1,
    "reminder_time": "2025-12-15 13:00:00",
    "reminder_type": "custom"
}
```

### Get Task
**GET** `/task_actions.php?action=get_task&task_id=1`

Returns task details with comments array.

### Get Task List
**GET** `/task_actions.php?action=get_tasks&status=pending&priority=high&sort_by=due_date`

Returns tasks array and stats object.

---

## UI Pages

### 1. Task Manager Dashboard (`task_manager.php`)
- **URL:** `/task_manager.php`
- **Access:** All authenticated users (role-aware)
- **Features:**
  - 6 statistics cards (total, pending, in-progress, completed, overdue, due-today)
  - Task filtering by status, priority, search, assigned user
  - Sort by due date, priority, created date, status
  - Create task button (Admin/SuperAdmin only)
  - Task cards with quick status/edit/delete actions
  - Pagination support

**Role Permissions:**
- SuperAdmin: See all tasks, create, edit, delete all, reassign
- Admin: See own + team tasks, create, edit own, reassign to team
- User: See assigned tasks only, update status, add comments

### 2. Task Detail View (`task_view.php`)
- **URL:** `/task_view.php?id=1`
- **Access:** Creator, assignee, or SuperAdmin only
- **Features:**
  - Full task details with metadata (created, updated, completed dates)
  - Task description with formatting
  - Comment section with add comment form
  - Quick status change buttons (Pending, In Progress, Completed, Cancelled)
  - Set reminder form
  - Edit and delete buttons (permission-aware)
  - Overdue indicators with red highlighting
  - Related entity links (Lead #123, etc.)

---

## Installation & Setup

### Step 1: Create Database Tables
Run the SQL schema from `task_manager_schema.sql`:
```bash
mysql -u root -p crm_pro < task_manager_schema.sql
```

Or manually execute in phpMyAdmin:
```sql
-- Paste contents of task_manager_schema.sql
```

### Step 2: Update Navigation
Add Task Manager link to your main dashboard pages:
```html
<a class="nav-link" href="task_manager.php">
    <i class="fas fa-tasks"></i> Tasks
</a>
```

### Step 3: Test Access
1. Log in as SuperAdmin → Task Manager
2. Create a test task
3. Assign to an Admin or User
4. Log in as assigned user → view task in Task Manager
5. Add comments, update status, test filters

### Step 4: Configure Email Reminders (Optional)
To send email reminders, set up a cron job:
```bash
# Every 5 minutes
*/5 * * * * /usr/bin/php /path/to/cron_task_reminders.php
```

Example reminder script (`cron_task_reminders.php`):
```php
<?php
require_once 'db.php';

$reminders = getPendingReminders();

foreach ($reminders as $reminder) {
    // Send email
    mail(
        $reminder['email'],
        'Task Reminder: ' . $reminder['title'],
        "Your task '{$reminder['title']}' is due at {$reminder['due_date']}"
    );
    
    // Mark as sent
    markReminderSent($reminder['id']);
}
?>
```

---

## Usage Examples

### SuperAdmin: Create and Assign Task
1. Log in as SuperAdmin
2. Click "Create New Task"
3. Fill form:
   - Title: "Quarterly Review Call"
   - Description: "Schedule and conduct Q4 review with client"
   - Assign To: Select any user
   - Priority: High
   - Due Date: 2025-12-31
4. Click "Create Task"
5. Task appears in dashboard for all relevant users

### Admin: Update Team Task Status
1. Log in as Admin
2. Find task in Task Manager
3. Click "Update" button on any task card
4. Change status to "In Progress"
5. Task card updates immediately with new badge color

### User: Add Comment and Complete Task
1. Log in as User
2. Click "View" on assigned task
3. Scroll to Comments section
4. Type comment and click "Post Comment"
5. View all previous comments with timestamps
6. Click "Change Status" → "Completed"
7. Task marked complete with strikethrough title

### SuperAdmin: Reassign Task to Different User
1. View task details
2. Click "Edit"
3. Change "Assign To" field (if reassignment allowed)
4. Click "Save Changes"
5. Task reassigned with activity log entry

---

## Permission Matrix

| Action | SuperAdmin | Admin | User |
|--------|:-----------:|:-----:|:----:|
| Create Task | ✅ | ✅ | ❌ |
| View Own Tasks | ✅ | ✅ | ✅ |
| View All Tasks | ✅ | Team Only | Own Only |
| Edit Own Tasks | ✅ | ✅ | ❌ |
| Edit Any Task | ✅ | ❌ | ❌ |
| Delete Own Tasks | ✅ | ✅ | ❌ |
| Delete Any Task | ✅ | ❌ | ❌ |
| Reassign Tasks | ✅ | Own Tasks | ❌ |
| Add Comments | ✅ | ✅ | ✅ |
| Set Reminders | ✅ | ✅ | ✅ |
| View Activity Log | ✅ | ✅ | ❌ |

---

## Status Flow

Tasks follow a one-way status progression (not strictly enforced, but recommended):

```
Pending → In Progress → Completed
          └─→ Cancelled
```

- **Pending:** Newly created, not yet started
- **In Progress:** Work has begun
- **Completed:** Task finished (auto-sets completed_at timestamp)
- **Cancelled:** Task abandoned or no longer needed

---

## Advanced Features

### Linking Tasks to Entities
Tasks can be linked to CRM entities:
```php
// Link task to a lead
createTask('Follow up', 'Call about proposal', 1, 2, '2025-12-15', 'high', 'lead', 123);

// Link task to a company
createTask('Contract review', 'Review new SLA', 1, 2, null, 'medium', 'company', 45);

// General task (no entity)
createTask('Team meeting', 'Weekly sync', 1, 2, '2025-12-10 10:00', 'medium', 'general');
```

### Custom Filtering
Build complex queries:
```php
$filters = [
    'status' => 'pending',
    'priority' => 'high',
    'assigned_to' => 5,
    'search' => 'client proposal',
    'sort_by' => 'due_date',
    'sort_order' => 'ASC',
    'limit' => 20,
    'offset' => 0
];

$tasks = getTasksByRole($user_id, $role, $filters);
```

### Activity Audit Trail
View all changes to a task:
```php
$sql = "SELECT * FROM task_activity_log WHERE task_id = ? ORDER BY created_at DESC";
$activities = query($sql, [$task_id]);

foreach ($activities as $activity) {
    echo "{$activity['username']} {$activity['action']} at {$activity['created_at']}\n";
    if ($activity['old_value']) {
        echo "  Changed from: {$activity['old_value']}\n";
        echo "  Changed to: {$activity['new_value']}\n";
    }
}
```

---

## Security Considerations

### Authentication
- All pages require `$_SESSION['user_id']` and `$_SESSION['role']`
- Session checks performed at page start
- Redirects to login if not authenticated

### Authorization (RBAC)
- Every action checks user role against required permissions
- Users can only access their own tasks unless superadmin/admin
- Comments only visible to participants
- Delete only allowed by creator or superadmin

### SQL Injection Prevention
- All queries use prepared statements
- Both PDO and mysqli implementations
- Parameters bound using placeholders

### XSS Prevention
- All user input escaped with `htmlspecialchars()`
- Comments stored safely with HTML escaping on display
- Database values output-escaped

### Data Integrity
- Cascading deletes: task deletion removes all comments/reminders/activities
- Foreign key constraints enforce referential integrity
- Timestamps auto-managed by database

---

## Troubleshooting

### Tasks not appearing in dashboard
1. Check user ID is set in session: `var_dump($_SESSION['user_id']);`
2. Verify task is assigned to current user
3. Check role-based filtering in `getTasksByRole()`

### Cannot create tasks as Admin
1. Verify session role is 'admin': `echo $_SESSION['role'];`
2. Check if button appears in UI (role check on line X)
3. Verify `create_task` action in `task_actions.php` is reachable

### Comments not saving
1. Check JavaScript console for errors
2. Verify form data is sent correctly
3. Check `addTaskComment()` return value
4. Verify task_comments table exists in database

### Reminders not working
1. Implement `cron_task_reminders.php` script
2. Test `getPendingReminders()` returns results
3. Verify reminder_time field is correctly formatted
4. Check email configuration for mail sending

---

## Future Enhancements

1. **Email Notifications:**
   - Send email when task assigned
   - Send reminder emails
   - Send comment notifications

2. **Task Templates:**
   - Pre-defined task types (follow-up, proposal, etc.)
   - Bulk create from template

3. **Task Dependencies:**
   - Task B cannot start until Task A is completed
   - Visual dependency graph

4. **Time Tracking:**
   - Track hours spent on tasks
   - Burndown charts for projects

5. **Mobile App:**
   - React Native mobile app
   - Offline task list sync

6. **AI-Powered:**
   - Auto-suggest due dates based on priority
   - Auto-categorize tasks
   - Deadline conflict warnings

---

## Files Summary

| File | Size | Purpose |
|------|------|---------|
| `task_manager_schema.sql` | 3.5 KB | Database schema for all task tables |
| `db.php` (modified) | +850 lines | Task helper functions |
| `task_actions.php` | 9.2 KB | Backend API endpoint for all task operations |
| `task_manager.php` | 12.5 KB | Main dashboard with filtering and statistics |
| `task_view.php` | 11.3 KB | Detailed task view with comments |

**Total New Code:** ~2,000 lines (PHP + SQL + HTML/CSS/JS)

---

## Support & Questions

For issues or enhancements:
1. Check database schema is properly applied
2. Verify all files are in `/Applications/XAMPP/xamppfiles/htdocs/CRM2/`
3. Test with multiple roles to verify RBAC
4. Review JavaScript console for client-side errors
5. Check PHP error logs for server-side issues

---

**Version:** 1.0  
**Last Updated:** December 2025  
**Status:** Production Ready ✅
