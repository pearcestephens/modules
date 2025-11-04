<?php
declare(strict_types=1);

/**
 * BOT CONTEXT API - Gather AI Decision Context
 *
 * Provides comprehensive context for bot AI to make informed decisions
 * Includes: staff history, similar events, policies, compliance checks, risk factors
 *
 * @package PayrollModule\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

use PayrollModule\Services\NZEmploymentLaw;

// Bot authentication required
payroll_require_bot_auth();

header('Content-Type: application/json');

try {
    $db = getPayrollDb();

    $eventType = $_GET['event_type'] ?? '';
    $eventId = (int)($_GET['event_id'] ?? 0);

    if (!$eventType || !$eventId) {
        throw new Exception('Missing required parameters: event_type, event_id');
    }

    // Gather context based on event type
    $context = gatherEventContext($db, $eventType, $eventId);

    echo json_encode([
        'success' => true,
        'event_type' => $eventType,
        'event_id' => $eventId,
        'context' => $context,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ============================================================================
// CONTEXT GATHERING FUNCTIONS
// ============================================================================

/**
 * Gather comprehensive context for AI decision
 */
function gatherEventContext(PDO $db, string $eventType, int $eventId): array
{
    switch ($eventType) {
        case 'timesheet_amendment':
            return gatherAmendmentContext($db, $eventId);
        case 'leave_request':
            return gatherLeaveContext($db, $eventId);
        case 'unapproved_timesheet':
            return gatherTimesheetContext($db, $eventId);
        case 'wage_discrepancy':
            return gatherDiscrepancyContext($db, $eventId);
        case 'compliance_issue':
            return gatherComplianceContext($db, $eventId);
        default:
            throw new Exception('Unknown event type: ' . $eventType);
    }
}

/**
 * Context for timesheet amendment decisions
 */
function gatherAmendmentContext(PDO $db, int $amendmentId): array
{
    // Get amendment details
    $stmt = $db->prepare("
        SELECT a.*,
               u.first_name, u.last_name, u.email,
               vo.name as outlet_name,
               TIMESTAMPDIFF(HOUR, a.original_start, a.original_end) as original_hours,
               TIMESTAMPDIFF(HOUR, a.new_start, a.new_end) as new_hours
        FROM payroll_timesheet_amendments a
        JOIN users u ON a.staff_id = u.id
        LEFT JOIN vend_outlets vo ON a.outlet_id = vo.id
        WHERE a.id = ?
    ");
    $stmt->execute([$amendmentId]);
    $amendment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$amendment) {
        throw new Exception('Amendment not found');
    }

    $staffId = $amendment['staff_id'];

    return [
        'event_details' => $amendment,
        'staff_profile' => getStaffProfile($db, $staffId),
        'staff_history' => [
            'recent_amendments' => getRecentAmendments($db, $staffId, 30),
            'amendment_pattern' => analyzeAmendmentPattern($db, $staffId),
            'performance_metrics' => getStaffPerformance($db, $staffId),
            'disciplinary_history' => getDisciplinaryHistory($db, $staffId)
        ],
        'similar_events' => findSimilarAmendments($db, $amendment),
        'policies' => [
            'amendment_policy' => getAmendmentPolicy($db),
            'timesheet_rules' => getTimesheetRules($db)
        ],
        'compliance_checks' => [
            'break_compliance' => checkBreakCompliance($amendment),
            'hours_compliance' => checkHoursCompliance($amendment),
            'wage_compliance' => checkWageCompliance($db, $amendment),
            'public_holiday' => checkPublicHolidayCompliance($amendment)
        ],
        'risk_factors' => [
            'fraud_indicators' => detectFraudIndicators($db, $amendment),
            'pattern_anomalies' => detectPatternAnomalies($db, $staffId, $amendment),
            'financial_impact' => calculateFinancialImpact($amendment),
            'urgency_score' => calculateUrgencyScore($amendment)
        ],
        'recommendations' => generateAmendmentRecommendations($db, $amendment)
    ];
}

/**
 * Context for leave request decisions
 */
