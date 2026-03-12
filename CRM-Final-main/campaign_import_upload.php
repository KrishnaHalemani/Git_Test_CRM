<?php
session_start();
require_once 'db.php';
require_role(['admin', 'superadmin']);

$campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$campaign = getCampaignById($campaign_id);

if (!$campaign) {
    $_SESSION['error_message'] = "Invalid Campaign.";
    header('Location: campaign_manager.php');
    exit;
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $allowed_exts = ['csv', 'xlsx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        $error = "Invalid file type. Only CSV and XLSX allowed.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed.";
    } else {
        // Move to imports directory
        $upload_dir = __DIR__ . '/uploads/imports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $filename = 'import_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name']);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Redirect to mapping page
            header("Location: campaign_import_mapping.php?campaign_id=$campaign_id&file=" . urlencode($filename));
            exit;
        } else {
            $error = "Failed to save uploaded file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Leads - <?php echo htmlspecialchars($campaign['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-upload"></i> Import Leads: <?php echo htmlspecialchars($campaign['name']); ?></h4>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label fw-bold">Select File (CSV or Excel)</label>
                    <input type="file" name="import_file" class="form-control" accept=".csv, .xlsx" required>
                    <div class="form-text">
                        Ensure your file has a header row. Duplicate emails or phones will update existing records.
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="campaign_edit.php?id=<?php echo $campaign_id; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Next: Map Fields <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>