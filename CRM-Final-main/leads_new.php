<?php
ob_start();
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$campaigns = getCampaigns();
$all_users = ($role === 'admin' || $role === 'superadmin') ? getAllUsers() : [];

// Filtering and Pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'campaign_id' => $_GET['campaign_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

if (empty($filters['campaign_id']) && !empty($campaigns)) {
    $filters['campaign_id'] = $campaigns[0]['id'];
}

$leads_data = getLeadsAdvanced($user_id, $role, $filters);
$leads = $leads_data; // For simplicity, not implementing pagination in this scratch build

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Management - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .table-responsive { background-color: white; padding: 1.5rem; border-radius: 0.5rem; }
        .form-control, .form-select { font-size: 0.9rem; }
        .status-dropdown {
            border: none;
            background: transparent;
            font-weight: bold;
            padding: 0.375rem 0.75rem;
            border-radius: 50rem;
        }
        .status-new { background-color: #e0e7ff; color: #4338ca; }
        .status-contacted { background-color: #e0f2fe; color: #0369a1; }
        .status-qualified { background-color: #dcfce7; color: #15803d; }
        .status-proposal_sent { background-color: #fef3c7; color: #d97706; }
        .status-converted { background-color: #d1fae5; color: #047857; }
        .status-lost { background-color: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Lead Management</h1>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="fas fa-plus"></i> Add Lead</button>
            <a href="lead_import_upload.php" class="btn btn-success"><i class="fas fa-file-import"></i> Import Leads</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-body mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..." value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Campaign</label>
                <select name="campaign_id" class="form-select">
                    <?php foreach($campaigns as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filters['campaign_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="new" <?php if($filters['status'] == 'new') echo 'selected'; ?>>New</option>
                    <option value="contacted" <?php if($filters['status'] == 'contacted') echo 'selected'; ?>>Contacted</option>
                    <option value="qualified" <?php if($filters['status'] == 'qualified') echo 'selected'; ?>>Qualified</option>
                    <option value="proposal_sent" <?php if($filters['status'] == 'proposal_sent') echo 'selected'; ?>>Proposal Sent</option>
                    <option value="converted" <?php if($filters['status'] == 'converted') echo 'selected'; ?>>Converted</option>
                    <option value="lost" <?php if($filters['status'] == 'lost') echo 'selected'; ?>>Lost</option>
                </select>
            </div>
            <?php if($role !== 'user'): ?>
            <div class="col-md-2">
                <label class="form-label">Assigned User</label>
                <select name="assigned_to" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($filters['assigned_to'] == $u['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <button type="submit" class="btn btn-info">Filter</button>
                <a href="leads_new.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Lead Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Latest Campaign</th>
                    <th>Total Campaigns</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr><td colspan="8" class="text-center">No leads found.</td></tr>
                <?php else: ?>
                    <?php foreach($leads as $lead): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($lead['email']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($lead['phone']); ?></small>
                            </td>
                            <td>
                                <select class="form-select form-select-sm status-dropdown status-<?php echo $lead['status']; ?>" onchange="updateLeadStatus(<?php echo $lead['id']; ?>, this.value)">
                                    <option value="new" <?php if($lead['status'] == 'new') echo 'selected'; ?>>New</option>
                                    <option value="contacted" <?php if($lead['status'] == 'contacted') echo 'selected'; ?>>Contacted</option>
                                    <option value="qualified" <?php if($lead['status'] == 'qualified') echo 'selected'; ?>>Qualified</option>
                                    <option value="proposal_sent" <?php if($lead['status'] == 'proposal_sent') echo 'selected'; ?>>Proposal Sent</option>
                                    <option value="converted" <?php if($lead['status'] == 'converted') echo 'selected'; ?>>Converted</option>
                                    <option value="lost" <?php if($lead['status'] == 'lost') echo 'selected'; ?>>Lost</option>
                                </select>
                            </td>
                            <td><?php echo htmlspecialchars($lead['assigned_to_name'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($lead['latest_campaign'] ?? 'N/A'); ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo $lead['campaign_count']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" onclick='editLead(<?php echo json_encode($lead); ?>)'><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteLead(<?php echo $lead['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Lead Modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addLeadForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_lead">
                    <div class="row">
                        <div class="col-md-6 mb-3"><input type="text" name="name" class="form-control" placeholder="Full Name" required></div>
                        <div class="col-md-6 mb-3"><input type="email" name="email" class="form-control" placeholder="Email Address" required></div>
                        <div class="col-md-6 mb-3"><input type="text" name="phone" class="form-control" placeholder="Phone Number"></div>
                        <?php if($role !== 'user'): ?>
                        <div class="col-md-6 mb-3">
                            <select name="assigned_to" class="form-select">
                                <option value="">Assign to...</option>
                                <?php foreach($all_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editLeadForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_lead">
                    <input type="hidden" name="lead_id" id="edit_lead_id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><input type="text" name="name" id="edit_name" class="form-control" placeholder="Full Name" required></div>
                        <div class="col-md-6 mb-3"><input type="email" name="email" id="edit_email" class="form-control" placeholder="Email Address" required></div>
                        <div class="col-md-6 mb-3"><input type="text" name="phone" id="edit_phone" class="form-control" placeholder="Phone Number"></div>
                        <?php if($role !== 'user'): ?>
                        <div class="col-md-6 mb-3">
                            <select name="assigned_to" id="edit_assigned_to" class="form-select">
                                <option value="">Assign to...</option>
                                <?php foreach($all_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function handleFormSubmit(formId, url, successCallback) {
    document.getElementById(formId).addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (successCallback) successCallback(data);
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'An unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('A network error occurred.');
        });
    });
}

handleFormSubmit('addLeadForm', 'lead_actions.php');
handleFormSubmit('editLeadForm', 'lead_actions.php');

function updateLeadStatus(leadId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('lead_id', leadId);
    formData.append('status', newStatus);

    fetch('lead_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Visually update the dropdown color without full reload
            const dropdown = event.target;
            dropdown.className = 'form-select form-select-sm status-dropdown status-' + newStatus;
        } else {
            alert('Failed to update status: ' + data.message);
            location.reload(); // Revert on failure
        }
    });
}

function editLead(lead) {
    document.getElementById('edit_lead_id').value = lead.id;
    document.getElementById('edit_name').value = lead.name;
    document.getElementById('edit_email').value = lead.email;
    document.getElementById('edit_phone').value = lead.phone;
    if (document.getElementById('edit_assigned_to')) {
        document.getElementById('edit_assigned_to').value = lead.assigned_to;
    }
    const editModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
    editModal.show();
}

function deleteLead(leadId) {
    if (!confirm('Are you sure you want to delete this lead?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_lead');
    formData.append('lead_id', leadId);

    fetch('lead_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to delete lead: ' + data.message);
        }
    });
}
</script>

</body>
</html>