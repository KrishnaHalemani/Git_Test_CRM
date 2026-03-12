<?php
session_start();
include 'db.php';

// Check database connection
echo "<h1>Analytics Dashboard Test</h1>";

// Test 1: Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo "<div style='color: red;'><strong>ERROR:</strong> User not logged in. Redirecting to login...</div>";
    header("Location: login.php");
    exit();
}

echo "<div style='color: green;'><strong>✓ User logged in as:</strong> " . htmlspecialchars($_SESSION['username']) . " (Role: " . htmlspecialchars($_SESSION['role']) . ")</div><br>";

// Test 2: Check database connection
try {
    if($db_type === 'pdo') {
        $testStmt = $pdo->query("SELECT 1");
        echo "<div style='color: green;'><strong>✓ Database connection OK (PDO)</strong></div><br>";
    } else {
        echo "<div style='color: green;'><strong>✓ Database connection OK (mysqli)</strong></div><br>";
    }
} catch(Exception $e) {
    echo "<div style='color: red;'><strong>✗ Database connection ERROR:</strong> " . $e->getMessage() . "</div><br>";
}

// Test 3: Check if getLeads() function works
try {
    $leads = getLeads();
    echo "<div style='color: green;'><strong>✓ getLeads() function works - Found " . count($leads) . " leads</strong></div><br>";
} catch(Exception $e) {
    echo "<div style='color: red;'><strong>✗ getLeads() ERROR:</strong> " . $e->getMessage() . "</div><br>";
}

// Test 4: Check leads table structure
echo "<h3>Leads Table Structure:</h3>";
try {
    if($db_type === 'pdo') {
        $columns = $pdo->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' style='margin-bottom: 20px;'><tr><th>Field</th><th>Type</th></tr>";
        foreach($columns as $col) {
            echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td></tr>";
        }
        echo "</table>";
        
        // Check for walk_in column
        $hasWalkIn = false;
        foreach($columns as $col) {
            if($col['Field'] === 'walk_in') {
                $hasWalkIn = true;
                break;
            }
        }
        
        if($hasWalkIn) {
            echo "<div style='color: green;'><strong>✓ walk_in column EXISTS</strong></div><br>";
        } else {
            echo "<div style='color: orange;'><strong>⚠ walk_in column NOT FOUND - Using source field for walk-in tracking</strong></div><br>";
        }
    }
} catch(Exception $e) {
    echo "<div style='color: red;'><strong>✗ ERROR checking table:</strong> " . $e->getMessage() . "</div><br>";
}

// Test 5: Sample walk-in query
echo "<h3>Walk-in Tracking Query Test:</h3>";
try {
    if($db_type === 'pdo') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE source = 'walk-in'");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<div style='color: green;'><strong>✓ Walk-in leads with source='walk-in':</strong> " . $result['count'] . "</div><br>";
    }
} catch(Exception $e) {
    echo "<div style='color: red;'><strong>✗ Walk-in query ERROR:</strong> " . $e->getMessage() . "</div><br>";
}

// Test 6: Sample data
echo "<h3>Sample Leads Data (first 5):</h3>";
try {
    $leads = getLeads();
    if(count($leads) > 0) {
        echo "<table border='1' style='width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Source</th><th>Status</th><th>Created</th></tr>";
        $count = 0;
        foreach($leads as $lead) {
            if($count >= 5) break;
            echo "<tr>";
            echo "<td>" . $lead['id'] . "</td>";
            echo "<td>" . htmlspecialchars($lead['name']) . "</td>";
            echo "<td>" . htmlspecialchars($lead['source']) . "</td>";
            echo "<td>" . htmlspecialchars($lead['status']) . "</td>";
            echo "<td>" . substr($lead['created_at'], 0, 10) . "</td>";
            echo "</tr>";
            $count++;
        }
        echo "</table>";
    } else {
        echo "<div style='color: orange;'><strong>⚠ No leads found in database</strong></div>";
    }
} catch(Exception $e) {
    echo "<div style='color: red;'><strong>✗ ERROR fetching leads:</strong> " . $e->getMessage() . "</div><br>";
}

echo "<br><br>";
echo "<a href='analytics_dashboard.php' class='btn btn-primary'>Go to Analytics Dashboard</a>";
?>