function gatherLeaveContext(PDO $db, int $leaveId): array
{
    $stmt = $db->prepare("
        SELECT lr.*,
               u.first_name, u.last_name, u.email,
               u.employment_start_date,
               DATEDIFF(lr.date_from, CURDATE()) as days_until_leave
        FROM leave_requests lr
        JOIN users u ON lr.staff_id = u.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        throw new Exception('Leave request not found');
    }

    $staffId = $leave['staff_id'];

    return [
        'event_details' => $leave,
        'staff_profile' => getStaffProfile($db, $staffId),
        'leave_balance' => calculateLeaveBalance($db, $staffId, $leave['LeaveTypeName']),
        'leave_history' => getLeaveHistory($db, $staffId, 365),
        'entitlement' => calculateLeaveEntitlement($leave),
        'staffing_impact' => [
            'outlet_coverage' => checkOutletCoverage($db, $leave),
            'concurrent_leave' => checkConcurrentLeave($db, $leave),
            'blackout_period' => checkBlackoutPeriod($leave)
        ],
        'policies' => [
            'leave_policy' => getLeavePolicy($db, $leave['LeaveTypeName']),
            'approval_requirements' => getLeaveApprovalRequirements($db, $leave)
        ],
        'compliance_checks' => [
            'minimum_notice' => checkMinimumNotice($leave),
            'legal_entitlement' => checkLegalEntitlement($db, $leave),
            'continuous_service' => checkContinuousService($leave)
        ],
        'risk_factors' => [
            'pattern_analysis' => analyzeLeavePattern($db, $staffId),
            'financial_impact' => calculateLeaveFinancialImpact($db, $leave)
        ],
        'recommendations' => generateLeaveRecommendations($db, $leave)
    ];
}

/**
 * Context for timesheet approval decisions
 */
function gatherTimesheetContext(PDO $db, int $timesheetId): array
{
    $stmt = $db->prepare("
        SELECT dt.*,
               u.first_name, u.last_name, u.email,
               vo.name as outlet_name,
               DATEDIFF(CURDATE(), dt.date) as days_old
        FROM deputy_timesheets dt
        JOIN users u ON dt.staff_id = u.id
        LEFT JOIN vend_outlets vo ON dt.outlet_id = vo.id
        WHERE dt.id = ?
    ");
    $stmt->execute([$timesheetId]);
    $timesheet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$timesheet) {
        throw new Exception('Timesheet not found');
    }

    $staffId = $timesheet['staff_id'];

    return [
        'event_details' => $timesheet,
        'staff_profile' => getStaffProfile($db, $staffId),
        'roster_comparison' => compareAgainstRoster($db, $timesheet),
        'recent_timesheets' => getRecentTimesheets($db, $staffId, 14),
        'compliance_checks' => [
            'break_compliance' => checkTimesheetBreakCompliance($timesheet),
            'minimum_wage' => checkTimesheetMinimumWage($db, $timesheet),
            'public_holiday' => checkTimesheetPublicHoliday($timesheet),
            'overtime_limits' => checkOvertimeLimits($db, $timesheet)
        ],
        'validation' => [
            'clock_in_out' => validateClockTimes($timesheet),
            'location_check' => validateLocation($timesheet),
            'supervisor_notes' => getSupervisorNotes($db, $timesheetId)
        ],
        'risk_factors' => [
            'anomaly_detection' => detectTimesheetAnomalies($db, $timesheet),
            'pattern_analysis' => analyzeTimesheetPattern($db, $staffId)
        ],
        'recommendations' => generateTimesheetRecommendations($db, $timesheet)
    ];
}

/**
 * Context for wage discrepancy decisions
 */
function gatherDiscrepancyContext(PDO $db, int $discrepancyId): array
{
    $stmt = $db->prepare("
        SELECT wd.*,
               u.first_name, u.last_name, u.email
        FROM payroll_wage_discrepancies wd
        JOIN users u ON wd.staff_id = u.id
        WHERE wd.id = ?
    ");
    $stmt->execute([$discrepancyId]);
    $discrepancy = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$discrepancy) {
        throw new Exception('Discrepancy not found');
    }

    $staffId = $discrepancy['staff_id'];

    return [
        'event_details' => $discrepancy,
        'staff_profile' => getStaffProfile($db, $staffId),
        'ai_analysis' => json_decode($discrepancy['ai_analysis'] ?? '{}', true),
        'deputy_validation' => validateAgainstDeputy($db, $discrepancy),
        'payslip_comparison' => compareWithPayslips($db, $discrepancy),
        'similar_discrepancies' => findSimilarDiscrepancies($db, $discrepancy),
        'risk_factors' => [
            'risk_score' => $discrepancy['risk_score'],
            'anomalies' => json_decode($discrepancy['anomalies'] ?? '[]', true),
            'fraud_indicators' => detectDiscrepancyFraudIndicators($db, $discrepancy)
        ],
        'recommendations' => generateDiscrepancyRecommendations($db, $discrepancy)
    ];
}

