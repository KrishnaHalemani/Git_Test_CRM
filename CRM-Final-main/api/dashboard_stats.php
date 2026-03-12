<?php
include '../db.php';
header('Content-Type: application/json');

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error'=>'Forbidden']);
    exit;
}

$stats = getDashboardStats();

// Lead source breakdown
$sourceData = [];
if (isset($db_type) && $db_type === 'pdo') {
    $stmt = $pdo->query("SELECT source, COUNT(*) as c FROM leads GROUP BY source");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceData[$row['source']] = (int)$row['c'];
    }
} else {
    $res = $conn->query("SELECT source, COUNT(*) as c FROM leads GROUP BY source");
    while($row = $res->fetch_assoc()) {
        $sourceData[$row['source']] = (int)$row['c'];
    }
}

// Prepare response
$response = [
    'monthLabels' => array_map(function($m) {
        return date('M Y', strtotime($m['month'] . '-01'));
    }, $stats['monthly_data']),
    'monthlyLeads' => array_map(function($m) { return $m['created']; }, $stats['monthly_data']),
    'monthlyConverted' => array_map(function($m) { return $m['converted']; }, $stats['monthly_data']),
    'revenueData' => array_map(function($m) { return $m['created'] * 2500; }, $stats['monthly_data']),
    'sourceLabels' => array_map(function($k){return ucfirst($k);}, array_keys($sourceData)),
    'sourceDataArr' => array_values($sourceData)
];

echo json_encode($response);
