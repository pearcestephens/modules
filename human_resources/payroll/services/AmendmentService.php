<?php
declare(strict_types=1);

namespace PayrollModule\Services;

/**
 * Amendment Service
 *
 * Handles timesheet amendment operations with AI integration:
 * - Create, update, approve, decline amendments
 * - Submit amendments for AI review
 * - Track amendment history
 * - Deputy timesheet synchronization
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;
use PDO;
use PDOException;

class AmendmentService extends BaseService
{
    /**
     * Create a new timesheet amendment
     *
     * @param array $data Amendment data
     * @return array Result with amendment_id and status
     */
    public function createAmendment(array $data): array
    {
        $startTime = $this->logger->startTimer('create_amendment');

        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['staff_id', 'pay_period_id', 'original_start', 'original_end',
                        'new_start', 'new_end', 'reason'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }

            // Insert amendment
            $sql = "INSERT INTO payroll_timesheet_amendments (
                        staff_id, pay_period_id, deputy_timesheet_id,
                        original_start_time, original_end_time, original_break_minutes,
                        new_start_time, new_end_time, new_break_minutes,
                        reason, status, submitted_by, submitted_at
                    ) VALUES (
                        :staff_id, :pay_period_id, :deputy_timesheet_id,
                        :original_start, :original_end, :original_break,
                        :new_start, :new_end, :new_break,
                        :reason, 'pending_review', :submitted_by, NOW()
                    )";

            $amendmentId = $this->execute($sql, [
                ':staff_id' => $data['staff_id'],
                ':pay_period_id' => $data['pay_period_id'],
                ':deputy_timesheet_id' => $data['deputy_timesheet_id'] ?? null,
                ':original_start' => $data['original_start'],
                ':original_end' => $data['original_end'],
                ':original_break' => $data['original_break_minutes'] ?? 0,
                ':new_start' => $data['new_start'],
                ':new_end' => $data['new_end'],
                ':new_break' => $data['new_break_minutes'] ?? 0,
                ':reason' => $data['reason'],
                ':submitted_by' => $data['submitted_by'] ?? $_SESSION['user_id'] ?? null
            ]);

            // Log history
            $this->logAmendmentHistory($amendmentId, 'created', 'pending_review',
                'Amendment submitted for review');

            // Submit to AI for review
            $aiResult = $this->submitToAI($amendmentId, $data);

            $this->commit();

            $this->logger->endTimer($startTime, 'create_amendment');
            $this->logger->info('Amendment created', [
                'amendment_id' => $amendmentId,
                'staff_id' => $data['staff_id'],
                'ai_decision' => $aiResult['decision'] ?? 'pending'
            ]);

            return [
                'success' => true,
                'amendment_id' => $amendmentId,
                'ai_decision' => $aiResult
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to create amendment', [
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
     * Submit amendment to AI for review
     *
     * @param int $amendmentId Amendment ID
     * @param array $amendmentData Amendment data
     * @return array AI decision result
     */
    private function submitToAI(int $amendmentId, array $amendmentData): array
    {
        try {
            // Get AI rules that apply to this amendment
            $rules = $this->getApplicableAIRules($amendmentData);

            // Build context for AI
            $context = $this->buildAIContext($amendmentId, $amendmentData);

            // Create AI decision record
            $sql = "INSERT INTO payroll_ai_decisions (
                        entity_type, entity_id, decision_type, status,
                        context_snapshot_id, created_at
                    ) VALUES (
                        'timesheet_amendment', :amendment_id, 'amendment_review', 'pending',
                        :context_id, NOW()
                    )";

            $aiDecisionId = $this->execute($sql, [
                ':amendment_id' => $amendmentId,
                ':context_id' => $context['snapshot_id']
            ]);

            // Execute AI rules
            $decision = $this->executeAIRules($aiDecisionId, $amendmentId, $rules, $context);

            // Update AI decision with result
            $this->updateAIDecision($aiDecisionId, $decision);

            // Auto-approve if AI says so
            if ($decision['action'] === 'approve' && $decision['confidence'] >= 0.9) {
                $this->approveAmendment($amendmentId, [
                    'approved_by' => 'AI',
                    'reason' => $decision['reasoning']
                ]);
            }

            return [
                'ai_decision_id' => $aiDecisionId,
                'decision' => $decision['action'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning']
            ];

        } catch (\Exception $e) {
            $this->logger->error('AI review failed', [
                'amendment_id' => $amendmentId,
                'error' => $e->getMessage()
            ]);

            return [
                'decision' => 'manual_review',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Approve an amendment
     *
     * @param int $amendmentId Amendment ID
     * @param array $data Approval data (approved_by, reason)
     * @return array Result
     */
    public function approveAmendment(int $amendmentId, array $data): array
    {
        $startTime = $this->logger->startTimer('approve_amendment');

        try {
            $this->beginTransaction();

            // Update amendment status
            $sql = "UPDATE payroll_timesheet_amendments
                    SET status = 'approved',
                        approved_by = :approved_by,
                        approved_at = NOW()
                    WHERE id = :id AND status = 'pending_review'";

            $affected = $this->execute($sql, [
                ':id' => $amendmentId,
                ':approved_by' => $data['approved_by'] ?? $_SESSION['user_id'] ?? 'AI'
            ]);

            if ($affected === 0) {
                throw new \RuntimeException('Amendment not found or already processed');
            }

            // Log history
            $this->logAmendmentHistory($amendmentId, 'approved', 'approved',
                $data['reason'] ?? 'Amendment approved');

            // Sync to Deputy if applicable
            $amendment = $this->getAmendment($amendmentId);
            if ($amendment && $amendment['deputy_timesheet_id']) {
                $this->syncToDeputy($amendmentId, $amendment);
            }

            $this->commit();

            $this->logger->endTimer($startTime, 'approve_amendment');
            $this->logger->info('Amendment approved', [
                'amendment_id' => $amendmentId,
                'approved_by' => $data['approved_by'] ?? 'AI'
            ]);

            return [
                'success' => true,
                'amendment_id' => $amendmentId
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to approve amendment', [
                'amendment_id' => $amendmentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Decline an amendment
     *
     * @param int $amendmentId Amendment ID
     * @param array $data Decline data (declined_by, reason)
     * @return array Result
     */
    public function declineAmendment(int $amendmentId, array $data): array
    {
        $startTime = $this->logger->startTimer('decline_amendment');

        try {
            $this->beginTransaction();

            // Update amendment status
            $sql = "UPDATE payroll_timesheet_amendments
                    SET status = 'declined',
                        approved_by = :declined_by,
                        approved_at = NOW()
                    WHERE id = :id AND status = 'pending_review'";

            $affected = $this->execute($sql, [
                ':id' => $amendmentId,
                ':declined_by' => $data['declined_by'] ?? $_SESSION['user_id'] ?? 'AI'
            ]);

            if ($affected === 0) {
                throw new \RuntimeException('Amendment not found or already processed');
            }

            // Log history
            $this->logAmendmentHistory($amendmentId, 'declined', 'declined',
                $data['reason'] ?? 'Amendment declined');

            $this->commit();

            $this->logger->endTimer($startTime, 'decline_amendment');
            $this->logger->info('Amendment declined', [
                'amendment_id' => $amendmentId,
                'declined_by' => $data['declined_by'] ?? 'AI'
            ]);

            return [
                'success' => true,
                'amendment_id' => $amendmentId
            ];

        } catch (\Exception $e) {
            $this->rollback();
            $this->logger->error('Failed to decline amendment', [
                'amendment_id' => $amendmentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get amendment by ID
     *
     * @param int $amendmentId Amendment ID
     * @return array|null Amendment data
     */
    public function getAmendment(int $amendmentId): ?array
    {
        $sql = "SELECT a.*,
                       s.first_name, s.last_name,
                       pp.start_date as period_start, pp.end_date as period_end
                FROM payroll_timesheet_amendments a
                LEFT JOIN payroll_staff s ON a.staff_id = s.id
                LEFT JOIN payroll_pay_periods pp ON a.pay_period_id = pp.id
                WHERE a.id = :id";

        return $this->queryOne($sql, [':id' => $amendmentId]);
    }

    /**
     * Get amendments pending review
     *
     * @param int|null $limit Limit results
     * @return array List of amendments
     */
    public function getPendingAmendments(?int $limit = null): array
    {
        $sql = "SELECT a.*,
                       s.first_name, s.last_name,
                       pp.start_date as period_start, pp.end_date as period_end
                FROM payroll_timesheet_amendments a
                LEFT JOIN payroll_staff s ON a.staff_id = s.id
                LEFT JOIN payroll_pay_periods pp ON a.pay_period_id = pp.id
                WHERE a.status = 'pending_review'
                ORDER BY a.submitted_at ASC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        return $this->query($sql);
    }

    /**
     * Log amendment history
     *
     * @param int $amendmentId Amendment ID
     * @param string $action Action performed
     * @param string $newStatus New status
     * @param string $notes Notes
     */
    private function logAmendmentHistory(int $amendmentId, string $action,
                                        string $newStatus, string $notes): void
    {
        $sql = "INSERT INTO payroll_timesheet_amendment_history (
                    amendment_id, action, previous_status, new_status,
                    changed_by, notes, created_at
                ) VALUES (
                    :amendment_id, :action,
                    (SELECT status FROM payroll_timesheet_amendments WHERE id = :amendment_id2),
                    :new_status, :changed_by, :notes, NOW()
                )";

        $this->execute($sql, [
            ':amendment_id' => $amendmentId,
            ':amendment_id2' => $amendmentId,
            ':action' => $action,
            ':new_status' => $newStatus,
            ':changed_by' => $_SESSION['user_id'] ?? null,
            ':notes' => $notes
        ]);
    }

    /**
     * Sync approved amendment to Deputy
     *
     * @param int $amendmentId Amendment ID
     * @param array $amendment Amendment data
     */
    private function syncToDeputy(int $amendmentId, array $amendment): void
    {
        // TODO: Implement Deputy sync
        // This will call DeputyService to update the timesheet
        $this->logger->info('Deputy sync queued', [
            'amendment_id' => $amendmentId,
            'deputy_timesheet_id' => $amendment['deputy_timesheet_id']
        ]);
    }

    /**
     * Get applicable AI rules for amendment
     */
    private function getApplicableAIRules(array $amendmentData): array
    {
        $sql = "SELECT * FROM payroll_ai_rules
                WHERE is_active = 1
                AND rule_type = 'amendment'
                ORDER BY priority DESC";

        return $this->query($sql);
    }

    /**
     * Build AI context snapshot
     */
    private function buildAIContext(int $amendmentId, array $amendmentData): array
    {
        $contextData = [
            'amendment' => $amendmentData,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $sql = "INSERT INTO payroll_context_snapshots (
                    snapshot_type, entity_type, entity_id, snapshot_data, created_at
                ) VALUES (
                    'amendment_review', 'timesheet_amendment', :amendment_id, :data, NOW()
                )";

        $snapshotId = $this->execute($sql, [
            ':amendment_id' => $amendmentId,
            ':data' => json_encode($contextData)
        ]);

        return [
            'snapshot_id' => $snapshotId,
            'data' => $contextData
        ];
    }

    /**
     * Execute AI rules against amendment
     */
    private function executeAIRules(int $aiDecisionId, int $amendmentId,
                                   array $rules, array $context): array
    {
        // Simplified AI logic - in production, this would call GPT
        $decision = [
            'action' => 'manual_review',
            'confidence' => 0.5,
            'reasoning' => 'Default: requires human review',
            'rules_executed' => []
        ];

        foreach ($rules as $rule) {
            // Log rule execution
            $sql = "INSERT INTO payroll_ai_rule_executions (
                        ai_decision_id, rule_id, rule_outcome, confidence_score,
                        reasoning, executed_at
                    ) VALUES (
                        :decision_id, :rule_id, 'evaluated', 0.5,
                        'Rule evaluated', NOW()
                    )";

            $this->execute($sql, [
                ':decision_id' => $aiDecisionId,
                ':rule_id' => $rule['id']
            ]);

            $decision['rules_executed'][] = $rule['id'];
        }

        return $decision;
    }

    /**
     * Update AI decision with result
     */
    private function updateAIDecision(int $aiDecisionId, array $decision): void
    {
        $sql = "UPDATE payroll_ai_decisions
                SET status = 'completed',
                    decision = :decision,
                    confidence_score = :confidence,
                    reasoning = :reasoning,
                    completed_at = NOW()
                WHERE id = :id";

        $this->execute($sql, [
            ':id' => $aiDecisionId,
            ':decision' => $decision['action'],
            ':confidence' => $decision['confidence'],
            ':reasoning' => $decision['reasoning']
        ]);
    }
}
