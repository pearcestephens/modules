<?php
/**
 * AI PAYROLL ENGINE
 *
 * The intelligent decision-making core that:
 * - Evaluates items against configured rules
 * - Calculates confidence scores
 * - Makes auto-approve/deny decisions
 * - Learns from human overrides
 * - Provides actionable insights
 */

class AIPayrollEngine
{
    private $pdo;
    private $autoPilotEnabled = true;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadAutoPilotStatus();
    }

    /**
     * Load auto-pilot status from config
     */
    private function loadAutoPilotStatus(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT config_value FROM payroll_bot_config
            WHERE config_key = 'auto_pilot_enabled'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $this->autoPilotEnabled = (bool)$result['config_value'];
        }
    }

    /**
     * Evaluate an amendment/change and make AI decision
     *
     * @param string $type Type of item (timesheet, payrun, vend, etc)
     * @param array $data Item data
     * @return array Decision with confidence, action, reasoning
     */
    public function evaluate(string $type, array $data): array
    {
        // Get applicable rules for this type
        $rules = $this->getActiveRules($type);

        if (empty($rules)) {
            return [
                'decision' => 'manual_review',
                'confidence' => 0.5,
                'reasoning' => 'No AI rules configured for this type',
                'matched_rules' => []
            ];
        }

        $matchedRules = [];
        $highestConfidence = 0;
        $decision = 'manual_review';
        $reasoning = '';

        foreach ($rules as $rule) {
            $match = $this->evaluateRule($rule, $data);

            if ($match['matches']) {
                $matchedRules[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['rule_name'],
                    'confidence' => $match['confidence']
                ];

                // Check if this rule should trigger an action
                if ($match['confidence'] > $highestConfidence) {
                    $highestConfidence = $match['confidence'];

                    // Determine action based on rule configuration
                    if ($rule['auto_approve'] && $match['confidence'] >= $rule['confidence_required']) {
                        $decision = 'auto_approve';
                        $reasoning = "Auto-approved: {$rule['description']} (Confidence: " . round($match['confidence'] * 100) . "%)";
                    } elseif ($rule['auto_decline']) {
                        $decision = 'auto_deny';
                        $reasoning = "Auto-denied: {$rule['description']}";
                    } elseif ($rule['require_escalation']) {
                        $decision = 'escalate';
                        $reasoning = "Escalated: {$rule['description']}";
                    } elseif ($rule['require_human_review']) {
                        $decision = 'manual_review';
                        $reasoning = "Requires review: {$rule['description']}";
                    }
                }
            }
        }

        // If no rules matched with high confidence, default to manual review
        if ($highestConfidence < 0.5) {
            $decision = 'manual_review';
            $reasoning = 'No high-confidence rule match found';
        }

        $result = [
            'decision' => $decision,
            'confidence' => $highestConfidence,
            'reasoning' => $reasoning,
            'matched_rules' => $matchedRules,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log the decision
        $this->logDecision($type, $data['id'] ?? null, $result);

        return $result;
    }

    /**
     * Get active rules for a specific type
     */
    private function getActiveRules(string $type): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payroll_ai_rules
            WHERE rule_type = ?
              AND is_active = 1
            ORDER BY priority DESC, confidence_required DESC
        ");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Evaluate a single rule against data
     */
    private function evaluateRule(array $rule, array $data): array
    {
        $conditions = json_decode($rule['conditions_json'], true) ?? [];
        $matches = true;
        $confidence = 1.0;

        // Evaluate based on rule type
        switch ($rule['rule_type']) {
            case 'timesheet':
                $result = $this->evaluateTimesheetRule($conditions, $data, $rule);
                break;

            case 'payrun':
                $result = $this->evaluatePayrunRule($conditions, $data, $rule);
                break;

            case 'vend':
                $result = $this->evaluateVendRule($conditions, $data, $rule);
                break;

            case 'fraud':
            case 'anomaly':
                $result = $this->evaluateFraudRule($conditions, $data, $rule);
                break;

            default:
                $result = ['matches' => false, 'confidence' => 0.5];
        }

        return $result;
    }

    /**
     * Evaluate timesheet-specific rules
     */
    private function evaluateTimesheetRule(array $conditions, array $data, array $rule): array
    {
        $matches = true;
        $confidence = 0.9; // Base confidence

        // Check max time difference (minutes)
        if (isset($conditions['max_time_difference_minutes'])) {
            $diffMinutes = abs($this->calculateTimeDifference($data));
            if ($diffMinutes > $conditions['max_time_difference_minutes']) {
                $matches = false;
            } else {
                // Lower confidence for larger changes
                $confidence -= ($diffMinutes / $conditions['max_time_difference_minutes']) * 0.2;
            }
        }

        // Check if evidence is required
        if (isset($conditions['requires_evidence']) && $conditions['requires_evidence']) {
            if (empty($data['evidence_url']) && empty($data['evidence_text'])) {
                $matches = false;
                $confidence = 0.3;
            }
        }

        // Check if it's break time only
        if (isset($conditions['break_time_only']) && $conditions['break_time_only']) {
            if (!$this->isBreakTimeAmendment($data)) {
                $matches = false;
            }
        }

        // Check minimum time difference (hours) for escalation
        if (isset($conditions['min_time_difference_hours'])) {
            $diffHours = abs($this->calculateTimeDifference($data)) / 60;
            if ($diffHours < $conditions['min_time_difference_hours']) {
                $matches = false;
            }
        }

        return [
            'matches' => $matches,
            'confidence' => max(0, min(1, $confidence))
        ];
    }

    /**
     * Evaluate payrun-specific rules
     */
    private function evaluatePayrunRule(array $conditions, array $data, array $rule): array
    {
        $matches = true;
        $confidence = 0.85;

        // Check max amount for auto-approval
        if (isset($conditions['max_amount'])) {
            $amount = abs($data['amount'] ?? $data['adjustment_amount'] ?? 0);
            if ($amount > $conditions['max_amount']) {
                $matches = false;
            } else {
                // Lower confidence for larger amounts
                $confidence -= ($amount / $conditions['max_amount']) * 0.15;
            }
        }

        // Check min amount for escalation
        if (isset($conditions['min_amount'])) {
            $amount = abs($data['amount'] ?? $data['adjustment_amount'] ?? 0);
            if ($amount < $conditions['min_amount']) {
                $matches = false;
            }
        }

        // Check for evidence
        if (isset($conditions['has_evidence']) && $conditions['has_evidence']) {
            if (empty($data['evidence_url']) && empty($data['evidence_text'])) {
                $matches = false;
                $confidence = 0.4;
            }
        }

        return [
            'matches' => $matches,
            'confidence' => max(0, min(1, $confidence))
        ];
    }

    /**
     * Evaluate Vend payment rules
     */
    private function evaluateVendRule(array $conditions, array $data, array $rule): array
    {
        $matches = true;
        $confidence = 0.9;

        // Check valid account balance
        if (isset($conditions['valid_account_balance']) && $conditions['valid_account_balance']) {
            if (!$this->isValidVendBalance($data)) {
                $matches = false;
                $confidence = 0.3;
            }
        }

        // Check for anomalies
        if (isset($conditions['no_anomalies']) && $conditions['no_anomalies']) {
            if ($this->detectVendAnomalies($data)) {
                $matches = false;
                $confidence = 0.2;
            }
        }

        return [
            'matches' => $matches,
            'confidence' => max(0, min(1, $confidence))
        ];
    }

    /**
     * Evaluate fraud detection rules
     */
    private function evaluateFraudRule(array $conditions, array $data, array $rule): array
    {
        $matches = false;
        $confidence = 0.5;

        // Check for duplicates within window
        if (isset($conditions['duplicate_window_hours'])) {
            if ($this->detectDuplicateSubmission($data, $conditions['duplicate_window_hours'])) {
                $matches = true;
                $confidence = 0.95;
            }
        }

        // Check for unusual patterns (statistical deviation)
        if (isset($conditions['deviation_threshold'])) {
            $deviation = $this->calculateStatisticalDeviation($data);
            if ($deviation > $conditions['deviation_threshold']) {
                $matches = true;
                $confidence = min(0.99, 0.5 + ($deviation / 10));
            }
        }

        return [
            'matches' => $matches,
            'confidence' => max(0, min(1, $confidence))
        ];
    }

    /**
     * Helper: Calculate time difference in minutes
     */
    private function calculateTimeDifference(array $data): float
    {
        $original = strtotime($data['original_start'] ?? $data['original_time'] ?? 'now');
        $new = strtotime($data['new_start'] ?? $data['new_time'] ?? 'now');
        return abs($new - $original) / 60;
    }

    /**
     * Helper: Check if amendment is break time only
     */
    private function isBreakTimeAmendment(array $data): bool
    {
        return !empty($data['is_break_amendment']) ||
               (isset($data['amendment_type']) && $data['amendment_type'] === 'break');
    }

    /**
     * Helper: Validate Vend account balance
     */
    private function isValidVendBalance(array $data): bool
    {
        // Check if staff has valid Vend account with sufficient balance
        // This would connect to your vend_customer_accounts table
        return true; // Placeholder
    }

    /**
     * Helper: Detect Vend payment anomalies
     */
    private function detectVendAnomalies(array $data): bool
    {
        // Check for unusual payment patterns
        // - Unusually large payments
        // - Payments at odd times
        // - Payments to wrong accounts
        return false; // Placeholder
    }

    /**
     * Helper: Detect duplicate submissions
     */
    private function detectDuplicateSubmission(array $data, int $windowHours): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM payroll_timesheet_amendments
            WHERE staff_id = ?
              AND DATE(original_start) = DATE(?)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
              AND id != ?
        ");
        $stmt->execute([
            $data['staff_id'] ?? 0,
            $data['original_start'] ?? date('Y-m-d'),
            $windowHours,
            $data['id'] ?? 0
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Helper: Calculate statistical deviation
     */
    private function calculateStatisticalDeviation(array $data): float
    {
        // Calculate how much this deviates from staff's normal patterns
        // This would analyze historical data
        return 1.0; // Placeholder
    }

    /**
     * Log AI decision to database
     */
    private function logDecision(string $type, ?int $itemId, array $decision): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payroll_ai_decisions
                (item_type, item_id, decision, confidence_score, reasoning, matched_rules_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $type,
                $itemId,
                $decision['decision'],
                $decision['confidence'],
                $decision['reasoning'],
                json_encode($decision['matched_rules'])
            ]);
        } catch (Exception $e) {
            error_log("Failed to log AI decision: " . $e->getMessage());
        }
    }

    /**
     * Get AI insights for dashboard
     */
    public function getInsights(): array
    {
        $insights = [];

        // Insight 1: Staff working excessive hours
        $excessiveHours = $this->detectExcessiveHours();
        if (!empty($excessiveHours)) {
            $insights[] = [
                'title' => 'Excessive Hours Detected',
                'message' => count($excessiveHours) . ' staff member(s) worked over 48 hours last week',
                'action' => 'Review',
                'action_url' => '#'
            ];
        }

        // Insight 2: Upcoming reviews
        $upcomingReviews = $this->getUpcomingReviews();
        if (!empty($upcomingReviews)) {
            $insights[] = [
                'title' => 'Performance Reviews Due',
                'message' => count($upcomingReviews) . ' staff members due for review this month',
                'action' => 'Schedule',
                'action_url' => '#'
            ];
        }

        // Insight 3: Turnover risk
        $turnoverRisk = $this->detectTurnoverRisk();
        if (!empty($turnoverRisk)) {
            $insights[] = [
                'title' => 'Turnover Risk Alert',
                'message' => 'AI detected potential turnover risk for ' . count($turnoverRisk) . ' staff member(s)',
                'action' => 'Review',
                'action_url' => '#'
            ];
        }

        return $insights;
    }

    /**
     * Detect staff working excessive hours
     */
    private function detectExcessiveHours(): array
    {
        // Query Deputy/timesheet data for excessive hours
        return []; // Placeholder
    }

    /**
     * Get upcoming performance reviews
     */
    private function getUpcomingReviews(): array
    {
        // Check employee_reviews table for upcoming reviews
        return []; // Placeholder
    }

    /**
     * Detect potential turnover risk
     */
    private function detectTurnoverRisk(): array
    {
        // Analyze patterns: increased sick days, decreased performance, etc.
        return []; // Placeholder
    }

    /**
     * Learn from human override
     */
    public function learnFromOverride(int $decisionId, string $humanAction, string $reason): void
    {
        // Log the override
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_ai_feedback
            (decision_id, human_action, feedback_reason, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$decisionId, $humanAction, $reason]);

        // Adjust confidence thresholds based on feedback
        // This would implement actual learning logic
    }

    /**
     * Get AI accuracy statistics
     */
    public function getAccuracyStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_decisions,
                SUM(CASE WHEN human_action = decision THEN 1 ELSE 0 END) as correct_decisions,
                AVG(confidence_score) as avg_confidence
            FROM payroll_ai_decisions d
            LEFT JOIN payroll_ai_feedback f ON d.id = f.decision_id
            WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $accuracy = 0;
        if ($result && $result['total_decisions'] > 0) {
            $accuracy = ($result['correct_decisions'] / $result['total_decisions']) * 100;
        }

        return [
            'accuracy' => round($accuracy, 1),
            'total_decisions' => $result['total_decisions'] ?? 0,
            'avg_confidence' => round(($result['avg_confidence'] ?? 0) * 100, 1)
        ];
    }
}
