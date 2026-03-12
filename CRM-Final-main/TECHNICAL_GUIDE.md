# Technical Implementation Details - Developers Guide

## 1. CRUD Operations - Code Architecture

### Database Structure
```sql
-- Users table with new branch field
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user',
    branch VARCHAR(100) DEFAULT 'Head Office',  -- ⭐ NEW FIELD
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);
```

### PHP Implementation - Superadmin Dashboard

#### CREATE Operation
```php
if($_POST['action'] === 'create_admin' || $_POST['action'] === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $branch = trim($_POST['branch'] ?? 'Head Office');
    $role = $_POST['action'] === 'create_admin' ? 'admin' : 'user';
    
    if($username && $email && $password && $full_name) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        if($db_type === 'pdo') {
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, full_name, password_hash, role, branch, status) 
                 VALUES (?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([$username, $email, $full_name, $password_hash, $role, $branch]);
            $message = ucfirst($role) . " created successfully!";
        }
    }
}
```

#### UPDATE Operation
```php
elseif($_POST['action'] === 'update_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $branch = trim($_POST['branch'] ?? 'Head Office');
    $status = trim($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';
    
    if($user_id && $full_name && $email) {
        if($db_type === 'pdo') {
            if($password) {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, branch = ?, status = ?, 
                     password_hash = ? WHERE id = ?"
                );
                $stmt->execute([$full_name, $email, $branch, $status, $password_hash, $user_id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, branch = ?, status = ? WHERE id = ?"
                );
                $stmt->execute([$full_name, $email, $branch, $status, $user_id]);
            }
            $message = "User updated successfully!";
        }
    }
}
```

#### DELETE Operation
```php
elseif($_POST['action'] === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if($user_id && $user_id != $_SESSION['user_id']) { // Self-delete protection
        if($db_type === 'pdo') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = "User deleted successfully!";
        }
    } else {
        $error = "Cannot delete your own account!";
    }
}
```

### JavaScript Functions

```javascript
// Edit User - Populate Modal with Data
function editUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editFullName').value = user.full_name || user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editBranch').value = user.branch || 'Head Office';
    document.getElementById('editStatus').value = user.status || 'active';
}

// Delete User - Show Confirmation Modal
function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    
    const deleteModal = new bootstrap.Modal(
        document.getElementById('deleteConfirmModal')
    );
    deleteModal.show();
}
```

### HTML Modals

#### Create Admin Modal
```html
<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Create New Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_admin">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch *</label>
                        <input type="text" class="form-control" name="branch" 
                               placeholder="e.g., Head Office, Mumbai Branch" 
                               value="Head Office" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

#### Edit User Modal
```html
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch *</label>
                        <input type="text" class="form-control" id="editBranch" name="branch" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-control" id="editStatus" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <small>(leave blank to keep current)</small></label>
                        <input type="password" class="form-control" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

