<?php
session_start();
require_once 'db.php';
require_role('superadmin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: campaign_manager.php');
    exit();
}

$campaign_id = (int)$_GET['id'];
$campaign = getCampaignById($campaign_id);

if (!$campaign) {
    $_SESSION['error_message'] = "Campaign not found.";
    header('Location: campaign_manager.php');
    exit();
}

$fields = getCampaignFields($campaign_id);
$all_users = array_values(array_filter(getAllUsers(), function ($user) {
    return ($user['status'] ?? 'active') === 'active';
}));
$assignment_targets = getCampaignUserTargets($campaign_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Campaign: <?php echo htmlspecialchars($campaign['name']); ?> - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .assignment-row .select2-container {
            width: 100% !important;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php // include 'components/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2">Edit Campaign</h1>
                    <h3 class="h5 text-muted"><?php echo htmlspecialchars($campaign['name']); ?></h3>
                </div>
                 <a href="campaign_manager.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to All Campaigns
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Define Custom Fields -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list-alt"></i> Define Custom Input Fields for this Campaign
                </div>
                <div class="card-body">
                    <p>These fields will become the columns in your Excel/CSV import file. Standard fields like `name`, `email`, and `phone` are always included.</p>
                    
                    <!-- List existing fields -->
                    <h5>Current Custom Fields:</h5>
                    <?php if (empty($fields)): ?>
                        <p class="text-muted">No custom fields defined yet.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($fields as $field): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong><?php echo htmlspecialchars($field['field_name']); ?></strong>
                                        <small class="text-muted">(Type: <?php echo $field['field_type']; ?>, Key: `<?php echo $field['field_key']; ?>`)</small>
                                    </span>
                                    <form action="campaign_actions.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete_field">
                                        <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this field?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Add new field form -->
                    <hr>
                    <h5>Add New Field:</h5>
                    <form action="campaign_actions.php" method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="add_field">
                        <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                        <div class="col-md-6">
                            <label for="fieldName" class="form-label">Field Name</label>
                            <input type="text" class="form-control" id="fieldName" name="field_name" placeholder="e.g., Ad Source" required>
                        </div>
                        <div class="col-md-4">
                            <label for="fieldType" class="form-label">Field Type</label>
                            <select id="fieldType" name="field_type" class="form-select">
                                <option value="text" selected>Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Add Field</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-check"></i> Lead Assignment Configuration
                </div>
                <div class="card-body">
                    <p class="mb-3">Assignment is optional. Configure sequential campaign import assignment by user target. If this section is left empty, imported leads remain unassigned.</p>

                    <form action="campaign_actions.php" method="POST" id="leadAssignmentConfigForm">
                        <input type="hidden" name="action" value="save_assignment_targets">
                        <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="assignmentConfigTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 280px;">User Dropdown</th>
                                        <th style="min-width: 180px;">Target Leads Input</th>
                                        <th style="width: 100px;">Remove</th>
                                    </tr>
                                </thead>
                                <tbody id="assignmentRows">
                                    <?php if (empty($assignment_targets)): ?>
                                        <tr class="assignment-row">
                                            <td>
                                                <select class="form-select assignment-user-select" name="assignment_user_id[]">
                                                    <option value="">Select user...</option>
                                                    <?php foreach ($all_users as $user): ?>
                                                        <option value="<?php echo (int)$user['id']; ?>">
                                                            <?php echo htmlspecialchars(($user['full_name'] ?: $user['username']) . ' (' . ucfirst($user['role']) . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control assignment-target-input" name="lead_target[]" min="1" placeholder="e.g. 50">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger remove-assignment-row">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($assignment_targets as $target): ?>
                                            <tr class="assignment-row">
                                                <td>
                                                    <select class="form-select assignment-user-select" name="assignment_user_id[]">
                                                        <option value="">Select user...</option>
                                                        <?php foreach ($all_users as $user): ?>
                                                            <option value="<?php echo (int)$user['id']; ?>" <?php echo ((int)$target['user_id'] === (int)$user['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars(($user['full_name'] ?: $user['username']) . ' (' . ucfirst($user['role']) . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control assignment-target-input" name="lead_target[]" min="1" value="<?php echo (int)$target['lead_target']; ?>" placeholder="e.g. 50">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger remove-assignment-row">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary" id="addAssignmentRow">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                            <div class="text-end">
                                <div class="small text-muted">Total configured target leads</div>
                                <div class="fw-bold" id="assignmentTargetTotal">0</div>
                            </div>
                        </div>

                        <div class="alert alert-danger mt-3 d-none" id="assignmentValidationMessage"></div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Lead Assignment Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Import Leads Section (Placeholder for Phase 2) -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-upload"></i> Import Leads for this Campaign
                </div>
                <div class="card-body">
                    <p>Import an Excel (.xlsx) or CSV file containing leads for this campaign. You will be able to map columns in the next step.</p>
                    <a href="campaign_import_upload.php?campaign_id=<?php echo $campaign_id; ?>" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Leads Now
                    </a>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include 'components/import_modal.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function preselectCampaign(campaignId) {
        // When the import modal is opened from this page, pre-select the current campaign.
        const campaignSelect = document.getElementById('campaign_id');
        if (campaignSelect) {
            campaignSelect.value = campaignId;
        }
    }

    const userOptionsHtml = <?php echo json_encode(trim(preg_replace('/\s+/', ' ', implode('', array_map(function ($user) {
        $label = htmlspecialchars(($user['full_name'] ?: $user['username']) . ' (' . ucfirst($user['role']) . ')', ENT_QUOTES);
        return '<option value="' . (int)$user['id'] . '">' . $label . '</option>';
    }, $all_users))))); ?>;

    function createAssignmentRow() {
        return `
            <tr class="assignment-row">
                <td>
                    <select class="form-select assignment-user-select" name="assignment_user_id[]">
                        <option value="">Select user...</option>
                        ${userOptionsHtml}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control assignment-target-input" name="lead_target[]" min="1" placeholder="e.g. 50">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger remove-assignment-row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    function initializeAssignmentSelect(context) {
        $(context).find('.assignment-user-select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select user...'
        });
    }

    function getSelectedAssignmentUsers() {
        const selected = [];
        document.querySelectorAll('.assignment-user-select').forEach((select) => {
            if (select.value) {
                selected.push(select.value);
            }
        });
        return selected;
    }

    function refreshDuplicateUserState() {
        const selectedUsers = getSelectedAssignmentUsers();
        document.querySelectorAll('.assignment-user-select').forEach((select) => {
            const currentValue = select.value;
            Array.from(select.options).forEach((option) => {
                if (!option.value) {
                    option.disabled = false;
                    return;
                }
                option.disabled = option.value !== currentValue && selectedUsers.includes(option.value);
            });
            $(select).trigger('change.select2');
        });
    }

    function updateAssignmentTargetTotal() {
        let total = 0;
        document.querySelectorAll('.assignment-target-input').forEach((input) => {
            total += parseInt(input.value || '0', 10);
        });
        document.getElementById('assignmentTargetTotal').textContent = total;
    }

    function setAssignmentValidation(message) {
        const box = document.getElementById('assignmentValidationMessage');
        if (!message) {
            box.classList.add('d-none');
            box.textContent = '';
            return;
        }
        box.textContent = message;
        box.classList.remove('d-none');
    }

    function validateAssignmentConfig() {
        const rows = Array.from(document.querySelectorAll('#assignmentRows .assignment-row'));
        let total = 0;
        const selectedUsers = new Set();
        let hasConfiguredRow = false;

        for (const row of rows) {
            const user = row.querySelector('.assignment-user-select').value;
            const targetValue = row.querySelector('.assignment-target-input').value;
            const target = parseInt(targetValue || '0', 10);

            if (!user && !targetValue) {
                continue;
            }

            hasConfiguredRow = true;

            if (!user || !targetValue || target <= 0) {
                return 'Each assignment row requires a user and a target greater than zero.';
            }

            if (selectedUsers.has(user)) {
                return 'Duplicate users are not allowed in lead assignment configuration.';
            }

            selectedUsers.add(user);
            total += target;
        }

        if (hasConfiguredRow && total <= 0) {
            return 'Total target leads must be greater than zero.';
        }

        return '';
    }

    $(function () {
        initializeAssignmentSelect(document);
        refreshDuplicateUserState();
        updateAssignmentTargetTotal();

        $('#addAssignmentRow').on('click', function () {
            $('#assignmentRows').append(createAssignmentRow());
            const newRow = $('#assignmentRows .assignment-row').last();
            initializeAssignmentSelect(newRow);
            refreshDuplicateUserState();
        });

        $(document).on('change', '.assignment-user-select', function () {
            refreshDuplicateUserState();
            setAssignmentValidation('');
        });

        $(document).on('input', '.assignment-target-input', function () {
            updateAssignmentTargetTotal();
            setAssignmentValidation('');
        });

        $(document).on('click', '.remove-assignment-row', function () {
            if ($('#assignmentRows .assignment-row').length === 1) {
                $(this).closest('tr').find('.assignment-user-select').val('').trigger('change');
                $(this).closest('tr').find('.assignment-target-input').val('');
            } else {
                $(this).closest('tr').remove();
            }
            refreshDuplicateUserState();
            updateAssignmentTargetTotal();
            setAssignmentValidation('');
        });

        $('#leadAssignmentConfigForm').on('submit', function (event) {
            updateAssignmentTargetTotal();
            const validationMessage = validateAssignmentConfig();
            if (validationMessage) {
                event.preventDefault();
                setAssignmentValidation(validationMessage);
            }
        });
    });
</script>
</body>
</html>
