<?php
session_start();
require_once 'db.php';

// Ensure only superadmins can perform these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    $_SESSION['error_message'] = "You do not have permission to perform this action.";
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard_advanced.php'));
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_campaign':
        $name = trim($_POST['campaign_name'] ?? '');
        if (empty($name)) {
            $_SESSION['error_message'] = "Campaign name cannot be empty.";
            header('Location: campaign_manager.php');
            exit();
        }

        $campaignId = createCampaign($name, $_SESSION['user_id']);
        if ($campaignId) {
            if (function_exists('logSystemActivity')) {
                logSystemActivity($_SESSION['user_id'], 'create_campaign', "Created campaign: $name");
            }
            $_SESSION['success_message'] = "Campaign '{$name}' created successfully! Now, define its custom fields.";
            header("Location: campaign_edit.php?id={$campaignId}");
        } else {
            $_SESSION['error_message'] = "Failed to create campaign.";
            header('Location: campaign_manager.php');
        }
        break;

    case 'add_field':
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $field_name = trim($_POST['field_name'] ?? '');
        $field_type = trim($_POST['field_type'] ?? 'text');

        if ($campaign_id > 0 && !empty($field_name)) {
            if (addCampaignField($campaign_id, $field_name, $field_type)) {
                if (function_exists('logSystemActivity')) {
                    logSystemActivity($_SESSION['user_id'], 'update_campaign', "Added field '$field_name' to campaign ID: $campaign_id");
                }
                $_SESSION['success_message'] = "Field '{$field_name}' added.";
            } else {
                $_SESSION['error_message'] = "Failed to add field. A field with a similar name might already exist.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid data provided for new field.";
        }
        header("Location: campaign_edit.php?id={$campaign_id}");
        break;

    case 'delete_field':
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $field_id = (int)($_POST['field_id'] ?? 0);

        if (deleteCampaignField($field_id, $campaign_id)) {
            if (function_exists('logSystemActivity')) {
                logSystemActivity($_SESSION['user_id'], 'update_campaign', "Deleted field ID: $field_id from campaign ID: $campaign_id");
            }
            $_SESSION['success_message'] = "Field deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete field.";
        }
        header("Location: campaign_edit.php?id={$campaign_id}");
        break;

    case 'save_assignment_targets':
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $user_ids = $_POST['assignment_user_id'] ?? [];
        $lead_targets = $_POST['lead_target'] ?? [];
        $targets = [];
        $selected_users = [];
        $total_targets = 0;

        if ($campaign_id <= 0 || !getCampaignById($campaign_id)) {
            $_SESSION['error_message'] = "Invalid campaign selected.";
            header('Location: campaign_manager.php');
            exit();
        }

        foreach ($user_ids as $index => $raw_user_id) {
            $user_id = (int)$raw_user_id;
            $lead_target = (int)($lead_targets[$index] ?? 0);

            if ($user_id <= 0 && $lead_target <= 0) {
                continue;
            }

            if ($user_id <= 0 || $lead_target <= 0) {
                $_SESSION['error_message'] = "Each lead assignment row must include a user and a target greater than zero.";
                header("Location: campaign_edit.php?id={$campaign_id}");
                exit();
            }

            if (in_array($user_id, $selected_users, true)) {
                $_SESSION['error_message'] = "Duplicate users are not allowed in lead assignment configuration.";
                header("Location: campaign_edit.php?id={$campaign_id}");
                exit();
            }

            $selected_users[] = $user_id;
            $total_targets += $lead_target;
            $targets[] = [
                'user_id' => $user_id,
                'lead_target' => $lead_target
            ];
        }

        if (!empty($targets) && $total_targets <= 0) {
            $_SESSION['error_message'] = "Total target leads must be greater than zero.";
            header("Location: campaign_edit.php?id={$campaign_id}");
            exit();
        }

        if (saveCampaignUserTargets($campaign_id, $targets)) {
            if (function_exists('logSystemActivity')) {
                $summary = empty($targets) ? 'Cleared lead assignment configuration' : ('Saved lead assignment configuration with total target ' . $total_targets);
                logSystemActivity($_SESSION['user_id'], 'update_campaign', $summary . " for campaign ID: $campaign_id");
            }
            $_SESSION['success_message'] = empty($targets)
                ? "Lead assignment configuration cleared. Future imports will remain unassigned unless configured again."
                : "Lead assignment configuration saved successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to save lead assignment configuration.";
        }

        header("Location: campaign_edit.php?id={$campaign_id}");
        break;

    default:
        $_SESSION['error_message'] = "Invalid action.";
        header('Location: campaign_manager.php');
        break;
}

exit();
