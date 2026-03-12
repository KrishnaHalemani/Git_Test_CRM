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

function deleteLeadDependencies($leadIds) {
    global $conn, $db_type;

    if (empty($leadIds)) {
        return true;
    }

    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));

    if ($db_type === 'pdo') {
        foreach (['lead_assignments', 'lead_campaigns', 'lead_custom_data'] as $table) {
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE lead_id IN ({$placeholders})");
            if (!$stmt->execute($leadIds)) {
                return false;
            }
        }

        $stmt = $conn->prepare("DELETE FROM leads WHERE id IN ({$placeholders})");
        return $stmt->execute($leadIds);
    }

    foreach (['lead_assignments', 'lead_campaigns', 'lead_custom_data'] as $table) {
        $stmt = $conn->prepare("DELETE FROM {$table} WHERE lead_id IN ({$placeholders})");
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('i', count($leadIds));
        $params = array_merge([$types], $leadIds);
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) {
            return false;
        }
    }

    $stmt = $conn->prepare("DELETE FROM leads WHERE id IN ({$placeholders})");
    if (!$stmt) {
        return false;
    }
    $types = str_repeat('i', count($leadIds));
    $params = array_merge([$types], $leadIds);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    return $stmt->execute();
}

$leadIds = normalizeLeadIds($_POST['lead_ids'] ?? []);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if (empty($leadIds)) {
    echo json_encode(['success' => false, 'message' => 'No leads selected.']);
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
    echo json_encode(['success' => false, 'message' => 'No permitted leads found for deletion.']);
    exit;
}

if (!beginDbTransaction()) {
    echo json_encode(['success' => false, 'message' => 'Failed to start transaction.']);
    exit;
}

try {
    if (!deleteLeadDependencies($allowedLeadIds)) {
        throw new RuntimeException('Delete query failed.');
    }

    commitDbTransaction();

    foreach ($allowedLeadIds as $leadId) {
        logLeadActivity($leadId, $userId, 'deleted', 'Lead deleted via bulk action.');
    }

    echo json_encode([
        'success' => true,
        'message' => count($allowedLeadIds) . ' leads deleted successfully.',
        'deleted_ids' => $allowedLeadIds
    ]);
} catch (Throwable $e) {
    rollbackDbTransaction();
    echo json_encode(['success' => false, 'message' => 'Failed to delete selected leads.']);
}
