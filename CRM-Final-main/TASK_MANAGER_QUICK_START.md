# Task Manager - Quick Start Guide

## ⚡ 5-Minute Setup

### Step 1: Create Database Tables
```bash
# Option A: Via terminal
mysql -u root -p crm_pro < task_manager_schema.sql

# Option B: Via phpMyAdmin
# 1. Open phpMyAdmin (http://localhost/phpmyadmin)
# 2. Select "crm_pro" database
# 3. Click "SQL" tab
# 4. Copy-paste entire contents of task_manager_schema.sql
# 5. Click "Go"
```

### Step 2: Verify Files Exist
```
✅ /db.php (modified - new task functions added)
✅ /task_manager_schema.sql (schema file)
✅ /task_actions.php (backend API)
✅ /task_manager.php (dashboard)
✅ /task_view.php (detail view)
```

### Step 3: Update Navigation (Optional)
Add to your main dashboard/header:
```html
<a href="task_manager.php" class="nav-link">
    <i class="fas fa-tasks"></i> Tasks
</a>
```

### Step 4: Test It!

#### Test as SuperAdmin:
1. Log in as SuperAdmin
2. Navigate to Task Manager
3. Click "Create New Task"
4. Fill form and click "Create Task"
5. ✅ Task appears in dashboard

#### Test as Admin:
1. Log in as Admin
2. Navigate to Task Manager
3. See your assigned tasks
4. Click "View" on any task
5. Add a comment
6. Change status to "In Progress"
7. ✅ Task updates

#### Test as User:
1. Log in as User
2. Navigate to Task Manager
3. See only your assigned tasks
4. Click "View" on any task
5. Add a comment
6. Update status
7. ✅ Cannot create/delete (expected)

---

## 🎯 Common Tasks

### Create a Task (Admin/SuperAdmin)
```
1. Click "Create New Task" button
2. Enter:
   - Title: "Follow up with ABC Corp"
   - Description: "Call about proposal status"
   - Assign To: Select user from dropdown
   - Priority: Choose High/Medium/Low
   - Due Date: Pick date and time
3. Click "Create Task"
```

### View Task Details
```
1. Click "View" button on task card
2. See full description, dates, assignee
3. Scroll down for comments
4. Add new comment if needed
5. Click status buttons to update
```

### Update Task Status
**Option 1 (Quick):**
- On task card, click "Update"
- Type new status: pending | in_progress | completed | cancelled
- ✅ Status changes

**Option 2 (Detailed):**
- Click "View" to open task
- Click "Change Status" buttons on right panel
- ✅ Task updates instantly

### Add Comment to Task
```
1. View task details
2. Scroll to "Comments" section
3. Type comment in text area
4. Click "Post Comment"
5. Comment appears with your name and timestamp
```

### Set Task Reminder
```
1. View task details
2. Scroll to "Set Reminder" card on right
3. Click date/time field
4. Choose reminder time
5. Click "Set Reminder"
✅ You'll be reminded at that time
```

### Filter Tasks on Dashboard
```
1. Use filter form at top:
   - Search: Type task title
   - Status: Select pending/in_progress/completed
   - Priority: Select high/medium/low
   - Sort By: Choose due date/priority/created
2. Click "Filter"
3. ✅ Task list updates
```

### Edit Task Details
```
1. Click "Edit" button on task card
2. Change title/description/priority/due date
3. Click "Save Changes"
4. ✅ Task updated
```

### Delete Task (Creator/SuperAdmin Only)
```
1. Click "Delete" button (red X icon)
2. Confirm deletion
3. ✅ Task removed from system
```

### Assign/Reassign Task
```
1. SuperAdmin can reassign any task
2. Click "Edit" → "Assign To" dropdown
3. Select new user
4. Click "Save Changes"
5. ✅ Task reassigned with new assignee
```

---

## 📊 Dashboard Statistics

The Task Manager dashboard shows 6 cards:

| Card | Shows | Example |
|------|-------|---------|
| **Total Tasks** | Sum of all tasks visible to you | 24 |
| **Pending** | Tasks awaiting start | 8 |
| **In Progress** | Active tasks | 10 |
| **Completed** | Finished tasks | 5 |
| **Overdue** | Past due date, not completed | 1 |
| **Due Today** | Due at end of today | 3 |

---

## 🔐 Role Permissions Quick Reference

| Feature | SuperAdmin | Admin | User |
|---------|:----:|:--:|:--:|
| Create task | ✅ | ✅ | ❌ |
| View own tasks | ✅ | ✅ | ✅ |
| View all tasks | ✅ | ❌ | ❌ |
| Edit task | ✅ | Own only | ❌ |
| Delete task | ✅ | Own only | ❌ |
| Assign task | ✅ | Own only | ❌ |
| Add comment | ✅ | ✅ | ✅ |
| Update status | ✅ | ✅ | ✅ |
| Set reminder | ✅ | ✅ | ✅ |

