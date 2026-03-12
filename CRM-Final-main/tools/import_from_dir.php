<?php
// Batch import runner: processes CSV files placed into /imports
// Usage: place .csv files into the imports directory and visit this script as Superadmin.

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../db.php';
require_role(['superadmin']);

$LOG = __DIR__ . '/../tools/import_errors.log';
function log_msg($m) { global $LOG; @file_put_contents($LOG, date('c') . ' ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX); }

$IN_DIR = __DIR__ . '/../imports';
$PROCESSED = $IN_DIR . '/processed';
$FAILED = $IN_DIR . '/failed';
@mkdir($IN_DIR, 0755, true);
@mkdir($PROCESSED, 0755, true);
@mkdir($FAILED, 0755, true);

$files = glob($IN_DIR . '/*.csv');
if (empty($files)) {
    echo "No CSV files found in imports directory (" . realpath($IN_DIR) . ").\n";
    echo "Place CSV files there and refresh this page to run the import.\n";
    exit;
}

$totalFiles = count($files);
$overallImported = 0;
$overallErrors = 0;

foreach ($files as $file) {
    $basename = basename($file);
    log_msg("Import-from-dir starting: $basename | user:" . ($_SESSION['user_id'] ?? 'unknown'));
    $rows = [];
    if (!is_readable($file)) {
        log_msg("Cannot read file: $file");
        rename($file, $FAILED . '/' . $basename);
        continue;
    }
    if (($h = fopen($file, 'r')) === false) {
        log_msg("Failed to open: $file");
        rename($file, $FAILED . '/' . $basename);
        continue;
    }
    while (($r = fgetcsv($h)) !== false) $rows[] = $r;
    fclose($h);

    if (count($rows) < 2) {
        log_msg("File $basename contains no data rows");
        rename($file, $FAILED . '/' . $basename);
        continue;
    }

    // normalize header
    $rawHeader = $rows[0];
    $header = array_map(function($h){ $h = strtolower(trim($h)); $h = preg_replace('/[^a-z0-9]+/', '_', $h); return trim($h, '_'); }, $rawHeader);

    // detect lead type (same heuristic as importer)
    $score = ['service'=>0,'course'=>0,'franchise'=>0];
    foreach ($header as $h) {
        if (strpos($h,'service') !== false) $score['service']++;
        if (strpos($h,'marketing') !== false) $score['service']++;
        if (strpos($h,'course') !== false) $score['course']++;
        if (strpos($h,'objective') !== false) $score['course']++;
        if (strpos($h,'investment') !== false) $score['franchise']++;
        if (strpos($h,'franchise') !== false) $score['franchise']++;
        if (strpos($h,'earn') !== false) $score['franchise']++;
    }
    arsort($score);
    $lead_type = key($score);
    log_msg("Detected lead type for $basename: $lead_type");

    // helpers
    $phone_clean = function($p){ $p = preg_replace('/\D/', '', (string)$p); return strlen($p) > 10 ? substr($p, -10) : $p; };
    $amount_clean = function($v, $lead_type=''){
        if (function_exists('parseAmountToINR')) return parseAmountToINR($v, $lead_type);
        if (preg_match('/(\d+(\.\d+)?)/', (string)$v, $m)) return (float)$m[1];
        return 0;
    };

    $imported = 0;
    $errors = [];

    for ($i = 1; $i < count($rows); $i++) {
        // normalize columns
        $numHeader = count($header);
        $rowCols = count($rows[$i]);
        if ($rowCols < $numHeader) {
            while (count($rows[$i]) < $numHeader) $rows[$i][] = '';
        } elseif ($rowCols > $numHeader) {
            $rows[$i] = array_slice($rows[$i], 0, $numHeader);
        }
        $data = @array_combine($header, $rows[$i]);
        if ($data === false) {
            $errors[] = "Row ".($i+1)." combine failed";
            log_msg("$basename Row " . ($i+1) . " combine failed");
            continue;
        }

        // map lead fields similar to import_leads.php
        if ($lead_type === 'service') {
            $lead = [
                'name' => trim($data['full_name'] ?? $data['name'] ?? ''),
                'email' => strtolower(trim($data['email'] ?? '')),
                'phone' => $phone_clean($data['phone'] ?? $data['phone_number'] ?? $data['work_phone_number'] ?? ''),
                'company' => trim($data['what_is_your_organization_name'] ?? $data['company'] ?? ''),
                'service' => trim($data['what_services_are_you_interested_in'] ?? ''),
                'notes' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'estimated_value' => $amount_clean($data['what_is_your_monthly_marketing_budget'] ?? '', 'service'),
                'source' => 'service', 'status' => 'new'
            ];
        } elseif ($lead_type === 'course') {
            $lead = [
                'name' => trim($data['first_name'] ?? $data['full_name'] ?? ''),
                'email' => strtolower(trim($data['email'] ?? '')),
                'phone' => $phone_clean($data['phone'] ?? $data['phone_number'] ?? $data['whatsapp_number'] ?? ''),
                'company' => trim($data['are_you_a'] ?? ''),
                'service' => trim($data['what_is_your_objective_to_join_this_course'] ?? ''),
                'notes' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'estimated_value' => 0,
                'source' => 'course', 'status' => 'new'
            ];
        } else {
            $lead = [
                'name' => trim($data['full_name'] ?? ''),
                'email' => strtolower(trim($data['email'] ?? '')),
                'phone' => $phone_clean($data['phone'] ?? $data['phone_number'] ?? $data['work_phone_number'] ?? ''),
                'company' => trim($data['what_is_the_name_of_your_institution_business'] ?? $data['company'] ?? ''),
                'service' => trim($data['in_which_franchise_vertical_are_you_interested'] ?? ''),
                'notes' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'estimated_value' => $amount_clean($data['what_is_your_investment_budget'] ?? '', 'franchise'),
                'source' => 'franchise', 'status' => 'new'
            ];
        }

        if (empty($lead['phone']) && empty($lead['email'])) {
            $errors[] = "Row ".($i+1)." missing phone/email";
            continue;
        }

        $lead['created_by'] = $_SESSION['user_id'] ?? 1;
        $lead['assigned_to'] = $_SESSION['user_id'] ?? 1;

        $newId = addLead($lead);
        if ($newId) {
            $imported++;
            // assign to all active users so visible everywhere
            $all = getAllUsers(10000);
            foreach ($all as $u) {
                if (in_array($u['role'] ?? '', ['user','admin']) && ($u['status'] ?? '') === 'active') {
                    addLeadAssignment($newId, $u['id'], $_SESSION['user_id'] ?? 1);
                }
            }
        } else {
            $errors[] = "Row ".($i+1)." database insert failed";
            log_msg("$basename Row " . ($i+1) . " db insert failed | data: " . substr(json_encode($data, JSON_UNESCAPED_UNICODE),0,800));
        }
    }

    $overallImported += $imported;
    $overallErrors += count($errors);

    // move file to processed or failed
    $dst = ($imported > 0 && count($errors) === 0) ? $PROCESSED : $PROCESSED; // keep for now
    $dstName = $PROCESSED . '/' . date('Ymd_His_') . $basename;
    if (!@rename($file, $dstName)) {
        log_msg("Failed to move processed file $basename");
    }

    log_msg("Finished import $basename Imported=$imported Errors=" . count($errors));
}

echo "Processed $totalFiles file(s). Total imported: $overallImported, total errors: $overallErrors\n";
exit;
