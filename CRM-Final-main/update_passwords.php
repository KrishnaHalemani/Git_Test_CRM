<?php
include 'db.php';

echo "Updating test user passwords to known hashes...\n\n";

$testUsers = [
    ['username' => 'superadmin', 'password' => 'super123'],
    ['username' => 'admin', 'password' => 'admin123'],
    ['username' => 'user', 'password' => 'user123']
];

foreach ($testUsers as $testUser) {
    $hash = password_hash($testUser['password'], PASSWORD_BCRYPT);
    $username = $testUser['username'];
    
    if ($db_type === 'pdo') {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $result = $stmt->execute([$hash, $username]);
    } else {
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $stmt->bind_param("ss", $hash, $username);
        $result = $stmt->execute();
    }
    
    echo "Updated {$username}: " . ($result ? "SUCCESS" : "FAILED") . "\n";
}

echo "\nVerifying authentication works now...\n\n";
foreach ($testUsers as $testUser) {
    $user = authenticateUser($testUser['username'], $testUser['password']);
    echo $testUser['username'] . " / " . $testUser['password'] . ": " . ($user ? "AUTHENTICATED" : "FAILED") . "\n";
}
