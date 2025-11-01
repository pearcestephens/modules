<?php
/**
 * Email Queue Processor
 *
 * Processes pending emails from email_queue table using SendGrid
 *
 * Run via cron:
 * */5 * * * * php /path/to/process-email-queue.php
 *
 * @package CIS\Shared
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app.php';
require_once __DIR__ . '/SendGridService.php';

use CIS\Shared\Services\SendGridService;

echo "=== Email Queue Processor ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get database connection
    $db = function_exists('cis_pdo') ? cis_pdo() : ($GLOBALS['pdo'] ?? null);
    if (!$db instanceof PDO) {
        throw new Exception('Database connection unavailable');
    }

    // Test SendGrid connection first
    echo "Testing SendGrid connection...\n";
    $testResult = SendGridService::test();
    if (!$testResult['success']) {
        throw new Exception('SendGrid test failed: ' . $testResult['message']);
    }
    echo "✅ SendGrid connected: {$testResult['account']}\n\n";

    // Get pending emails (limit to 50 per run)
    $stmt = $db->prepare(
        "SELECT * FROM email_queue
         WHERE status = 'pending'
         AND attempts < 3
         ORDER BY priority ASC, created_at ASC
         LIMIT 50"
    );
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails)) {
        echo "No pending emails to process.\n";
        exit(0);
    }

    echo "Found " . count($emails) . " pending email(s)\n\n";

    $sent = 0;
    $failed = 0;

    foreach ($emails as $email) {
        $id = (int)$email['id'];
        $to = $email['email_to'];
        $subject = $email['subject'];

        echo "Processing email #{$id} to {$to}...\n";
        echo "  Subject: {$subject}\n";

        // Update attempts counter
        $updateAttempt = $db->prepare(
            "UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?"
        );
        $updateAttempt->execute([$id]);

        // Send via SendGrid
        $result = SendGridService::sendFromQueue($email);

        if ($result['success']) {
            // Mark as sent
            $updateSent = $db->prepare(
                "UPDATE email_queue
                 SET status = 'sent', sent_at = NOW(), last_error = NULL
                 WHERE id = ?"
            );
            $updateSent->execute([$id]);

            echo "  ✅ Sent successfully\n";
            $sent++;
        } else {
            // Mark as failed (if max attempts reached)
            $newStatus = ($email['attempts'] + 1 >= 3) ? 'failed' : 'pending';
            $updateFailed = $db->prepare(
                "UPDATE email_queue
                 SET status = ?, last_error = ?
                 WHERE id = ?"
            );
            $updateFailed->execute([
                $newStatus,
                $result['message'],
                $id
            ]);

            echo "  ❌ Failed: {$result['message']}\n";
            if ($newStatus === 'failed') {
                echo "  ⚠️  Max attempts reached, marked as failed\n";
            }
            $failed++;
        }

        echo "\n";
    }

    echo "=== Processing Complete ===\n";
    echo "Sent: {$sent}\n";
    echo "Failed: {$failed}\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
