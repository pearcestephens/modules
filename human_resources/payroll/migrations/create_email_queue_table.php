<?php
/**
 * Create email_queue table for payroll email functionality
 * Run: php create_email_queue_table.php
 */

require_once __DIR__ . '/../../../base/Database.php';

use CIS\Base\Database;

echo "Creating email_queue table...\n\n";

try {
    $conn = Database::vapeshed();

    $sql = "CREATE TABLE IF NOT EXISTS email_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email_from VARCHAR(255) NOT NULL,
        email_to VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        html_body TEXT,
        text_body TEXT,
        attachments JSON DEFAULT NULL,
        priority INT DEFAULT 2 COMMENT '1=immediate, 2=batched, 3=digest',
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_status (status),
        INDEX idx_created (created_at),
        INDEX idx_priority (priority, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✅ email_queue table created successfully!\n\n";

        // Verify table structure
        $result = $conn->query("DESCRIBE email_queue");
        echo "Table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
        echo "\n✅ Setup complete!\n";
    } else {
        echo "❌ Error: " . $conn->error . "\n";
    }

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
