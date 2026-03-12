<?php
// cpanel_debug.php
// Upload this to your live server root (e.g., public_html/CRM2/) to diagnose issues.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>CRM Live Environment Debugger</h1>";

// 1. Check Database Connection
echo "<h2>1. Database Connection</h2>";
if (!file_exists('db.php')) {
    die("<p style='color:red'>❌ db.php not found in this directory. Please upload it.</p>");
}

require_once 'db.php';

// Detect connection type from db.php (PDO or MySQLi)
global $pdo, $conn;
$db_connected = false;
$driver = '';

if (isset($pdo) && $pdo instanceof PDO) {
    echo "<p style='color:green'>✅ Connected using PDO.</p>";
    $db_connected = true;
    $driver = 'pdo';
} elseif (isset($conn) && $conn instanceof mysqli) {
    echo "<p style='color:green'>✅ Connected using MySQLi.</p>";
    $db_connected = true;
    $driver = 'mysqli';
} else {
    echo "<p style='color:red'>❌ Database connection failed. Check credentials in db.php.</p>";
    global $db_error;
    if (!empty($db_error)) {
        echo "<p style='background:#ffebee; padding:10px; border:1px solid red;'><strong>Error Details:</strong> " . htmlspecialchars($db_error) . "</p>";
    }
}

// 2. Check Task Manager Tables
if ($db_connected) {
    echo "<h2>2. Task Manager Tables Check</h2>";
    $required_tables = ['tasks', 'task_comments', 'task_reminders', 'task_activity_log'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        $exists = false;
        if ($driver === 'pdo') {
            try {
                $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                $exists = true;
            } catch (Exception $e) {
                $exists = false;
            }
        } else {
            $result = $conn->query("SELECT 1 FROM $table LIMIT 1");
            if ($result) $exists = true;
        }

        if ($exists) {
            echo "<p>✅ Table <strong>$table</strong> exists.</p>";
        } else {
            echo "<p style='color:red'>❌ Table <strong>$table</strong> is MISSING.</p>";
            $missing_tables[] = $table;
        }
    }

    if (!empty($missing_tables)) {
        echo "<div style='background:#ffebee; padding:15px; border:1px solid red; margin-top:10px;'>";
        echo "<h3>⚠️ Action Required</h3>";
        echo "<p>The Task Manager is not working because the database tables are missing.</p>";
        echo "<p><strong>Solution:</strong> Go to phpMyAdmin in your C-Panel and import <code>task_manager_schema.sql</code>.</p>";
        echo "</div>";
    } else {
        echo "<p style='color:green; font-weight:bold;'>All tables appear to be present.</p>";
    }
}

// 3. Check Critical Files
echo "<h2>3. File Existence Check</h2>";
$files = ['task_manager.php', 'task_actions.php', 'task_view.php'];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file found.</p>";
    } else {
        echo "<p style='color:red'>❌ $file NOT found. Please upload this file.</p>";
    }
}
?>