<?php
// fix_lead_assignments.php
// Run this script once to fix missing lead assignments

require_once 'db.php';
session_start();

// Basic security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    die("Access denied. Please log in as Admin or SuperAdmin first.");
}

echo "<h1>Fixing Lead Assignments</h1>";

global $conn, $db_type;

// 1. Ensure table exists
$create = "CREATE TABLE IF NOT EXISTS lead_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY ux_lead_user (lead_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($db_type === 'pdo') {
    $conn->exec($create);
} else {
    $conn->query($create);
}
echo "✅ Table 'lead_assignments' checked.<br>";

// 2. Get all leads
$sql = "SELECT id, assigned_to FROM leads";
if ($db_type === 'pdo') {
    $stmt = $conn->query($sql);
    $leads = $stmt->fetchAll();
} else {
    $result = $conn->query($sql);
    $leads = [];
    while ($row = $result->fetch_assoc()) $leads[] = $row;
}

echo "🔍 Found " . count($leads) . " leads in database.<br>";

// 3. Insert assignments
$count = 0;
foreach ($leads as $lead) {
    // Default to user ID 2 (Admin) if assigned_to is 0 or null
    $user_id = !empty($lead['assigned_to']) ? $lead['assigned_to'] : 2;
    
    if (addLeadAssignment($lead['id'], $user_id)) {
        $count++;
    }
}

echo "✅ Fixed/Verified assignments for $count leads.<br>";
echo "<br><a href='index.php'>Go back to Dashboard</a>";
?>