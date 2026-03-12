<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
if (!$campaign_id) {
    echo json_encode(['error' => 'Missing campaign_id']);
    exit;
}

// Determine user context for permission-aware lead fetching
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

// Fetch campaign fields (ordered)
$fields = getCampaignFields($campaign_id);

// Build filters and get leads using existing function (it supports campaign filtering)
$filters = ['campaign_id' => $campaign_id];
$leads = getLeadsAdvanced($user_id, $role, $filters);

// Prepare lead ids for custom data query
$lead_ids = array_column($leads, 'id');
$custom_map = [];
if (!empty($lead_ids)) {
    $ids_str = implode(',', array_map('intval', $lead_ids));
    $sql = "SELECT lcd.lead_id, cf.field_key, lcd.value
            FROM lead_custom_data lcd
            JOIN campaign_fields cf ON lcd.campaign_field_id = cf.id
            WHERE lcd.lead_id IN ($ids_str) AND cf.campaign_id = " . intval($campaign_id);
    global $conn, $db_type, $pdo;
    try {
        if (isset($db_type) && $db_type === 'pdo') {
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $custom_map[$row['lead_id']][$row['field_key']] = $row['value'];
            }
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $custom_map[$row['lead_id']][$row['field_key']] = $row['value'];
                }
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

// Attach custom values to leads (ensuring every field_key exists)
$field_keys = array_map(function($f){ return $f['field_key']; }, $fields);
foreach ($leads as &$l) {
    $l['custom'] = [];
    foreach ($field_keys as $fk) {
        $l['custom'][$fk] = isset($custom_map[$l['id']][$fk]) ? $custom_map[$l['id']][$fk] : null;
    }
}
unset($l);

echo json_encode([
    'fields' => $fields,
    'leads' => $leads
]);

?>
