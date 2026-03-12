<?php
session_start();
require_once 'db.php';
require_role(['admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: campaign_manager.php');
    exit;
}

$campaign_id = (int)$_POST['campaign_id'];
$filename = $_POST['file'];
$mapping = $_POST['mapping']; // Array: [file_col_index => crm_field_key]
$filepath = __DIR__ . '/uploads/imports/' . basename($filename);

// make sure bridge table exists before we start inserting
if (!function_exists('ensureLeadCampaignsTable')) require_once 'db.php';
// calling returns boolean, ignore result
ensureLeadCampaignsTable();

$campaign = getCampaignById($campaign_id);
if (!$campaign || !file_exists($filepath)) {
    $_SESSION['error_message'] = "Invalid import request.";
    header('Location: campaign_manager.php');
    exit;
}

// Campaign custom field metadata for smart standard<->custom syncing.
$campaign_fields = getCampaignFields($campaign_id);
$campaign_fields_by_id = [];
$campaign_fields_by_key = [];
foreach ($campaign_fields as $cf) {
    $field_id = (int)($cf['id'] ?? 0);
    if ($field_id <= 0) {
        continue;
    }
    $key = strtolower(trim((string)($cf['field_key'] ?? '')));
    $campaign_fields_by_id[$field_id] = $cf;
    if ($key !== '') {
        $campaign_fields_by_key[$key] = $field_id;
    }
}

function getStandardLeadFieldFromCampaignKey($key) {
    $k = strtolower(trim((string)$key));
    if ($k === '') {
        return null;
    }

    $alias_map = [
        'name' => 'name',
        'full_name' => 'name',
        'fullname' => 'name',
        'email' => 'email',
        'email_address' => 'email',
        'phone' => 'phone',
        'number' => 'phone',
        'mobile' => 'phone',
        'mobile_number' => 'phone',
        'phone_number' => 'phone',
    ];

    return $alias_map[$k] ?? null;
}

// Read File Data
$rows = [];
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

if ($ext === 'csv') {
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        fgetcsv($handle); // Skip header
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
} elseif ($ext === 'xlsx') {
    require_once 'lib/SimpleXLSX.php';
    if ($xlsx = SimpleXLSX::parse($filepath)) {
        $all_rows = $xlsx->rows();
        array_shift($all_rows); // Remove header
        $rows = $all_rows;
    }
}

$success_count = 0;
$failed_count = 0;
$updated_count = 0;
$processed_lead_ids = [];

