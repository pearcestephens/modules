<?php
// AI Decision Processor
// Hooks wages discrepancies, leave requests, holiday pay entitlement, and statutory deductions
// Safe to run via CLI. Minimal auto-actions; human review when confidence is low.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}

require_once __DIR__ . '/../classes/PayrollAIDecisionEngine.php';
require_once __DIR__ . '/../classes/PayrollAIHardening.php';

// Expect global DB connection $conn to be available via app bootstrap
$bootstrapped = false;
$bootstrapPaths = [
    dirname(__FILE__, 4) . '/private_html/app.php',
    dirname(__FILE__, 5) . '/private_html/app.php',
    dirname(__FILE__, 4) . '/app.php',
];
foreach ($bootstrapPaths as $p) {
    if (file_exists($p)) { require_once $p; $bootstrapped = true; break; }
}
if (!$bootstrapped) {
    fwrite(STDERR, "Warning: app bootstrap not found. Assuming $conn is initialized elsewhere.\n");
}

$engine = new PayrollAIDecisionEngine();

function now(): string { return date('Y-m-d H:i:s'); }

function process_discrepancies(mysqli $db, PayrollAIDecisionEngine $engine): void {
    $sql = "SELECT id, staff_id, payroll_run_id, discrepancy_type, description, pay_period_start, pay_period_end
            FROM payroll_wages_discrepancies
            WHERE ai_reviewed = 0 AND status IN ('pending','ai_review')
            ORDER BY created_at ASC LIMIT 50";
    $res = $db->query($sql);
    if (!$res) { fwrite(STDERR, "SQL error: {$db->error}\n"); return; }

    while ($row = $res->fetch_assoc()) {
        // Check circuit breaker
        if (PayrollAIHardening::isCircuitOpen()) {
            fwrite(STDERR, "[CIRCUIT_BREAKER] Skipping discrepancy {$row['id']}, circuit open\n");
            PayrollAIHardening::logDeadLetter(['type' => 'discrepancy', 'id' => $row['id'], 'reason' => 'Circuit breaker open']);
            continue;
        }

        // Rate limit check
        try {
            PayrollAIHardening::checkRateLimit();
        } catch (Exception $e) {
            fwrite(STDERR, "[RATE_LIMIT] {$e->getMessage()}\n");
            sleep(5);
            continue;
        }

        $context = [
            'staff_id' => (int)$row['staff_id'],
            'payroll_run_id' => $row['payroll_run_id'],
            'pay_period' => [$row['pay_period_start'], $row['pay_period_end']],
            'scenario' => "Wages discrepancy: {$row['discrepancy_type']}",
            'description' => $row['description']
        ];

        try {
            $decision = $engine->resolvePayDispute((int)$row['id'], $context);
            PayrollAIHardening::resetFailures();
        } catch (Exception $e) {
            fwrite(STDERR, "[ERROR] AI decision failed for discrepancy {$row['id']}: {$e->getMessage()}\n");
            PayrollAIHardening::recordFailure(['type' => 'discrepancy', 'id' => $row['id'], 'error' => $e->getMessage()]);
            continue;
        }

        $ai_decision = match ($decision['decision']) {
            'approve', 'partial_approve' => 'valid',
            'decline' => 'invalid',
            'request_evidence' => 'needs_review',
            default => 'needs_review',
        };
        $status = match ($decision['decision']) {
            'approve', 'partial_approve' => 'validated',
            'decline' => 'rejected',
            default => 'escalated',
        };

        $stmt = $db->prepare("UPDATE payroll_wages_discrepancies
            SET ai_reviewed=1, ai_decision=?, ai_confidence_score=?, ai_reasoning=?, ai_reviewed_at=?, status=?, validated_at=CASE WHEN ?='validated' THEN NOW() ELSE validated_at END
            WHERE id = ?");
        $reasoning = $decision['reasoning'] ?? null;
        $conf = $decision['confidence'] ?? null;
        $now = now();
        $vstatus = $status; // for CASE param
        $id = (int)$row['id'];
    $stmt->bind_param('sdssssi', $ai_decision, $conf, $reasoning, $now, $status, $vstatus, $id);
        $stmt->execute();

        echo "Discrepancy #{$id} => {$status} ({$ai_decision}) conf=" . ($conf ?? 'n/a') . "\n";
    }
}

function process_leave_requests(mysqli $db, PayrollAIDecisionEngine $engine): void {
    $sql = "SELECT id, staff_id, leave_type, reason, start_date, end_date, is_partial_day, covers_public_holiday
            FROM payroll_nz_leave_requests
            WHERE status='pending'
            ORDER BY created_at ASC
            LIMIT 50";
    $res = $db->query($sql);
    if (!$res) { fwrite(STDERR, "SQL error: {$db->error}\n"); return; }

    while ($row = $res->fetch_assoc()) {
        // Check circuit breaker
        if (PayrollAIHardening::isCircuitOpen()) {
            fwrite(STDERR, "[CIRCUIT_BREAKER] Skipping leave request {$row['id']}, circuit open\n");
            PayrollAIHardening::logDeadLetter(['type' => 'leave_request', 'id' => $row['id'], 'reason' => 'Circuit breaker open']);
            continue;
        }

        // Rate limit check
        try {
            PayrollAIHardening::checkRateLimit();
        } catch (Exception $e) {
            fwrite(STDERR, "[RATE_LIMIT] {$e->getMessage()}\n");
            sleep(5);
            continue;
        }

        $context = [
            'staff_id' => (int)$row['staff_id'],
            'leave_type' => $row['leave_type'],
            'reason' => $row['reason'],
            'dates' => [$row['start_date'], $row['end_date']],
            'is_partial_day' => (int)$row['is_partial_day'] === 1,
            'covers_public_holiday' => (int)$row['covers_public_holiday'] === 1,
            'scenario' => 'Leave request assessment'
        ];

        $decisionType = match ($row['leave_type']) {
            'sick_leave' => 'sick_leave_validation',
            'bereavement_leave' => 'bereavement_assessment',
            'domestic_violence_leave' => 'domestic_violence_leave',
            default => 'leave_request_assessment',
        };

        // Use typed methods when available
        try {
        if ($decisionType === 'sick_leave_validation') {
            $decision = $engine->validateSickLeave((int)$row['id'], $context);
        } elseif ($decisionType === 'bereavement_assessment') {
            $decision = $engine->assessBereavementLeave((int)$row['id'], $context);
        } elseif ($decisionType === 'domestic_violence_leave') {
            $decision = $engine->validateDomesticViolenceLeave((int)$row['id'], $context);
        } else {
            $decision = $engine->makeDecision('leave_request_assessment', $context, (int)$row['id'], 'leave_request');
        }

        $action = $decision['decision'] ?? 'escalate';
        $conf = (float)($decision['confidence'] ?? 0);
        $requiresReview = !empty($decision['requires_human_review']);
        $now = now();

        // Conservative: auto-approve only if high confidence and no human review required
        if ($action === 'approve' && $conf >= 0.85 && !$requiresReview) {
            $stmt = $db->prepare("UPDATE payroll_nz_leave_requests SET status='approved', approved_at=?, status_changed_at=? WHERE id=?");
            $stmt->bind_param('ssi', $now, $now, $row['id']);
            $stmt->execute();
            echo "Leave #{$row['id']} => approved conf={$conf}\n";
        } elseif ($action === 'decline' && $conf >= 0.85 && !$requiresReview) {
            $decline = substr((string)($decision['reasoning'] ?? 'AI decline'), 0, 1000);
            $stmt = $db->prepare("UPDATE payroll_nz_leave_requests SET status='declined', decline_reason=?, status_changed_at=? WHERE id=?");
            $stmt->bind_param('ssi', $decline, $now, $row['id']);
            $stmt->execute();
            echo "Leave #{$row['id']} => declined conf={$conf}\n";
        } else {
            // Keep pending; human review path
            echo "Leave #{$row['id']} => human_review conf={$conf}\n";
        }
    }
}

function process_statutory_deductions(mysqli $db, PayrollAIDecisionEngine $engine): void {
    $sql = "SELECT id, staff_id, order_type, order_reference, calculation_method, amount, percentage, min_net_protected
            FROM payroll_nz_deduction_applications
            WHERE status='active' AND effective_from <= CURDATE() AND (effective_to IS NULL OR effective_to >= CURDATE())
            ORDER BY received_date ASC LIMIT 50";
    if (!$res = $db->query($sql)) { fwrite(STDERR, "SQL error: {$db->error}\n"); return; }

    while ($row = $res->fetch_assoc()) {
        $context = [
            'staff_id' => (int)$row['staff_id'],
            'order_type' => $row['order_type'],
            'order_reference' => $row['order_reference'],
            'calculation_method' => $row['calculation_method'],
            'amount' => $row['amount'],
            'percentage' => $row['percentage'],
            'min_net_protected' => $row['min_net_protected'],
            'scenario' => 'Assess statutory deduction application'
        ];
        $decision = $engine->assessStatutoryDeductionApplication((int)$row['id'], $context);
        echo "Deduction application #{$row['id']} => decision=" . ($decision['decision'] ?? 'n/a') . " conf=" . ($decision['confidence'] ?? 'n/a') . "\n";
    }
}

// Execute
process_discrepancies($conn, $engine);
process_leave_requests($conn, $engine);
process_statutory_deductions($conn, $engine);

echo "Done." . PHP_EOL;
