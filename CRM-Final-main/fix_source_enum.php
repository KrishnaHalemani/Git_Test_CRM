<?php
// Temporary script to fix the ENUM definition for the 'source' column in the 'leads' table.

require_once 'db.php';

if (!db_is_connected()) {
    echo "Error: Database connection failed. Cannot apply schema fix.\n";
    exit(1);
}

try {
    // The current ENUM is: ('website', 'social-media', 'referral', 'advertisement', 'manual', 'other')
    // The import script tries to insert 'franchise', 'course', and 'service'.
    // This command modifies the column to include these new values.
    $sql = "ALTER TABLE leads MODIFY COLUMN source ENUM('website', 'social-media', 'referral', 'advertisement', 'manual', 'other', 'franchise', 'course', 'service') DEFAULT 'manual';";
    
    if ($db_type === 'pdo') {
        $conn->exec($sql);
    } else {
        $conn->query($sql);
    }
    
    echo "✅ Success: The 'leads' table schema has been updated to support 'franchise', 'course', and 'service' sources.\n";
    echo "You should now be able to import your CSV files successfully.\n";

} catch (Exception $e) {
    echo "❌ Error: Failed to update the database schema.\n";
    echo "Message: " . $e->getMessage() . "\n";
    exit(1);
}

