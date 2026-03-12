<?php
include 'db.php';

echo "Testing authenticateUser function...\n\n";

$user = authenticateUser('superadmin', 'super123');

if ($user) {
    echo "Authentication SUCCESS!\n";
    echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Authentication FAILED\n";
    echo "Testing raw password_verify...\n";
    
    if ($db_type === 'pdo') {
        $stmt = $pdo->query("SELECT password_hash FROM users WHERE username = 'superadmin'");
        $row = $stmt->fetch();
    } else {
        $res = $conn->query("SELECT password_hash FROM users WHERE username = 'superadmin'");
        $row = $res->fetch_assoc();
    }
    
    if ($row) {
        echo "Hash from DB: " . substr($row['password_hash'], 0, 50) . "...\n";
        $verify = password_verify('super123', $row['password_hash']);
        echo "password_verify result: " . ($verify ? "TRUE" : "FALSE") . "\n";
    }
}
