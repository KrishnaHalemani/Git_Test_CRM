<?php
session_start();
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

function normalizeLeadIds($input) {
    if (is_string($input) || is_numeric($input)) {
        $input = preg_split('/[\s,]+/', (string)$input, -1, PREG_SPLIT_NO_EMPTY);
    }

    if (!is_array($input)) {
        return [];
    }

    $ids = array_map('intval', $input);
    $ids = array_values(array_unique(array_filter($ids, function ($id) {
        return $id > 0;
    })));

    return $ids;
}

function canManageLeadRecord($lead, $userId, $role) {
    if (!$lead) {
        return false;
    }

    if ($role === 'admin' || $role === 'superadmin') {
        return true;
    }

    return (int)($lead['assigned_to'] ?? 0) === (int)$userId || (int)($lead['created_by'] ?? 0) === (int)$userId;
}

function leadsTableHasUpdatedAt() {
    global $conn, $db_type;

    try {
        if ($db_type === 'pdo') {
            $stmt = $conn->query("SHOW COLUMNS FROM leads LIKE 'updated_at'");
            return $stmt && $stmt->fetch();
        }

        $result = $conn->query("SHOW COLUMNS FROM leads LIKE 'updated_at'");
        return $result && $result->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$leadIds = normalizeLeadIds($_POST['lead_ids'] ?? []);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$updateFields = [];
foreach (['status', 'priority', 'source'] as $field) {
    $value = trim((string)($_POST[$field] ?? ''));
    if ($value !== '') {
        $updateFields[$field] = $value;
    }
}

if (($role === 'admin' || $role === 'superadmin') && isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '') {
    $updateFields['assigned_to'] = (int)$_POST['assigned_to'];
}

if (empty($leadIds)) {
    echo json_encode(['success' => false, 'message' => 'No leads selected.']);
    exit;
}

if (empty($updateFields)) {
    echo json_encode(['success' => false, 'message' => 'No update fields were provided.']);
    exit;
}

$allowedLeadIds = [];
foreach ($leadIds as $leadId) {
    $lead = getLeadById($leadId);
    if (canManageLeadRecord($lead, $userId, $role)) {
        $allowedLeadIds[] = $leadId;
    }
}

if (empty($allowedLeadIds)) {
    echo json_encode(['success' => false, 'message' => 'No permitted leads found for update.']);
    exit;
}

$hasUpdatedAt = leadsTableHasUpdatedAt();

if (!beginDbTransaction()) {
    echo json_encode(['success' => false, 'message' => 'Failed to start transaction.']);
    exit;
}

try {
    foreach ($allowedLeadIds as $leadId) {
        $payload = $updateFields;
        if ($hasUpdatedAt) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        if (!updateLead($leadId, $payload)) {
            throw new RuntimeException('Update failed for lead ' . $leadId);
        }
    }

    commitDbTransaction();

    echo json_encode([
        'success' => true,
        'message' => count($allowedLeadIds) . ' leads updated successfully.',
        'updated_ids' => $allowedLeadIds,
        'updated_fields' => array_keys($updateFields)
    ]);
} catch (Throwable $e) {
    rollbackDbTransaction();
    echo json_encode(['success' => false, 'message' => 'Failed to update selected leads.']);
}
