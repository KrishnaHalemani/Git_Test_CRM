<?php
include __DIR__ . '/../db.php';
$leads = getLeads();
echo "Total leads: " . count($leads) . "\n";
foreach(array_slice($leads,0,20) as $l) {
    echo sprintf("ID:%s | %s | %s | assigned_to:%s | created_by:%s | source:%s\n", $l['id'] ?? '', $l['name'] ?? '', $l['email'] ?? '', $l['assigned_to'] ?? '', $l['created_by'] ?? '', $l['source'] ?? '');
}
