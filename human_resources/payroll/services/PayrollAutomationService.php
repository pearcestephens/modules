<?php
declare(strict_types=1);

namespace PayrollModule\Services;

/**
 * Payroll Automation Service
 *
 * Orchestrates the AI-powered payroll automation workflow:
 * - Automated amendment processing
 * - AI decision-making pipeline
 * - Integration with Deputy, Xero, and Vend
 * - Notification management
 *
 * This is the main service that coordinates the entire automation flow
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;

class PayrollAutomationService extends BaseService
{
    private AmendmentService $amendmentService;
    private XeroService $xeroService;
    private DeputyService $deputyService;
    private VendService $vendService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize child services
        $this->amendmentService = new AmendmentService();
        $this->xeroService = new XeroService();
        $this->deputyService = new DeputyService($this->db);
        $this->vendService = new VendService();
    }

    /**
     * Process all pending AI reviews
     *
     * Main automation loop - runs via cron every 5 minutes
     * Reviews pending amendments and makes AI decisions
     *
     * @return array Results with counts and errors
     */
    public function processAutomatedReviews(): array
    {
        $startTime = $this->logger->startTimer('automated_review_cycle');

        $this->logger->info('Starting automated review cycle');

        $stats = [
            'total_reviewed' => 0,
            'auto_approved' => 0,
            'manual_review' => 0,
            'declined' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            // Get all pending AI reviews
            $pendingReviews = $this->getPendingAIReviews();

            foreach ($pendingReviews as $review) {
                try {
                    $result = $this->processAIReview($review);

                    $stats['total_reviewed']++;

                    switch ($result['decision']) {
                        case 'approve':
                            $stats['auto_approved']++;
                            break;
                        case 'decline':
                            $stats['declined']++;
                            break;
                        case 'manual_review':
                        case 'escalate':
                            $stats['manual_review']++;
                            break;
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    $stats['error_details'][] = [
                        'review_id' => $review['id'],
                        'error' => $e->getMessage()
                    ];

                    $this->logger->error('Failed to process AI review', [
                        'review_id' => $review['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->endTimer($startTime, 'automated_review_cycle');
            $this->logger->info('Automated review cycle completed', $stats);

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Automated review cycle failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $stats
            ];
        }
    }

    /**
     * Process a single AI review
     *
     * @param array $review AI review record
     * @return array Decision result
     */
    private function processAIReview(array $review): array
    {
        $entityType = $review['entity_type'];
        $entityId = $review['entity_id'];

        $this->logger->info('Processing AI review', [
            'ai_decision_id' => $review['id'],
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);

        // Get entity data
        $entityData = $this->getEntityData($entityType, $entityId);

        if (!$entityData) {
            throw new \RuntimeException("Entity not found: {$entityType} {$entityId}");
        }

        // Get applicable AI rules
        $rules = $this->getApplicableRules($entityType, $entityData);

        // Execute AI decision pipeline
        $decision = $this->executeAIDecisionPipeline($review['id'], $entityData, $rules);

        // Update AI decision record
        $this->updateAIDecision($review['id'], $decision);

        // Act on decision
        $this->actOnDecision($entityType, $entityId, $decision);

        // Send notifications
        $this->sendNotifications($entityType, $entityId, $decision);

        return $decision;
    }

    /**
     * Execute AI decision pipeline
     *
     * @param int $aiDecisionId AI decision ID
     * @param array $entityData Entity data to review
     * @param array $rules Applicable AI rules
     * @return array Decision with action, confidence, reasoning
     */
    private function executeAIDecisionPipeline(int $aiDecisionId, array $entityData, array $rules): array
    {
        $this->logger->info('Executing AI decision pipeline', [
            'ai_decision_id' => $aiDecisionId,
            'rules_count' => count($rules)
        ]);

        // Initialize decision
        $decision = [
            'action' => 'manual_review',
            'confidence' => 0.0,
            'reasoning' => '',
            'rules_executed' => [],
            'flags' => []
        ];

        // Execute each rule
        foreach ($rules as $rule) {
            $ruleResult = $this->executeRule($rule, $entityData);

            // Log rule execution
            $this->logRuleExecution($aiDecisionId, $rule['id'], $ruleResult);

            $decision['rules_executed'][] = [
                'rule_id' => $rule['id'],
                'rule_name' => $rule['name'],
                'outcome' => $ruleResult['outcome'],
                'confidence' => $ruleResult['confidence']
            ];

            // Aggregate results
            if ($ruleResult['outcome'] === 'approve') {
                $decision['action'] = 'approve';
                $decision['confidence'] = max($decision['confidence'], $ruleResult['confidence']);
            } elseif ($ruleResult['outcome'] === 'decline') {
                $decision['action'] = 'decline';
                $decision['confidence'] = max($decision['confidence'], $ruleResult['confidence']);
                $decision['reasoning'] = $ruleResult['reasoning'];
                break; // Decline takes precedence
            }

            // Collect flags
            if (!empty($ruleResult['flags'])) {
                $decision['flags'] = array_merge($decision['flags'], $ruleResult['flags']);
            }
        }

        // Final decision logic
        if ($decision['confidence'] < 0.8) {
            $decision['action'] = 'manual_review';
            $decision['reasoning'] = 'Confidence below threshold for auto-approval';
        }

        if (count($decision['flags']) > 0) {
            $decision['action'] = 'escalate';
            $decision['reasoning'] = 'Flagged for human review: ' . implode(', ', $decision['flags']);
        }

        $this->logger->info('AI decision completed', [
            'ai_decision_id' => $aiDecisionId,
            'action' => $decision['action'],
            'confidence' => $decision['confidence']
        ]);

        return $decision;
    }

    /**
     * Execute a single AI rule
     *
     * @param array $rule Rule definition
     * @param array $entityData Entity data
     * @return array Rule result
     */
    private function executeRule(array $rule, array $entityData): array
    {
        // Parse rule conditions
        $conditions = json_decode($rule['conditions'], true) ?? [];

        // Simple rule engine (in production, this would be more sophisticated)
        $result = [
            'outcome' => 'neutral',
            'confidence' => 0.5,
            'reasoning' => '',
            'flags' => []
        ];

        // Example rule: Auto-approve if hours change < 2 hours
        if (isset($entityData['original_hours'], $entityData['new_hours'])) {
            $hoursDiff = abs($entityData['new_hours'] - $entityData['original_hours']);

            if ($hoursDiff < 2) {
                $result['outcome'] = 'approve';
                $result['confidence'] = 0.9;
                $result['reasoning'] = "Small change ({$hoursDiff} hours) within auto-approval threshold";
            } elseif ($hoursDiff > 4) {
                $result['outcome'] = 'decline';
                $result['confidence'] = 0.8;
                $result['reasoning'] = "Large change ({$hoursDiff} hours) requires review";
                $result['flags'][] = 'large_hours_change';
            }
        }

        // Example rule: Flag late night hours
        if (isset($entityData['new_start_time'])) {
            $hour = (int)date('H', strtotime($entityData['new_start_time']));
            if ($hour >= 22 || $hour <= 4) {
                $result['flags'][] = 'late_night_hours';
            }
        }

        return $result;
    }

    /**
     * Act on AI decision
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param array $decision Decision result
     */
    private function actOnDecision(string $entityType, int $entityId, array $decision): void
    {
        if ($entityType === 'timesheet_amendment') {
            if ($decision['action'] === 'approve' && $decision['confidence'] >= 0.9) {
                // Auto-approve
                $this->amendmentService->approveAmendment($entityId, [
                    'approved_by' => 'AI',
                    'reason' => $decision['reasoning']
                ]);

                $this->logger->info('Amendment auto-approved', [
                    'amendment_id' => $entityId,
                    'confidence' => $decision['confidence']
                ]);
            } elseif ($decision['action'] === 'decline') {
                // Auto-decline
                $this->amendmentService->declineAmendment($entityId, [
                    'declined_by' => 'AI',
                    'reason' => $decision['reasoning']
                ]);

                $this->logger->info('Amendment auto-declined', [
                    'amendment_id' => $entityId,
                    'reasoning' => $decision['reasoning']
                ]);
            }
        }

        // For manual_review or escalate, no action - just log
        if ($decision['action'] === 'manual_review' || $decision['action'] === 'escalate') {
            $this->logger->info('Amendment requires manual review', [
                'amendment_id' => $entityId,
                'action' => $decision['action'],
                'reasoning' => $decision['reasoning']
            ]);
        }
    }

    /**
     * Send notifications based on decision
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param array $decision Decision result
     */
    private function sendNotifications(string $entityType, int $entityId, array $decision): void
    {
        $notification = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'notification_type' => $decision['action'],
            'message' => $this->buildNotificationMessage($decision),
            'priority' => $this->getNotificationPriority($decision),
            'status' => 'pending'
        ];

        // Determine recipient based on decision
        if ($decision['action'] === 'manual_review' || $decision['action'] === 'escalate') {
            $notification['recipient_type'] = 'payroll_manager';
        } else {
            $notification['recipient_type'] = 'staff_member';
        }

        // Insert notification
        $sql = "INSERT INTO payroll_notifications (
                    recipient_type, recipient_id, notification_type,
                    entity_type, entity_id, message, priority,
                    status, created_at
                ) VALUES (
                    :recipient_type, NULL, :notification_type,
                    :entity_type, :entity_id, :message, :priority,
                    :status, NOW()
                )";

        $this->execute($sql, [
            ':recipient_type' => $notification['recipient_type'],
            ':notification_type' => $notification['notification_type'],
            ':entity_type' => $notification['entity_type'],
            ':entity_id' => $notification['entity_id'],
            ':message' => $notification['message'],
            ':priority' => $notification['priority'],
            ':status' => $notification['status']
        ]);

        $this->logger->info('Notification created', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'notification_type' => $notification['notification_type']
        ]);
    }

    /**
     * Get pending AI reviews
     */
    private function getPendingAIReviews(): array
    {
        $sql = "SELECT * FROM payroll_ai_decisions
                WHERE status = 'pending'
                ORDER BY created_at ASC
                LIMIT 100";

        return $this->query($sql);
    }

    /**
     * Get entity data
     */
    private function getEntityData(string $entityType, int $entityId): ?array
    {
        if ($entityType === 'timesheet_amendment') {
            return $this->amendmentService->getAmendment($entityId);
        }

        return null;
    }

    /**
     * Get applicable AI rules
     */
    private function getApplicableRules(string $entityType, array $entityData): array
    {
        $sql = "SELECT * FROM payroll_ai_rules
                WHERE is_active = 1
                AND rule_type = :rule_type
                ORDER BY priority DESC";

        return $this->query($sql, [':rule_type' => $entityType]);
    }

    /**
     * Update AI decision record
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

    /**
     * Log rule execution
     */
    private function logRuleExecution(int $aiDecisionId, int $ruleId, array $result): void
    {
        $sql = "INSERT INTO payroll_ai_rule_executions (
                    ai_decision_id, rule_id, rule_outcome,
                    confidence_score, reasoning, executed_at
                ) VALUES (
                    :decision_id, :rule_id, :outcome,
                    :confidence, :reasoning, NOW()
                )";

        $this->execute($sql, [
            ':decision_id' => $aiDecisionId,
            ':rule_id' => $ruleId,
            ':outcome' => $result['outcome'],
            ':confidence' => $result['confidence'],
            ':reasoning' => $result['reasoning']
        ]);
    }

    /**
     * Build notification message
     */
    private function buildNotificationMessage(array $decision): string
    {
        switch ($decision['action']) {
            case 'approve':
                return "Your timesheet amendment has been automatically approved. {$decision['reasoning']}";
            case 'decline':
                return "Your timesheet amendment was declined. Reason: {$decision['reasoning']}";
            case 'manual_review':
                return "Your timesheet amendment is under review by payroll management.";
            case 'escalate':
                return "Your timesheet amendment requires additional review. {$decision['reasoning']}";
            default:
                return "Your timesheet amendment status has been updated.";
        }
    }

    /**
     * Get notification priority
     */
    private function getNotificationPriority(array $decision): string
    {
        if ($decision['action'] === 'escalate') {
            return 'high';
        } elseif ($decision['action'] === 'decline') {
            return 'medium';
        }
        return 'normal';
    }

    /**
     * Get automation dashboard stats
     *
     * @return array Dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $stats = [];

        // Pending reviews
        $stats['pending_reviews'] = $this->queryOne(
            "SELECT COUNT(*) as count FROM payroll_ai_decisions WHERE status = 'pending'"
        )['count'] ?? 0;

        // Auto-approval rate (last 30 days)
        $stats['auto_approval_rate'] = $this->queryOne(
            "SELECT
                ROUND(SUM(CASE WHEN decision = 'approve' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as rate
             FROM payroll_ai_decisions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND status = 'completed'"
        )['rate'] ?? 0;

        // Avg processing time
        $stats['avg_processing_time_seconds'] = $this->queryOne(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_time
             FROM payroll_ai_decisions
             WHERE completed_at IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['avg_time'] ?? 0;

        // Recent decisions
        $stats['recent_decisions'] = $this->query(
            "SELECT decision, COUNT(*) as count
             FROM payroll_ai_decisions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND status = 'completed'
             GROUP BY decision"
        );

        return $stats;
    }
}
