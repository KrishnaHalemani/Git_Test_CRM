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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Campaign: <?php echo htmlspecialchars($campaign['name']); ?> - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function preselectCampaign(campaignId) {
        // When the import modal is opened from this page, pre-select the current campaign.
        const campaignSelect = document.getElementById('campaign_id');
        if (campaignSelect) {
            campaignSelect.value = campaignId;
        }
    }
</script>
</body>
</html>