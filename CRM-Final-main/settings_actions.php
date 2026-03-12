<?php
session_start();
include 'db.php';
require_role(['superadmin']);

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_phone = $_POST['company_phone'] ?? '';
    $theme_color = $_POST['theme_color'] ?? '';

    if ($company_name !== '') setSetting('company_name', $company_name);
    if ($company_email !== '') setSetting('company_email', $company_email);
    if ($company_phone !== '') setSetting('company_phone', $company_phone);
    if ($theme_color !== '') setSetting('theme_color', $theme_color);

    // Handle logo upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        $tmp = $_FILES['company_logo']['tmp_name'];
        $name = basename($_FILES['company_logo']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','gif'];
        if (in_array($ext, $allowed)) {
            $newName = 'logo_' . time() . '.' . $ext;
            $dest = $uploadDir . $newName;
            if (move_uploaded_file($tmp, $dest)) {
                // Store web-accessible path (relative)
                $webPath = 'uploads/' . $newName;
                setSetting('company_logo', $webPath);
            }
        }
    }

    header('Location: superadmin_dashboard.php?success=settings_saved');
    exit();
}

header('Location: superadmin_dashboard.php');
exit();
?>