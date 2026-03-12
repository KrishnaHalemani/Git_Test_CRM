<?php
// Usage: http://localhost/CRM2/tools/check_user_visibility.php?user_id=5
include __DIR__ . '/../db.php';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id) {
    echo "Please provide user_id param.\n";
    exit;
}
$leads = getLeads($user_id, 'user');
echo "User ID: $user_id\n";
echo "Visible leads: " . count($leads) . "\n";
foreach(array_slice($leads,0,50) as $l) {
    echo sprintf("ID:%s | %s | %s | source:%s\n", $l['id'] ?? '', $l['name'] ?? '', $l['phone'] ?? '', $l['source'] ?? '');
}
