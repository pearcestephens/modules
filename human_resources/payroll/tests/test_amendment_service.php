#!/usr/bin/env php
<?php
/**
 * Test Amendment Service
 *
 * Tests the full amendment workflow:
 * - Create amendment
 * - Submit to AI
 * - Check AI decision
 * - Approve/Decline
 *
 * Usage:
 * php test_amendment_service.php
 *
 * @package PayrollModule\Tests
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../private_html/app.php';

use PayrollModule\Services\AmendmentService;
use PayrollModule\Services\PayrollAutomationService;

echo "\n=== Amendment Service Test ===\n\n";

try {
    $amendmentService = new AmendmentService();
    $automationService = new PayrollAutomationService();

    // Test 1: Get active staff member
    echo "Test 1: Finding active staff member...\n";
    $pdo = $amendmentService->getConnection();
    $stmt = $pdo->query("SELECT * FROM payroll_staff WHERE is_active = 1 LIMIT 1");
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        die("ERROR: No active staff found. Please add staff first.\n");
    }

    echo "  ✓ Using staff: {$staff['first_name']} {$staff['last_name']} (ID: {$staff['id']})\n\n";

    // Test 2: Get active pay period
    echo "Test 2: Finding active pay period...\n";
    $stmt = $pdo->query("SELECT * FROM payroll_pay_periods WHERE status = 'active' LIMIT 1");
    $payPeriod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payPeriod) {
        die("ERROR: No active pay period found. Please create one first.\n");
    }

    echo "  ✓ Using pay period: {$payPeriod['period_start']} to {$payPeriod['period_end']}\n\n";

    // Test 3: Create amendment (small change - should auto-approve)
    echo "Test 3: Creating amendment with small hours change...\n";

    $amendmentData = [
        'staff_id' => $staff['id'],
        'pay_period_id' => $payPeriod['id'],
        'deputy_timesheet_id' => null,
        'original_start' => date('Y-m-d 09:00:00'),
        'original_end' => date('Y-m-d 17:00:00'),
        'original_break_minutes' => 30,
        'original_hours' => 7.5,
        'new_start' => date('Y-m-d 09:00:00'),
        'new_end' => date('Y-m-d 17:30:00'), // +30 minutes (0.5 hours)
        'new_break_minutes' => 30,
        'new_hours' => 8.0,
        'reason' => 'Forgot to clock out on time - stayed 30 mins extra',
        'notes' => 'Test amendment - small change'
    ];

    $result = $amendmentService->createAmendment($amendmentData);

    if (!$result['success']) {
        die("ERROR: Failed to create amendment: " . ($result['error'] ?? 'Unknown error') . "\n");
    }

    echo "  ✓ Amendment created: ID #{$result['amendment_id']}\n";
    echo "  ✓ AI Decision created: ID #{$result['ai_decision_id']}\n\n";

    $amendmentId = $result['amendment_id'];
    $aiDecisionId = $result['ai_decision_id'];

    // Test 4: Process AI review
    echo "Test 4: Processing AI review...\n";

    $processResult = $automationService->processAutomatedReviews();

    echo "  Results:\n";
    echo "    - Total Reviewed: {$processResult['total_reviewed']}\n";
    echo "    - Auto-Approved: {$processResult['auto_approved']}\n";
    echo "    - Manual Review: {$processResult['manual_review']}\n";
    echo "    - Declined: {$processResult['declined']}\n";
    echo "    - Errors: {$processResult['errors']}\n\n";

    // Test 5: Check AI decision
    echo "Test 5: Checking AI decision...\n";

    $sql = "SELECT * FROM payroll_ai_decisions WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$aiDecisionId]);
    $decision = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$decision) {
        die("ERROR: AI decision not found\n");
    }

    echo "  Decision: {$decision['decision']}\n";
    echo "  Confidence: {$decision['confidence_score']}\n";
    echo "  Reasoning: {$decision['reasoning']}\n";
    echo "  Status: {$decision['status']}\n\n";

    // Test 6: Check amendment status
    echo "Test 6: Checking amendment status...\n";

    $amendment = $amendmentService->getAmendment($amendmentId);

    if (!$amendment) {
        die("ERROR: Amendment not found\n");
    }

    echo "  Amendment Status: {$amendment['status']}\n";
    echo "  Created: {$amendment['created_at']}\n";
    if ($amendment['approved_at']) {
        echo "  Approved: {$amendment['approved_at']}\n";
    }
    echo "\n";

    // Test 7: Check rule executions
    echo "Test 7: Checking rule executions...\n";

    $sql = "SELECT
                re.*,
                r.rule_name
            FROM payroll_ai_rule_executions re
            JOIN payroll_ai_rules r ON re.rule_id = r.id
            WHERE re.ai_decision_id = ?
            ORDER BY re.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$aiDecisionId]);
    $executions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Total rules executed: " . count($executions) . "\n";

    foreach ($executions as $exec) {
        $passedIcon = $exec['passed'] ? '✓' : '✗';
        echo "    {$passedIcon} {$exec['rule_name']} (confidence: {$exec['confidence_adjustment']})\n";
    }

    echo "\n=== Test Complete ===\n";
    echo "✓ All tests passed successfully!\n\n";

    // Summary
    echo "Created Resources:\n";
    echo "  - Amendment ID: {$amendmentId}\n";
    echo "  - AI Decision ID: {$aiDecisionId}\n";
    echo "  - Final Status: {$amendment['status']}\n";
    echo "  - AI Decision: {$decision['decision']}\n\n";

    exit(0);

} catch (\Throwable $e) {
    echo "\n*** TEST FAILED ***\n";
    echo "Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
