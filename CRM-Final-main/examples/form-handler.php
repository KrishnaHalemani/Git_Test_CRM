<?php
// Example form-handler.php for your website (server-side forwarding to CRM)
// Place this on your website server. It forwards form data to CRM's API endpoint.

// Basic validation and sanitization
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$company = trim($_POST['company'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) && empty($email) && empty($phone)) {
    // Bad request
    header('Location: /contact.php?error=missing');
    exit();
}

// Build payload
$payload = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'company' => $company,
    'service' => $service,
    'message' => $message,
    'source' => 'website-contact-form'
];

// CRM endpoint and API key (store API key in server environment variable or config file)
$crmUrl = 'https://your-crm-domain/CRM2/api/receive_lead.php';
$apiKey = 'REPLACE_WITH_YOUR_API_KEY';

$ch = curl_init($crmUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-CRM-API-KEY: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($http === 201) {
    header('Location: /thank-you.php');
    exit();
} else {
    error_log('CRM API error: ' . $http . ' resp: ' . $response . ' err: ' . $err);
    // Optionally fallback to local DB or send email to sales
    header('Location: /contact.php?error=server');
    exit();
}
