<?php
/**
 * Test VapeShed Database Connection
 * Purpose: Verify Database::vapeshed() works correctly
 */

// Load base Database class
require_once __DIR__ . '/../../../base/Database.php';

use CIS\Base\Database;

echo "=== VapeShed Connection Test (Base Database) ===\n\n";

// Test 1: Get connection
echo "Testing Database::vapeshed()...\n";
try {
    $conn = Database::vapeshed();

    if ($conn instanceof mysqli) {
        echo "✅ Got valid mysqli object\n\n";

        // Test 2: Check database name
        $result = $conn->query("SELECT DATABASE() as db");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Connected to database: {$row['db']}\n\n";
        }

        // Test 3: Check if email_queue table exists
        $result = $conn->query("SHOW TABLES LIKE 'email_queue'");
        if ($result && $result->num_rows > 0) {
            echo "✅ email_queue table exists\n\n";

            // Test 4: Get table structure
            $result = $conn->query("DESCRIBE email_queue");
            if ($result) {
                echo "Email queue table structure:\n";
                while ($row = $result->fetch_assoc()) {
                    echo "  - {$row['Field']} ({$row['Type']})\n";
                }
                echo "\n";
            }

            // Test 5: Count queued emails
            $result = $conn->query("SELECT COUNT(*) as count FROM email_queue WHERE sent_at IS NULL");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "📧 Pending emails in queue: {$row['count']}\n\n";
            }
        } else {
            echo "⚠️  email_queue table NOT found\n";
            echo "Need to create email_queue table in VapeShed database\n\n";
        }

        echo "✅ Connection test complete\n";
    } else {
        echo "❌ Unexpected return type: " . gettype($conn) . "\n";
        var_dump($conn);
    }

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
