<?php
session_start();
require_once 'db.php';
require_role(['admin', 'superadmin']);

$campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$filename = isset($_GET['file']) ? $_GET['file'] : '';
$filepath = __DIR__ . '/uploads/imports/' . basename($filename);

if (!$campaign_id || !file_exists($filepath)) {
    $_SESSION['error_message'] = "File not found or invalid campaign.";
    header('Location: campaign_manager.php');
    exit;
}

$campaign = getCampaignById($campaign_id);

// Read Headers
$headers = [];
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

if ($ext === 'csv') {
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        fclose($handle);
    }
} elseif ($ext === 'xlsx') {
    require_once 'lib/SimpleXLSX.php'; // Assuming library exists as per context
    if ($xlsx = SimpleXLSX::parse($filepath)) {
        $rows = $xlsx->rows();
        $headers = $rows[0] ?? [];
    }
}

if (empty($headers)) {
    $_SESSION['error_message'] = "Could not read headers from file.";
    header("Location: campaign_import_upload.php?campaign_id=$campaign_id");
    exit;
}

// Get CRM Fields
$crm_fields = [
    'name' => 'Full Name',
    'email' => 'Email',
    'phone' => 'Phone',
    'company' => 'Company',
    'service' => 'Service',
    'status' => 'Status',
    'source' => 'Source',
    'priority' => 'Priority',
    'notes' => 'Notes',
    'estimated_value' => 'Estimated Value'
];

// Get Dynamic Fields
$custom_fields = getCampaignFields($campaign_id);
foreach ($custom_fields as $field) {
    $crm_fields['custom_' . $field['id']] = $field['field_name'] . ' (Custom)';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Map Fields - <?php echo htmlspecialchars($campaign['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fas fa-exchange-alt"></i> Map Columns</h4>
        </div>
        <div class="card-body">
            <p>Map the columns from your file (Left) to the CRM fields (Right).</p>
            
            <form action="campaign_import_process.php" method="POST">
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($filename); ?>">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50%">File Column Header</th>
                                <th width="50%">Map to CRM Field</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($headers as $index => $header): 
                                $clean_header = strtolower(trim($header));
                                $suggested = '';
                                
                                // Simple auto-suggest logic
                                foreach ($crm_fields as $key => $label) {
                                    if (strpos($clean_header, strtolower($label)) !== false || 
                                        strpos($clean_header, str_replace('_', ' ', $key)) !== false) {
                                        $suggested = $key;
                                        break;
                                    }
                                }
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($header); ?></td>
                                <td>
                                    <select name="mapping[<?php echo $index; ?>]" class="form-select">
                                        <option value="">-- Do Not Import --</option>
                                        <optgroup label="Standard Fields">
                                            <?php foreach ($crm_fields as $key => $label): 
                                                if (strpos($key, 'custom_') === false): ?>
                                                <option value="<?php echo $key; ?>" <?php echo ($suggested === $key) ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endif; endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Custom Fields">
                                            <?php foreach ($crm_fields as $key => $label): 
                                                if (strpos($key, 'custom_') !== false): ?>
                                                <option value="<?php echo $key; ?>" <?php echo ($suggested === $key) ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endif; endforeach; ?>
                                        </optgroup>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-success w-100 btn-lg">Start Import <i class="fas fa-check"></i></button>
            </form>
        </div>
    </div>
</div>
</body>
</html>