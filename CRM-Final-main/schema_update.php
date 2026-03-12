<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'crm_pro';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Check if branch column exists in users table
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'branch'");
    
    if ($result->rowCount() == 0) {
        // Add branch field to users table
        $pdo->exec("ALTER TABLE users ADD COLUMN branch VARCHAR(100) DEFAULT 'Head Office' AFTER role");
        echo "✓ Branch field added to users table successfully<br>";
    } else {
        echo "✓ Branch field already exists in users table<br>";
    }
    
    // Check if walk_in field exists in leads table
    $result = $pdo->query("SHOW COLUMNS FROM leads LIKE 'walk_in'");
    
    if ($result->rowCount() == 0) {
        // Add walk_in field to leads table
        $pdo->exec("ALTER TABLE leads ADD COLUMN walk_in BOOLEAN DEFAULT FALSE AFTER source");
        echo "✓ Walk-in field added to leads table successfully<br>";
    } else {
        echo "✓ Walk-in field already exists in leads table<br>";
    }
    
    echo "<br>✓ Database schema updated successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
