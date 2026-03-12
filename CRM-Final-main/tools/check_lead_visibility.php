<?php
// Diagnostic helper to check lead visibility for a user and overall counts
// Usage: tools/check_lead_visibility.php?user_id=1
require_once __DIR__ . '/../db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: text/plain; charset=utf-8');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    echo "Provide user_id as ?user_id=NN or login as a user.\n";
    exit;
}

echo "Lead visibility diagnostic for user_id={$user_id}\n";

try {
    // total leads
    $total = 0;
    $assigned_to_you = 0;
    $mapped_to_you = 0;
    $created_by_you = 0;

    if ($db_type === 'pdo') {
        $total = $conn->query("SELECT COUNT(*) as c FROM leads")->fetch()['c'];
        $assigned_to_you = $conn->prepare("SELECT COUNT(*) as c FROM leads WHERE assigned_to = ?");
        $assigned_to_you->execute([$user_id]);
        $assigned_to_you = $assigned_to_you->fetch()['c'];

        $created_by_you = $conn->prepare("SELECT COUNT(*) as c FROM leads WHERE created_by = ?");
        $created_by_you->execute([$user_id]);
        $created_by_you = $created_by_you->fetch()['c'];

        $mapped_to_you = $conn->prepare("SELECT COUNT(DISTINCT lead_id) as c FROM lead_assignments WHERE user_id = ?");
        $mapped_to_you->execute([$user_id]);
        $mapped_to_you = $mapped_to_you->fetch()['c'];

        // fetch getLeads results
        $visible = getLeads($user_id, 'user');
    } else {
        $total = $conn->query("SELECT COUNT(*) as c FROM leads")->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM leads WHERE assigned_to = ?");
        $stmt->bind_param('i', $user_id); $stmt->execute(); $assigned_to_you = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM leads WHERE created_by = ?");
        $stmt->bind_param('i', $user_id); $stmt->execute(); $created_by_you = $stmt->get_result()->fetch_assoc()['c'];

        $stmt = $conn->prepare("SELECT COUNT(DISTINCT lead_id) as c FROM lead_assignments WHERE user_id = ?");
        $stmt->bind_param('i', $user_id); $stmt->execute(); $mapped_to_you = $stmt->get_result()->fetch_assoc()['c'];

        $visible = getLeads($user_id, 'user');
    }

    echo "Total leads in DB: {$total}\n";
    echo "Assigned to user: {$assigned_to_you}\n";
    echo "Created by user: {$created_by_you}\n";
    echo "Mapped via lead_assignments: {$mapped_to_you}\n";
    echo "\nLeads returned by getLeads(): " . count($visible) . "\n";

    if (!empty($visible)) {
        echo "Sample returned lead IDs: \n";
        $ids = array_map(function($l){ return $l['id']; }, array_slice($visible,0,20));
        echo implode(', ', $ids) . "\n";
    }

    // Also run the raw query we use in getLeads for debugging
    echo "\nRaw SQL used for users (debug):\n";
    ob_start();
    // replicate logic
    $sql_base = "FROM leads l \n        LEFT JOIN users u1 ON l.assigned_to = u1.id \n        LEFT JOIN users u2 ON l.created_by = u2.id";
    $sql = "SELECT l.*, u1.full_name as assigned_to_name, u2.full_name as created_by_name " . $sql_base . " \n            WHERE (l.assigned_to = ? OR l.created_by = ? OR EXISTS (SELECT 1 FROM lead_assignments la WHERE la.lead_id = l.id AND la.user_id = ?)) \n            ORDER BY l.created_at DESC";
    echo $sql . "\n\n";

    // Show first 30 rows from the raw query
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id,$user_id,$user_id]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $user_id,$user_id,$user_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo "First 30 rows (id, assigned_to, created_by):\n";
    $rows_sample = array_slice($rows,0,30);
    foreach ($rows_sample as $r) {
        echo sprintf("%s | assigned_to=%s | created_by=%s\n", $r['id'],$r['assigned_to'],$r['created_by']);
    }

} catch (Exception $e) {
    echo "Error during diagnostics: " . $e->getMessage() . "\n";
}


