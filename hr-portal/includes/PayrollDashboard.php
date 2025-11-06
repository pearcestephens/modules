<?php
/**
 * PAYROLL DASHBOARD
 *
 * Data aggregation and business logic for the hybrid payroll system
 * Provides stats, pending items, recent activity, and audit trail
 */

class PayrollDashboard
{
    private $pdo;
    private $aiEngine;

    public function __construct(PDO $pdo, AIPayrollEngine $aiEngine)
    {
        $this->pdo = $pdo;
        $this->aiEngine = $aiEngine;
    }

    /**
     * Get today's statistics for dashboard
     */
    public function getTodayStats(): array
    {
        $stats = [
            'auto_approved' => 0,
            'needs_review' => 0,
            'escalated' => 0,
            'ai_accuracy' => 0
        ];

        // Get auto-approved count (today)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count
            FROM payroll_ai_decisions
            WHERE decision = 'auto_approve'
              AND DATE(created_at) = CURDATE()
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['auto_approved'] = $result['count'] ?? 0;

        // Get needs review count (pending)
        $stats['needs_review'] = $this->getPendingCount('manual_review');

        // Get escalated count (pending)
        $stats['escalated'] = $this->getPendingCount('escalate');

        // Get AI accuracy (last 30 days)
        $accuracyStats = $this->aiEngine->getAccuracyStats();
        $stats['ai_accuracy'] = $accuracyStats['accuracy'];

        return $stats;
    }

