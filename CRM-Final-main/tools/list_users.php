<?php
include __DIR__ . '/../db.php';
$users = getAllUsers(10000);
echo "Total users: " . count($users) . "\n";
foreach ($users as $u) {
    echo sprintf("ID:%s | username:%s | role:%s | status:%s\n", $u['id'] ?? '', $u['username'] ?? '', $u['role'] ?? '', $u['status'] ?? '');
}
