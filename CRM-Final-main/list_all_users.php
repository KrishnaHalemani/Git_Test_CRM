<?php
include 'db.php';

echo "=== ALL USERS IN DATABASE ===\n\n";

if ($db_type === 'pdo') {
    $stmt = $pdo->query("SELECT id, username, role, status, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
} else {
    $res = $conn->query("SELECT id, username, role, status, created_at FROM users ORDER BY id");
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

foreach ($users as $user) {
    echo "ID: {$user['id']} | Username: {$user['username']} | Role: {$user['role']} | Status: {$user['status']}\n";
}

echo "\n=== CURRENT WORKING LOGIN CREDENTIALS ===\n";
echo "superadmin / super123\n";
echo "admin / admin123\n";
echo "user / user123\n";
