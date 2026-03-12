# Implementation Code Snippets

Copy-paste ready code to integrate the 4 changes into your existing dashboards.

---

## 1️⃣ Add Action Planner Navigation

**File**: `dashboard_advanced.php` (or any dashboard)  
**Location**: In the sidebar nav section (around line 60)

**Find this section**:
```html
<div class="nav-section">
    <div class="nav-section-title">MAIN</div>
    <div class="nav-item">
        <a href="dashboard_advanced.php" class="nav-link active">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
    </div>
```

**Add after "Leads" nav item**:
```html
<div class="nav-item">
    <a href="action_planner.php" class="nav-link">
        <i class="fas fa-calendar-check"></i>Action Planner
        <span class="nav-badge"><?php echo getActionPlannerStats($user_id)['total_actions']; ?></span>
    </a>
</div>
```

---

## 2️⃣ Add Import Leads Button

**File**: `user_dashboard.php` or `dashboard_advanced.php`  
**Location**: In the toolbar/action buttons area

**Add this button**:
```html
<!-- Add in the quick actions section or header -->
<button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
    <i class="fas fa-upload"></i> Import Leads
</button>
```

**Add at bottom of page** (before closing `</body>` tag):
```php
<?php include 'components/import_modal.php'; ?>
```

---

## 3️⃣ Update Currency Displays

**File**: `dashboard_advanced.php` (around line 330 in revenue chart)

**Find this**:
```javascript
callbacks: {
    label: function(context) {
        return 'Revenue: $' + context.parsed.y.toLocaleString();
    }
}
```

**Replace with**:
```javascript
callbacks: {
    label: function(context) {
        return 'Revenue: ₹' + context.parsed.y.toLocaleString();
    }
}
```

**Also update in PHP** (around line 200):
```php
// OLD
echo $lead['estimated_value']; // Shows: 5000

// NEW
echo formatCurrency($lead['estimated_value']); // Shows: ₹5,000.00
```

---

## 4️⃣ Add Duplicate Check to Forms

**File**: Any page with lead creation form (e.g., `leads_advanced.php`)  
**Location**: In the form submission handler

**Add this check before creating lead**:
```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Check for duplicates
    if ($email && checkEmailExists($email)) {
        $existing = getExistingLeadByEmailOrPhone($email, null);
        echo '<div class="alert alert-warning">';
        echo 'Email already exists: <a href="?id=' . $existing['id'] . '">' . $existing['name'] . '</a>';
        echo '</div>';
    } elseif ($phone && checkPhoneExists($phone)) {
        $existing = getExistingLeadByEmailOrPhone(null, $phone);
        echo '<div class="alert alert-warning">';
        echo 'Phone already exists: <a href="?id=' . $existing['id'] . '">' . $existing['name'] . '</a>';
        echo '</div>';
    } else {
        // Safe to create
        $lead_id = addLead($lead_data);
        echo '<div class="alert alert-success">Lead created successfully!</div>';
    }
}
?>
```

---

## 5️⃣ Display Action Planner Stats in Dashboard

**File**: `dashboard_advanced.php` or `user_dashboard.php`  
**Location**: In the stats cards section

**Add this card**:
```html
<?php
$actions = getActionPlannerStats($user_id);
?>

<div class="stat-card primary animate__animated animate__fadeInUp" style="animation-delay: 0.5s;">
    <div class="stat-header">
        <div>
            <div class="stat-title">Actions Today</div>
            <div class="stat-value"><?php echo $actions['total_actions']; ?></div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-right"></i>
                <span><?php echo count($actions['yet_to_call']); ?> to call</span>
            </div>
            <div class="stat-trend">
                <a href="action_planner.php" class="btn btn-sm btn-primary mt-2">View Planner</a>
            </div>
        </div>
        <div class="stat-icon primary float">
            <i class="fas fa-calendar-check"></i>
        </div>
    </div>
</div>
```

---

## 6️⃣ Add INR Formatting Throughout

### In Charts
```javascript
// OLD
scales: {
    y: {
        ticks: {
            callback: function(value) {
                return '$' + value.toLocaleString();
            }
        }
    }
}

// NEW
scales: {
    y: {
        ticks: {
            callback: function(value) {
                return '₹' + value.toLocaleString();
            }
        }
    }
}
```

### In PHP Output
```php
// OLD
echo "Total: $" . $total;

// NEW
echo "Total: " . formatCurrency($total);
```

### In Tables
```html
<!-- OLD -->
<td>$<?php echo $lead['estimated_value']; ?></td>

<!-- NEW -->
<td><?php echo formatCurrency($lead['estimated_value']); ?></td>
```

---

## 7️⃣ Complete Example - User Dashboard Quick Actions

**File**: `user_dashboard.php`  
**Add in quick actions section**:

