<?php
/**
 * One-off script to generate and store Meta integration credentials
 * Run once, then delete this file
 * 
 * Usage: php set_meta_credentials.php
 */

require_once 'db.php';

// Generate secure random tokens
$meta_verify_token = bin2hex(random_bytes(32)); // 64-char hex string
$meta_webhook_secret = bin2hex(random_bytes(32)); // 64-char hex string

try {
    // Store in CRM settings table
    setSetting('meta_verify_token', $meta_verify_token);
    setSetting('meta_webhook_secret', $meta_webhook_secret);
    
    echo "✓ Meta credentials set successfully!\n\n";
    echo "====================================\n";
    echo "Meta Verification Token:\n";
    echo $meta_verify_token . "\n\n";
    echo "Meta Webhook Secret:\n";
    echo $meta_webhook_secret . "\n";
    echo "====================================\n\n";
    
    echo "IMPORTANT:\n";
    echo "1. Copy the tokens above and save them securely\n";
    echo "2. Use the Verification Token in Meta Business Suite webhook setup\n";
    echo "3. Keep the Webhook Secret safe (used to validate webhook signatures)\n";
    echo "4. DELETE this script (set_meta_credentials.php) after noting the tokens\n";
    
} catch (Exception $e) {
    echo "✗ Error setting credentials: " . $e->getMessage() . "\n";
    exit(1);
}
?>
