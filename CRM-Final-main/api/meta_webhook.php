<?php
/**
 * Meta Ads Lead Integration Webhook
 * Receives leads from Meta Lead Ads form and inserts them into CRM
 * 
 * Setup:
 * 1. In Meta Business Suite, configure the webhook URL to: https://your-crm-domain/CRM2/api/meta_webhook.php
 * 2. Add your Meta verification token in the settings table (see setup instructions)
 * 3. Meta will send form submissions as POST requests to this endpoint
 */

header('Content-Type: application/json');
require_once '../db.php';

// Get Meta verification token from CRM settings
$meta_verify_token = getSetting('meta_verify_token');
$meta_webhook_secret = getSetting('meta_webhook_secret');

/**
 * Step 1: Handle Meta verification request (when first setting up webhook)
 * Meta sends a GET request with challenge parameter to verify the webhook URL
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleMetaVerification();
    exit();
}

/**
 * Step 2: Handle lead submission from Meta (POST request)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMetaLead();
    exit();
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit();

/**
 * Verify the webhook challenge from Meta
 */
function handleMetaVerification() {
    global $meta_verify_token;
    
    $verify_token = $_GET['verify_token'] ?? null;
    $challenge = $_GET['challenge'] ?? null;
    
    // If token matches, return challenge to confirm webhook
    if ($verify_token === $meta_verify_token) {
        http_response_code(200);
        echo $challenge;
        return;
    }
    
    // Token mismatch
    http_response_code(403);
    echo json_encode(['error' => 'Verification token mismatch']);
}

/**
 * Process lead submission from Meta
 */
function handleMetaLead() {
    global $conn, $db_type, $meta_webhook_secret;
    
    // Get raw POST data
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    // Validate request signature (optional but recommended for security)
    if ($meta_webhook_secret) {
        if (!validateMetaSignature($raw_data)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            error_log('Meta webhook: Signature validation failed');
            return;
        }
    }
    
    // Ensure data is present
    if (!$data || !isset($data['entry'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing entry data']);
        return;
    }
    
    // Process each entry (Meta may send batches)
    $processed = 0;
    $errors = [];
    
    foreach ($data['entry'] as $entry) {
        // Extract lead data from Meta webhook
        $leads = $entry['changes'][0]['value']['leadgen_id'] ?? null;
        
        if (!$leads) {
            continue;
        }
        
        // Meta sends minimal data in the webhook; you'll need to:
        // 1. Store the leadgen_id
        // 2. Use Meta's Graph API to fetch full lead details
        
        // For now, create a lead with minimal info from webhook
        $lead_data = [
            'name' => 'Meta Lead (ID: ' . $leads . ')',
            'email' => '',
            'phone' => '',
            'source' => 'meta-ads',
            'meta_leadgen_id' => $leads,
            'status' => 'new',
            'assigned_to' => null, // Will be assigned by admin later
            'created_by' => 1, // System/API
            'notes' => 'Received from Meta Ads webhook at ' . date('Y-m-d H:i:s')
        ];
        
        // Insert lead into CRM
        try {
            $lead_id = addLead($lead_data);
            if ($lead_id) {
                $processed++;
                // Log success
                error_log('Meta webhook: Lead created with ID ' . $lead_id);
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to create lead: ' . $e->getMessage();
            error_log('Meta webhook error: ' . $e->getMessage());
        }
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'errors' => $errors
    ]);
}

/**
 * Validate Meta webhook signature
 * Meta includes an X-Hub-Signature header with HMAC signature
 */
function validateMetaSignature($raw_data) {
    global $meta_webhook_secret;
    
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? null;
    
    if (!$signature) {
        error_log('Meta webhook: Missing X-Hub-Signature header');
        return false;
    }
    
    // Signature format: sha256=<hash>
    list($algo, $hash) = explode('=', $signature, 2);
    
    if ($algo !== 'sha256') {
        error_log('Meta webhook: Unsupported signature algorithm: ' . $algo);
        return false;
    }
    
    // Calculate expected signature
    $expected_hash = hash_hmac('sha256', $raw_data, $meta_webhook_secret);
    
    // Use timing-safe comparison to prevent timing attacks
    if (!hash_equals($hash, $expected_hash)) {
        error_log('Meta webhook: Signature mismatch');
        return false;
    }
    
    return true;
}

/**
 * Fetch full lead details from Meta Graph API (optional advanced step)
 * Requires Meta access token with leads:read permission
 * 
 * Usage: $full_lead = fetchMetaLeadDetails($leadgen_id, $access_token);
 */
function fetchMetaLeadDetails($leadgen_id, $access_token) {
    $url = 'https://graph.instagram.com/v18.0/' . $leadgen_id . '?fields=id,created_time,field_data&access_token=' . urlencode($access_token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}
?>
