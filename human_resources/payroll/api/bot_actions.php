<?php
declare(strict_types=1);

/**
 * BOT ACTIONS API - Execute Bot Decisions
 *
 * Bot calls this after making AI decision to execute the action
 * Handles: approve, decline, escalate, fix, validate
 *
 * @package PayrollModule\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

use PayrollModule\Services\AmendmentService;
use PayrollModule\Services\DeputyService;
use PayrollModule\Services\NZEmploymentLaw;

// Bot authentication required
payroll_require_bot_auth();

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

try {
    $db = getPayrollDb();

    $eventType = $input['event_type'] ?? '';
    $eventId = (int)($input['event_id'] ?? 0);
    $action = $input['action'] ?? ''; // approve, decline, escalate, fix
    $reasoning = $input['reasoning'] ?? '';
    $confidence = (float)($input['confidence'] ?? 0.0);
    $botMetadata = $input['bot_metadata'] ?? [];

    if (!$eventType || !$eventId || !$action) {
        throw new Exception('Missing required fields: event_type, event_id, action');
    }

    // Log bot decision
    logBotDecision($db, $eventType, $eventId, $action, $reasoning, $confidence, $botMetadata);

    // Execute action based on event type
    switch ($eventType) {

        case 'timesheet_amendment':
            $result = handleAmendmentAction($db, $eventId, $action, $reasoning, $confidence, $botMetadata);
            break;

        case 'leave_request':
            $result = handleLeaveAction($db, $eventId, $action, $reasoning, $confidence, $botMetadata);
            break;

        case 'unapproved_timesheet':
            $result = handleTimesheetApproval($db, $eventId, $action, $reasoning, $confidence, $botMetadata);
            break;

        case 'wage_discrepancy':
            $result = handleDiscrepancyAction($db, $eventId, $action, $reasoning, $confidence, $botMetadata);
            break;

        case 'compliance_issue':
            $result = handleComplianceAction($db, $eventId, $action, $reasoning, $confidence, $botMetadata);
            break;

        default:
            throw new Exception('Unknown event type: ' . $eventType);
    }

    echo json_encode([
        'success' => true,
        'event_type' => $eventType,
        'event_id' => $eventId,
        'action' => $action,
        'result' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// ============================================================================
// ACTION HANDLERS
// ============================================================================

/**
 * Handle timesheet amendment action
 */
function handleAmendmentAction(PDO $db, int $amendmentId, string $action, string $reasoning, float $confidence, array $metadata): array
{
    $amendmentService = new AmendmentService();

    switch ($action) {
        case 'approve':
            // Get amendment details
            $stmt = $db->prepare("SELECT * FROM payroll_timesheet_amendments WHERE id = ?");
            $stmt->execute([$amendmentId]);
            $amendment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$amendment) {
                throw new Exception('Amendment not found');
            }

            // Approve via service
            $result = $amendmentService->approveAmendment($amendmentId, [
                'approved_by_bot' => true,
                'bot_reasoning' => $reasoning,
                'bot_confidence' => $confidence,
                'auto_sync_deputy' => true
            ]);

            // Sync to Deputy if needed
            if ($amendment['deputy_timesheet_id']) {
                $deputyService = new DeputyService($db);
                $syncResult = $deputyService->syncAmendmentToDeputy(
                    (object)$amendment,
                    strtotime($amendment['new_start']),
                    strtotime($amendment['new_end']),
                    $metadata['multi_shift_intent'] ?? null
                );

                $result['deputy_sync'] = $syncResult;
            }

            return $result;

        case 'decline':
            return $amendmentService->declineAmendment($amendmentId, [
                'declined_by_bot' => true,
                'reason' => $reasoning,
                'bot_confidence' => $confidence
            ]);

        case 'escalate':
            // Mark for human review
            $stmt = $db->prepare("
                UPDATE payroll_timesheet_amendments
                SET status = 'escalated',
                    escalation_reason = ?,
                    escalated_at = NOW(),
                    bot_confidence = ?
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $amendmentId]);

            // Send notification to managers
            notifyManagers('amendment_escalated', $amendmentId, $reasoning);

            return ['escalated' => true, 'reason' => $reasoning];

        default:
            throw new Exception('Unknown action: ' . $action);
    }
}

/**
 * Handle leave request action
 */
