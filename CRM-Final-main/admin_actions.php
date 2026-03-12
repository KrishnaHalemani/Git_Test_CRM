<?php
session_start();
include 'db.php';
require_role(['superadmin']);

header('Content-Type: text/html; charset=utf-8');

$action = $_POST['action'] ?? '';

switch($action) {
    case 'create_admin':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($email) || empty($password)) {
            header('Location: superadmin_dashboard.php?error=missing'); exit();
        }
    $phone = $_POST['phone'] ?? null;
    $branch = $_POST['branch'] ?? null;
    $id = createUser(['username'=>$username,'email'=>$email,'password'=>$password,'full_name'=>$full_name,'role'=>'admin','status'=>'active','phone'=>$phone,'branch'=>$branch]);
        if ($id) header('Location: superadmin_dashboard.php?success=admin_created');
        else header('Location: superadmin_dashboard.php?error=failed');
    exit();

    case 'create_user':
        // Create a regular user (superadmin only)
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($email) || empty($password)) {
            header('Location: superadmin_dashboard.php?error=missing'); exit();
        }
        $phone = $_POST['phone'] ?? null;
        $branch = $_POST['branch'] ?? null;
        $id = createUser(['username'=>$username,'email'=>$email,'password'=>$password,'full_name'=>$full_name,'role'=>'user','status'=>'active','phone'=>$phone,'branch'=>$branch]);
        if ($id) header('Location: superadmin_dashboard.php?success=user_created');
        else header('Location: superadmin_dashboard.php?error=failed');
    exit();

    case 'delete_admin':
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $ok = deleteUser($id);
            if ($ok) header('Location: superadmin_dashboard.php?success=admin_deleted');
            else header('Location: superadmin_dashboard.php?error=delete_failed');
        } else header('Location: superadmin_dashboard.php?error=invalid_id');
    exit();

    case 'update_admin':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { header('Location: superadmin_dashboard.php?error=invalid_id'); exit(); }
        
        $update_data = [];
        if (!empty($_POST['full_name'])) $update_data['full_name'] = $_POST['full_name'];
        if (!empty($_POST['email'])) $update_data['email'] = $_POST['email'];
        if (!empty($_POST['phone'])) $update_data['phone'] = $_POST['phone'];
        if (!empty($_POST['branch'])) $update_data['branch'] = $_POST['branch'];
    if (!empty($_POST['password'])) $update_data['password'] = $_POST['password'];
        
        if (count($update_data) > 0) {
            // If password was provided raw, allow updateUser to hash it
            if (isset($update_data['password']) && !empty($update_data['password'])) {
                // updateUser expects raw password in 'password' and will hash it
            }
            $ok = updateUser($id, $update_data);
            if ($ok) header('Location: superadmin_dashboard.php?success=admin_updated');
            else header('Location: superadmin_dashboard.php?error=update_failed');
        } else header('Location: superadmin_dashboard.php?error=no_changes');
    exit();

    case 'update_user':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { header('Location: superadmin_dashboard.php?error=invalid_id'); exit(); }
        $update_data = [];
        if (!empty($_POST['full_name'])) $update_data['full_name'] = $_POST['full_name'];
        if (!empty($_POST['email'])) $update_data['email'] = $_POST['email'];
        if (!empty($_POST['phone'])) $update_data['phone'] = $_POST['phone'];
        if (!empty($_POST['branch'])) $update_data['branch'] = $_POST['branch'];
        if (!empty($_POST['password'])) $update_data['password'] = $_POST['password'];
        if (count($update_data) > 0) {
            $ok = updateUser($id, $update_data);
            if ($ok) header('Location: superadmin_dashboard.php?success=user_updated');
            else header('Location: superadmin_dashboard.php?error=update_failed');
        } else header('Location: superadmin_dashboard.php?error=no_changes');
        exit();

    case 'toggle_user':
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $u = getUserById($id);
            if (!$u) { header('Location: superadmin_dashboard.php?error=notfound'); exit(); }
            $new = ($u['status']==='active')? 'inactive':'active';
            $ok = updateUser($id, ['status'=>$new]);
            header('Location: superadmin_dashboard.php?success=user_toggled'); exit();
        }
        header('Location: superadmin_dashboard.php?error=invalid_id'); exit();

    case 'delete_user':
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $ok = deleteUser($id);
            if ($ok) header('Location: superadmin_dashboard.php?success=user_deleted');
            else header('Location: superadmin_dashboard.php?error=delete_failed');
        } else header('Location: superadmin_dashboard.php?error=invalid_id');
    exit();

    case 'reassign_lead':
        // Allow admins and superadmins to reassign leads
        require_role(['admin', 'superadmin']);
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $assigned_to = intval($_POST['assigned_to'] ?? 0);
        
        if ($lead_id && $assigned_to) {
            $ok = updateLead($lead_id, ['assigned_to' => $assigned_to]);
            if ($ok) header('Location: leads_advanced.php?success=reassigned');
            else header('Location: leads_advanced.php?error=reassign_failed');
        } else header('Location: leads_advanced.php?error=invalid_data');
    exit();

    default:
        header('Location: superadmin_dashboard.php'); exit();
}

?>