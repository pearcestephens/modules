<?php
/**
 * Complete Payroll Module Test Suite
 * Tests: Email Queue, PDF Generation, PayslipEmailer, Full Workflow
 */

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  PAYROLL MODULE - COMPLETE TEST SUITE                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

$allPassed = true;

// Test 1: Email Queue Functions
echo "TEST 1: Email Queue Functions\n";
echo str_repeat("─", 60) . "\n";

require_once __DIR__ . '/../lib/EmailQueueHelper.php';

try {
    // Test 1a: Get stats
    echo "  1a. Getting queue stats... ";
    $stats = queue_get_stats();
    if (isset($stats['total'])) {
        echo "✅ PASS (Total: {$stats['total']}, Pending: {$stats['pending']})\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

    // Test 1b: Queue simple email
    echo "  1b. Queueing test email... ";
    $emailId = queue_enqueue_email(
        'pearcestephens@gmail.com',
        'Payroll Test Email',
        '<h1>Test from Payroll Module</h1><p>This is a test email from the payroll system.</p>',
        [],
        'payroll@vapeshed.co.nz',
        2
    );

    if ($emailId) {
        echo "✅ PASS (ID: {$emailId})\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

    // Test 1c: Queue email with attachment
    echo "  1c. Queueing email with PDF attachment... ";
    $pdfContent = base64_encode("<html><body><h1>Test PDF</h1></body></html>");
    $emailId2 = queue_enqueue_email(
        'pearcestephens@gmail.com',
        'Payroll Test with Attachment',
        '<h1>Email with PDF</h1>',
        [[
            'filename' => 'test.pdf',
            'content' => $pdfContent,
            'mime' => 'application/pdf'
        ]],
        'payroll@vapeshed.co.nz',
        1
    );

    if ($emailId2) {
        echo "✅ PASS (ID: {$emailId2})\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

    // Test 1d: Verify stats updated
    echo "  1d. Verifying stats updated... ";
    $newStats = queue_get_stats();
    if ($newStats['pending'] >= $stats['pending'] + 2) {
        echo "✅ PASS (New pending: {$newStats['pending']})\n";
    } else {
        echo "⚠️  WARNING (Might have been processed already)\n";
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// Test 2: PDF Service
echo "TEST 2: PDF Service\n";
echo str_repeat("─", 60) . "\n";

require_once __DIR__ . '/../../../shared/services/PdfService.php';

use CIS\Shared\Services\PdfService;

try {
    // Test 2a: Check service status
    echo "  2a. Checking PDF service status... ";
    $status = PdfService::getStatus();
    echo ($status['dompdf_installed'] ? "✅ Dompdf installed" : "⚠️  Fallback mode") . "\n";

    // Test 2b: Generate PDF from HTML
    echo "  2b. Generating PDF from HTML... ";
    $html = "<html><body><h1>Test Payslip</h1><p>Employee: Test User</p></body></html>";
    $pdf = PdfService::fromHtml($html);
    $bytes = $pdf->output();

    if (strlen($bytes) > 0) {
        echo "✅ PASS (" . number_format(strlen($bytes)) . " bytes)\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

    // Test 2c: Generate base64
    echo "  2c. Converting to base64... ";
    $base64 = $pdf->toBase64();
    if (strlen($base64) > 0) {
        echo "✅ PASS (" . number_format(strlen($base64)) . " chars)\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// Test 3: PayslipPdfGenerator
echo "TEST 3: PayslipPdfGenerator\n";
echo str_repeat("─", 60) . "\n";

require_once __DIR__ . '/../lib/PayslipPdfGenerator.php';

try {
    $mockPayslip = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'period_start' => '2025-10-01',
        'period_end' => '2025-10-31',
        'gross' => 5000.00,
        'net' => 4200.00
    ];

    $mockLines = [
        [
            'type' => 'Earnings',
            'description' => 'Base Salary',
            'units' => '160',
            'rate' => 31.25,
            'amount' => 5000.00
        ],
        [
            'type' => 'Deductions',
            'description' => 'Tax',
            'units' => '1',
            'rate' => 800.00,
            'amount' => -800.00
        ]
    ];

    // Test 3a: Render HTML
    echo "  3a. Rendering payslip HTML... ";
    $html = PayslipPdfGenerator::renderHtml($mockPayslip, $mockLines);
    if (strlen($html) > 500 && strpos($html, 'John Doe') !== false) {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

    // Test 3b: Convert to PDF
    echo "  3b. Converting to PDF bytes... ";
    $pdfBytes = PayslipPdfGenerator::toPdfBytes($html);
    if (strlen($pdfBytes) > 0) {
        echo "✅ PASS (" . number_format(strlen($pdfBytes)) . " bytes)\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// Test 4: PayslipEmailer
echo "TEST 4: PayslipEmailer\n";
echo str_repeat("─", 60) . "\n";

require_once __DIR__ . '/../lib/PayslipEmailer.php';

try {
    $mockPayslip = [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'pearcestephens@gmail.com',
        'period_start' => '2025-10-01',
        'period_end' => '2025-10-31',
        'gross' => 6000.00,
        'net' => 5100.00
    ];

    $mockLines = [
        [
            'type' => 'Earnings',
            'description' => 'Base Salary',
            'units' => '160',
            'rate' => 37.50,
            'amount' => 6000.00
        ]
    ];

    // Test 4a: Queue payslip email
    echo "  4a. Queueing payslip email to pearcestephens@gmail.com... ";
    $emailId = PayslipEmailer::queueEmail($mockPayslip, $mockLines);

    if ($emailId) {
        echo "✅ PASS (Email queued with ID: {$emailId})\n";
    } else {
        echo "❌ FAIL\n";
        $allPassed = false;
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// Final Summary
echo "╔═══════════════════════════════════════════════════════════════╗\n";
if ($allPassed) {
    echo "║  ✅ ALL TESTS PASSED!                                         ║\n";
} else {
    echo "║  ❌ SOME TESTS FAILED - Review output above                  ║\n";
}
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

echo "📧 Check your email (pearcestephens@gmail.com) for test emails!\n";
echo "   (May take a few minutes for cron job to process queue)\n\n";

echo "Next Steps:\n";
echo "  1. Install Dompdf: cd /home/master/applications/jcepnzzkmj/public_html/modules && composer install\n";
echo "  2. Test endpoints: curl tests in test-endpoints.sh\n";
echo "  3. View payslip: https://staff.vapeshed.co.nz/modules/human_resources/payroll/views/payslip.php?id=1\n\n";

exit($allPassed ? 0 : 1);
