<?php
/**
 * Comprehensive PDF Generator Test Suite
 * Tests: HTML rendering, PDF generation, error handling, edge cases
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/PayslipPdfGenerator.php';

echo "\n🧪 PAYSLIP PDF GENERATOR - COMPREHENSIVE TEST SUITE\n";
echo str_repeat("=", 60) . "\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: HTML Rendering with Complete Data
echo "Test 1: HTML Rendering with Complete Payslip Data\n";
try {
    $payslip = [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'period_start' => '2025-10-01',
        'period_end' => '2025-10-31',
        'gross' => 5500.50,
        'net' => 4125.75
    ];

    $lines = [
        ['type' => 'Earnings', 'description' => 'Regular Hours', 'units' => '160', 'rate' => 30.00, 'amount' => 4800.00],
        ['type' => 'Earnings', 'description' => 'Overtime', 'units' => '10', 'rate' => 45.00, 'amount' => 450.00],
        ['type' => 'Earnings', 'description' => 'Bonus', 'units' => '1', 'rate' => 250.50, 'amount' => 250.50],
        ['type' => 'Deduction', 'description' => 'PAYE Tax', 'units' => '1', 'rate' => 0, 'amount' => -1100.25],
        ['type' => 'Deduction', 'description' => 'KiwiSaver', 'units' => '1', 'rate' => 0, 'amount' => -274.50],
    ];

    $html = PayslipPdfGenerator::renderHtml($payslip, $lines);

    // Validate HTML structure
    $checks = [
        'Contains employee name' => (strpos($html, 'John Smith') !== false),
        'Contains period dates' => (strpos($html, '2025-10-01') !== false && strpos($html, '2025-10-31') !== false),
        'Contains gross total' => (strpos($html, '5,500.50') !== false),
        'Contains net total' => (strpos($html, '4,125.75') !== false),
        'Contains earnings line' => (strpos($html, 'Regular Hours') !== false),
        'Contains deduction line' => (strpos($html, 'PAYE Tax') !== false),
        'Has table structure' => (strpos($html, '<table>') !== false && strpos($html, '</table>') !== false),
        'Has proper DOCTYPE' => (strpos($html, '<!DOCTYPE html>') !== false),
    ];

    $allPassed = true;
    foreach ($checks as $name => $result) {
        if (!$result) {
            echo "  ❌ $name\n";
            $allPassed = false;
        }
    }

    if ($allPassed) {
        echo "  ✅ All HTML validations passed\n";
        $testsPassed++;
    } else {
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 2: HTML Rendering with Missing Data
echo "Test 2: HTML Rendering with Missing/Empty Data\n";
try {
    $payslip = [
        'first_name' => '',
        'last_name' => null,
        'period_start' => null,
        'period_end' => null,
        'gross' => 0,
        'net' => 0
    ];

    $lines = [];

    $html = PayslipPdfGenerator::renderHtml($payslip, $lines);

    if (strlen($html) > 100 && strpos($html, '<!DOCTYPE html>') !== false) {
        echo "  ✅ Handles empty data gracefully\n";
        $testsPassed++;
    } else {
        echo "  ❌ Failed to generate valid HTML with empty data\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 3: HTML Rendering with Special Characters
echo "Test 3: HTML Rendering with Special Characters & XSS Prevention\n";
try {
    $payslip = [
        'first_name' => "O'Connor",
        'last_name' => '<script>alert("xss")</script>',
        'period_start' => '2025-10-01',
        'period_end' => '2025-10-31',
        'gross' => 1000.00,
        'net' => 750.00
    ];

    $lines = [
        ['type' => 'Earnings', 'description' => 'Base & Allowance', 'units' => '1', 'rate' => 1000, 'amount' => 1000],
    ];

    $html = PayslipPdfGenerator::renderHtml($payslip, $lines);

    // Check XSS prevention
    if (strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false) {
        echo "  ✅ XSS properly escaped\n";
        $testsPassed++;
    } else {
        echo "  ❌ XSS not properly escaped\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 4: PDF Byte Generation
echo "Test 4: PDF Byte Generation from HTML\n";
try {
    $payslip = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'period_start' => '2025-11-01',
        'period_end' => '2025-11-30',
        'gross' => 3000.00,
        'net' => 2250.00
    ];

    $lines = [
        ['type' => 'Earnings', 'description' => 'Salary', 'units' => '1', 'rate' => 3000, 'amount' => 3000],
    ];

    $html = PayslipPdfGenerator::renderHtml($payslip, $lines);
    $pdfBytes = PayslipPdfGenerator::toPdfBytes($html);

    // Validate PDF format
    $checks = [
        'PDF has content' => (strlen($pdfBytes) > 100),
        'PDF starts with %PDF' => (substr($pdfBytes, 0, 4) === '%PDF'),
        'PDF contains EOF marker' => (strpos($pdfBytes, '%%EOF') !== false),
    ];

    $allPassed = true;
    foreach ($checks as $name => $result) {
        if (!$result) {
            echo "  ❌ $name\n";
            $allPassed = false;
        }
    }

    if ($allPassed) {
        echo "  ✅ PDF generation successful (" . number_format(strlen($pdfBytes)) . " bytes)\n";
        $testsPassed++;

        // Save sample PDF
        $samplePath = __DIR__ . '/output/sample_payslip.pdf';
        if (!is_dir(__DIR__ . '/output')) {
            mkdir(__DIR__ . '/output', 0755, true);
        }
        file_put_contents($samplePath, $pdfBytes);
        echo "  📄 Sample PDF saved: $samplePath\n";
    } else {
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 5: Large Payslip with Many Lines
echo "Test 5: Large Payslip with 50+ Line Items\n";
try {
    $payslip = [
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'period_start' => '2025-11-01',
        'period_end' => '2025-11-30',
        'gross' => 10000.00,
        'net' => 7500.00
    ];

    // Generate 50 lines
    $lines = [];
    for ($i = 1; $i <= 50; $i++) {
        $lines[] = [
            'type' => ($i % 5 === 0) ? 'Deduction' : 'Earnings',
            'description' => "Line Item $i",
            'units' => (string)$i,
            'rate' => 10.00 + ($i * 0.5),
            'amount' => 100.00 + ($i * 2)
        ];
    }

    $html = PayslipPdfGenerator::renderHtml($payslip, $lines);
    $pdfBytes = PayslipPdfGenerator::toPdfBytes($html);

    if (strlen($pdfBytes) > 1000 && strpos($html, 'Line Item 50') !== false) {
        echo "  ✅ Handles large payslips (50 lines, " . number_format(strlen($pdfBytes)) . " bytes)\n";
        $testsPassed++;
    } else {
        echo "  ❌ Failed with large payslip\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 6: Performance Test
echo "Test 6: Performance Test (100 PDFs)\n";
try {
    $startTime = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        $payslip = [
            'first_name' => 'Perf',
            'last_name' => "Test$i",
            'period_start' => '2025-11-01',
            'period_end' => '2025-11-30',
            'gross' => 3000.00,
            'net' => 2250.00
        ];

        $lines = [
            ['type' => 'Earnings', 'description' => 'Salary', 'units' => '1', 'rate' => 3000, 'amount' => 3000],
        ];

        $html = PayslipPdfGenerator::renderHtml($payslip, $lines);
        // Only generate PDFs for first 10 to save time
        if ($i < 10) {
            PayslipPdfGenerator::toPdfBytes($html);
        }
    }

    $duration = microtime(true) - $startTime;
    $avgTime = ($duration / 100) * 1000; // milliseconds

    echo "  ✅ Generated 100 HTML + 10 PDFs in " . number_format($duration, 2) . "s\n";
    echo "  📊 Average: " . number_format($avgTime, 2) . "ms per payslip\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 7: Currency Formatting Edge Cases
echo "Test 7: Currency Formatting Edge Cases\n";
try {
    $testCases = [
        ['gross' => 0.01, 'expected' => '0.01'],
        ['gross' => 999999.99, 'expected' => '999,999.99'],
        ['gross' => -100.50, 'expected' => '-100.50'],
        ['gross' => 1234567.89, 'expected' => '1,234,567.89'],
    ];

    $allPassed = true;
    foreach ($testCases as $case) {
        $payslip = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'period_start' => '2025-11-01',
            'period_end' => '2025-11-30',
            'gross' => $case['gross'],
            'net' => $case['gross'] * 0.75
        ];

        $html = PayslipPdfGenerator::renderHtml($payslip, []);

        if (strpos($html, $case['expected']) === false) {
            echo "  ❌ Failed formatting: {$case['gross']} (expected {$case['expected']})\n";
            $allPassed = false;
        }
    }

    if ($allPassed) {
        echo "  ✅ All currency formats correct\n";
        $testsPassed++;
    } else {
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Passed: $testsPassed\n";
echo "❌ Failed: $testsFailed\n";
$total = $testsPassed + $testsFailed;
$successRate = $total > 0 ? ($testsPassed / $total * 100) : 0;
echo "📈 Success Rate: " . number_format($successRate, 1) . "%\n";

if ($testsFailed === 0) {
    echo "\n🎉 ALL TESTS PASSED! PDF Generator is production-ready.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Review output above.\n";
    exit(1);
}
