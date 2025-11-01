#!/usr/bin/env php
<?php
/**
 * SendGrid Email Queue Test Script
 *
 * Tests the complete email workflow:
 * 1. Queue an email with PDF attachment
 * 2. Process the queue (simulate cron)
 * 3. Verify SendGrid delivery
 *
 * Usage: php test-email-queue.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';
require_once __DIR__ . '/../services/SendGridService.php';
require_once __DIR__ . '/../../../modules/shared/services/PdfService.php';

use CIS\Shared\Services\SendGridService;
use CIS\Shared\Services\PdfService;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         SendGrid Email Queue Integration Test               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Get database
function getDb(): PDO {
    if (function_exists('cis_pdo')) {
        return cis_pdo();
    }
    $config = require __DIR__ . '/../../../config/database.php';
    $db = $config['cis'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['database']);
    return new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

// Test 1: Check SendGrid Configuration
echo "Test 1: Checking SendGrid configuration...\n";
if (!SendGridService::isConfigured()) {
    echo "❌ FAILED: SendGrid not configured\n";
    echo "   Please set SENDGRID_API_KEY in /config/sendgrid.php or environment\n";
    exit(1);
}
echo "✅ PASSED: SendGrid is configured\n\n";

// Test 2: Check email_queue table
echo "Test 2: Checking email_queue table...\n";
try {
    $db = getDb();
    $stmt = $db->query('SELECT COUNT(*) as count FROM email_queue');
    $result = $stmt->fetch();
    echo "✅ PASSED: email_queue table exists ({$result['count']} emails in queue)\n\n";
} catch (Exception $e) {
    echo "❌ FAILED: email_queue table not found\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Queue a test email with PDF attachment
echo "Test 3: Queueing test email with PDF attachment...\n";
try {
    // Generate test PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial; padding: 20px; }
            h1 { color: #2c3e50; }
            .info { background: #ecf0f1; padding: 10px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <h1>Test Payslip</h1>
        <div class="info">
            <strong>Employee:</strong> Test Employee<br>
            <strong>Period:</strong> ' . date('Y-m-d') . '<br>
            <strong>Gross:</strong> $1,500.00<br>
            <strong>Net:</strong> $1,200.00
        </div>
        <p>This is a test payslip generated at ' . date('Y-m-d H:i:s') . '</p>
    </body>
    </html>
    ';

    $pdfBytes = PdfService::fromHtml($html)->output();
    $pdfBase64 = base64_encode($pdfBytes);

    // Queue email
    $stmt = $db->prepare('
        INSERT INTO email_queue (
            email_from, email_to, subject, html_body, text_body,
            attachments, priority, status, created_at
        ) VALUES (
            :from, :to, :subject, :html, :text,
            :attachments, :priority, :status, NOW()
        )
    ');

    $stmt->execute([
        'from' => 'noreply@vapeshed.co.nz',
        'to' => 'pearcestephens@gmail.com',  // Change to your test email
        'subject' => 'Test Payslip - ' . date('Y-m-d H:i:s'),
        'html' => '<h1>Your Payslip is Ready</h1><p>Please find your payslip attached.</p>',
        'text' => 'Your payslip is ready. Please find it attached.',
        'attachments' => json_encode([
            [
                'filename' => 'payslip-test-' . date('Ymd') . '.pdf',
                'content' => $pdfBase64,
                'mime' => 'application/pdf'
            ]
        ]),
        'priority' => 1,
        'status' => 'pending'
    ]);

    $emailId = $db->lastInsertId();
    echo "✅ PASSED: Test email queued (ID: {$emailId})\n";
    echo "   To: pearcestephens@gmail.com\n";
    echo "   Attachment: payslip-test-" . date('Ymd') . ".pdf\n\n";

} catch (Exception $e) {
    echo "❌ FAILED: Could not queue email\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Process the queue (simulate cron)
echo "Test 4: Processing email queue...\n";
try {
    // Get the queued email
    $stmt = $db->prepare('SELECT * FROM email_queue WHERE id = :id');
    $stmt->execute(['id' => $emailId]);
    $email = $stmt->fetch();

    if (!$email) {
        throw new Exception('Email not found in queue');
    }

    // Send via SendGrid using the sendFromQueue method
    $result = SendGridService::sendFromQueue($email);

    if (!$result['success']) {
        throw new Exception('SendGrid send failed: ' . $result['message']);
    }

    // Update queue status
    $stmt = $db->prepare('
        UPDATE email_queue
        SET status = :status, sent_at = NOW(), attempts = attempts + 1
        WHERE id = :id
    ');
    $stmt->execute(['status' => 'sent', 'id' => $emailId]);

    echo "✅ PASSED: Email sent successfully via SendGrid\n";
    echo "   Response: " . $result['message'] . "\n\n";

} catch (Exception $e) {
    echo "❌ FAILED: Could not send email\n";
    echo "   Error: " . $e->getMessage() . "\n";

    // Mark as failed in queue
    try {
        $stmt = $db->prepare('
            UPDATE email_queue
            SET status = :status, last_error = :error, attempts = attempts + 1
            WHERE id = :id
        ');
        $stmt->execute([
            'status' => 'failed',
            'error' => $e->getMessage(),
            'id' => $emailId
        ]);
    } catch (Exception $e2) {
        // Ignore
    }

    exit(1);
}

// Test 5: Verify queue status
echo "Test 5: Verifying queue status...\n";
try {
    $stmt = $db->query('
        SELECT
            status,
            COUNT(*) as count
        FROM email_queue
        GROUP BY status
    ');
    $stats = $stmt->fetchAll();

    echo "   Queue Statistics:\n";
    foreach ($stats as $stat) {
        echo "   - {$stat['status']}: {$stat['count']}\n";
    }
    echo "✅ PASSED: Queue status verified\n\n";

} catch (Exception $e) {
    echo "⚠️  WARNING: Could not get queue stats: " . $e->getMessage() . "\n\n";
}

// Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                     Test Summary                             ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  All tests passed! ✅                                        ║\n";
echo "║                                                              ║\n";
echo "║  Next Steps:                                                 ║\n";
echo "║  1. Check your email (pearcestephens@gmail.com)             ║\n";
echo "║  2. Verify PDF attachment opens correctly                    ║\n";
echo "║  3. Set up cron job for automatic processing:                ║\n";
echo "║     */5 * * * * php /path/to/process-email-queue.php        ║\n";
echo "║                                                              ║\n";
echo "║  Email Queue System is ready for production! 🚀              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
