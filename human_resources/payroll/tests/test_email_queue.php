<?php
/**
 * Test EmailQueueHelper Functions
 * Tests email queue insertion and stats retrieval
 */

require_once __DIR__ . '/../lib/EmailQueueHelper.php';

echo "=== Email Queue Helper Test ===\n\n";

// Test 1: Get queue stats
echo "TEST 1: Queue Stats\n";
$stats = queue_get_stats();
print_r($stats);
echo "\n";

// Test 2: Queue a simple email
echo "TEST 2: Queue Simple Email\n";
$result = queue_enqueue_email(
    'test@example.com',
    'Test Payslip Email',
    '<h1>Test Payslip</h1><p>This is a test email from the payroll system.</p>',
    [], // no attachments
    'payroll@vapeshed.co.nz',
    2 // batched priority
);

if ($result) {
    echo "✓ Email queued successfully! Insert ID: $result\n";
} else {
    echo "✗ Failed to queue email\n";
}
echo "\n";

// Test 3: Queue email with attachment
echo "TEST 3: Queue Email with PDF Attachment\n";
$pdfContent = base64_encode('This is fake PDF content for testing');
$attachments = [[
    'filename' => 'test-payslip.pdf',
    'content' => $pdfContent,
    'mime' => 'application/pdf'
]];

$result2 = queue_enqueue_email(
    'test2@example.com',
    'Payslip with Attachment',
    '<h1>Payslip Attached</h1><p>Please find your payslip attached.</p>',
    $attachments,
    'payroll@vapeshed.co.nz',
    1 // immediate priority
);

if ($result2) {
    echo "✓ Email with attachment queued! Insert ID: $result2\n";
} else {
    echo "✗ Failed to queue email with attachment\n";
}
echo "\n";

// Test 4: Get updated stats
echo "TEST 4: Updated Queue Stats\n";
$stats2 = queue_get_stats();
print_r($stats2);
echo "\n";

echo "=== Test Complete ===\n";
