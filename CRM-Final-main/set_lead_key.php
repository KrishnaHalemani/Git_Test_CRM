<?php
// One-off script to set or generate a lead API key in CRM settings
// WARNING: run this once and then remove or protect the file
include 'db.php';

function random_key($length = 40) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length/2));
    }
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=';
    $str = '';
    for ($i=0;$i<$length;$i++) $str .= $chars[rand(0, strlen($chars)-1)];
    return $str;
}

$provided = $argv[1] ?? null;
$key = $provided ?: random_key(48);

$ok = setSetting('lead_api_key', $key);
setSetting('lead_api_allowed_origins', '*');
if ($ok) {
    echo "Lead API key set successfully:\n";
    echo $key . "\n";
    echo "IMPORTANT: store this key safely and remove or protect this script.\n";
} else {
    echo "Failed to set lead_api_key. Check DB connection and settings table.\n";
}