---

## 🆘 Troubleshooting

### ❌ "No tasks found" on dashboard
**Cause:** No tasks assigned to you yet
**Solution:** As SuperAdmin, create a task and assign it to yourself

### ❌ "Create New Task" button missing
**Cause:** You're logged in as User role
**Solution:** Only Admin and SuperAdmin can create tasks (by design)

### ❌ "Access Denied" when viewing task
**Cause:** You don't have permission to view this task
**Solution:** 
- SuperAdmin can view all
- Admin can view own + team tasks
- User can view only own assigned tasks

### ❌ Comment not posting
**Cause:** JavaScript error or network issue
**Solution:**
1. Check browser console (F12 → Console tab)
2. Verify task_actions.php file exists
3. Try refreshing page and trying again

### ❌ Database error on first access
**Cause:** Tables not created yet
**Solution:** Run task_manager_schema.sql (see Step 1 above)

### ❌ "Edit" button greyed out
**Cause:** You don't have permission
**Solution:**
- Only creator and SuperAdmin can edit tasks
- Ask task creator or SuperAdmin to edit

---

## 🚀 Tips & Tricks

### Pro Tip 1: Link to Entities
When creating a task, set "Related To" to Link to Lead/Contact/Company/Deal:
```
Create task for "Follow up with ABC Corp"
→ Related To: Lead
→ Related ID: 123
→ Task now shows "Lead #123" in details
```

### Pro Tip 2: Use Priorities Effectively
- 🔴 **High:** Urgent, client-facing, revenue-critical
- 🟡 **Medium:** Standard tasks, internal, important
- 🟢 **Low:** Nice-to-have, can defer, supporting

### Pro Tip 3: Filter for Your Workflow
Save common filters:
```
Due Today:
- Status: pending
- Priority: (any)
→ See what you need to finish today

Overdue:
- Status: pending + in_progress
- Sort: Due Date (ASC)
→ Catch falling-behind tasks

Team Progress:
- Status: in_progress
- Sort: Priority
→ See active work by priority
```

### Pro Tip 4: Batch Operations
SuperAdmin can use filters to:
1. Filter to Show all "pending" tasks
2. Reassign multiple by opening each → Edit → Assign
3. Or delete old completed tasks in bulk

### Pro Tip 5: Use Due Dates Wisely
```
Today: Due TODAY at HH:MM
Yesterday: OVERDUE badge (red)
Next week: Future date (normal)
```

---

## 📱 Mobile/Responsive

Task Manager is fully responsive:
- Works on desktop browsers
- Tablet friendly (responsive layout)
- Mobile friendly (stacked layout)
- Touch-friendly buttons and inputs

---

## 🔄 Workflow Examples

### Sales Workflow
```
1. SuperAdmin creates task: "Call ABC Corp"
2. Assigns to Sales Rep (User)
3. Rep updates status to "In Progress" when calling
4. Rep adds comment: "Discussed proposal, waiting for feedback"
5. Rep marks "Completed" when done
6. SuperAdmin sees in dashboard as complete
```

### Support Workflow
```
1. Admin creates task: "Resolve customer issue #456"
2. Assigns to Support Team Member (User)
3. Member adds comment with troubleshooting steps
4. Member marks "In Progress"
5. Another member adds comment with solution
6. Member marks "Completed"
```

### Project Workflow
```
1. SuperAdmin creates linked tasks:
   - "Scope Review" → Related to Deal #1
   - "Contract Negotiation" → Related to Deal #1
   - "Implementation" → Related to Deal #1
2. Assigns to different team members
3. Each member updates their task progress
4. SuperAdmin views dashboard to track overall project health
```

---

## 📞 Need Help?

1. **Check Permissions:** Review role permissions table above
2. **Check Logs:** Look at PHP error logs in `/Applications/XAMPP/logs/`
3. **Test Database:** Run simple query: `SELECT COUNT(*) FROM tasks;`
4. **Verify Files:** Confirm all 5 files exist in CRM directory
5. **Check Syntax:** Run: `/Applications/XAMPP/bin/php -l task_manager.php`

---

## ✅ Implementation Checklist

- [ ] Run task_manager_schema.sql to create database tables
- [ ] Verify 5 files exist (db.php, task_actions.php, task_manager.php, task_view.php, task_manager_schema.sql)
- [ ] Log in as SuperAdmin
- [ ] Create test task
- [ ] Assign to Admin user
- [ ] Log in as Admin, view task
- [ ] Add comment as Admin
- [ ] Change status as Admin
- [ ] Log in as User, view assigned task
- [ ] Try to create task (should fail - not permitted)
- [ ] Add comment as User
- [ ] Update status as User
- [ ] Log back as SuperAdmin, verify all activity visible
- [ ] Test filters and sorting
- [ ] Test set reminder functionality
- [ ] ✅ All working!

---

**Ready to use! Start creating tasks now! 🎉**