```html
<!-- Existing quick actions -->
<div class="quick-actions">
    <div class="chart-header">
        <h3 class="chart-title">Quick Actions</h3>
    </div>
    <div class="action-grid">
        <!-- Action Planner Link -->
        <a href="action_planner.php" class="action-btn">
            <div class="action-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="action-title">Action Planner</div>
        </a>

        <!-- Import Leads -->
        <button class="action-btn" data-bs-toggle="modal" data-bs-target="#importLeadsModal" style="background: white; border: 2px solid #e2e8f0; color: #2d3748; cursor: pointer;">
            <div class="action-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); width: 60px; height: 60px;">
                <i class="fas fa-upload"></i>
            </div>
            <div class="action-title">Import Leads</div>
        </button>

        <!-- View All Leads -->
        <a href="leads_advanced.php" class="action-btn">
            <div class="action-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="action-title">View All Leads</div>
        </a>

        <!-- Export Data -->
        <a href="export.php" class="action-btn">
            <div class="action-icon">
                <i class="fas fa-download"></i>
            </div>
            <div class="action-title">Export Leads</div>
        </a>
    </div>
</div>

<!-- Import Modal -->
<?php include 'components/import_modal.php'; ?>
```

---

## 8️⃣ Statistics Widget - Show Action Planner Summary

**Add anywhere in your dashboard**:

```html
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">Actions Overview</h3>
            </div>
            <?php
            $actions = getActionPlannerStats($user_id);
            ?>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="stat-card warning" style="border: none; box-shadow: none;">
                        <h4><?php echo count($actions['yet_to_call']); ?></h4>
                        <p class="text-muted">Yet to Call</p>
                        <a href="action_planner.php" class="btn btn-sm btn-warning">Manage</a>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="stat-card info" style="border: none; box-shadow: none;">
                        <h4><?php echo count($actions['call_back']); ?></h4>
                        <p class="text-muted">Call Back</p>
                        <a href="action_planner.php" class="btn btn-sm btn-info">Manage</a>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="stat-card success" style="border: none; box-shadow: none;">
                        <h4><?php echo count($actions['walk_in']); ?></h4>
                        <p class="text-muted">Walk-In</p>
                        <a href="action_planner.php" class="btn btn-sm btn-success">Manage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 9️⃣ Leads Table with Actions & INR Currency

**In your leads table**:

```html
<table class="table table-hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Value</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($leads as $lead): ?>
        <tr>
            <td><?php echo htmlspecialchars($lead['name']); ?></td>
            <td><?php echo htmlspecialchars($lead['email']); ?></td>
            <td><?php echo htmlspecialchars($lead['phone']); ?></td>
            <!-- INR Currency Display -->
            <td><?php echo formatCurrency($lead['estimated_value'] ?? 0); ?></td>
            <td>
                <span class="badge bg-<?php echo $lead['status'] === 'hot' ? 'danger' : ($lead['status'] === 'warm' ? 'warning' : 'info'); ?>">
                    <?php echo ucfirst($lead['status']); ?>
                </span>
            </td>
            <td>
                <a href="leads_advanced.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-primary">View</a>
                <button onclick="markAction(<?php echo $lead['id']; ?>, 'yet_to_call')" class="btn btn-sm btn-warning">Call</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function markAction(leadId, action) {
    // This updates the action planner category
    const data = new FormData();
    data.append('lead_id', leadId);
    data.append('category', action);
    
    fetch('action_planner.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Lead marked as ' + action.replace('_', ' '));
            location.reload();
        }
    });
}
</script>
```

---

## 🔟 Complete Navigation Update

**File**: Any dashboard with sidebar  
**Replace entire nav section with**:

```html
<nav class="sidebar-nav">
    <div class="nav-section">
        <div class="nav-section-title">MAIN</div>
        <div class="nav-item">
            <a href="dashboard_advanced.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="leads_advanced.php" class="nav-link">
                <i class="fas fa-users"></i>Leads
                <span class="nav-badge"><?php echo count($leads); ?></span>
            </a>
        </div>
        <!-- NEW: Action Planner -->
        <div class="nav-item">
            <a href="action_planner.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>Action Planner
                <span class="nav-badge"><?php echo getActionPlannerStats($user_id)['total_actions']; ?></span>
            </a>
        </div>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">TOOLS</div>
        <div class="nav-item">
            <a href="export.php" class="nav-link">
                <i class="fas fa-download"></i>Export
            </a>
        </div>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">ACCOUNT</div>
        <div class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </div>
    </div>
</nav>
```

---

## Testing Checklist

- [ ] Action Planner link appears in navigation
- [ ] Action Planner page loads at `/action_planner.php`
- [ ] Import button appears on dashboard
- [ ] Import modal opens when clicking button
- [ ] Can select and upload CSV file
- [ ] Duplicates are detected and skipped
- [ ] Currency shows ₹ symbol instead of $
- [ ] Duplicate check prevents email duplicates
- [ ] All pages load without errors

---

**All snippets are copy-paste ready!** 🎉
