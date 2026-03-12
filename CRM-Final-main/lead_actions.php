<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

header('Content-Type: application/json');

switch ($action) {
    case 'add_lead':
    case 'add':
        $lead_data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'status' => 'new',
            'created_by' => $user_id,
            'assigned_to' => ($role !== 'user' && !empty($_POST['assigned_to'])) ? $_POST['assigned_to'] : $user_id,
            'company' => $_POST['company'] ?? '',
            'service' => !empty($_POST['service']) ? $_POST['service'] : ($_POST['lead_type'] ?? 'service'),
            'source' => $_POST['source'] ?? 'manual',
            'priority' => $_POST['priority'] ?? 'medium',
            'notes' => $_POST['message'] ?? ($_POST['notes'] ?? ''),
            'estimated_value' => $_POST['estimated_value'] ?? 0.00,
            'campaign_id' => !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null
        ];
        $lead_id = addLead($lead_data);
        if ($lead_id) {
            // Handle custom fields
            $custom_data = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'custom_') === 0) {
                    $field_id = (int)str_replace('custom_', '', $key);
                    $custom_data[] = ['field_id' => $field_id, 'value' => $value];
                }
            }
            if (!empty($custom_data) && function_exists('saveLeadCustomData')) {
                saveLeadCustomData($lead_id, $custom_data);
            }

            logLeadActivity($lead_id, $user_id, 'created', "Lead created by " . $_SESSION['username']);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create lead.']);
        }
        break;

    case 'update_lead':
    case 'update':
        $lead_id = (int)($_POST['lead_id'] ?? 0);
        $lead = getLeadById($lead_id);

        if (!$lead || ($role === 'user' && $lead['assigned_to'] != $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit;
        }

        $update_data = [
            'name' => $_POST['name'],
            'phone' => $_POST['phone'],
            'service' => !empty($_POST['service']) ? $_POST['service'] : ($_POST['lead_type'] ?? $lead['service']),
            'status' => $_POST['status'] ?? $lead['status'],
            'source' => $_POST['source'] ?? $lead['source'],
            'priority' => $_POST['priority'] ?? $lead['priority'],
            'notes' => $_POST['message'] ?? ($_POST['notes'] ?? $lead['notes']),
            'estimated_value' => $_POST['estimated_value'] ?? $lead['estimated_value'],
            'campaign_id' => !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : $lead['campaign_id']
        ];
        $requested_assigned_to = ($role !== 'user' && !empty($_POST['assigned_to'])) ? (int)$_POST['assigned_to'] : null;

        if (!beginDbTransaction()) {
            echo json_encode(['success' => false, 'message' => 'Failed to start update transaction.']);
            exit;
        }

        if (updateLead($lead_id, $update_data)) {
            if ($requested_assigned_to !== null && !updateLeadAssignee($lead_id, $requested_assigned_to, $user_id, 'manual', false)) {
                rollbackDbTransaction();
                echo json_encode(['success' => false, 'message' => 'Failed to update lead assignment.']);
                exit;
            }

            // Handle custom fields
            $custom_data = [];
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'custom_') === 0) {
                    $field_id = (int)str_replace('custom_', '', $key);
                    $custom_data[] = ['field_id' => $field_id, 'value' => $value];
                }
            }
            if (!empty($custom_data) && function_exists('saveLeadCustomData') && !saveLeadCustomData($lead_id, $custom_data)) {
                rollbackDbTransaction();
                echo json_encode(['success' => false, 'message' => 'Failed to save custom field data.']);
                exit;
            }

            commitDbTransaction();
            logLeadActivity($lead_id, $user_id, 'updated', "Lead details updated.");
            echo json_encode(['success' => true]);
        } else {
            rollbackDbTransaction();
            echo json_encode(['success' => false, 'message' => 'Failed to update lead.']);
        }
        break;

    case 'update_status':
        $lead_id = (int)($_POST['lead_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (updateLead($lead_id, ['status' => $status])) {
            logLeadActivity($lead_id, $user_id, 'status_change', "Status changed to $status.");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        break;

    case 'delete_lead':
    case 'delete':
        $lead_id = (int)($_POST['lead_id'] ?? 0);
        if (deleteLead($lead_id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete lead.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>