#### Delete Confirmation Modal
```html
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

---

## 2. Walk-in Count Tracking - Implementation

### Database Schema Update
```sql
ALTER TABLE leads ADD COLUMN walk_in BOOLEAN DEFAULT FALSE AFTER source;
```

### Query Logic - Analytics Dashboard

```php
// Get real walk-in data from leads table
$dailyWalkins = [];
for($i = 14; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Count walk-in leads created on this date
    if($db_type === 'pdo') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as count FROM leads 
             WHERE DATE(created_at) = ? AND (source = 'walk-in' OR walk_in = TRUE)"
        );
        
        if($role === 'user') {
            // User only sees their own leads
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) as count FROM leads 
                 WHERE DATE(created_at) = ? AND created_by = ? 
                 AND (source = 'walk-in' OR walk_in = TRUE)"
            );
            $stmt->execute([$date, $_SESSION['user_id']]);
        } else {
            $stmt->execute([$date]);
        }
        
        $result = $stmt->fetch();
        $dailyWalkins[] = (int)$result['count'];
    } else {
        // mysqli implementation
        if($role === 'user') {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) as count FROM leads 
                 WHERE DATE(created_at) = ? AND created_by = ? 
                 AND (source = 'walk-in' OR walk_in = TRUE)"
            );
            $stmt->bind_param("si", $date, $_SESSION['user_id']);
        } else {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) as count FROM leads 
                 WHERE DATE(created_at) = ? AND (source = 'walk-in' OR walk_in = TRUE)"
            );
            $stmt->bind_param("s", $date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $dailyWalkins[] = (int)$result['count'];
    }
}
```

### Chart.js Integration

```javascript
new Chart(document.getElementById('dailyWalkinsChart'), {
    type: 'bar',
    data: {
        labels: dayLabels,  // Last 15 days
        datasets: [{
            label: 'Walk-ins',
            data: dailyWalkins,  // Real data from database
            backgroundColor: 'rgba(102, 51, 153, 0.3)',
            borderColor: '#663399',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
```

### Role-Based Filtering Explanation

| Role | Query Filter | Behavior |
|------|--------------|----------|
| superadmin | No user filter | Sees all organization walk-ins |
| admin | No user filter | Sees all team walk-ins |
| user | WHERE created_by = user_id | Sees only personal walk-ins |

---

## 3. Branch Field Integration - Database

### Migration SQL
```sql
-- Add branch field to users table
ALTER TABLE users 
ADD COLUMN branch VARCHAR(100) DEFAULT 'Head Office' AFTER role;

-- Add index for better query performance (optional)
ALTER TABLE users 
ADD INDEX idx_branch (branch);
```

### Verification Query
```sql
-- Check if branch field exists
SHOW COLUMNS FROM users LIKE 'branch';

-- Display current branches
SELECT DISTINCT branch FROM users;

-- Count users by branch
SELECT branch, COUNT(*) as user_count FROM users GROUP BY branch;
```

---

## 4. Security Considerations

### Input Validation
```php
// All user inputs trimmed and validated
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$branch = trim($_POST['branch'] ?? 'Head Office');

// Required field check
if(!$username || !$email || !$password || !$full_name) {
    $error = "All fields are required!";
}
```

### Password Hashing
```php
// Using bcrypt with cost factor 10
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Verification during login (in db.php)
if(password_verify($input_password, $hash)) {
    // Password correct
}
```

### SQL Injection Protection
```php
// Using prepared statements with bound parameters
$stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash, role, branch, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'active')");
$stmt->execute([$username, $email, $full_name, $password_hash, $role, $branch]);

// mysqli version
$stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password_hash, role, branch, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'active')");
$stmt->bind_param("ssssss", $username, $email, $full_name, $password_hash, $role, $branch);
$stmt->execute();
```

### Self-Delete Protection
```php
// Prevent deleting your own account
if($user_id && $user_id != $_SESSION['user_id']) {
    // Allow delete
} else {
    $error = "Cannot delete your own account!";
}
```

### Role-Based Access Control
```php
// Only superadmin can access superadmin_dashboard.php
require_role(['superadmin']);

// Definition in db.php
function require_role($allowed_roles) {
    if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: login.php?error=Unauthorized");
        exit();
    }
}
```

---

## 5. Error Handling

### Try-Catch for PDO
```php
if($db_type === 'pdo') {
    try {
        $stmt = $pdo->prepare("INSERT INTO users ...");
        $stmt->execute([...]);
        $message = "Success message";
    } catch(Exception $e) {
        $error = "Error creating user: " . $e->getMessage();
    }
}
```

### Error Output
```php
// Display error or success message
if($error) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
}
if($message) {
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
}
```

---

## 6. Performance Optimization

### Database Indexes
```sql
-- Existing indexes in users table
INDEX idx_username (username)
INDEX idx_email (email)
INDEX idx_role (role)

-- Optional: Add branch index for filtering
ALTER TABLE users ADD INDEX idx_branch (branch);

-- For walk-in queries
ALTER TABLE leads ADD INDEX idx_source (source);
ALTER TABLE leads ADD INDEX idx_created_at (created_at);
ALTER TABLE leads ADD INDEX idx_created_by (created_by);
```

### Query Optimization
```php
// Walk-in query uses DATE() function with indexed created_at
// This is efficient for date-range queries
WHERE DATE(created_at) = ?  // Searches indexed created_at column

// Combined conditions for flexibility
AND (source = 'walk-in' OR walk_in = TRUE)  // Checks both sources
```

---

## 7. Testing Scenarios

### Unit Test Cases

```php
// Test 1: Create user with all fields
$_POST = [
    'action' => 'create_user',
    'username' => 'testuser',
    'email' => 'test@example.com',
    'full_name' => 'Test User',
    'password' => 'SecurePass123',
    'branch' => 'Mumbai Branch'
];
// Expected: User created with branch

// Test 2: Update user status
$_POST = [
    'action' => 'update_user',
    'user_id' => 5,
    'full_name' => 'Updated Name',
    'email' => 'updated@example.com',
    'branch' => 'Delhi Branch',
    'status' => 'inactive',
    'password' => ''  // Keep current password
];
// Expected: User updated, status changed to inactive

// Test 3: Delete user
$_POST = [
    'action' => 'delete_user',
    'user_id' => 5
];
$_SESSION['user_id'] = 1;  // Different user
// Expected: User deleted successfully

// Test 4: Prevent self-delete
$_POST = [
    'action' => 'delete_user',
    'user_id' => 1  // Same as current user
];
$_SESSION['user_id'] = 1;
// Expected: Error - "Cannot delete your own account!"
```

---

## 8. Compatibility Notes

### Database Drivers
- ✅ PDO (Recommended)
- ✅ mysqli (Fallback)
- Both implemented with automatic selection in `db.php`

### PHP Versions
- ✅ PHP 7.4+
- ✅ PHP 8.0+
- ✅ PHP 8.1+

### Browsers
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

---

## 9. Deployment Checklist

```sql
-- 1. Run database migrations
ALTER TABLE users ADD COLUMN branch VARCHAR(100) DEFAULT 'Head Office' AFTER role;
ALTER TABLE leads ADD COLUMN walk_in BOOLEAN DEFAULT FALSE AFTER source;

-- 2. Create indexes (optional but recommended)
ALTER TABLE users ADD INDEX idx_branch (branch);
ALTER TABLE leads ADD INDEX idx_source (source);

-- 3. Verify changes
SHOW COLUMNS FROM users;
SHOW COLUMNS FROM leads;
```

```
-- 4. Update application files
[ ] Upload superadmin_dashboard.php
[ ] Upload analytics_dashboard.php
[ ] Upload leads_advanced.php
[ ] Backup existing files first

-- 5. Testing
[ ] Create test admin account
[ ] Create test user account
[ ] Edit user details including branch
[ ] Delete test user
[ ] Check walk-in chart shows real data
[ ] Verify all roles can access appropriate features

-- 6. Monitoring
[ ] Check error logs for any issues
[ ] Monitor database performance
[ ] Verify walk-in tracking accuracy
[ ] Test role-based access control
```

---

## 10. Troubleshooting Guide

### Issue: Branch field not showing in forms
```
Solution: Clear browser cache (Ctrl+F5)
Check: superadmin_dashboard.php has branch input field
Verify: HTML form includes <input name="branch">
```

### Issue: Walk-in chart shows zero data
```
Cause: No leads with source='walk-in' exist
Solution: Create test lead with source='walk-in'
Check: Query: SELECT * FROM leads WHERE source='walk-in'
```

### Issue: User cannot be deleted
```
Cause: Attempting to delete self
Solution: Delete different user account
Check: $_SESSION['user_id'] != user_id_to_delete
```

### Issue: Database connection fails
```
Solution: Verify credentials in db.php
Check: host, username, password, database name
Test: Try connecting via MySQL client
```

---

**Document Version:** 1.0
**Last Updated:** 2024
**Status:** Complete & Production Ready

