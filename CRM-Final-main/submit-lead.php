<?php
include 'db.php';

// Check if form was submitted
if($_POST) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $company = $_POST['company'] ?? '';
    $service = $_POST['service'] ?? '';
    $form_source = $_POST['form_source'] ?? 'website';
    $message = $_POST['message'] ?? '';

    // Determine assignment: prefer configured admin user, fallback to first admin
    $adminId = null;
    if (function_exists('getUserIdByUsername')) {
        $adminId = getUserIdByUsername('admin');
    }
    if (!$adminId && function_exists('getAdmins')) {
        $admins = getAdmins();
        if (!empty($admins)) $adminId = $admins[0]['id'];
    }
    if (!$adminId) $adminId = null; // leave null if no admin exists

    // Create lead array for MySQL
    $lead = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'service' => $service,
        'source' => $form_source,
        'status' => 'new',
        'priority' => 'medium',
        'notes' => $message,
        'assigned_to' => $adminId,
        'created_by' => $adminId,
        'estimated_value' => 0.00
    ];

    // Add lead to database
    $leadId = addLead($lead);

    // Optional: Send Email Notification (requires PHPMailer setup)
    /*
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';
    require 'PHPMailer/Exception.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your_email@gmail.com';
        $mail->Password = 'your_app_password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your_email@gmail.com', 'CRM Notifier');
        $mail->addAddress('admin_email@gmail.com');
        $mail->Subject = 'New Lead Received - CRM Pro';
        $mail->Body = "New Lead Details:\n\nName: $name\nEmail: $email\nPhone: $phone\nCompany: $company\nService: $service\nMessage: $message\n\nLead ID: $leadId";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
    */

    // Optional WhatsApp link
    $wa_msg = urlencode("New Lead from CRM Pro:\nName: $name\nPhone: $phone\nService: $service");
    $wa_url = "https://wa.me/$phone?text=$wa_msg";

    // Redirect to thank you page
    header("Location: thank-you.php?success=1");
    exit();
} else {
    // If not a POST request, redirect to home
    header("Location: index.php");
    exit();
}
?>