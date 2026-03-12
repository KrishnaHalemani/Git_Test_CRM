<?php
require_once 'db.php';
header('Content-Type: application/json');

$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fields = getCampaignFields($campaign_id);

echo json_encode($fields);
?>