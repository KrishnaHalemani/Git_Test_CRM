<?php
ob_start();
session_start();
require_once 'db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Helper for safe JSON encoding in HTML attributes
if (!function_exists('safe_json_lead')) {
    function safe_json_lead($lead) {
        if (!is_array($lead)) return '{}';
        return htmlspecialchars(json_encode($lead, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_custom_field_display_value')) {
    function get_custom_field_display_value($lead, $field, $lead_custom_data_map) {
        $lead_id = (int)($lead['id'] ?? 0);
        $field_key = strtolower(trim((string)($field['field_key'] ?? '')));

        if ($lead_id > 0 && isset($lead_custom_data_map[$lead_id][$field_key]) && $lead_custom_data_map[$lead_id][$field_key] !== '') {
            return $lead_custom_data_map[$lead_id][$field_key];
        }

        // Fallback: show standard lead values for campaign fields like name/email/number.
        $fallback_map = [
            'name' => 'name',
            'full_name' => 'name',
            'fullname' => 'name',
            'email' => 'email',
            'email_address' => 'email',
            'phone' => 'phone',
            'number' => 'phone',
            'mobile' => 'phone',
            'mobile_number' => 'phone',
            'phone_number' => 'phone'
        ];

        if (isset($fallback_map[$field_key])) {
            $standard_key = $fallback_map[$field_key];
            $value = $lead[$standard_key] ?? '';
            if ($value !== '') {
                return $value;
            }
        }

        return '-';
    }
}

// Get campaigns for filter
$campaigns = getCampaigns();
$all_users = ($role === 'admin' || $role === 'superadmin') ? getAllUsers() : [];

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'campaign_id' => $_GET['campaign_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get leads using advanced query
$leads = getLeadsAdvanced($user_id, $role, $filters);

// Fetch custom field data if a campaign is selected
$campaign_custom_fields = [];
$lead_custom_data_map = [];

if (!empty($filters['campaign_id'])) {
    $campaign_custom_fields = getCampaignFields($filters['campaign_id']);
    
    if (!empty($leads) && !empty($campaign_custom_fields)) {
        $lead_ids = array_column($leads, 'id');
        // Sanitize IDs
        $lead_ids = array_map('intval', $lead_ids);
        
        if (!empty($lead_ids)) {
            // Use prepared statements for the IN clause for security and scalability
            $in_placeholders = implode(',', array_fill(0, count($lead_ids), '?'));

            $sql = "SELECT lcd.lead_id, cf.field_key, lcd.value
                    FROM lead_custom_data lcd 
                    JOIN campaign_fields cf ON lcd.campaign_field_id = cf.id 
                    WHERE lcd.lead_id IN ($in_placeholders) AND cf.campaign_id = ?";
            
            // Combine lead IDs and campaign ID for parameters
            $params = $lead_ids;
            $params[] = (int)$filters['campaign_id'];
            
            try {
                if (isset($db_type) && $db_type === 'pdo') {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $lead_custom_data_map[$row['lead_id']][$row['field_key']] = $row['value'];
                    }
                } else {
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        // Build types string: 'i' for each lead_id, plus one 'i' for campaign_id
                        $types = str_repeat('i', count($lead_ids)) . 'i';
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $lead_custom_data_map[$row['lead_id']][$row['field_key']] = $row['value'];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Management - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-new { background: #e0e7ff; color: #4338ca; }
        .status-contacted { background: #e0f2fe; color: #0369a1; }
        .status-qualified { background: #dcfce7; color: #15803d; }
        .status-hot { background: #fee2e2; color: #b91c1c; }
        .status-proposal_sent { background: #fef3c7; color: #d97706; }
        .status-converted { background: #d1fae5; color: #047857; }
        .status-lost { background: #f3f4f6; color: #4b5563; }
        
        .priority-low { background: rgba(67, 233, 123, 0.2); color: #059669; }
        .priority-medium { background: rgba(250, 112, 154, 0.2); color: #d97706; }
        .priority-high { background: rgba(239, 68, 68, 0.2); color: #dc2626; }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            background: #f3f4f6;
            color: #6b7280;
        }
        .btn-action:hover { background: #e5e7eb; color: #4f46e5; }
        .btn-delete:hover { background: #fee2e2; color: #ef4444; }
        
    </style>
</head>
<body>
<div class="container-fluid my-4">
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <a href="campaign_manager.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <h1 class="h2 mb-0">Lead Management</h1>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="fas fa-plus"></i> Add Lead</button>
            <a href="campaign_import_upload.php?campaign_id=<?php echo $filters['campaign_id']; ?>" class="btn btn-success"><i class="fas fa-file-import"></i> Import Leads</a>
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
                <select class="form-select" style="max-width: 150px;" id="campaignFilter" name="campaign_id" onchange="applyFilters()">
                    <option value="">All Campaigns</option>
                    <?php foreach($campaigns as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filters['campaign_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" style="max-width: 150px;" id="statusFilter" name="status" onchange="applyFilters()">
                    <option value="">All Status</option>
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
                <select class="form-select" style="max-width: 150px;" id="userFilter" name="assigned_to" onchange="applyFilters()">
                    <option value="">All Users</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($filters['assigned_to'] == $u['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <button type="submit" class="btn btn-info">Filter</button>
                <a href="leads_advanced.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Lead Table -->
    <div id="dynamicLeadsContainer">
        <!-- Server-rendered table is kept as fallback; JS will replace this when a campaign is selected -->
        <div class="table-responsive">
            <div class="table-responsive">
                <table class="table" id="leadsTable">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Lead Info</th>
                            <th>Contact</th>
                            <th>Company</th>
                            <?php if (!empty($campaign_custom_fields)): ?>
                                <?php foreach ($campaign_custom_fields as $field): ?>
                                    <th><?php echo htmlspecialchars($field['field_name']); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <th>Campaigns</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Source</th>
                            <th>Value</th>
                            <th>Created</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leads as $lead): 
                            // Attach custom data for JS usage (Edit Modal)
                            if (isset($lead_custom_data_map[$lead['id']])) {
                                $lead['custom_data'] = $lead_custom_data_map[$lead['id']];
                            }
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input lead-checkbox" value="<?php echo $lead['id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        <?php echo strtoupper(substr($lead['name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($lead['name'] ?? ''); ?></div>
                                        <small class="text-muted">ID: <?php echo $lead['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($lead['email'] ?? ''); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($lead['phone'] ?? ''); ?>
                                        <?php if (!empty($lead['phone'])): 
                                            $wa_phone = preg_replace('/[^0-9]/', '', $lead['phone']);
                                            if (!empty($wa_phone)): ?>
                                            <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" class="text-success ms-1 text-decoration-none" title="Chat on WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; endif; ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($lead['company'] ?? 'N/A'); ?></span>
                            </td>
                            <?php if (!empty($campaign_custom_fields)): ?>
                                <?php foreach ($campaign_custom_fields as $field): ?>
                                    <td>
                                        <?php echo htmlspecialchars(get_custom_field_display_value($lead, $field, $lead_custom_data_map)); ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="badge bg-info text-dark mb-1"><?php echo htmlspecialchars($lead['latest_campaign'] ?? 'None'); ?></span>
                                    <small class="text-muted">Total: <?php echo $lead['campaign_count']; ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $lead['status'] ?? 'new'; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($lead['status'] ?? 'new'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-badge priority-<?php echo $lead['priority'] ?? 'medium'; ?>">
                                    <?php echo ucfirst($lead['priority'] ?? 'medium'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?php echo ucfirst(str_replace('-', ' ', $lead['source'] ?? 'manual')); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-success"><?php echo formatLeadValue($lead['estimated_value'] ?? 0, $lead['service'] ?? ''); ?></span>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-semibold"><?php echo date('M j, Y', strtotime($lead['created_at'] ?? 'now')); ?></div>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($lead['created_at'] ?? 'now')); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-view" onclick="viewLead(<?php echo safe_json_lead($lead); ?>)" title="View Details">
                                        <i class="fas fa-eye text-info"></i>
                                    </button>
                                    <button class="btn-action btn-edit" onclick="editLead(<?php echo safe_json_lead($lead); ?>)" title="Edit Lead">
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteLead('<?php echo $lead['id']; ?>')" title="Delete Lead">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

            <!-- Kanban Board View (Hidden by default) -->
            <div id="kanbanView" style="display:none;" class="animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                <h3 class="mb-3">Lead Pipeline - Kanban View</h3>
                <div class="row" style="gap:1rem;">
                    <?php 
                    $statuses = ['new', 'contacted', 'qualified', 'proposal_sent', 'converted', 'lost'];
                    $statusLabels = ['New', 'Contacted', 'Qualified', 'Proposal Sent', 'Converted', 'Lost'];
                    $statusColors = ['#667eea', '#a8edea', '#43e97b', '#fa709a', '#4facfe', '#71809e'];
                    
                    foreach($statuses as $idx => $status):
                        $statusLeads = array_filter($leads, function($l) use ($status) {
                            return ($l['status'] ?? 'new') === $status;
                        });
                    ?>
                    <div class="col-md-2" style="min-width:280px;">
                        <div style="background:#f8f9fa;border-radius:12px;padding:1rem;border:2px solid <?php echo $statusColors[$idx]; ?>20;min-height:600px;">
                            <h6 class="fw-bold mb-3" style="color:<?php echo $statusColors[$idx]; ?>;">
                                <?php echo $statusLabels[$idx]; ?> (<?php echo count($statusLeads); ?>)
                            </h6>
                            <div style="display:flex;flex-direction:column;gap:0.75rem;" class="kanban-column" data-status="<?php echo $status; ?>">
                                <?php foreach($statusLeads as $lead): ?>
                                <div class="card p-3 kanban-card" data-lead-id="<?php echo $lead['id']; ?>" draggable="true" style="cursor:move;border-left:4px solid <?php echo $statusColors[$idx]; ?>;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($lead['name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($lead['email']); ?></div>
                                    <div class="my-2">
                                        <span class="badge bg-secondary small"><?php echo htmlspecialchars($lead['service'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?php echo formatLeadValue($lead['estimated_value'] ?? 0, $lead['service'] ?? ''); ?></small>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="#" onclick="viewLead(<?php echo safe_json_lead($lead); ?>); return false;"><i class="fas fa-eye me-2"></i>View</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="editLead(<?php echo safe_json_lead($lead); ?>); return false;"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#reassignModal" onclick="prepareReassign(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['name']); ?>')"><i class="fas fa-share me-2"></i>Reassign</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteLead(<?php echo $lead['id']; ?>); return false;"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
    </div> <!-- End Main Container -->
    
    <?php include 'components/import_modal.php'; ?>

        <!-- Advanced Add Lead Modal -->
    <div class="modal fade" id="addLeadModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Lead</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addLeadForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Lead Type</label>
                                <select class="form-select" name="lead_type" id="add_lead_type">
                                    <option value="service" selected>Service</option>
                                    <option value="course">Course</option>
                                    <option value="franchise">Franchise</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Campaign</label>
                                <select class="form-select" name="campaign_id" id="add_campaign_id" onchange="loadCampaignFields(this.value, 'add_campaign_fields_container')">
                                    <option value="">-- None --</option>
                                    <?php foreach($campaigns as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Assign To</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="<?php echo $_SESSION['user_id']; ?>">Me (<?php echo htmlspecialchars($_SESSION['username']); ?>)</option>
                                    <?php foreach($all_users as $u): ?>
                                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <option value="<?php echo $u['id']; ?>">
                                            <?php echo htmlspecialchars($u['username'] . ' (' . ucfirst($u['role']) . ')'); ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Full Name *</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Email Address *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Company</label>
                                            <input type="text" class="form-control" name="company">
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Campaign Fields -->
                                <div id="add_campaign_fields_container" class="row mb-3"></div>

                                <div class="row lead-fields lead-service">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Service Interest</label>
                                            <select class="form-select" name="service">
                                                <option value="">Select Service</option>
                                                <option value="web-development">Web Development</option>
                                                <option value="mobile-app">Mobile App Development</option>
                                                <option value="digital-marketing">Digital Marketing</option>
                                                <option value="consulting">Business Consulting</option>
                                                <option value="e-commerce">E-commerce Solutions</option>
                                                <option value="ui-ux-design">UI/UX Design</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Estimated Value (INR)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="estimated_value" min="0" step="0.01">
                                                </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Course specific fields -->
                                <div class="row lead-fields d-none" id="add_course_fields">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Are you a</label>
                                            <input type="text" class="form-control" name="are_you_a">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Objective to join</label>
                                            <input type="text" class="form-control" name="objective">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Whatsapp Number</label>
                                            <input type="text" class="form-control" name="whatsapp_number">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Street Address</label>
                                            <input type="text" class="form-control" name="street_address">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Post Code</label>
                                            <input type="text" class="form-control" name="post_code">
                                        </div>
                                    </div>
                                </div>

                                <!-- Franchise specific fields -->
                                <div class="row lead-fields d-none" id="add_franchise_fields">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                                <label class="form-label fw-bold">Investment Budget (in Lakhs)</label>
                                                <input type="text" class="form-control" name="investment_budget" placeholder="e.g. 3.5">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Expected Earnings</label>
                                            <input type="text" class="form-control" name="expected_earnings">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Growth Intent</label>
                                            <input type="text" class="form-control" name="growth_intent">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Franchise Vertical</label>
                                            <input type="text" class="form-control" name="franchise_vertical">
                                        </div>
                                    </div>
                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Message</label>
                                                    <textarea class="form-control" name="message" rows="4" placeholder="Message / Details (matches 'message' column in Service CSV)"></textarea>
                                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="fw-bold mb-3">Lead Classification</h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="new">New</option>
                                            <option value="contacted">Contacted</option>
                                            <option value="qualified">Qualified</option>
                                            <option value="proposal_sent">Proposal Sent</option>
                                            <option value="converted">Converted</option>
                                            <option value="lost">Lost</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select class="form-select" name="priority">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Source</label>
                                        <select class="form-select" name="source">
                                            <option value="manual">Manual Entry</option>
                                            <option value="website">Website</option>
                                            <option value="social-media">Social Media</option>
                                            <option value="referral">Referral</option>
                                            <option value="advertisement">Advertisement</option>
                                            <option value="walk-in">Walk-in</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <hr class="my-3">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Log Activity (Daily Stats)</h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Follow-ups</label>
                                        <input type="number" class="form-control" name="followups_per_day" min="0" step="1" value="0" placeholder="e.g., 1">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Conversions</label>
                                        <input type="number" class="form-control" name="conversions_per_day" min="0" step="1" value="0" placeholder="e.g., 1">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Walk-ins</label>
                                        <input type="number" class="form-control" name="walkins_per_day" min="0" step="1" value="0" placeholder="e.g., 1">
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <small>This lead will be assigned to you automatically.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary-modern">
                            <i class="fas fa-save me-2"></i>Add Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Advanced Edit Lead Modal -->
    <div class="modal fade" id="editLeadModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editLeadForm" action="lead_actions.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="lead_id" id="edit_lead_id">
                        <input type="hidden" name="lead_type" id="edit_lead_type">

                        <div class="row">
                            <div class="col-lg-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Full Name *</label>
                                            <input type="text" class="form-control" name="name" id="edit_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Email Address *</label>
                                            <input type="email" class="form-control" name="email" id="edit_email" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone" id="edit_phone">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Company</label>
                                            <input type="text" class="form-control" name="company" id="edit_company">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Campaign</label>
                                        <select class="form-select" name="campaign_id" id="edit_campaign_id" onchange="loadCampaignFields(this.value, 'edit_campaign_fields_container')">
                                            <option value="">-- None --</option>
                                            <?php foreach($campaigns as $c): ?>
                                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dynamic Campaign Fields -->
                                <div id="edit_campaign_fields_container" class="row mb-3"></div>

                                <div class="row">
                                    <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Assign To</label>
                                            <select class="form-select" name="assigned_to" id="edit_assigned_to">
                                                <?php foreach($all_users as $u): ?>
                                                <option value="<?php echo $u['id']; ?>">
                                                    <?php echo htmlspecialchars($u['username'] . ' (' . ucfirst($u['role']) . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Service Interest</label>
                                            <select class="form-select" name="service" id="edit_service">
                                                <option value="">Select Service</option>
                                                <option value="web-development">Web Development</option>
                                                <option value="mobile-app">Mobile App Development</option>
                                                <option value="digital-marketing">Digital Marketing</option>
                                                <option value="consulting">Business Consulting</option>
                                                <option value="e-commerce">E-commerce Solutions</option>
                                                <option value="ui-ux-design">UI/UX Design</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Estimated Value (INR)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" class="form-control" name="estimated_value" id="edit_estimated_value" min="0" step="0.01">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row lead-fields lead-service">
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Message</label>
                                            <textarea class="form-control" name="message" id="edit_message" rows="4"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Course specific fields for edit -->
                                <div class="row lead-fields d-none" id="edit_course_fields">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Are you a</label>
                                            <input type="text" class="form-control" name="are_you_a" id="edit_are_you_a">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Objective to join</label>
                                            <input type="text" class="form-control" name="objective" id="edit_objective">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Whatsapp Number</label>
                                            <input type="text" class="form-control" name="whatsapp_number" id="edit_whatsapp_number">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Street Address</label>
                                            <input type="text" class="form-control" name="street_address" id="edit_street_address">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Post Code</label>
                                            <input type="text" class="form-control" name="post_code" id="edit_post_code">
                                        </div>
                                    </div>
                                </div>

                                <!-- Franchise specific fields for edit -->
                                <div class="row lead-fields d-none" id="edit_franchise_fields">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Investment Budget</label>
                                            <input type="text" class="form-control" name="investment_budget" id="edit_investment_budget">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Expected Earnings</label>
                                            <input type="text" class="form-control" name="expected_earnings" id="edit_expected_earnings">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Growth Intent</label>
                                            <input type="text" class="form-control" name="growth_intent" id="edit_growth_intent">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Franchise Vertical</label>
                                            <input type="text" class="form-control" name="franchise_vertical" id="edit_franchise_vertical">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="bg-light p-3 rounded">
                                    <h6 class="fw-bold mb-3">Lead Classification</h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Status</label>
                                        <select class="form-select" name="status" id="edit_status">
                                            <option value="new">New</option>
                                            <option value="contacted">Contacted</option>
                                            <option value="qualified">Qualified</option>
                                            <option value="proposal_sent">Proposal Sent</option>
                                            <option value="converted">Converted</option>
                                            <option value="lost">Lost</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select class="form-select" name="priority" id="edit_priority">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Source</label>
                                        <select class="form-select" name="source" id="edit_source">
                                            <option value="manual">Manual Entry</option>
                                            <option value="website">Website</option>
                                            <option value="social-media">Social Media</option>
                                            <option value="referral">Referral</option>
                                            <option value="advertisement">Advertisement</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <hr class="my-3">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Log Activity (Daily Stats)</h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Follow-ups</label>
                                        <input type="number" class="form-control" name="followups_per_day" min="0" step="1" value="0">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Conversions</label>
                                        <input type="number" class="form-control" name="conversions_per_day" min="0" step="1" value="0">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Add Walk-ins</label>
                                        <input type="number" class="form-control" name="walkins_per_day" min="0" step="1" value="0">
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <small>Changes will be saved immediately.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-warning-modern">
                            <i class="fas fa-save me-2"></i>Update Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Lead Modal -->
    <div class="modal fade" id="viewLeadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Lead Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewLeadContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary-modern" onclick="editFromView()">
                        <i class="fas fa-edit me-2"></i>Edit Lead
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Action Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Bulk Update</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bulkActionForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_update">
                        <div id="selectedLeadsInput"></div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Update Status</label>
                            <select class="form-select" name="bulk_status" required>
                                <option value="">Select New Status</option>
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="proposal_sent">Proposal Sent</option>
                                <option value="converted">Converted</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="bulkActionCount">0</span> leads will be updated.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success-modern">
                            <i class="fas fa-save me-2"></i>Update Selected
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reassign Lead Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin_actions.php">
                    <input type="hidden" name="action" value="reassign_lead">
                    <input type="hidden" name="lead_id" id="reassignLeadId">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-share me-2"></i>Reassign Lead: <span id="reassignLeadName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Assign To</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select user...</option>
                                <?php 
                                foreach($all_users as $u):
                                ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name'] ?? $u['username']); ?> (<?php echo ucfirst($u['role']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning-modern"><i class="fas fa-save me-2"></i>Reassign Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
    <script>
        let currentLead = null;
        let leadsTable;
        
        // Calculate indices for DataTables based on dynamic columns
        <?php 
        $custom_cols_count = count($campaign_custom_fields);
        $created_col_idx = 9 + $custom_cols_count;
        $actions_col_idx = 10 + $custom_cols_count;
        ?>

        // Initialize Advanced DataTable
        $(document).ready(function() {
            leadsTable = $('#leadsTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[<?php echo $created_col_idx; ?>, 'desc']], // Sort by created date
                columnDefs: [
                    { orderable: false, targets: [0, <?php echo $actions_col_idx; ?>] }, // Disable sorting on checkbox and actions
                    { searchable: false, targets: [0, <?php echo $actions_col_idx; ?>] }
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                language: {
                    search: "Search leads:",
                    lengthMenu: "Show _MENU_ leads per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ leads",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                drawCallback: function() {
                    // Re-apply animations to new rows
                    $('.table tbody tr').addClass('animate__animated animate__fadeIn');
                }
            });

            // Global search functionality
            $('#globalSearch').on('keyup', function() {
                leadsTable.search(this.value).draw();
            });
            
            // Select all functionality
            $('#selectAll').on('change', function() {
                $('.lead-checkbox').prop('checked', this.checked);
                updateBulkActionButton();
            });

            // Individual checkbox functionality
            $(document).on('change', '.lead-checkbox', function() {
                updateBulkActionButton();

                // Update select all checkbox
                const totalCheckboxes = $('.lead-checkbox').length;
                const checkedCheckboxes = $('.lead-checkbox:checked').length;
                $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
            });
        });

        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const campaign = document.getElementById('campaignFilter').value;
            const user = document.getElementById('userFilter').value;
            const url = new URL(window.location.href);
            if(status) url.searchParams.set('status', status); else url.searchParams.delete('status');
            if(campaign) url.searchParams.set('campaign_id', campaign); else url.searchParams.delete('campaign_id');
            if(user) url.searchParams.set('assigned_to', user); else url.searchParams.delete('assigned_to');
            window.location.href = url.toString();
        }

        // Update bulk action button state
        function updateBulkActionButton() {
            const selectedCount = $('.lead-checkbox:checked').length;
            const bulkBtn = $('button[onclick="bulkAction()"]');
            const deleteBtn = $('button[onclick="deleteSelected()"]');

            if (selectedCount > 0) {
                bulkBtn.removeClass('btn-success-modern').addClass('btn-warning-modern');
                bulkBtn.html(`<i class="fas fa-edit"></i>Bulk Edit (${selectedCount})`);
                deleteBtn.removeClass('btn-danger-modern').addClass('btn-outline-danger');
                deleteBtn.html(`<i class="fas fa-trash"></i>Delete (${selectedCount})`);
            } else {
                bulkBtn.removeClass('btn-warning-modern').addClass('btn-success-modern');
                bulkBtn.html('<i class="fas fa-edit"></i>Bulk Edit');
                deleteBtn.removeClass('btn-outline-danger').addClass('btn-danger-modern');
                deleteBtn.html('<i class="fas fa-trash"></i>Delete Selected');
            }
        }

        // View lead function
        function viewLead(lead) {
            currentLead = lead;

            const content = `
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Contact Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong>Name:</strong><br>
                                        <span class="text-muted">${lead.name || 'N/A'}</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong>Email:</strong><br>
                                        <span class="text-muted">${lead.email || 'N/A'}</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong>Phone:</strong><br>
                                        <span class="text-muted">
                                            ${lead.phone || 'N/A'}
                                            ${lead.phone ? `<a href="https://wa.me/${lead.phone.replace(/[^0-9]/g, '')}" target="_blank" class="text-success ms-2" title="Chat on WhatsApp"><i class="fab fa-whatsapp"></i></a>` : ''}
                                        </span>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong>Company:</strong><br>
                                        <span class="text-muted">${lead.company || 'N/A'}</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-12">
                                        <strong>Service Interest:</strong><br>
                                        <span class="badge bg-primary">${lead.service ? lead.service.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A'}</span>
                                    </div>
                                </div>
                                ${lead.notes ? `
                                <hr>
                                <div class="row">
                                    <div class="col-12">
                                        <strong>Notes:</strong><br>
                                        <span class="text-muted">${lead.notes}</span>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Lead Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Status:</strong><br>
                                    <span class="status-badge status-${lead.status || 'new'}">
                                        <i class="fas fa-circle"></i>
                                        ${(lead.status || 'new').charAt(0).toUpperCase() + (lead.status || 'new').slice(1)}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Priority:</strong><br>
                                    <span class="priority-badge priority-${lead.priority || 'medium'}">
                                        ${(lead.priority || 'medium').charAt(0).toUpperCase() + (lead.priority || 'medium').slice(1)}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Source:</strong><br>
                                    <span class="badge bg-secondary">${lead.source ? lead.source.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Manual'}</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Estimated Value:</strong><br>
                                    <span class="fw-bold text-success">$${lead.estimated_value ? Number(lead.estimated_value).toLocaleString() : '0'}</span>
                                </div>
                                <div class="mb-0">
                                    <strong>Created:</strong><br>
                                    <span class="text-muted">${new Date(lead.created_at || Date.now()).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('viewLeadContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('viewLeadModal')).show();
        }

        // Edit from view modal
        function editFromView() {
            bootstrap.Modal.getInstance(document.getElementById('viewLeadModal')).hide();
            setTimeout(() => editLead(currentLead), 300);
        }

        // Edit lead function
        function editLead(lead) {
            currentLead = lead;

            document.getElementById('edit_lead_id').value = lead.id;
            document.getElementById('edit_name').value = lead.name || '';
            document.getElementById('edit_email').value = lead.email || '';
            document.getElementById('edit_phone').value = lead.phone || '';
            document.getElementById('edit_company').value = lead.company || '';
            document.getElementById('edit_service').value = lead.service || '';
            document.getElementById('edit_status').value = lead.status || 'new';
            document.getElementById('edit_priority').value = lead.priority || 'medium';
            document.getElementById('edit_source').value = lead.source || 'manual';
            // prefer explicit lead.campaign_id, fallback to latest_campaign_id returned by query
            var campaignToUse = lead.campaign_id || lead.latest_campaign_id || '';
            document.getElementById('edit_campaign_id').value = campaignToUse;
            if(document.getElementById('edit_assigned_to')) document.getElementById('edit_assigned_to').value = lead.assigned_to || '';
            document.getElementById('edit_estimated_value').value = lead.estimated_value || '';
            // notes may be a JSON string (interest/budget/remarks) or plain text
            var notesRaw = lead.notes || '';
            var notesObj = null;
            try {
                notesObj = JSON.parse(notesRaw);
            } catch(e) {
                notesObj = null;
            }

            // default lead type
            var lt = (lead.service || lead.source || 'service');
            lt = lt.toLowerCase();
            if (document.getElementById('edit_lead_type')) document.getElementById('edit_lead_type').value = lt;

            if (notesObj && typeof notesObj === 'object') {
                var remarks = notesObj.remarks || '';
                document.getElementById('edit_message').value = remarks;

                var interest = notesObj.interest || {};
                var budget = notesObj.budget || {};

                if (lt === 'service') {
                    if (document.getElementById('edit_service')) document.getElementById('edit_service').value = interest.services || '';
                    if (document.getElementById('edit_estimated_value')) document.getElementById('edit_estimated_value').value = budget.monthly || lead.estimated_value || '';
                } else if (lt === 'course') {
                    if (document.getElementById('edit_are_you_a')) document.getElementById('edit_are_you_a').value = interest.role || '';
                    if (document.getElementById('edit_objective')) document.getElementById('edit_objective').value = interest.objective || '';
                    if (document.getElementById('edit_whatsapp_number')) document.getElementById('edit_whatsapp_number').value = notesObj.whatsapp_number || '';
                    if (document.getElementById('edit_street_address')) document.getElementById('edit_street_address').value = notesObj.street_address || '';
                    if (document.getElementById('edit_post_code')) document.getElementById('edit_post_code').value = notesObj.post_code || '';
                } else if (lt === 'franchise') {
                    if (document.getElementById('edit_investment_budget')) document.getElementById('edit_investment_budget').value = budget.investment || '';
                    if (document.getElementById('edit_expected_earnings')) document.getElementById('edit_expected_earnings').value = budget.expected_earnings || '';
                    if (document.getElementById('edit_growth_intent')) document.getElementById('edit_growth_intent').value = interest.growth_intent || '';
                    if (document.getElementById('edit_franchise_vertical')) document.getElementById('edit_franchise_vertical').value = interest.vertical || '';
                }
            } else {
                // plain text fallback
                document.getElementById('edit_message').value = notesRaw;
            }

            toggleLeadFields('edit', lt);
            
            // Load campaign fields if a campaign is available (either lead.campaign_id or latest_campaign_id)
            if (campaignToUse) {
                // Pass the custom_data object attached in the PHP loop to populate values
                loadCampaignFields(campaignToUse, 'edit_campaign_fields_container', lead.custom_data);
            } else {
                document.getElementById('edit_campaign_fields_container').innerHTML = '';
            }

            new bootstrap.Modal(document.getElementById('editLeadModal')).show();
        }

        async function postLeadAction(formData) {
            const response = await fetch('lead_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const raw = await response.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (e) {
                throw new Error('Unexpected server response.');
            }
            if (!data.success) {
                throw new Error(data.message || 'Action failed.');
            }
            return data;
        }

        // Delete lead function
        async function deleteLead(leadId) {
            if (!confirm('Are you sure you want to delete this lead? This action cannot be undone.')) {
                return;
            }

            try {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('lead_id', String(leadId));
                await postLeadAction(fd);
                window.location.reload();
            } catch (err) {
                alert(err.message || 'Failed to delete lead.');
            }
        }

        // Bulk action function
        function bulkAction() {
            const selectedLeads = $('.lead-checkbox:checked');
            if (selectedLeads.length === 0) {
                alert('Please select at least one lead to update.');
                return;
            }

            // Populate hidden inputs for selected leads
            const inputsHtml = Array.from(selectedLeads).map(checkbox =>
                `<input type="hidden" name="lead_ids[]" value="${checkbox.value}">`
            ).join('');

            document.getElementById('selectedLeadsInput').innerHTML = inputsHtml;
            document.getElementById('bulkActionCount').textContent = selectedLeads.length;

            new bootstrap.Modal(document.getElementById('bulkActionModal')).show();
        }

        // Delete selected function
        function deleteSelected() {
            const selectedLeads = $('.lead-checkbox:checked');
            if (selectedLeads.length === 0) {
                alert('Please select at least one lead to delete.');
                return;
            }

            if(confirm(`Are you sure you want to delete ${selectedLeads.length} selected leads? This action cannot be undone.`)) {
                // Submit a single form with all selected lead IDs for bulk deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);

                selectedLeads.each(function() {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'lead_ids[]';
                    input.value = this.value;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Animate statistics on page load
        document.addEventListener('DOMContentLoaded', function() {
            const stats = document.querySelectorAll('.stat-value');
            stats.forEach((stat, index) => {
                const finalValue = parseInt(stat.textContent);
                let currentValue = 0;
                const increment = finalValue / 30;

                setTimeout(() => {
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(currentValue);
                        }
                    }, 50);
                }, index * 200);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N = New Lead
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                new bootstrap.Modal(document.getElementById('addLeadModal')).show();
            }
        });

        // Toggle lead-type specific fields in add/edit forms
        function toggleLeadFields(prefix, type) {
            // prefix: 'add' or 'edit'
            var svc = document.querySelectorAll('.lead-service');
            var course = document.getElementById(prefix + '_course_fields');
            var franchise = document.getElementById(prefix + '_franchise_fields');
            if (type === 'course') {
                // hide service fields
                svc.forEach(function(n){ n.classList.add('d-none'); });
                if (course) course.classList.remove('d-none');
                if (franchise) franchise.classList.add('d-none');
            } else if (type === 'franchise') {
                svc.forEach(function(n){ n.classList.add('d-none'); });
                if (course) course.classList.add('d-none');
                if (franchise) franchise.classList.remove('d-none');
            } else {
                svc.forEach(function(n){ n.classList.remove('d-none'); });
                if (course) course.classList.add('d-none');
                if (franchise) franchise.classList.add('d-none');
            }
        }

        // Wire up lead type selectors
        document.addEventListener('DOMContentLoaded', function(){
            var addSel = document.getElementById('add_lead_type');
            if (addSel) addSel.addEventListener('change', function(){ toggleLeadFields('add', this.value); });
            var editSel = document.getElementById('edit_lead_type');
            if (editSel) editSel.addEventListener('change', function(){ toggleLeadFields('edit', this.value); });

            // Save edits through lead_actions.php and stay on Lead Management page.
            var editForm = document.getElementById('editLeadForm');
            if (editForm) {
                editForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    try {
                        await postLeadAction(new FormData(editForm));
                        window.location.reload();
                    } catch (err) {
                        alert(err.message || 'Failed to update lead.');
                    }
                });
            }
        });

        // Sidebar toggler
        document.addEventListener('DOMContentLoaded', function(){
            var toggler = document.getElementById('sidebar-toggler');
            if (toggler) {
                toggler.addEventListener('click', function(){
                    var sb = document.querySelector('.sidebar');
                    var mc = document.querySelector('.main-content');
                    if (!sb || !mc) return;
                    sb.classList.toggle('collapsed');
                    mc.classList.toggle('collapsed');
                });
            }
        });

        // View switcher
        function switchView(view) {
            const tableView = document.querySelector('.table-container');
            const kanbanView = document.getElementById('kanbanView');
            const tableBtn = document.getElementById('viewTableBtn');
            const kanbanBtn = document.getElementById('viewKanbanBtn');

            if (view === 'table') {
                tableView.style.display = '';
                kanbanView.style.display = 'none';
                tableBtn.classList.add('active');
                kanbanBtn.classList.remove('active');
            } else {
                tableView.style.display = 'none';
                kanbanView.style.display = '';
                tableBtn.classList.remove('active');
                kanbanBtn.classList.add('active');
            }
        }

        // Prepare reassign modal
        function prepareReassign(leadId, leadName) {
            document.getElementById('reassignLeadId').value = leadId;
            document.getElementById('reassignLeadName').textContent = leadName;
        }

        // Kanban drag and drop functionality
        document.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('kanban-card')) {
                e.dataTransfer.effectAllowed = 'move';
                e.target.style.opacity = '0.5';
            }
        });

        document.addEventListener('dragend', function(e) {
            if (e.target.classList.contains('kanban-card')) {
                e.target.style.opacity = '1';
            }
        });

        document.addEventListener('dragover', function(e) {
            if (e.target.classList.contains('kanban-column')) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                e.target.style.backgroundColor = 'rgba(102, 126, 234, 0.05)';
            }
        });

        document.addEventListener('dragleave', function(e) {
            if (e.target.classList.contains('kanban-column')) {
                e.target.style.backgroundColor = '';
            }
        });

        document.addEventListener('drop', function(e) {
            e.preventDefault();
            if (e.target.classList.contains('kanban-column')) {
                e.target.style.backgroundColor = '';
                const card = document.querySelector('.kanban-card[style*="opacity"]');
                if (card) {
                    const leadId = card.dataset.leadId;
                    const newStatus = e.target.dataset.status;
                    
                    // Update lead status
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="lead_id" value="${leadId}">
                        <input type="hidden" name="status" value="${newStatus}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });
        
        // Function to load campaign fields dynamically
        function loadCampaignFields(campaignId, containerId, customData = null) {
            const container = document.getElementById(containerId);
            if (!campaignId) {
                container.innerHTML = '';
                return;
            }
            
            // Fetch fields definition
            fetch('ajax_campaign_fields.php?id=' + campaignId)
                .then(r => r.json())
                .then(fields => {
                    if (!fields || fields.length === 0) {
                        container.innerHTML = '';
                        return;
                    }
                    
                    let html = '<div class="col-12"><h6 class="fw-bold text-primary border-bottom pb-2">Campaign Fields</h6></div>';
                    fields.forEach(f => {
                        let value = '';
                        if (customData && customData[f.field_key]) {
                            value = customData[f.field_key];
                        }
                        // Simple escape for attribute
                        value = value.replace(/"/g, '&quot;');

                        html += `
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">${f.field_name}</label>
                            <input type="${f.field_type}" class="form-control" name="custom_${f.id}" value="${value}" placeholder="${f.field_name}">
                        </div>`;
                    });
                    container.innerHTML = html;
                })
                .catch(e => console.error('Error loading fields', e));
        }
    </script>
</body>
</html>