function handleLeaveAction(PDO $db, int $leaveId, string $action, string $reasoning, float $confidence, array $metadata): array
{
    switch ($action) {
        case 'approve':
            // Check leave balance
            $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ?");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leave) {
                throw new Exception('Leave request not found');
            }

            // Validate leave balance
            $balanceCheck = validateLeaveBalance($db, $leave['staff_id'], $leave['hours_requested']);
            if (!$balanceCheck['sufficient']) {
                throw new Exception('Insufficient leave balance');
            }

            // Approve
            $stmt = $db->prepare("
                UPDATE leave_requests
                SET status = 1,
                    decided_by_bot = 1,
                    decision_reasoning = ?,
                    bot_confidence = ?,
                    decided_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $leaveId]);

            // Notify staff
            notifyStaff($leave['staff_id'], 'leave_approved', $leaveId);

            return ['approved' => true, 'leave_balance_remaining' => $balanceCheck['remaining']];

        case 'decline':
            $stmt = $db->prepare("
                UPDATE leave_requests
                SET status = 2,
                    decided_by_bot = 1,
                    decision_reasoning = ?,
                    bot_confidence = ?,
                    decided_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $leaveId]);

            notifyStaff($leave['staff_id'], 'leave_declined', $leaveId);

            return ['declined' => true, 'reason' => $reasoning];

        case 'escalate':
            $stmt = $db->prepare("
                UPDATE leave_requests
                SET escalated = 1,
                    escalation_reason = ?,
                    bot_confidence = ?,
                    escalated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $leaveId]);

            notifyManagers('leave_escalated', $leaveId, $reasoning);

            return ['escalated' => true];

        default:
            throw new Exception('Unknown action: ' . $action);
    }
}

/**
 * Handle unapproved timesheet approval
 */
function handleTimesheetApproval(PDO $db, int $timesheetId, string $action, string $reasoning, float $confidence, array $metadata): array
{
    switch ($action) {
        case 'approve':
            // Validate timesheet meets legal requirements
            $stmt = $db->prepare("SELECT * FROM deputy_timesheets WHERE id = ?");
            $stmt->execute([$timesheetId]);
            $timesheet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$timesheet) {
                throw new Exception('Timesheet not found');
            }

            // Check compliance
            $complianceCheck = checkTimesheetCompliance($timesheet);
            if (!$complianceCheck['compliant']) {
                return [
                    'approved' => false,
                    'reason' => 'Compliance issues: ' . implode(', ', $complianceCheck['issues']),
                    'escalated' => true
                ];
            }

            // Approve via Deputy API
            try {
                deputyApproveTimeSheet((int)$timesheet['deputy_timesheet_id']);

                // Update local record
                $stmt = $db->prepare("UPDATE deputy_timesheets SET approved = 1, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$timesheetId]);

                return ['approved' => true, 'deputy_approved' => true];

            } catch (Exception $e) {
                throw new Exception('Failed to approve in Deputy: ' . $e->getMessage());
            }

        case 'escalate':
            // Mark for human review
            $stmt = $db->prepare("
                INSERT INTO payroll_timesheet_escalations
                (timesheet_id, reason, bot_confidence, escalated_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$timesheetId, $reasoning, $confidence]);

            notifyManagers('timesheet_escalated', $timesheetId, $reasoning);

            return ['escalated' => true];

        default:
            throw new Exception('Unknown action: ' . $action);
    }
}

/**
 * Handle wage discrepancy action
 */
function handleDiscrepancyAction(PDO $db, int $discrepancyId, string $action, string $reasoning, float $confidence, array $metadata): array
{
    switch ($action) {
        case 'approve':
            // Auto-create amendment for approved discrepancy
            $stmt = $db->prepare("SELECT * FROM payroll_wage_discrepancies WHERE id = ?");
            $stmt->execute([$discrepancyId]);
            $discrepancy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$discrepancy) {
                throw new Exception('Discrepancy not found');
            }

            // Update status
            $stmt = $db->prepare("
                UPDATE payroll_wage_discrepancies
                SET status = 'auto_approved',
                    resolution_notes = ?,
                    bot_confidence = ?,
                    resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $discrepancyId]);

            // Create amendment to fix the issue
            $amendmentId = createDiscrepancyAmendment($db, $discrepancy);

            return ['approved' => true, 'amendment_created' => $amendmentId];

        case 'decline':
            $stmt = $db->prepare("
                UPDATE payroll_wage_discrepancies
                SET status = 'declined',
                    resolution_notes = ?,
                    bot_confidence = ?,
                    resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $discrepancyId]);

            notifyStaff($discrepancy['staff_id'], 'discrepancy_declined', $discrepancyId);

            return ['declined' => true];

        case 'escalate':
            $stmt = $db->prepare("
                UPDATE payroll_wage_discrepancies
                SET status = 'escalated',
                    escalation_reason = ?,
                    bot_confidence = ?,
                    escalated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reasoning, $confidence, $discrepancyId]);

            notifyManagers('discrepancy_escalated', $discrepancyId, $reasoning);

            return ['escalated' => true];

        default:
            throw new Exception('Unknown action: ' . $action);
    }
}

