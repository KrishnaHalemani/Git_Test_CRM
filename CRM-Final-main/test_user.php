<?php
include 'db.php';

echo "Database connection: " . ($conn ? "SUCCESS" : "FAILED") . "\n";
echo "DB Type: " . $db_type . "\n";

if ($conn) {
    if ($db_type === 'pdo') {
        $stmt = $pdo->query("SELECT id, username, role, status FROM users WHERE username = 'superadmin'");
        $user = $stmt->fetch();
    } else {
        $res = $conn->query("SELECT id, username, role, status FROM users WHERE username = 'superadmin'");
        $user = $res->fetch_assoc();
    }
    
    if ($user) {
        echo "User found: " . json_encode($user, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "User 'superadmin' not found in database\n";
        echo "Available users:\n";
        if ($db_type === 'pdo') {
            $stmt = $pdo->query("SELECT username, role FROM users");
            $users = $stmt->fetchAll();
        } else {
            $res = $conn->query("SELECT username, role FROM users");
            $users = [];
            while ($row = $res->fetch_assoc()) {
                $users[] = $row;
            }
        }
        echo json_encode($users, JSON_PRETTY_PRINT) . "\n";
    }
}
