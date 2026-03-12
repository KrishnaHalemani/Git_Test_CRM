<?php
// Simple Database Connection Test

echo "<h2>Database Connection Test</h2>";

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'crm_pro';

try {
    // Test basic MySQL connection
    echo "<p>Testing MySQL connection...</p>";
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ MySQL connection successful!</p>";

    // Test database connection
    echo "<p>Testing database 'crm_pro' connection...</p>";
    $pdo_db = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Database 'crm_pro' connection successful!</p>";

    // Test if users table exists and has data
    echo "<p>Checking users table...</p>";
    $stmt = $pdo_db->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    echo "<p style='color: green;'>✓ Users table exists with $userCount users!</p>";

    // List all users
    echo "<p>Current users in database:</p>";
    $stmt = $pdo_db->query("SELECT username, full_name, role FROM users");
    $users = $stmt->fetchAll();
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li><strong>{$user['username']}</strong> ({$user['full_name']}) - Role: {$user['role']}</li>";
    }
    echo "</ul>";

    echo "<h3 style='color: green;'>🎉 Database is ready!</h3>";
    echo "<p><a href='login.php' style='background: #6366f1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        echo "<p style='color: orange;'>⚠ The 'crm_pro' database doesn't exist yet.</p>";
        echo "<p><a href='auto_setup.php' style='background: #f59e0b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Auto Setup</a></p>";
    } else {
        echo "<p><strong>Troubleshooting:</strong></p>";
        echo "<ul>";
        echo "<li>Make sure MySQL is running in XAMPP Control Panel</li>";
        echo "<li>Check if the MySQL username and password are correct</li>";
        echo "<li>Try running the auto setup script</li>";
        echo "</ul>";
    }
}
?>
