<?php
// This file is included, so db.php should already be available.
// But just in case, let's ensure it's there.
if (!function_exists('getCampaigns')) {
    // To prevent fatal error if this modal is included somewhere db.php is not.
    // A better approach is to ensure db.php is always included before this component.
    function getCampaigns() { return []; }
}
$campaigns_for_import = getCampaigns();
?>
<!-- /components/import_modal.php -->
<div class="modal fade" id="importLeadsModal" tabindex="-1" aria-labelledby="importLeadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="import_leads.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="importLeadsModalLabel"><i class="fas fa-file-import me-2"></i>Import Leads for a Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="campaign_id" class="form-label"><strong>Step 1: Select Campaign</strong></label>
                        <select class="form-select" name="campaign_id" id="campaign_id" required>
                            <option value="">-- Choose a campaign to import leads into --</option>
                            <?php foreach ($campaigns_for_import as $campaign): ?>
                                <option value="<?php echo $campaign['id']; ?>"><?php echo htmlspecialchars($campaign['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The leads will be associated with this campaign. The Excel/CSV columns must match the fields defined for this campaign.</div>
                    </div>

                    <div class="mb-3">
                        <label for="leadsFile" class="form-label"><strong>Step 2: Select Excel or CSV File</strong></label>
                        <input class="form-control" type="file" id="leadsFile" name="leadsFile" accept=".csv, .xlsx" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle"></i> File Format Instructions:</strong>
                        <p>Your Excel (.xlsx) or CSV file's first row must be a header row. The column names in the header should match the **field keys** defined in your campaign.</p>
                        <ul>
                            <li><strong>Standard Fields:</strong> You can include columns for standard fields like `name`, `email`, `phone`, `company`, `source`, `status`, `priority`, and `notes`.</li>
                            <li><strong>Custom Fields:</strong> For each custom field you defined in the campaign, create a column with a header that matches its `field_key` (e.g., `ad_source`, `landing_page_version`).</li>
                            <li>The system will automatically map the columns in your file to the correct fields.</li>
                        </ul>
                        <p>You can find the `field_key` for your custom fields on the campaign's "Define Fields" page.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Step 3: Lead Assignment</strong></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="assign_mode" id="assign_all" value="all" checked>
                            <label class="form-check-label" for="assign_all">Assign imported leads to all active users (recommended)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="assign_mode" id="assign_none" value="none">
                            <label class="form-check-label" for="assign_none">Leave leads assigned only to me (the importer)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload and Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>