/**
 * Handle compliance issue action
 */
function handleComplianceAction(PDO $db, int $issueId, string $action, string $reasoning, float $confidence, array $metadata): array
{
    // Handle break violations, minimum wage issues, public holiday pay, etc.
    return ['handled' => true];
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Log bot decision for audit trail
 */
function logBotDecision(PDO $db, string $eventType, int $eventId, string $action, string $reasoning, float $confidence, array $metadata): void
{
    $stmt = $db->prepare("
        INSERT INTO payroll_bot_decisions
        (event_type, event_id, action, reasoning, confidence, metadata, decided_at, executed_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $eventType,
        $eventId,
        $action,
        $reasoning,
        $confidence,
        json_encode($metadata)
    ]);
}

/**
 * Validate leave balance
 */
function validateLeaveBalance(PDO $db, int $staffId, float $hoursRequested): array
{
    // Calculate total leave balance
    $stmt = $db->prepare("
        SELECT SUM(leave_hours_accrued) as total_accrued,
               SUM(leave_hours_taken) as total_taken
        FROM payroll_payslips
        WHERE staff_id = ?
    ");
    $stmt->execute([$staffId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);

    $available = ($balance['total_accrued'] ?? 0) - ($balance['total_taken'] ?? 0);
    $sufficient = $available >= $hoursRequested;

    return [
        'sufficient' => $sufficient,
        'available' => $available,
        'remaining' => $available - $hoursRequested
    ];
}

/**
 * Check timesheet compliance with NZ law
 */
function checkTimesheetCompliance(array $timesheet): array
{
    $issues = [];

    // Check minimum wage
    if (isset($timesheet['hourly_rate'])) {
        $minWageCheck = NZEmploymentLaw::checkMinimumWage((float)$timesheet['hourly_rate']);
        if (!$minWageCheck['compliant']) {
            $issues[] = 'Below minimum wage: $' . $timesheet['hourly_rate'];
        }
    }

    // Check break requirements
    $hours = (float)$timesheet['total_hours'];
    $breakMinutes = (int)$timesheet['break_minutes'];

    if ($hours >= 5 && $breakMinutes < 30) {
        $issues[] = '5+ hour shift requires 30 min break (has ' . $breakMinutes . ')';
    }

    // Check if public holiday
    if (NZEmploymentLaw::isPublicHoliday($timesheet['date'])) {
        // Should have public holiday pay rate
        $issues[] = 'Public holiday - verify pay rate is 1.5x or time in lieu';
    }

    return [
        'compliant' => empty($issues),
        'issues' => $issues
    ];
}

/**
 * Create amendment to fix wage discrepancy
 */
function createDiscrepancyAmendment(PDO $db, array $discrepancy): int
{
    // Create timesheet amendment to fix the wage issue
    $stmt = $db->prepare("
        INSERT INTO payroll_timesheet_amendments
        (staff_id, period_start, period_end, adjustment_amount, reason,
         status, created_by_bot, created_at)
        VALUES (?, ?, ?, ?, ?, 'auto_approved', 1, NOW())
    ");

    $stmt->execute([
        $discrepancy['staff_id'],
        $discrepancy['period_start'],
        $discrepancy['period_end'],
        $discrepancy['claimed_amount'],
        'Auto-fix for wage discrepancy #' . $discrepancy['id']
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Notify managers of escalation
 */
function notifyManagers(string $eventType, int $eventId, string $reason): void
{
    // Send notification to payroll managers
    // Implementation depends on your notification system
}

/**
 * Notify staff member
 */
function notifyStaff(int $staffId, string $eventType, int $eventId): void
{
    // Send notification to staff
    // Implementation depends on your notification system
}

/**
 * Require bot authentication
 */
function payroll_require_bot_auth(): void
{
    $botToken = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_GET['bot_token'] ?? null;

    if (!$botToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Bot token required']);
        exit;
    }

    if (!payroll_validate_bot_token($botToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid bot token']);
        exit;
    }
}
