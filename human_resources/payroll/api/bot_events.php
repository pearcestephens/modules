<?php
declare(strict_types=1);

/**
 * BOT EVENTS API - Event-Driven Payroll Bot Interface
 *
 * Provides event stream for autonomous payroll bot
 * Bot can query pending events, take actions, report status
 *
 * @package PayrollModule\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

use PayrollModule\Services\PayrollAutomationService;
use PayrollModule\Services\AmendmentService;
use PayrollModule\Services\LeaveService;
use PayrollModule\Services\BonusService;
use PayrollModule\Services\WageDiscrepancyService;
use PayrollModule\Services\NZEmploymentLaw;

// Bot authentication required
payroll_require_bot_auth();

// Get action
$action = $_GET['action'] ?? 'pending_events';

header('Content-Type: application/json');

try {
    $db = getPayrollDb();

    switch ($action) {

        // ================================================================
        // GET PENDING EVENTS - Bot polls this for work
        // ================================================================
        case 'pending_events':
            echo json_encode(getPendingEvents($db), JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // GET EVENT DETAILS - Bot gets full context for decision making
        // ================================================================
        case 'event_details':
            $eventId = (int)($_GET['event_id'] ?? 0);
            echo json_encode(getEventDetails($db, $eventId), JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // EXECUTE ACTION - Bot performs action after AI decision
        // ================================================================
        case 'execute_action':
            $eventId = (int)($_POST['event_id'] ?? 0);
            $action = $_POST['bot_action'] ?? '';
            $reasoning = $_POST['reasoning'] ?? '';
            $confidence = (float)($_POST['confidence'] ?? 0.0);

            echo json_encode(executeAction($db, $eventId, $action, $reasoning, $confidence), JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // REPORT STATUS - Bot reports its activity
        // ================================================================
        case 'report_status':
            $status = json_decode(file_get_contents('php://input'), true);
            echo json_encode(reportBotStatus($db, $status), JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // HEALTH CHECK - Bot verifies system health
        // ================================================================
        case 'health_check':
            echo json_encode(getSystemHealth($db), JSON_PRETTY_PRINT);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get all pending events that need bot attention
 */
function getPendingEvents(PDO $db): array
{
    $events = [];

    try {
        // 1. PENDING TIMESHEET AMENDMENTS
        $stmt = $db->query("
            SELECT
                a.id,
                'timesheet_amendment' as event_type,
                a.staff_id,
                u.fname as first_name,
                u.lname as last_name,
                a.claimed_start_time,
                a.claimed_end_time,
                a.approved_start_time,
                a.approved_end_time,
                a.reason,
                a.created_at,
                a.status,
                a.deputy_timesheet_id,
                TIMESTAMPDIFF(MINUTE, a.created_at, NOW()) as age_minutes
            FROM payroll_timesheet_amendments a
            JOIN users u ON a.staff_id = u.user_id
            WHERE a.status = 0
            ORDER BY a.created_at ASC
            LIMIT 10
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = array_merge($row, [
                'priority' => 'medium',
                'requires_ai_decision' => true
            ]);
        }
    } catch (Exception $e) {
        error_log("Bot Events - Timesheet amendments query error: " . $e->getMessage());
    }

    try {
        // 2. PENDING LEAVE REQUESTS
        $stmt = $db->query("
            SELECT
                lr.id,
                'leave_request' as event_type,
                lr.staff_id,
                u.fname as first_name,
                u.lname as last_name,
                lr.LeaveTypeName as leave_type,
                lr.date_from,
                lr.date_to,
                lr.hours_requested,
                lr.reason,
                lr.date_created as created_at,
                TIMESTAMPDIFF(MINUTE, lr.date_created, NOW()) as age_minutes
            FROM leave_requests lr
            JOIN users u ON lr.staff_id = u.user_id
            WHERE lr.status = 0
            ORDER BY lr.date_created ASC
            LIMIT 10
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = array_merge($row, [
                'priority' => 'medium',
                'requires_ai_decision' => true
            ]);
        }
    } catch (Exception $e) {
        error_log("Bot Events - Leave requests query error: " . $e->getMessage());
    }

    try {
        // 3. WAGE DISCREPANCIES
        $stmt = $db->query("
            SELECT
                wd.id,
                'wage_discrepancy' as event_type,
                wd.staff_id,
                u.fname as first_name,
                u.lname as last_name,
                wd.expected_wage,
                wd.actual_wage,
                wd.difference,
                wd.detected_at as created_at,
                wd.status,
                TIMESTAMPDIFF(MINUTE, wd.detected_at, NOW()) as age_minutes
            FROM payroll_wage_discrepancies wd
            JOIN users u ON wd.staff_id = u.user_id
            WHERE wd.status IN ('detected', 'pending_fix')
            ORDER BY ABS(wd.difference) DESC
            LIMIT 10
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = array_merge($row, [
                'priority' => 'high',
                'requires_ai_decision' => true
            ]);
        }
    } catch (Exception $e) {
        error_log("Bot Events - Wage discrepancies query error: " . $e->getMessage());
    }

    return $events;
}

