<?php
// Run this script once via browser or CLI to migrate DB schema for CRM Pro.
// Usage: php migrate.php  OR open http://localhost/CRM2/migrate.php

include 'db.php';

if (!db_is_connected()) {
    echo "Database not connected: " . ($db_error ?? 'unknown');
    exit;
}

$messages = [];

// Add phone and branch columns to users table if missing
try {
    if ($db_type === 'pdo') {
        $cols = $conn->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
        $res = $conn->query("SHOW COLUMNS FROM users");
        $cols = [];
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    }

    if (!in_array('phone', $cols)) {
        $sql = "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL";
        $conn->exec($sql);
        $messages[] = "Added 'phone' column to users table.";
    } else $messages[] = "'phone' column already exists.";

    if (!in_array('branch', $cols)) {
        $sql = "ALTER TABLE users ADD COLUMN branch VARCHAR(100) DEFAULT NULL";
        $conn->exec($sql);
        $messages[] = "Added 'branch' column to users table.";
    } else $messages[] = "'branch' column already exists.";

} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
    exit;
}

// Ensure uploads directory exists
$uploads = __DIR__ . '/uploads';
if (!file_exists($uploads)) {
    mkdir($uploads, 0755, true);
    $messages[] = "Created uploads directory.";
} else $messages[] = "uploads directory exists.";

// Ensure settings keys exist for company logo and theme
try {
    if ($db_type === 'pdo') {
        $stmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key IN ('company_logo','theme_color')");
        $stmt->execute();
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
        $res = $conn->query("SELECT setting_key FROM settings WHERE setting_key IN ('company_logo','theme_color')");
        $existing = [];
        while ($r = $res->fetch_assoc()) $existing[] = $r['setting_key'];
    }

    if (!in_array('company_logo', $existing)) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute(['company_logo', '', 'string', 'Company logo path']);
        $messages[] = "Inserted company_logo setting.";
    } else $messages[] = "company_logo setting exists.";

    if (!in_array('theme_color', $existing)) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute(['theme_color', '#667eea', 'string', 'Primary theme color']);
        $messages[] = "Inserted theme_color setting.";
    } else $messages[] = "theme_color setting exists.";
} catch (Exception $e) {
    $messages[] = "Settings migration warning: " . $e->getMessage();
}

// Fix for leads.source column to allow new import types
try {
    $column_type = '';
    if ($db_type === 'pdo') {
        $stmt = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'source'");
        $column_type = $stmt->fetchColumn();
    } else {
        $res = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'source'");
        $row = $res->fetch_assoc();
        $column_type = $row['COLUMN_TYPE'];
    }

    if ($column_type && strpos($column_type, "'franchise'") === false) {
        $sql = "ALTER TABLE leads MODIFY COLUMN source ENUM('website', 'social-media', 'referral', 'advertisement', 'manual', 'other', 'franchise', 'course', 'service') DEFAULT 'manual'";
        if ($db_type === 'pdo') {
            $conn->exec($sql);
        } else {
            $conn->query($sql);
        }
        $messages[] = "✅ <b>FIXED:</b> Updated 'leads.source' column to allow 'franchise', 'course', and 'service'. The import should now work.";
    } else {
        $messages[] = "✅ 'leads.source' column is already up-to-date.";
    }
} catch (Exception $e) {
    $messages[] = "⚠️ Could not update 'leads.source' column. " . $e->getMessage();
}

echo "<h2>Migration Results</h2>
<ul>";
foreach ($messages as $m) echo "<li>" . ($m) . "</li>
";
echo "</ul>
";

echo "<p>Done. If SQL changes were applied, please verify your application.</p>
";
?>