foreach ($rows as $row) {
    // Skip empty rows
    if (empty(array_filter($row))) continue;

    $lead_data = [
        'campaign_id' => $campaign_id,
        // Keep a schema-safe default; campaign identity is tracked via campaign_id/lead_campaigns.
        'source' => 'manual',
        'created_by' => $_SESSION['user_id'],
        'status' => 'new',
        'email' => '',
        'phone' => '',
        'company' => '',
        'service' => '',
        'notes' => ''
    ];
    $custom_data = [];
    $custom_data_by_field_id = [];

    // Map Data
    foreach ($mapping as $file_index => $crm_field) {
        if (empty($crm_field) || !isset($row[$file_index])) continue;
        
        $value = trim($row[$file_index]);

        if (strpos($crm_field, 'custom_') === 0) {
            // Custom Field
            $field_id = (int)str_replace('custom_', '', $crm_field);
            if ($field_id > 0) {
                $custom_data_by_field_id[$field_id] = $value;
                $custom_data[] = ['field_id' => $field_id, 'value' => $value];
            }

            // If this custom field semantically matches a standard field, fill it too.
            if (isset($campaign_fields_by_id[$field_id])) {
                $standard_key = getStandardLeadFieldFromCampaignKey($campaign_fields_by_id[$field_id]['field_key'] ?? '');
                if ($standard_key && empty($lead_data[$standard_key])) {
                    $lead_data[$standard_key] = $value;
                }
            }
        } else {
            // Standard Field
            $lead_data[$crm_field] = $value;

            // Mirror standard values into same-named campaign custom fields (if present).
            $matching_custom_keys = [];
            if ($crm_field === 'name') {
                $matching_custom_keys = ['name', 'full_name', 'fullname'];
            } elseif ($crm_field === 'email') {
                $matching_custom_keys = ['email', 'email_address'];
            } elseif ($crm_field === 'phone') {
                $matching_custom_keys = ['phone', 'number', 'mobile', 'mobile_number', 'phone_number'];
            }

            foreach ($matching_custom_keys as $custom_key) {
                if (!isset($campaign_fields_by_key[$custom_key])) {
                    continue;
                }
                $matched_field_id = (int)$campaign_fields_by_key[$custom_key];
                $custom_data_by_field_id[$matched_field_id] = $value;
            }
        }
    }

    // Rebuild deduplicated custom data payload.
    if (!empty($custom_data_by_field_id)) {
        $custom_data = [];
        foreach ($custom_data_by_field_id as $field_id => $value) {
            $custom_data[] = ['field_id' => (int)$field_id, 'value' => $value];
        }
    }

    // Validation
    if (empty($lead_data['name'])) {
        $failed_count++;
        continue; // Name is required
    }

    if (!empty($lead_data['email']) && !filter_var($lead_data['email'], FILTER_VALIDATE_EMAIL)) {
        // Invalid email, but we might still import if phone exists? 
        // For now, let's just clear invalid email to avoid DB error, or skip.
        // Let's clear it.
        $lead_data['email'] = ''; 
    }

    // Check Duplicates (Upsert Logic)
    $existing_lead = null;
    if (!empty($lead_data['email'])) {
        $existing_lead = getExistingLeadByEmailOrPhone($lead_data['email'], null);
    }
    if (!$existing_lead && !empty($lead_data['phone'])) {
        $existing_lead = getExistingLeadByEmailOrPhone(null, $lead_data['phone']);
    }

    $lead_id = 0;

    if ($existing_lead) {
        // Update Existing Lead
        $lead_id = $existing_lead['id'];
        
        // Prepare update data (exclude created_by, etc if you want to preserve original owner)
        // Request says: "If email or phone already exists -> update existing lead"
        // We update mapped fields.
        if (updateLead($lead_id, $lead_data)) {
            $updated_count++;
        } else {
            $failed_count++;
            continue;
        }
    } else {
        // Create New Lead
        $create_lead_data = $lead_data;
        $create_lead_data['assigned_to'] = null;
        $lead_id = addLead($create_lead_data);
        if ($lead_id) {
            $success_count++;
        } else {
            $failed_count++;
            continue;
        }
    }

    // Always maintain campaign bridge mapping so leads appear in campaign-based views.
    if ($lead_id && $campaign_id && function_exists('linkLeadToCampaign')) {
        $ok = linkLeadToCampaign($lead_id, $campaign_id);
        if (!$ok) {
            // log failure for debugging
            if (function_exists('logSystemActivity')) {
                logSystemActivity($_SESSION['user_id'], 'import_leads', "Failed to link lead $lead_id to campaign $campaign_id");
            }
        }
    }

    // Save Custom Data
    if ($lead_id && !empty($custom_data)) {
        saveLeadCustomData($lead_id, $custom_data);
    }

    if ($lead_id) {
        $processed_lead_ids[] = $lead_id;
    }
}

$assignment_summary = assignLeadsByCampaignTarget($campaign_id, $processed_lead_ids, $_SESSION['user_id']);

// Cleanup
@unlink($filepath);

$msg = "Import Completed. Created: $success_count, Updated: $updated_count, Failed: $failed_count";
if (!empty($assignment_summary['configured'])) {
    $msg .= ", Auto Assigned: " . (int)$assignment_summary['assigned_count'] . ", Left Unassigned: " . (int)$assignment_summary['unassigned_count'];
} elseif (!empty($processed_lead_ids)) {
    $msg .= ", Assignment: Not configured";
}

if (empty($assignment_summary['success'])) {
    $_SESSION['error_message'] = "Import completed, but campaign lead assignment failed. Please review assignment configuration and try again.";
}

// Log to system activity
if (function_exists('logSystemActivity')) {
    logSystemActivity(
        $_SESSION['user_id'], 
        'import_leads', 
        "Campaign Import ($filename): $msg"
    );
}

$_SESSION['success_message'] = $msg;
// after import, show leads list filtered by this campaign so user can immediately verify
global $campaign_id;
header("Location: leads_advanced.php?campaign_id=$campaign_id&success=" . urlencode($msg));
exit;
?>
