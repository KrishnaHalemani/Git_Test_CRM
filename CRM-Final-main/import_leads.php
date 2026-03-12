<?php
ob_start(); // CRITICAL: prevents blank page due to headers

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'db.php';
require_role(['superadmin']);

/* =========================================================
   LOGGING
   ========================================================= */
$LOG_FILE = __DIR__ . '/tools/import_errors.log';

function log_import($msg) {
    global $LOG_FILE;
    file_put_contents($LOG_FILE, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

log_import('Import request received');

/* =========================================================
   BASIC REQUEST VALIDATION
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['leadsFile']['tmp_name'])) {
    $_SESSION['import_status'] = ['success' => false, 'message' => 'No file received for import'];
    header('Location: superadmin_dashboard.php#lead-management');
    exit;
}

$campaign_id = (int)($_POST['campaign_id'] ?? 0);
if (!$campaign_id) {
    $_SESSION['import_status'] = ['success' => false, 'message' => 'A campaign must be selected for the import.'];
    header('Location: campaign_manager.php');
    exit;
}

$file = $_FILES['leadsFile'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['import_status'] = ['success' => false, 'message' => 'File upload failed with error code ' . $file['error']];
    header('Location: superadmin_dashboard.php#lead-management');
    exit;
}

$tmp = $file['tmp_name'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];
$assign_mode = $_POST['assign_mode'] ?? 'all';

/* =========================================================
   FILE PARSING (CSV / XLSX)
   ========================================================= */
try {
    if ($ext === 'csv') {
        $handle = fopen($tmp, 'r');
        if (!$handle) throw new Exception('Unable to open CSV file');
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    } elseif ($ext === 'xlsx') {
        require_once 'lib/SimpleXLSX.php';
        if ($xlsx = SimpleXLSX::parse($tmp)) {
            $rows = $xlsx->rows();
        } else {
            throw new Exception('Failed to parse XLSX file: ' . SimpleXLSX::errorMessage());
        }
    } else {
        throw new Exception('Unsupported file type. Only CSV and XLSX files are allowed.');
    }
} catch (Throwable $e) {
    log_import('Parse error: '.$e->getMessage());
    $_SESSION['import_status'] = ['success' => false, 'message' => $e->getMessage()];
    header('Location: superadmin_dashboard.php#lead-management');
    exit;
}

if (count($rows) < 2) {
    $_SESSION['import_status'] = ['success' => false, 'message' => 'File must contain a header row and at least one data row.'];
    header('Location: superadmin_dashboard.php#lead-management');
    exit;
}

/* =========================================================
   HEADER & FIELD MAPPING
   ========================================================= */
$rawHeader = array_shift($rows); // Get header and remove it from data rows
$header = array_map(fn($h) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($h))), '_'), $rawHeader);

// Standard lead fields and their common aliases from spreadsheets
$standard_field_map = [
    'name' => 'name', 'full_name' => 'name', 'lead_name' => 'name',
    'email' => 'email', 'email_address' => 'email',
    'phone' => 'phone', 'phone_number' => 'phone', 'contact_number' => 'phone',
    'company' => 'company', 'organization' => 'company', 'company_name' => 'company',
    'source' => 'source', 'lead_source' => 'source',
    'status' => 'status', 'lead_status' => 'status',
    'priority' => 'priority', 'lead_priority' => 'priority',
    'notes' => 'notes', 'remark' => 'notes', 'message' => 'notes',
    'estimated_value' => 'estimated_value', 'value' => 'estimated_value', 'budget' => 'estimated_value',
];

// Fetch custom fields for the selected campaign
$custom_fields = getCampaignFields($campaign_id);
$custom_field_map = array_column($custom_fields, 'id', 'field_key');

log_import("Campaign #{$campaign_id} import started. Header: " . json_encode($header));

/* =========================================================
   PROCESS ROWS
   ========================================================= */
$imported = 0;
$errors = [];
$skipped = 0;

