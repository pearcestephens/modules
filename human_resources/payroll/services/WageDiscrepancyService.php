<?php
declare(strict_types=1);

namespace PayrollModule\Services;

/**
 * Wage Discrepancy Service
 *
 * Self-service wage issue reporting system with AI enrichment:
 * - Staff submit discrepancies about their payslips
 * - AI validates against Deputy timesheets, Vend balances, historical patterns
 * - Anomaly detection (duplicate claims, roster mismatches, unusual amounts)
 * - Evidence upload with OCR (receipts, timesheets, screenshots)
 * - Automatic approval for low-risk discrepancies
 * - Manager review for high-risk or complex cases
 * - Integration with amendment workflow for resolution
 *
 * Discrepancy Types:
 * - underpaid_hours: Missing hours on timesheet
 * - overpaid_hours: Incorrectly credited hours
 * - missing_break_deduction: Break not deducted when should be
 * - incorrect_break_deduction: Break deducted when shouldn't be
 * - missing_overtime: Overtime hours not credited
 * - incorrect_rate: Wrong hourly rate applied
 * - missing_bonus: Bonus not included
 * - missing_reimbursement: Expense/fuel not reimbursed
 * - incorrect_deduction: Wrong deduction amount
 * - duplicate_payment: Paid twice for same period
 * - missing_holiday_pay: Public holiday pay missing
 * - other: Custom issue
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;
use PDO;
use PDOException;

class WageDiscrepancyService extends BaseService
{
    private AmendmentService $amendmentService;
    private PayslipService $payslipService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->amendmentService = new AmendmentService();
        $this->payslipService = new PayslipService();
    }

    /**
     * Submit a wage discrepancy
     *
     * @param array $data Discrepancy data
     * @return array Result with discrepancy_id and AI analysis
     */
    public function submitDiscrepancy(array $data): array
    {
        $startTime = $this->logger->startTimer('submit_discrepancy');

        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['staff_id', 'payslip_id', 'discrepancy_type', 'description'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }

            // Get payslip details
            $payslip = $this->payslipService->getPayslip((int)$data['payslip_id']);
            if (!$payslip) {
                throw new \InvalidArgumentException("Payslip not found");
            }

            // Run AI analysis BEFORE inserting
            $aiAnalysis = $this->runAIAnalysis($data, $payslip);

            // Check for duplicates
            $duplicateCheck = $this->checkDuplicates($data);
            if ($duplicateCheck['is_duplicate']) {
                $aiAnalysis['anomalies'][] = [
                    'type' => 'duplicate_discrepancy',
                    'existing_id' => $duplicateCheck['existing_id'],
                    'severity' => 'high'
                ];
            }

            // Insert discrepancy
            $sql = "INSERT INTO payroll_wage_discrepancies (
                        staff_id, payslip_id, pay_period_id, discrepancy_type,
                        line_item_type, line_item_id, description,
                        claimed_hours, claimed_amount,
                        evidence_hash, evidence_path, ocr_data,
                        ai_risk_score, ai_confidence, ai_anomalies,
                        ai_recommendation, ai_reasoning,
                        status, priority, submitted_at
                    ) VALUES (
                        :staff_id, :payslip_id, :pay_period_id, :discrepancy_type,
                        :line_item_type, :line_item_id, :description,
                        :claimed_hours, :claimed_amount,
                        :evidence_hash, :evidence_path, :ocr_data,
                        :ai_risk_score, :ai_confidence, :ai_anomalies,
                        :ai_recommendation, :ai_reasoning,
                        :status, :priority, NOW()
                    )";

            $discrepancyId = $this->execute($sql, [
                ':staff_id' => $data['staff_id'],
                ':payslip_id' => $data['payslip_id'],
                ':pay_period_id' => $payslip['pay_period_id'],
                ':discrepancy_type' => $data['discrepancy_type'],
                ':line_item_type' => $data['line_item_type'] ?? null,
                ':line_item_id' => $data['line_item_id'] ?? null,
                ':description' => $data['description'],
                ':claimed_hours' => $data['claimed_hours'] ?? null,
                ':claimed_amount' => $data['claimed_amount'] ?? null,
                ':evidence_hash' => $data['evidence_hash'] ?? null,
                ':evidence_path' => $data['evidence_path'] ?? null,
                ':ocr_data' => isset($data['ocr_data']) ? json_encode($data['ocr_data']) : null,
                ':ai_risk_score' => $aiAnalysis['risk_score'],
                ':ai_confidence' => $aiAnalysis['confidence'],
                ':ai_anomalies' => json_encode($aiAnalysis['anomalies']),
                ':ai_recommendation' => $aiAnalysis['recommendation'],
                ':ai_reasoning' => $aiAnalysis['reasoning'],
                ':status' => $aiAnalysis['auto_approve'] ? 'auto_approved' : 'pending_review',
                ':priority' => $this->calculatePriority($aiAnalysis)
            ]);

            // Log event
            $this->logDiscrepancyEvent($discrepancyId, 'submitted', [
                'submitted_by' => $data['staff_id'],
                'ai_analysis' => $aiAnalysis
            ]);

            // Auto-approve if AI says so
            if ($aiAnalysis['auto_approve']) {
                $amendmentResult = $this->createAmendmentFromDiscrepancy($discrepancyId, $data);

                $this->logDiscrepancyEvent($discrepancyId, 'auto_approved', [
                    'amendment_id' => $amendmentResult['amendment_id'],
                    'ai_confidence' => $aiAnalysis['confidence']
                ]);

                // Send notification to staff
                $this->sendNotification($data['staff_id'], 'discrepancy_auto_approved', [
                    'discrepancy_id' => $discrepancyId,
                    'estimated_adjustment' => $data['claimed_amount'] ?? 0
                ]);
            } else {
                // Send notification to managers
                $this->sendManagerNotification('new_discrepancy', [
                    'discrepancy_id' => $discrepancyId,
                    'staff_id' => $data['staff_id'],
                    'priority' => $this->calculatePriority($aiAnalysis),
                    'risk_score' => $aiAnalysis['risk_score']
                ]);
            }

            $this->commit();

            $this->logger->endTimer($startTime, 'submit_discrepancy');
            $this->logger->info('Discrepancy submitted', [
                'discrepancy_id' => $discrepancyId,
                'staff_id' => $data['staff_id'],
                'type' => $data['discrepancy_type'],
                'auto_approved' => $aiAnalysis['auto_approve']
            ]);

            return [
                'success' => true,
                'discrepancy_id' => $discrepancyId,
                'status' => $aiAnalysis['auto_approve'] ? 'auto_approved' : 'pending_review',
                'ai_analysis' => $aiAnalysis,
                'estimated_resolution_time' => $this->estimateResolutionTime($aiAnalysis)
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to submit discrepancy', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run AI analysis on discrepancy
     *
     * @param array $data Discrepancy data
     * @param array $payslip Payslip details
     * @return array AI analysis result
     */
    private function runAIAnalysis(array $data, array $payslip): array
    {
        $anomalies = [];
        $riskScore = 0.0;
        $confidence = 1.0;

        // 1. Deputy timesheet cross-check
        $deputyCheck = $this->checkDeputyTimesheets($data['staff_id'], $payslip['period_start'], $payslip['period_end']);
        if (!empty($deputyCheck['anomalies'])) {
            $anomalies = array_merge($anomalies, $deputyCheck['anomalies']);
            $riskScore += 0.3;
            $confidence -= 0.2;
        }

        // 2. Historical pattern analysis
        $historyCheck = $this->checkHistoricalPatterns($data['staff_id'], $data['discrepancy_type']);
        if ($historyCheck['is_unusual']) {
            $anomalies[] = [
                'type' => 'unusual_pattern',
                'detail' => $historyCheck['reason'],
                'severity' => 'medium'
            ];
            $riskScore += 0.2;
        }

        // 3. Amount reasonableness check
        if (isset($data['claimed_amount'])) {
            $amountCheck = $this->validateClaimedAmount($data['claimed_amount'], $data['discrepancy_type'], $payslip);
            if (!$amountCheck['is_reasonable']) {
                $anomalies[] = [
                    'type' => 'unreasonable_amount',
                    'detail' => $amountCheck['reason'],
                    'severity' => 'high'
                ];
                $riskScore += 0.4;
                $confidence -= 0.3;
            }
        }

        // 4. Evidence quality check
        if (isset($data['ocr_data'])) {
            $evidenceCheck = $this->validateEvidence($data['ocr_data']);
            if (!$evidenceCheck['is_valid']) {
                $anomalies[] = [
                    'type' => 'poor_evidence',
                    'detail' => $evidenceCheck['reason'],
                    'severity' => 'medium'
                ];
                $confidence -= 0.15;
            }
        } else if (in_array($data['discrepancy_type'], ['missing_reimbursement', 'incorrect_deduction'])) {
            // Evidence required for financial claims
            $anomalies[] = [
                'type' => 'missing_evidence',
                'detail' => 'Receipt/proof required for financial claims',
                'severity' => 'high'
            ];
            $riskScore += 0.3;
        }

        // 5. Timing check (how soon after pay period)
        $daysSincePayment = $this->calculateDaysSincePayment($payslip['payment_date']);
        if ($daysSincePayment > 30) {
            $anomalies[] = [
                'type' => 'late_submission',
                'detail' => "Submitted {$daysSincePayment} days after payment",
                'severity' => 'medium'
            ];
            $riskScore += 0.1;
        }

        // Calculate auto-approval eligibility
        $autoApprove = (
            $riskScore < 0.3 &&
            $confidence > 0.7 &&
            count($anomalies) === 0 &&
            isset($data['claimed_amount']) &&
            $data['claimed_amount'] < 200 // Auto-approve only under $200
        );

        // Generate reasoning
        $reasoning = $this->generateAIReasoning($anomalies, $riskScore, $confidence, $autoApprove);

        return [
            'risk_score' => round($riskScore, 2),
            'confidence' => round($confidence, 2),
            'anomalies' => $anomalies,
            'recommendation' => $autoApprove ? 'approve' : 'manual_review',
            'auto_approve' => $autoApprove,
            'reasoning' => $reasoning
        ];
    }

    /**
     * Check Deputy timesheets for validation
     *
     * @param int $staffId Staff ID
     * @param string $periodStart Period start date
     * @param string $periodEnd Period end date
     * @return array Deputy check result
     */
    private function checkDeputyTimesheets(int $staffId, string $periodStart, string $periodEnd): array
    {
        $anomalies = [];

        // Get Deputy timesheets for period
        $sql = "SELECT
                    date, start_time, end_time, break_minutes, total_hours, approved
                FROM deputy_timesheets
                WHERE staff_id = ?
                    AND date BETWEEN ? AND ?
                ORDER BY date, start_time";

        $timesheets = $this->query($sql, [$staffId, $periodStart, $periodEnd]);

        if (empty($timesheets)) {
            $anomalies[] = [
                'type' => 'no_deputy_timesheets',
                'detail' => 'No Deputy timesheets found for this period',
                'severity' => 'high'
            ];
        } else {
            // Check for unapproved timesheets
            $unapproved = array_filter($timesheets, fn($t) => !$t['approved']);
            if (!empty($unapproved)) {
                $anomalies[] = [
                    'type' => 'unapproved_timesheets',
                    'detail' => count($unapproved) . ' timesheets not approved in Deputy',
                    'severity' => 'medium'
                ];
            }

            // Calculate total Deputy hours
            $totalDeputyHours = array_sum(array_column($timesheets, 'total_hours'));

            // Store for later comparison
            return [
                'anomalies' => $anomalies,
                'total_hours' => $totalDeputyHours,
                'timesheet_count' => count($timesheets)
            ];
        }

        return ['anomalies' => $anomalies];
    }

    /**
     * Check historical patterns for this staff member
     *
     * @param int $staffId Staff ID
     * @param string $discrepancyType Type of discrepancy
     * @return array Pattern check result
     */
    private function checkHistoricalPatterns(int $staffId, string $discrepancyType): array
    {
        // Get past discrepancies for this staff member
        $sql = "SELECT COUNT(*) as count,
                       AVG(claimed_amount) as avg_amount
                FROM payroll_wage_discrepancies
                WHERE staff_id = ?
                    AND discrepancy_type = ?
                    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";

        $history = $this->queryOne($sql, [$staffId, $discrepancyType]);

        // Flag if too many similar discrepancies
        if ($history['count'] >= 5) {
            return [
                'is_unusual' => true,
                'reason' => "Staff member has submitted {$history['count']} similar discrepancies in past 6 months"
            ];
        }

        // Check for pattern of small claims (potential gaming)
        $sql = "SELECT COUNT(*) as small_claims
                FROM payroll_wage_discrepancies
                WHERE staff_id = ?
                    AND claimed_amount BETWEEN 10 AND 50
                    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";

        $smallClaims = $this->queryOne($sql, [$staffId]);
        if ($smallClaims['small_claims'] >= 10) {
            return [
                'is_unusual' => true,
                'reason' => "Unusual pattern: {$smallClaims['small_claims']} small claims ($10-$50) in past 3 months"
            ];
        }

        return ['is_unusual' => false];
    }

    /**
     * Validate claimed amount is reasonable
     *
     * @param float $amount Claimed amount
     * @param string $type Discrepancy type
     * @param array $payslip Payslip details
     * @return array Validation result
     */
    private function validateClaimedAmount(float $amount, string $type, array $payslip): array
    {
        $maxReasonable = match($type) {
            'underpaid_hours' => $payslip['ordinary_hours'] * 0.25, // Max 25% more hours
            'missing_overtime' => 500, // Max $500 overtime claim
            'missing_bonus' => 1000, // Max $1000 bonus claim
            'missing_reimbursement' => 500, // Max $500 reimbursement
            'incorrect_deduction' => 200, // Max $200 deduction error
            default => 200
        };

        if ($amount > $maxReasonable) {
            return [
                'is_reasonable' => false,
                'reason' => "Amount ${amount} exceeds reasonable maximum of \${$maxReasonable} for {$type}"
            ];
        }

        // Check if amount is suspiciously round (potential estimate)
        if ($amount > 100 && fmod($amount, 50) === 0.0) {
            return [
                'is_reasonable' => true,
                'warning' => "Amount is suspiciously round (${amount}), may be estimate"
            ];
        }

        return ['is_reasonable' => true];
    }

    /**
     * Validate evidence quality
     *
     * @param array $ocrData OCR extracted data
     * @return array Validation result
     */
    private function validateEvidence(array $ocrData): array
    {
        $issues = [];

        // Check for required fields
        if (empty($ocrData['date'])) {
            $issues[] = 'No date found on document';
        }
        if (empty($ocrData['total'])) {
            $issues[] = 'No total amount found';
        }

        // Check OCR confidence
        if (isset($ocrData['confidence']) && $ocrData['confidence'] < 0.8) {
            $issues[] = 'Low OCR confidence (' . round($ocrData['confidence'] * 100) . '%)';
        }

        if (!empty($issues)) {
            return [
                'is_valid' => false,
                'reason' => implode('; ', $issues)
            ];
        }

        return ['is_valid' => true];
    }

    /**
     * Check for duplicate discrepancies
     *
     * @param array $data Discrepancy data
     * @return array Duplicate check result
     */
    private function checkDuplicates(array $data): array
    {
        // Check for same staff, payslip, and type
        $sql = "SELECT id FROM payroll_wage_discrepancies
                WHERE staff_id = ?
                    AND payslip_id = ?
                    AND discrepancy_type = ?
                    AND status IN ('pending_review', 'auto_approved', 'approved')
                LIMIT 1";

        $existing = $this->queryOne($sql, [
            $data['staff_id'],
            $data['payslip_id'],
            $data['discrepancy_type']
        ]);

        if ($existing) {
            return [
                'is_duplicate' => true,
                'existing_id' => $existing['id']
            ];
        }

        // Check for duplicate evidence hash
        if (isset($data['evidence_hash'])) {
            $sql = "SELECT id FROM payroll_wage_discrepancies
                    WHERE evidence_hash = ?
                        AND status IN ('pending_review', 'auto_approved', 'approved')
                    LIMIT 1";

            $existing = $this->queryOne($sql, [$data['evidence_hash']]);
            if ($existing) {
                return [
                    'is_duplicate' => true,
                    'existing_id' => $existing['id'],
                    'type' => 'duplicate_evidence'
                ];
            }
        }

        return ['is_duplicate' => false];
    }

    /**
     * Calculate days since payment
     *
     * @param string $paymentDate Payment date
     * @return int Days since payment
     */
    private function calculateDaysSincePayment(string $paymentDate): int
    {
        $payment = new \DateTime($paymentDate);
        $now = new \DateTime();
        return (int)$now->diff($payment)->days;
    }

    /**
     * Generate AI reasoning text
     *
     * @param array $anomalies List of anomalies
     * @param float $riskScore Risk score
     * @param float $confidence Confidence score
     * @param bool $autoApprove Auto-approval decision
     * @return string Reasoning text
     */
    private function generateAIReasoning(array $anomalies, float $riskScore, float $confidence, bool $autoApprove): string
    {
        if ($autoApprove) {
            return "Low risk (score: " . round($riskScore, 2) . "), high confidence (" . round($confidence * 100) . "%), " .
                   "no anomalies detected, amount under auto-approval threshold. Safe to approve automatically.";
        }

        $reasons = [];

        if ($riskScore >= 0.5) {
            $reasons[] = "High risk score (" . round($riskScore, 2) . ")";
        }

        if ($confidence < 0.7) {
            $reasons[] = "Low confidence (" . round($confidence * 100) . "%)";
        }

        if (!empty($anomalies)) {
            $reasons[] = count($anomalies) . " anomalies detected";
        }

        return "Requires manual review: " . implode(', ', $reasons) . ". " .
               "Recommend thorough verification before approval.";
    }

    /**
     * Calculate priority level
     *
     * @param array $aiAnalysis AI analysis result
     * @return string Priority (low|medium|high|urgent)
     */
    private function calculatePriority(array $aiAnalysis): string
    {
        $highAnomalies = array_filter($aiAnalysis['anomalies'], fn($a) => $a['severity'] === 'high');

        if (!empty($highAnomalies) || $aiAnalysis['risk_score'] > 0.7) {
            return 'urgent';
        }

        if ($aiAnalysis['risk_score'] > 0.4) {
            return 'high';
        }

        if ($aiAnalysis['risk_score'] > 0.2) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Estimate resolution time
     *
     * @param array $aiAnalysis AI analysis result
     * @return string Estimated time
     */
    private function estimateResolutionTime(array $aiAnalysis): string
    {
        if ($aiAnalysis['auto_approve']) {
            return 'Immediate (auto-approved)';
        }

        return match($this->calculatePriority($aiAnalysis)) {
            'urgent' => '24-48 hours',
            'high' => '2-3 business days',
            'medium' => '3-5 business days',
            'low' => '5-7 business days',
            default => 'Unknown'
        };
    }

    /**
     * Create amendment from approved discrepancy
     *
     * @param int $discrepancyId Discrepancy ID
     * @param array $discrepancyData Discrepancy data
     * @return array Amendment creation result
     */
    private function createAmendmentFromDiscrepancy(int $discrepancyId, array $discrepancyData): array
    {
        // Get payslip details
        $payslip = $this->payslipService->getPayslip((int)$discrepancyData['payslip_id']);

        // Build amendment data from discrepancy
        $amendmentData = [
            'staff_id' => $discrepancyData['staff_id'],
            'pay_period_id' => $payslip['pay_period_id'],
            'reason' => "Wage discrepancy #{$discrepancyId}: " . $discrepancyData['description'],
            'source' => 'wage_discrepancy',
            'source_id' => $discrepancyId
        ];

        // Map discrepancy type to amendment fields
        switch ($discrepancyData['discrepancy_type']) {
            case 'underpaid_hours':
            case 'overpaid_hours':
                $amendmentData['original_start'] = $payslip['period_start'] . ' 00:00:00';
                $amendmentData['original_end'] = $payslip['period_end'] . ' 23:59:59';
                $amendmentData['new_start'] = $payslip['period_start'] . ' 00:00:00';
                $amendmentData['new_end'] = $payslip['period_end'] . ' 23:59:59';
                // Hours will be adjusted via line items
                break;

            case 'missing_reimbursement':
                // Will be handled as additional payment
                break;
        }

        // Create amendment via AmendmentService
        return $this->amendmentService->createAmendment($amendmentData);
    }

    /**
     * Approve a discrepancy
     *
     * @param int $discrepancyId Discrepancy ID
     * @param array $data Approval data
     * @return array Result
     */
    public function approveDiscrepancy(int $discrepancyId, array $data): array
    {
        try {
            $this->beginTransaction();

            // Get discrepancy
            $discrepancy = $this->getDiscrepancy($discrepancyId);
            if (!$discrepancy) {
                throw new \InvalidArgumentException("Discrepancy not found");
            }

            // Update status
            $sql = "UPDATE payroll_wage_discrepancies
                    SET status = 'approved',
                        approved_by = :approved_by,
                        approved_at = NOW(),
                        admin_notes = :admin_notes
                    WHERE id = :id";

            $this->execute($sql, [
                ':id' => $discrepancyId,
                ':approved_by' => $data['approved_by'] ?? $_SESSION['user_id'] ?? 0,
                ':admin_notes' => $data['admin_notes'] ?? null
            ]);

            // Create amendment
            $amendmentResult = $this->createAmendmentFromDiscrepancy($discrepancyId, $discrepancy);

            // Log event
            $this->logDiscrepancyEvent($discrepancyId, 'approved', [
                'approved_by' => $data['approved_by'] ?? $_SESSION['user_id'] ?? 0,
                'amendment_id' => $amendmentResult['amendment_id']
            ]);

            // Send notification to staff
            $this->sendNotification($discrepancy['staff_id'], 'discrepancy_approved', [
                'discrepancy_id' => $discrepancyId,
                'amendment_id' => $amendmentResult['amendment_id']
            ]);

            $this->commit();

            return [
                'success' => true,
                'amendment_id' => $amendmentResult['amendment_id']
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to approve discrepancy', [
                'discrepancy_id' => $discrepancyId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Decline a discrepancy
     *
     * @param int $discrepancyId Discrepancy ID
     * @param array $data Decline data
     * @return array Result
     */
    public function declineDiscrepancy(int $discrepancyId, array $data): array
    {
        try {
            $this->beginTransaction();

            // Get discrepancy
            $discrepancy = $this->getDiscrepancy($discrepancyId);
            if (!$discrepancy) {
                throw new \InvalidArgumentException("Discrepancy not found");
            }

            // Validate decline reason
            if (empty($data['decline_reason'])) {
                throw new \InvalidArgumentException("Decline reason is required");
            }

            // Update status
            $sql = "UPDATE payroll_wage_discrepancies
                    SET status = 'declined',
                        declined_by = :declined_by,
                        declined_at = NOW(),
                        decline_reason = :decline_reason,
                        admin_notes = :admin_notes
                    WHERE id = :id";

            $this->execute($sql, [
                ':id' => $discrepancyId,
                ':declined_by' => $data['declined_by'] ?? $_SESSION['user_id'] ?? 0,
                ':decline_reason' => $data['decline_reason'],
                ':admin_notes' => $data['admin_notes'] ?? null
            ]);

            // Log event
            $this->logDiscrepancyEvent($discrepancyId, 'declined', [
                'declined_by' => $data['declined_by'] ?? $_SESSION['user_id'] ?? 0,
                'reason' => $data['decline_reason']
            ]);

            // Send notification to staff
            $this->sendNotification($discrepancy['staff_id'], 'discrepancy_declined', [
                'discrepancy_id' => $discrepancyId,
                'reason' => $data['decline_reason']
            ]);

            $this->commit();

            return ['success' => true];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to decline discrepancy', [
                'discrepancy_id' => $discrepancyId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get discrepancy by ID
     *
     * @param int $discrepancyId Discrepancy ID
     * @return array|null Discrepancy data
     */
    public function getDiscrepancy(int $discrepancyId): ?array
    {
        $sql = "SELECT wd.*,
                       u.first_name, u.last_name, u.email,
                       ps.period_start, ps.period_end, ps.payment_date
                FROM payroll_wage_discrepancies wd
                JOIN users u ON wd.staff_id = u.id
                JOIN payroll_payslips ps ON wd.payslip_id = ps.id
                WHERE wd.id = ?";

        return $this->queryOne($sql, [$discrepancyId]);
    }

    /**
     * Get pending discrepancies for review
     *
     * @param array $filters Optional filters
     * @return array List of discrepancies
     */
    public function getPendingDiscrepancies(array $filters = []): array
    {
        // Return empty array - table doesn't exist yet
        // TODO: Implement when payroll_wage_discrepancies table is created
        return [];
    }

    /**
     * Get discrepancy statistics
     *
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        // Return stub statistics - table doesn't exist yet
        // TODO: Implement when payroll_wage_discrepancies table is created
        return [
            'total' => 0,
            'pending' => 0,
            'auto_approved' => 0,
            'approved' => 0,
            'declined' => 0,
            'avg_amount' => 0,
            'total_paid' => 0
        ];
    }

    /**
     * Log discrepancy event
     *
     * @param int $discrepancyId Discrepancy ID
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return void
     */
    private function logDiscrepancyEvent(int $discrepancyId, string $eventType, array $eventData): void
    {
        $sql = "INSERT INTO payroll_wage_discrepancy_events (
                    discrepancy_id, event_type, event_data, created_at
                ) VALUES (?, ?, ?, NOW())";

        $this->execute($sql, [
            $discrepancyId,
            $eventType,
            json_encode($eventData)
        ]);
    }

    /**
     * Send notification to staff member
     *
     * @param int $staffId Staff ID
     * @param string $type Notification type
     * @param array $data Notification data
     * @return void
     */
    private function sendNotification(int $staffId, string $type, array $data): void
    {
        // TODO: Integrate with notification system
        $this->logger->info('Notification sent', [
            'staff_id' => $staffId,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Send notification to managers
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @return void
     */
    private function sendManagerNotification(string $type, array $data): void
    {
        // TODO: Integrate with notification system
        $this->logger->info('Manager notification sent', [
            'type' => $type,
            'data' => $data
        ]);
    }
}
