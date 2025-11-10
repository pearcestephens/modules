<?php
/**
 * Check Email Queue Status
 */

require_once __DIR__ . '/../app.php';

function getDb(): PDO {
    if (function_exists('cis_pdo')) {
        return cis_pdo();
    }
    $config = require __DIR__ . '/../config/database.php';
    $db = $config['cis'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']);
    return new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

$db = getDb();

echo "=== Email Queue Status ===\n\n";

// Get recent emails
$stmt = $db->query('
    SELECT id, email_to, subject, status, attempts, created_at, sent_at, error_message
    FROM email_queue
    ORDER BY id DESC
    LIMIT 10
');

$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($emails)) {
    echo "No emails in queue.\n";
} else {
    foreach ($emails as $email) {
        echo "ID: {$email['id']}\n";
        echo "To: {$email['email_to']}\n";
        echo "Subject: {$email['subject']}\n";
        echo "Status: {$email['status']}\n";
        echo "Attempts: {$email['attempts']}\n";
        echo "Created: {$email['created_at']}\n";
        echo "Sent: " . ($email['sent_at'] ?? 'Not sent') . "\n";
        if ($email['error_message']) {
            echo "Error: {$email['error_message']}\n";
        }
        echo str_repeat('-', 50) . "\n";
    }
}

// Test SendGrid connection
echo "\n=== SendGrid Connection Test ===\n";
require_once __DIR__ . '/shared/services/SendGridService.php';
use CIS\Shared\Services\SendGridService;

$testResult = SendGridService::test();
echo "Status: " . ($testResult['success'] ? '✅ Connected' : '❌ Failed') . "\n";
echo "Message: {$testResult['message']}\n";
if (isset($testResult['account'])) {
    echo "Account: {$testResult['account']}\n";
}