foreach ($rows as $i => $row) {
    $row_num = $i + 2; // 1-based index, plus header row

    // Normalize row length
    $row_data = array_pad($row, count($header), '');
    $data = @array_combine($header, $row_data);
    if ($data === false) {
        $errors[] = "Row {$row_num}: Column count mismatch.";
        log_import("Row {$row_num} column combine failed. Header count: " . count($header) . ", Row count: " . count($row));
        continue;
    }

    $lead_payload = ['campaign_id' => $campaign_id];
    $custom_data_payload = [];
    $unmapped_data = [];

    foreach ($data as $col_header => $value) {
        $value = trim($value);
        // Map to standard field
        if (isset($standard_field_map[$col_header])) {
            $db_field = $standard_field_map[$col_header];
            $lead_payload[$db_field] = $value;
        } 
        // Map to custom field
        elseif (isset($custom_field_map[$col_header])) {
            $custom_data_payload[] = [
                'field_id' => $custom_field_map[$col_header],
                'value' => $value
            ];
        } 
        // Store unmapped data
        else {
            if (!empty($value)) {
                $unmapped_data[$col_header] = $value;
            }
        }
    }

    // Append unmapped data to notes if it exists
    if (!empty($unmapped_data)) {
        $existing_notes = $lead_payload['notes'] ?? '';
        $unmapped_json = json_encode($unmapped_data, JSON_PRETTY_PRINT);
        $lead_payload['notes'] = trim($existing_notes . "\n\n--- Additional Data ---\n" . $unmapped_json);
    }

    // Clean phone number
    if (isset($lead_payload['phone'])) {
        $lead_payload['phone'] = preg_replace('/\D/', '', (string)$lead_payload['phone']);
    }

    // Validate essential fields
    if (empty($lead_payload['name']) || (empty($lead_payload['phone']) && empty($lead_payload['email']))) {
        $errors[] = "Row {$row_num}: Skipped. Missing Name, or both Email and Phone.";
        continue;
    }

    // Set defaults
    $lead_payload['created_by'] = $_SESSION['user_id'];
    $lead_payload['assigned_to'] = $_SESSION['user_id'];
    $lead_payload['status'] = $lead_payload['status'] ?? 'new';
    $lead_payload['source'] = $lead_payload['source'] ?? 'import';

    // Sync Lead (Create or Update + Link Campaign)
    $newId = syncCampaignLead($lead_payload, $campaign_id);
    
    if ($newId) {
        $imported++;
        // Add custom field data
        if (!empty($custom_data_payload)) {
            addLeadCustomData($newId, $custom_data_payload);
        }

        // Handle lead sharing/assignment
        if ($assign_mode === 'all') {
            $all_users = getAllUsers(10000);
            foreach ($all_users as $u) {
                if (in_array($u['role'] ?? '', ['user','admin']) && ($u['status'] ?? '') === 'active') {
                    addLeadAssignment($newId, $u['id'], $_SESSION['user_id']);
                }
            }
        }
    } else {
        // Capture DB error details for easier debugging
        global $conn;
        $dbErr = $conn->error ?? 'unknown DB error';
        $msg = "Row {$row_num}: Database insert failed: " . $dbErr;
        $errors[] = $msg;
        log_import($msg . ' | data: ' . substr(json_encode($lead_payload), 0, 800));
    }
}

/* =========================================================
   FINAL RESPONSE
   ========================================================= */
$message = "Import complete for Campaign ID #{$campaign_id}. Imported: {$imported} leads.";
if ($skipped > 0) $message .= " Skipped {$skipped} duplicate leads.";
if (count($errors) > 0) $message .= " Encountered " . count($errors) . " errors (see log for details).";

$_SESSION['import_status'] = [
    'success' => $imported > 0,
    'message' => $message
];

$_SESSION['import_errors_list'] = array_slice($errors, 0, 50);

log_import("Import completed. Imported=$imported Skipped=$skipped Errors=".count($errors));

// Redirect back to the campaign edit page
header("Location: campaign_edit.php?id={$campaign_id}");
exit;