    /**
     * Get count of pending items by decision type
     */
    private function getPendingCount(string $decision): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM payroll_ai_decisions
            WHERE decision = ?
              AND human_action IS NULL
        ");
        $stmt->execute([$decision]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Get pending items that need human review
     */
    public function getPendingItems(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.item_type,
                d.item_id,
                d.decision,
                d.confidence_score,
                d.reasoning,
                d.created_at,
                CASE
                    WHEN d.item_type = 'timesheet' THEN t.staff_id
                    WHEN d.item_type = 'payrun' THEN p.staff_id
                    WHEN d.item_type = 'vend' THEN v.staff_id
                END as staff_id,
                CASE
                    WHEN d.item_type = 'timesheet' THEN s.name
                    WHEN d.item_type = 'payrun' THEN s2.name
                    WHEN d.item_type = 'vend' THEN s3.name
                END as staff_name,
                CASE
                    WHEN d.item_type = 'timesheet' THEN t.original_start
                    WHEN d.item_type = 'payrun' THEN p.created_at
                    WHEN d.item_type = 'vend' THEN v.created_at
                END as item_date
            FROM payroll_ai_decisions d
            LEFT JOIN payroll_timesheet_amendments t ON d.item_type = 'timesheet' AND d.item_id = t.id
            LEFT JOIN payroll_payrun_amendments p ON d.item_type = 'payrun' AND d.item_id = p.id
            LEFT JOIN payroll_vend_account_payments v ON d.item_type = 'vend' AND d.item_id = v.id
            LEFT JOIN staff s ON t.staff_id = s.id
            LEFT JOIN staff s2 ON p.staff_id = s2.id
            LEFT JOIN staff s3 ON v.staff_id = s3.id
            WHERE d.human_action IS NULL
              AND d.decision IN ('manual_review', 'escalate')
            ORDER BY
                CASE d.decision
                    WHEN 'escalate' THEN 1
                    WHEN 'manual_review' THEN 2
                END,
                d.confidence_score DESC,
                d.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich items with details
        foreach ($items as &$item) {
            $item['details'] = $this->getItemDetails($item['item_type'], $item['item_id']);
            $item['icon'] = $this->getItemIcon($item['item_type']);
            $item['type_label'] = $this->getItemTypeLabel($item['item_type']);
        }

        return $items;
    }

    /**
     * Get detailed info for an item
     */
    private function getItemDetails(string $type, int $id): array
    {
        switch ($type) {
            case 'timesheet':
                return $this->getTimesheetDetails($id);
            case 'payrun':
                return $this->getPayrunDetails($id);
            case 'vend':
                return $this->getVendDetails($id);
            default:
                return [];
        }
    }

    /**
     * Get timesheet amendment details
     */
    private function getTimesheetDetails(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                staff_id,
                original_start,
                original_end,
                new_start,
                new_end,
                reason,
                evidence_url,
                evidence_text,
                created_at
            FROM payroll_timesheet_amendments
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return [];

        $originalMinutes = (strtotime($result['original_end']) - strtotime($result['original_start'])) / 60;
        $newMinutes = (strtotime($result['new_end']) - strtotime($result['new_start'])) / 60;
        $diffMinutes = abs($newMinutes - $originalMinutes);

        return [
            'original_hours' => round($originalMinutes / 60, 2),
            'new_hours' => round($newMinutes / 60, 2),
            'diff_minutes' => $diffMinutes,
            'diff_hours' => round($diffMinutes / 60, 2),
            'date' => date('d M Y', strtotime($result['original_start'])),
            'reason' => $result['reason'] ?? 'No reason provided',
            'has_evidence' => !empty($result['evidence_url']) || !empty($result['evidence_text'])
        ];
    }

    /**
     * Get payrun amendment details
     */
    private function getPayrunDetails(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                staff_id,
                original_amount,
                adjustment_amount,
                reason,
                evidence_url,
                evidence_text,
                created_at
            FROM payroll_payrun_amendments
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return [];

        return [
            'original_amount' => $result['original_amount'] ?? 0,
            'adjustment_amount' => $result['adjustment_amount'] ?? 0,
            'new_amount' => ($result['original_amount'] ?? 0) + ($result['adjustment_amount'] ?? 0),
            'reason' => $result['reason'] ?? 'No reason provided',
            'has_evidence' => !empty($result['evidence_url']) || !empty($result['evidence_text'])
        ];
    }

    /**
     * Get Vend payment details
     */
    private function getVendDetails(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                staff_id,
                amount,
                payment_type,
                reference,
                created_at
            FROM payroll_vend_account_payments
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return [];

        return [
            'amount' => $result['amount'] ?? 0,
            'payment_type' => $result['payment_type'] ?? 'Unknown',
            'reference' => $result['reference'] ?? 'No reference'
        ];
    }

    /**
     * Get icon for item type
     */
    private function getItemIcon(string $type): string
    {
        $icons = [
            'timesheet' => '⏰',
            'payrun' => '💵',
            'vend' => '🏪',
            'fraud' => '⚠️'
        ];
        return $icons[$type] ?? '📄';
    }

    /**
     * Get human-readable label for item type
     */
    private function getItemTypeLabel(string $type): string
    {
        $labels = [
            'timesheet' => 'Timesheet Amendment',
            'payrun' => 'Pay Adjustment',
            'vend' => 'Vend Payment',
            'fraud' => 'Fraud Alert'
        ];
        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Get recent AI activity
     */
    public function getRecentActivity(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.item_type,
                d.item_id,
                d.decision,
                d.confidence_score,
                d.reasoning,
                d.human_action,
                d.created_at,
                d.processed_at,
                CASE
                    WHEN d.item_type = 'timesheet' THEN s.name
                    WHEN d.item_type = 'payrun' THEN s2.name
                    WHEN d.item_type = 'vend' THEN s3.name
                END as staff_name
            FROM payroll_ai_decisions d
            LEFT JOIN payroll_timesheet_amendments t ON d.item_type = 'timesheet' AND d.item_id = t.id
            LEFT JOIN payroll_payrun_amendments p ON d.item_type = 'payrun' AND d.item_id = p.id
            LEFT JOIN payroll_vend_account_payments v ON d.item_type = 'vend' AND d.item_id = v.id
            LEFT JOIN staff s ON t.staff_id = s.id
            LEFT JOIN staff s2 ON p.staff_id = s2.id
            LEFT JOIN staff s3 ON v.staff_id = s3.id
            WHERE d.decision = 'auto_approve'
              OR d.human_action IS NOT NULL
            ORDER BY COALESCE(d.processed_at, d.created_at) DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get audit trail with search/filter
     */
    public function getAuditTrail(array $filters = [], int $limit = 100): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['staff_id'])) {
            $where[] = "(t.staff_id = ? OR p.staff_id = ? OR v.staff_id = ?)";
            $params[] = $filters['staff_id'];
            $params[] = $filters['staff_id'];
            $params[] = $filters['staff_id'];
        }

        if (!empty($filters['item_type'])) {
            $where[] = "d.item_type = ?";
            $params[] = $filters['item_type'];
        }

        if (!empty($filters['decision'])) {
            $where[] = "d.decision = ?";
            $params[] = $filters['decision'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(d.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(d.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $params[] = $limit;

        $sql = "
            SELECT
                d.*,
                CASE
                    WHEN d.item_type = 'timesheet' THEN s.name
                    WHEN d.item_type = 'payrun' THEN s2.name
                    WHEN d.item_type = 'vend' THEN s3.name
                END as staff_name,
                u.username as processed_by_username
            FROM payroll_ai_decisions d
            LEFT JOIN payroll_timesheet_amendments t ON d.item_type = 'timesheet' AND d.item_id = t.id
            LEFT JOIN payroll_payrun_amendments p ON d.item_type = 'payrun' AND d.item_id = p.id
            LEFT JOIN payroll_vend_account_payments v ON d.item_type = 'vend' AND d.item_id = v.id
            LEFT JOIN staff s ON t.staff_id = s.id
            LEFT JOIN staff s2 ON p.staff_id = s2.id
            LEFT JOIN staff s3 ON v.staff_id = s3.id
            LEFT JOIN users u ON d.processed_by = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve an item
     */
    public function approveItem(int $decisionId, int $userId, string $notes = ''): array
    {
        try {
            // Get the decision
            $stmt = $this->pdo->prepare("SELECT * FROM payroll_ai_decisions WHERE id = ?");
            $stmt->execute([$decisionId]);
            $decision = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$decision) {
                return ['success' => false, 'error' => 'Decision not found'];
            }

            // Update decision with human action
            $stmt = $this->pdo->prepare("
                UPDATE payroll_ai_decisions
                SET human_action = 'approved',
                    processed_by = ?,
                    processed_at = NOW(),
                    processing_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $notes, $decisionId]);

            // Process the actual item (timesheet, payrun, vend)
            $this->processItem($decision['item_type'], $decision['item_id'], 'approved');

            // Log to audit trail
            $this->logAuditAction('approve', $decisionId, $userId, $notes);

            // If AI was wrong, learn from it
            if ($decision['decision'] !== 'auto_approve') {
                $this->aiEngine->learnFromOverride($decisionId, 'approved', 'Human approved after AI flagged');
            }

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Failed to approve item: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deny an item
     */
    public function denyItem(int $decisionId, int $userId, string $reason): array
    {
        try {
            // Get the decision
            $stmt = $this->pdo->prepare("SELECT * FROM payroll_ai_decisions WHERE id = ?");
            $stmt->execute([$decisionId]);
            $decision = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$decision) {
                return ['success' => false, 'error' => 'Decision not found'];
            }

            // Update decision with human action
            $stmt = $this->pdo->prepare("
                UPDATE payroll_ai_decisions
                SET human_action = 'denied',
                    processed_by = ?,
                    processed_at = NOW(),
                    processing_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $reason, $decisionId]);

            // Process the actual item (reject it)
            $this->processItem($decision['item_type'], $decision['item_id'], 'denied');

            // Log to audit trail
            $this->logAuditAction('deny', $decisionId, $userId, $reason);

            // If AI was wrong, learn from it
            if ($decision['decision'] === 'auto_approve') {
                $this->aiEngine->learnFromOverride($decisionId, 'denied', $reason);
            }

            return ['success' => true];

        } catch (Exception $e) {
            error_log("Failed to deny item: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process the actual item based on type
     */
    private function processItem(string $type, int $id, string $action): void
    {
        switch ($type) {
            case 'timesheet':
                $this->processTimesheetAmendment($id, $action);
                break;
            case 'payrun':
                $this->processPayrunAmendment($id, $action);
                break;
            case 'vend':
                $this->processVendPayment($id, $action);
                break;
        }
    }

    /**
     * Process timesheet amendment (send to Deputy/Xero)
     */
    private function processTimesheetAmendment(int $id, string $action): void
    {
        if ($action === 'denied') {
            // Mark as rejected
            $this->pdo->prepare("
                UPDATE payroll_timesheet_amendments
                SET status = 'rejected', processed_at = NOW()
                WHERE id = ?
            ")->execute([$id]);
            return;
        }

        // Mark as approved and queue for Deputy sync
        $this->pdo->prepare("
            UPDATE payroll_timesheet_amendments
            SET status = 'approved', processed_at = NOW()
            WHERE id = ?
        ")->execute([$id]);

        // Queue Deputy sync job
        // This would call your existing Deputy API integration
    }

    /**
     * Process payrun amendment (send to Xero)
     */
    private function processPayrunAmendment(int $id, string $action): void
    {
        if ($action === 'denied') {
            $this->pdo->prepare("
                UPDATE payroll_payrun_amendments
                SET status = 'rejected', processed_at = NOW()
                WHERE id = ?
            ")->execute([$id]);
            return;
        }

        // Mark as approved and queue for Xero sync
        $this->pdo->prepare("
            UPDATE payroll_payrun_amendments
            SET status = 'approved', processed_at = NOW()
            WHERE id = ?
        ")->execute([$id]);

        // Queue Xero payroll update
    }

    /**
     * Process Vend payment
     */
    private function processVendPayment(int $id, string $action): void
    {
        if ($action === 'denied') {
            $this->pdo->prepare("
                UPDATE payroll_vend_account_payments
                SET status = 'rejected', processed_at = NOW()
                WHERE id = ?
            ")->execute([$id]);
            return;
        }

        // Mark as approved
        $this->pdo->prepare("
            UPDATE payroll_vend_account_payments
            SET status = 'approved', processed_at = NOW()
            WHERE id = ?
        ")->execute([$id]);
    }

    /**
     * Log action to audit trail
     */
    private function logAuditAction(string $action, int $decisionId, int $userId, string $notes): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_audit_log
            (action_type, decision_id, user_id, notes, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $action,
            $decisionId,
            $userId,
            $notes,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Batch approve items with high confidence
     */
    public function batchApproveHighConfidence(float $minConfidence, int $userId): array
    {
        try {
            // Get high-confidence items
            $stmt = $this->pdo->prepare("
                SELECT id FROM payroll_ai_decisions
                WHERE decision = 'manual_review'
                  AND confidence_score >= ?
                  AND human_action IS NULL
            ");
            $stmt->execute([$minConfidence]);
            $items = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $approved = 0;
            foreach ($items as $decisionId) {
                $result = $this->approveItem($decisionId, $userId, "Batch approved (confidence >= {$minConfidence})");
                if ($result['success']) {
                    $approved++;
                }
            }

            return [
                'success' => true,
                'approved' => $approved,
                'total' => count($items)
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