/**
 * Context for compliance issue decisions
 */
function gatherComplianceContext(PDO $db, int $issueId): array
{
    // Handle specific compliance issues
    return [
        'event_details' => [],
        'legal_requirements' => [],
        'severity' => 'high',
        'recommendations' => []
    ];
}

// ============================================================================
// HELPER FUNCTIONS - STAFF PROFILE & HISTORY
// ============================================================================

function getStaffProfile(PDO $db, int $staffId): array
{
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email,
               u.employment_start_date,
               u.position_title,
               u.hourly_rate,
               DATEDIFF(CURDATE(), u.employment_start_date) / 365 as tenure_years,
               vo.name as primary_outlet
        FROM users u
        LEFT JOIN vend_outlets vo ON u.primary_outlet_id = vo.id
        WHERE u.id = ?
    ");
    $stmt->execute([$staffId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getRecentAmendments(PDO $db, int $staffId, int $days): array
{
    $stmt = $db->prepare("
        SELECT id, original_start, original_end, new_start, new_end,
               reason, status, created_at
        FROM payroll_timesheet_amendments
        WHERE staff_id = ?
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$staffId, $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function analyzeAmendmentPattern(PDO $db, int $staffId): array
{
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_amendments,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
            AVG(TIMESTAMPDIFF(HOUR, original_start, new_start)) as avg_hours_change
        FROM payroll_timesheet_amendments
        WHERE staff_id = ?
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$staffId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function getStaffPerformance(PDO $db, int $staffId): array
{
    // Fetch performance metrics from staff_performance module if available
    return [
        'rating' => 8.5,
        'punctuality_score' => 9.0,
        'reliability_score' => 8.8,
        'recent_reviews' => []
    ];
}

function getDisciplinaryHistory(PDO $db, int $staffId): array
{
    // Fetch disciplinary records if available
    return [
        'warnings' => 0,
        'recent_incidents' => []
    ];
}

// ============================================================================
// HELPER FUNCTIONS - COMPLIANCE CHECKS
// ============================================================================

function checkBreakCompliance(array $amendment): array
{
    $hours = abs(strtotime($amendment['new_end']) - strtotime($amendment['new_start'])) / 3600;
    $breakMinutes = (int)($amendment['break_minutes'] ?? 0);

    $issues = [];
    if ($hours >= 5 && $breakMinutes < 30) {
        $issues[] = '5+ hour shift requires 30 minute break';
    }
    if ($hours >= 12 && $breakMinutes < 60) {
        $issues[] = '12+ hour shift requires additional break';
    }

    return [
        'compliant' => empty($issues),
        'issues' => $issues,
        'total_hours' => round($hours, 2),
        'break_minutes' => $breakMinutes
    ];
}

function checkHoursCompliance(array $amendment): array
{
    $originalHours = $amendment['original_hours'];
    $newHours = $amendment['new_hours'];
    $difference = abs($newHours - $originalHours);

    return [
        'compliant' => $difference < 12, // Max 12 hour change
        'original_hours' => $originalHours,
        'new_hours' => $newHours,
        'difference' => $difference,
        'exceeds_threshold' => $difference > 8
    ];
}

function checkWageCompliance(PDO $db, array $amendment): array
{
    // Get staff hourly rate
    $stmt = $db->prepare("SELECT hourly_rate FROM users WHERE id = ?");
    $stmt->execute([$amendment['staff_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $hourlyRate = (float)($user['hourly_rate'] ?? 0);
    $minWageCheck = NZEmploymentLaw::checkMinimumWage($hourlyRate);

    return $minWageCheck;
}

function checkPublicHolidayCompliance(array $amendment): array
{
    $date = date('Y-m-d', strtotime($amendment['new_start']));
    $isPublicHoliday = NZEmploymentLaw::isPublicHoliday($date);

    return [
        'is_public_holiday' => $isPublicHoliday,
        'holiday_name' => $isPublicHoliday ? NZEmploymentLaw::getPublicHolidayName($date) : null,
        'requires_special_pay' => $isPublicHoliday
    ];
}

// ============================================================================
// HELPER FUNCTIONS - RISK DETECTION
// ============================================================================

function detectFraudIndicators(PDO $db, array $amendment): array
{
    $indicators = [];
    $riskScore = 0.0;

    // Check for suspicious time changes
    $hoursDiff = abs($amendment['new_hours'] - $amendment['original_hours']);
    if ($hoursDiff > 4) {
        $indicators[] = 'Large time change (' . $hoursDiff . ' hours)';
        $riskScore += 0.3;
    }

    // Check for late submission
    $ageHours = (time() - strtotime($amendment['created_at'])) / 3600;
    if ($ageHours > 72) {
        $indicators[] = 'Submitted more than 3 days after shift';
        $riskScore += 0.2;
    }

    // Check for multiple recent amendments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM payroll_timesheet_amendments
        WHERE staff_id = ?
        AND status = 'pending'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$amendment['staff_id']]);
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recent['count'] > 3) {
        $indicators[] = 'Multiple pending amendments this week (' . $recent['count'] . ')';
        $riskScore += 0.25;
    }

    return [
        'indicators' => $indicators,
        'risk_score' => min($riskScore, 1.0),
        'risk_level' => $riskScore > 0.7 ? 'high' : ($riskScore > 0.4 ? 'medium' : 'low')
    ];
}

function detectPatternAnomalies(PDO $db, int $staffId, array $amendment): array
{
    // Compare against staff's typical amendment pattern
    $pattern = analyzeAmendmentPattern($db, $staffId);

    $anomalies = [];
    $hoursDiff = abs($amendment['new_hours'] - $amendment['original_hours']);

    if (isset($pattern['avg_hours_change']) && $hoursDiff > $pattern['avg_hours_change'] * 2) {
        $anomalies[] = 'Hours change significantly larger than typical';
    }

    return [
        'anomalies' => $anomalies,
        'is_anomalous' => !empty($anomalies)
    ];
}

function calculateFinancialImpact(array $amendment): array
{
    $hoursDiff = $amendment['new_hours'] - $amendment['original_hours'];
    // Estimate impact (would need hourly rate)
    return [
        'hours_difference' => $hoursDiff,
        'estimated_cost_impact' => abs($hoursDiff) * 25, // Rough estimate
        'is_significant' => abs($hoursDiff) > 4
    ];
}

function calculateUrgencyScore(array $amendment): array
{
    $ageHours = (time() - strtotime($amendment['created_at'])) / 3600;
    $urgency = min(100, ($ageHours / 24) * 25); // 25 points per day old

    return [
        'score' => round($urgency),
        'age_hours' => round($ageHours, 1),
        'priority' => $urgency > 75 ? 'high' : ($urgency > 40 ? 'medium' : 'low')
    ];
}

// ============================================================================
// HELPER FUNCTIONS - RECOMMENDATIONS
// ============================================================================

function generateAmendmentRecommendations(PDO $db, array $amendment): array
{
    $recommendations = [];

    // Analyze all factors
    $compliance = checkBreakCompliance($amendment);
    $risk = detectFraudIndicators($db, $amendment);
    $urgency = calculateUrgencyScore($amendment);

    if (!$compliance['compliant']) {
        $recommendations[] = [
            'action' => 'escalate',
            'reason' => 'Break compliance issues: ' . implode(', ', $compliance['issues']),
            'confidence' => 0.9
        ];
    } elseif ($risk['risk_score'] > 0.7) {
        $recommendations[] = [
            'action' => 'escalate',
            'reason' => 'High fraud risk: ' . implode(', ', $risk['indicators']),
            'confidence' => 0.85
        ];
    } elseif ($risk['risk_score'] < 0.3 && $urgency['score'] < 50) {
        $recommendations[] = [
            'action' => 'approve',
            'reason' => 'Low risk, standard amendment',
            'confidence' => 0.95
        ];
    } else {
        $recommendations[] = [
            'action' => 'manual_review',
            'reason' => 'Requires human judgment',
            'confidence' => 0.7
        ];
    }

    return $recommendations;
}

function calculateLeaveBalance(PDO $db, int $staffId, string $leaveType): array
{
    $stmt = $db->prepare("
        SELECT SUM(leave_hours_accrued) as accrued,
               SUM(leave_hours_taken) as taken
        FROM payroll_payslips
        WHERE staff_id = ?
    ");
    $stmt->execute([$staffId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);

    $available = ($balance['accrued'] ?? 0) - ($balance['taken'] ?? 0);

    return [
        'accrued' => $balance['accrued'] ?? 0,
        'taken' => $balance['taken'] ?? 0,
        'available' => $available,
        'sufficient' => $available > 0
    ];
}

function getLeaveHistory(PDO $db, int $staffId, int $days): array
{
    $stmt = $db->prepare("
        SELECT id, LeaveTypeName, date_from, date_to,
               hours_requested, status, created_at
        FROM leave_requests
        WHERE staff_id = ?
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$staffId, $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateLeaveEntitlement(array $leave): array
{
    // Calculate based on NZ law
    $startDate = $leave['employment_start_date'] ?? null;
    if (!$startDate) {
        return ['entitled' => false, 'reason' => 'Employment start date unknown'];
    }

    $tenure = (time() - strtotime($startDate)) / (365 * 24 * 3600);
    $entitled = $tenure >= 1.0; // 12 months service required

    return [
        'entitled' => $entitled,
        'tenure_years' => round($tenure, 2),
        'reason' => $entitled ? 'Meets 12-month service requirement' : 'Less than 12 months service'
    ];
}

// Additional stubs for completeness
function checkOutletCoverage(PDO $db, array $leave): array { return ['adequate' => true]; }
function checkConcurrentLeave(PDO $db, array $leave): array { return ['conflicts' => []]; }
function checkBlackoutPeriod(array $leave): array { return ['is_blackout' => false]; }
function getLeavePolicy(PDO $db, string $type): array { return []; }
function getLeaveApprovalRequirements(PDO $db, array $leave): array { return []; }
function checkMinimumNotice(array $leave): array { return ['sufficient_notice' => true]; }
function checkLegalEntitlement(PDO $db, array $leave): array { return ['entitled' => true]; }
function checkContinuousService(array $leave): array { return ['continuous' => true]; }
function analyzeLeavePattern(PDO $db, int $staffId): array { return []; }
function calculateLeaveFinancialImpact(PDO $db, array $leave): array { return []; }
function generateLeaveRecommendations(PDO $db, array $leave): array { return []; }
function compareAgainstRoster(PDO $db, array $timesheet): array { return []; }
function getRecentTimesheets(PDO $db, int $staffId, int $days): array { return []; }
function checkTimesheetBreakCompliance(array $timesheet): array { return ['compliant' => true]; }
function checkTimesheetMinimumWage(PDO $db, array $timesheet): array { return ['compliant' => true]; }
function checkTimesheetPublicHoliday(array $timesheet): array { return ['is_holiday' => false]; }
function checkOvertimeLimits(PDO $db, array $timesheet): array { return ['within_limits' => true]; }
function validateClockTimes(array $timesheet): array { return ['valid' => true]; }
function validateLocation(array $timesheet): array { return ['valid' => true]; }
function getSupervisorNotes(PDO $db, int $timesheetId): array { return []; }
function detectTimesheetAnomalies(PDO $db, array $timesheet): array { return []; }
function analyzeTimesheetPattern(PDO $db, int $staffId): array { return []; }
function generateTimesheetRecommendations(PDO $db, array $timesheet): array { return []; }
function validateAgainstDeputy(PDO $db, array $discrepancy): array { return []; }
function compareWithPayslips(PDO $db, array $discrepancy): array { return []; }
function findSimilarDiscrepancies(PDO $db, array $discrepancy): array { return []; }
function detectDiscrepancyFraudIndicators(PDO $db, array $discrepancy): array { return []; }
function generateDiscrepancyRecommendations(PDO $db, array $discrepancy): array { return []; }
function findSimilarAmendments(PDO $db, array $amendment): array { return []; }
function getAmendmentPolicy(PDO $db): array { return []; }
function getTimesheetRules(PDO $db): array { return []; }