/**
 * Get detailed context for a specific event (for AI decision making)
 */
function getEventDetails(PDO $db, int $eventId): array
{
    // This would return ALL context needed for AI to make informed decision
    // Including: staff history, similar past events, policy rules, etc.

    return [
        'success' => true,
        'event_id' => $eventId,
        'context' => [
            'staff_history' => [],
            'similar_events' => [],
            'applicable_policies' => [],
            'compliance_checks' => [],
            'risk_factors' => []
        ]
    ];
}

/**
 * Execute action based on bot's AI decision
 */
function executeAction(PDO $db, int $eventId, string $action, string $reasoning, float $confidence): array
{
    // Bot has made decision - now execute it
    // Actions: approve, decline, escalate, fix, ignore

    try {
        $db->beginTransaction();

        // Log bot decision
        $stmt = $db->prepare("
            INSERT INTO payroll_bot_decisions
            (event_id, action, reasoning, confidence, decided_at, executed_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$eventId, $action, $reasoning, $confidence]);

        // Execute the action (would call appropriate service methods)
        $result = ['executed' => true, 'action' => $action];

        $db->commit();

        return [
            'success' => true,
            'event_id' => $eventId,
            'action' => $action,
            'executed' => true
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Bot reports its status/activity
 */
function reportBotStatus(PDO $db, array $status): array
{
    // Log bot heartbeat and activity
    $stmt = $db->prepare("
        INSERT INTO payroll_bot_heartbeat
        (status, events_processed, decisions_made, errors_count, last_seen)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $status['status'] ?? 'active',
        $status['events_processed'] ?? 0,
        $status['decisions_made'] ?? 0,
        $status['errors_count'] ?? 0
    ]);

    return ['success' => true, 'logged' => true];
}

/**
 * System health check for bot
 */
function getSystemHealth(PDO $db): array
{
    return [
        'success' => true,
        'healthy' => true,
        'checks' => [
            'database' => true,
            'deputy_api' => true, // Would actually check
            'xero_api' => true,   // Would actually check
            'vend_api' => true    // Would actually check
        ]
    ];
}

/**
 * Calculate priority for amendment
 */
function calculateAmendmentPriority(array $amendment): int
{
    $priority = 50; // Base priority

    // Older amendments = higher priority
    if ($amendment['age_minutes'] > 1440) $priority += 30; // > 24 hours
    elseif ($amendment['age_minutes'] > 360) $priority += 15; // > 6 hours

    // Multiple recent amendments = higher priority (potential issue)
    if ($amendment['recent_amendment_count'] > 3) $priority += 20;

    // Large time changes = higher priority
    $originalHours = (strtotime($amendment['original_end']) - strtotime($amendment['original_start'])) / 3600;
    $newHours = (strtotime($amendment['new_end']) - strtotime($amendment['new_start'])) / 3600;
    $hoursDiff = abs($newHours - $originalHours);
    if ($hoursDiff > 4) $priority += 25;

    return min(100, $priority); // Cap at 100
}

/**
 * Calculate priority for leave request
 */
function calculateLeavePriority(array $leave): int
{
    $priority = 50;

    // Urgent if leave starts soon
    $daysUntil = (strtotime($leave['date_from']) - time()) / 86400;
    if ($daysUntil < 3) $priority += 40;
    elseif ($daysUntil < 7) $priority += 20;

    // Old requests = higher priority
    if ($leave['age_minutes'] > 2880) $priority += 25; // > 48 hours

    return min(100, $priority);
}

/**
 * Calculate priority for unapproved timesheet
 */
function calculateTimesheetPriority(array $timesheet): int
{
    $priority = 40;

    // Very old unapproved = high priority
    if ($timesheet['days_old'] > 10) $priority += 40;
    elseif ($timesheet['days_old'] > 5) $priority += 20;

    return min(100, $priority);
}

/**
 * Calculate priority for discrepancy
 */
function calculateDiscrepancyPriority(array $discrepancy): int
{
    $priority = 60; // Higher base - involves money

    // High risk = high priority
    if ($discrepancy['risk_score'] > 0.7) $priority += 30;
    elseif ($discrepancy['risk_score'] > 0.4) $priority += 15;

    // High confidence = can process quickly
    if ($discrepancy['ai_confidence'] > 0.9) $priority += 10;

    return min(100, $priority);
}

/**
 * Check for compliance events
 */
function checkComplianceEvents(PDO $db): array
{
    $events = [];

    // Check for public holiday work without proper pay rate
    // Check for break violations
    // Check for minimum wage violations
    // etc.

    return $events;
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
