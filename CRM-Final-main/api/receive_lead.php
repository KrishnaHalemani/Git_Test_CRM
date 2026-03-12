<?php
// Simple API endpoint to receive leads from external website forms
// Security: API key expected in X-CRM-API-KEY header or `api_key` POST/GET field

// Response JSON
header('Content-Type: application/json; charset=utf-8');

// Allow preflight and simple CORS handling. Configure allowed origins in settings (key: lead_api_allowed_origins)
if (strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
    // Allow all for now or set via settings
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CRM-API-KEY');
    http_response_code(204);
    exit();
}

// include DB and helpers
include_once __DIR__ . '/../db.php';

// Read allowed origins from settings (if set)
$allowed_origins = getSetting('lead_api_allowed_origins', '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowed_origins === '*' || strpos($allowed_origins, $origin) !== false) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
}

// Get API key expected value from settings
$expected_key = getSetting('lead_api_key', null);
// Fallback to environment variable or a default placeholder (please change in production)
if (!$expected_key) {
    $expected_key = getenv('CRM_LEAD_API_KEY') ?: 'changeme_crm_api_key';
}

// Get API key from header or request
$api_key = null;
if (!empty($_SERVER['HTTP_X_CRM_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_CRM_API_KEY'];
} elseif (!empty($_REQUEST['api_key'])) {
    $api_key = $_REQUEST['api_key'];
}

if (!$api_key || $api_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - invalid API key']);
    exit();
}

// Read input (JSON or form-encoded)
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    // fallback to $_POST
    $input = $_POST;
}

// Basic required fields
$name = trim($input['name'] ?? ($input['full_name'] ?? ''));
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$company = trim($input['company'] ?? '');
$service = trim($input['service'] ?? ($input['interest'] ?? ''));
$source = trim($input['source'] ?? 'website');
$message = trim($input['message'] ?? $input['notes'] ?? '');

if (empty($name) && empty($email) && empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required lead information. Provide at least a name, email or phone.']);
    exit();
}

// Sanitize input (simple)
$name = substr($name, 0, 255);
$email = substr($email, 0, 255);
$phone = substr($phone, 0, 50);
$company = substr($company, 0, 255);
$service = substr($service, 0, 255);
$message = substr($message, 0, 2000);

// Determine assignment like in submit-lead.php
$adminId = null;
if (function_exists('getUserIdByUsername')) {
    $adminId = getUserIdByUsername('admin');
}
if (!$adminId && function_exists('getAdmins')) {
    $admins = getAdmins();
    if (!empty($admins)) $adminId = $admins[0]['id'];
}
if (!$adminId) $adminId = null; // leave null if none

$lead = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'company' => $company,
    'service' => $service,
    'source' => $source,
    'status' => 'new',
    'priority' => $input['priority'] ?? 'medium',
    'notes' => $message,
    'assigned_to' => $adminId,
    'created_by' => $adminId,
    'estimated_value' => isset($input['estimated_value']) ? (function_exists('parseAmountToINR') ? parseAmountToINR($input['estimated_value'], $service) : floatval($input['estimated_value'])) : 0.00
];

// Insert lead using existing helper
$leadId = addLead($lead);

if ($leadId) {
    http_response_code(201);
    echo json_encode(['success' => true, 'lead_id' => $leadId]);
    exit();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create lead']);
    exit();
}
