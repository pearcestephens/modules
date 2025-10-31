<?php
declare(strict_types=1);

/**
 * Approval Service
 *
 * Handles multi-tier approval workflow for purchase orders.
 * Implements Q21-Q26 business requirements:
 * - Tiered approval thresholds by outlet and amount
 * - Dynamic approval matrix based on rules
 * - Delegation support
 * - Escalation logic
 * - Approval history tracking
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

namespace CIS\Consignments\Services;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

class ApprovalService
{
    private PDO $pdo;

    /**
     * Default approval thresholds (can be overridden by outlet-specific config)
     */
    private const DEFAULT_THRESHOLDS = [
        [
            'min_amount' => 0,
            'max_amount' => 500,
            'roles' => ['store_manager'],
            'required_count' => 1,
            'tier' => 1
        ],
        [
            'min_amount' => 500,
            'max_amount' => 2000,
            'roles' => ['store_manager', 'area_manager'],
            'required_count' => 1, // Either store manager OR area manager
            'tier' => 2
        ],
        [
            'min_amount' => 2000,
            'max_amount' => 5000,
            'roles' => ['area_manager'],
            'required_count' => 1,
            'tier' => 3
        ],
        [
            'min_amount' => 5000,
            'max_amount' => 10000,
            'roles' => ['operations_manager', 'financial_controller'],
            'required_count' => 2, // Both required
            'tier' => 4
        ],
        [
            'min_amount' => 10000,
            'max_amount' => null, // No upper limit
            'roles' => ['director'],
            'required_count' => 1,
            'tier' => 5
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if approval is required for a purchase order
     *
     * @param int $poId Purchase order ID
     * @return bool True if approval required
     */
    public function isApprovalRequired(int $poId): bool
    {
        // Get PO details
        $stmt = $this->pdo->prepare("
            SELECT total_cost, outlet_to
            FROM vend_consignments
            WHERE id = ? AND transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        // Get threshold for this PO
        $threshold = $this->getThreshold($po->total_cost, $po->outlet_to);

        // Approval required if threshold tier > 1 (tier 1 is auto-approved for managers)
        return $threshold['tier'] > 1 || $threshold['required_count'] > 1;
    }

    /**
     * Get required approvers for a purchase order
     *
     * @param int $poId Purchase order ID
     * @return array Array of required approver details
     *   [
     *     'tier' => int,
     *     'required_count' => int,
     *     'roles' => string[],
     *     'approvers' => [
     *       ['user_id' => int, 'name' => string, 'role' => string, 'email' => string]
     *     ]
     *   ]
     */
    public function getRequiredApprovers(int $poId): array
    {
        // Get PO details
        $stmt = $this->pdo->prepare("
            SELECT total_cost, outlet_to, created_by
            FROM vend_consignments
            WHERE id = ? AND transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        // Get threshold
        $threshold = $this->getThreshold($po->total_cost, $po->outlet_to);

        // Get users with required roles
        $placeholders = implode(',', array_fill(0, count($threshold['roles']), '?'));
        $stmt = $this->pdo->prepare("
            SELECT
                u.id AS user_id,
                u.full_name AS name,
                u.role,
                u.email
            FROM users u
            WHERE u.role IN ($placeholders)
              AND u.active = 1
              AND u.id != ?
            ORDER BY
                FIELD(u.role, " . implode(',', array_map(fn($r) => "'$r'", $threshold['roles'])) . ")
        ");

        $params = array_merge($threshold['roles'], [$po->created_by]);
        $stmt->execute($params);
        $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tier' => $threshold['tier'],
            'required_count' => $threshold['required_count'],
            'roles' => $threshold['roles'],
            'approvers' => $approvers
        ];
    }

    /**
     * Submit purchase order for approval
     *
     * @param int $poId Purchase order ID
     * @param int $submittedBy User ID submitting for approval
     * @return array Approval request details
     */
    public function submitForApproval(int $poId, int $submittedBy): array
    {
        // Verify PO is in correct state
        $stmt = $this->pdo->prepare("
            SELECT state, total_cost
            FROM vend_consignments
            WHERE id = ? AND transfer_category = 'PURCHASE_ORDER'
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found: $poId");
        }

        if ($po->state !== 'DRAFT') {
            throw new RuntimeException("Purchase order must be in DRAFT state to submit for approval");
        }

        // Get required approvers
        $approvalDetails = $this->getRequiredApprovers($poId);

        $this->pdo->beginTransaction();

        try {
            // Create approval request
            $stmt = $this->pdo->prepare("
                INSERT INTO approval_requests (
                    entity_type,
                    entity_id,
                    requested_by,
                    tier,
                    required_count,
                    status,
                    created_at,
                    metadata
                ) VALUES (
                    'purchase_order',
                    :po_id,
                    :requested_by,
                    :tier,
                    :required_count,
                    'pending',
                    NOW(),
                    :metadata
                )
            ");

            $stmt->execute([
                ':po_id' => $poId,
                ':requested_by' => $submittedBy,
                ':tier' => $approvalDetails['tier'],
                ':required_count' => $approvalDetails['required_count'],
                ':metadata' => json_encode([
                    'amount' => $po->total_cost,
                    'roles_required' => $approvalDetails['roles']
                ])
            ]);

            $requestId = (int)$this->pdo->lastInsertId();

            // Create approval records for each approver
            foreach ($approvalDetails['approvers'] as $approver) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO approvals (
                        approval_request_id,
                        approver_id,
                        status,
                        created_at
                    ) VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$requestId, $approver['user_id']]);
            }

            // Update PO state to OPEN (pending approval)
            $stmt = $this->pdo->prepare("
                UPDATE vend_consignments
                SET state = 'OPEN',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$poId]);

            // Log in audit trail
            $this->logAudit($poId, 'submit_for_approval', 'OPEN', [
                'actor_id' => $submittedBy,
                'approval_request_id' => $requestId,
                'tier' => $approvalDetails['tier']
            ]);

            $this->pdo->commit();

            return [
                'request_id' => $requestId,
                'tier' => $approvalDetails['tier'],
                'required_count' => $approvalDetails['required_count'],
                'approvers' => $approvalDetails['approvers']
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to submit for approval: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process approval action (approve/reject/request changes)
     *
     * @param int $poId Purchase order ID
     * @param int $approverId User ID of approver
     * @param string $action 'approve'|'reject'|'request_changes'
     * @param string|null $comments Optional comments
     * @return array Updated approval status
     */
    public function processApproval(int $poId, int $approverId, string $action, ?string $comments = null): array
    {
        if (!in_array($action, ['approve', 'reject', 'request_changes'])) {
            throw new InvalidArgumentException("Invalid action: $action");
        }

        // Get active approval request
        $stmt = $this->pdo->prepare("
            SELECT id, tier, required_count, status
            FROM approval_requests
            WHERE entity_type = 'purchase_order'
              AND entity_id = ?
              AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$poId]);
        $request = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$request) {
            throw new RuntimeException("No pending approval request found for PO: $poId");
        }

        // Verify approver has permission
        $stmt = $this->pdo->prepare("
            SELECT id, status
            FROM approvals
            WHERE approval_request_id = ?
              AND approver_id = ?
        ");
        $stmt->execute([$request->id, $approverId]);
        $approval = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$approval) {
            throw new RuntimeException("User $approverId is not an approver for this request");
        }

        if ($approval->status !== 'pending') {
            throw new RuntimeException("This approval has already been processed");
        }

        $this->pdo->beginTransaction();

        try {
            // Update approval record
            $stmt = $this->pdo->prepare("
                UPDATE approvals
                SET status = :status,
                    comments = :comments,
                    processed_at = NOW()
                WHERE id = :approval_id
            ");

            $stmt->execute([
                ':status' => $action === 'approve' ? 'approved' : 'rejected',
                ':comments' => $comments,
                ':approval_id' => $approval->id
            ]);

            // Check if approval is complete or rejected
            $newRequestStatus = $this->checkApprovalStatus($request->id, $action);

            // Update request status
            $stmt = $this->pdo->prepare("
                UPDATE approval_requests
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :request_id
            ");
            $stmt->execute([
                ':status' => $newRequestStatus,
                ':request_id' => $request->id
            ]);

            // Update PO state based on outcome
            if ($newRequestStatus === 'approved') {
                $stmt = $this->pdo->prepare("
                    UPDATE vend_consignments
                    SET state = 'PACKING',
                        approved_at = NOW(),
                        approved_by = :approver_id,
                        updated_at = NOW()
                    WHERE id = :po_id
                ");
                $stmt->execute([
                    ':approver_id' => $approverId,
                    ':po_id' => $poId
                ]);

                $this->logAudit($poId, 'approved', 'PACKING', [
                    'actor_id' => $approverId,
                    'comments' => $comments
                ]);

            } elseif ($newRequestStatus === 'rejected') {
                $stmt = $this->pdo->prepare("
                    UPDATE vend_consignments
                    SET state = 'CANCELLED',
                        rejection_reason = :comments,
                        updated_at = NOW()
                    WHERE id = :po_id
                ");
                $stmt->execute([
                    ':comments' => $comments,
                    ':po_id' => $poId
                ]);

                $this->logAudit($poId, 'rejected', 'CANCELLED', [
                    'actor_id' => $approverId,
                    'comments' => $comments
                ]);

            } elseif ($action === 'request_changes') {
                $stmt = $this->pdo->prepare("
                    UPDATE vend_consignments
                    SET state = 'DRAFT',
                        updated_at = NOW()
                    WHERE id = :po_id
                ");
                $stmt->execute([':po_id' => $poId]);

                $this->logAudit($poId, 'changes_requested', 'DRAFT', [
                    'actor_id' => $approverId,
                    'comments' => $comments
                ]);
            }

            $this->pdo->commit();

            return [
                'request_status' => $newRequestStatus,
                'action_taken' => $action,
                'approver_id' => $approverId
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to process approval: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delegate approval to another user
     *
     * @param int $approvalId Approval ID
     * @param int $delegateFrom User delegating
     * @param int $delegateTo User receiving delegation
     * @param string|null $reason Reason for delegation
     * @return bool Success
     */
    public function delegateApproval(int $approvalId, int $delegateFrom, int $delegateTo, ?string $reason = null): bool
    {
        // Verify approval exists and belongs to delegator
        $stmt = $this->pdo->prepare("
            SELECT id, approval_request_id, status
            FROM approvals
            WHERE id = ? AND approver_id = ? AND status = 'pending'
        ");
        $stmt->execute([$approvalId, $delegateFrom]);
        $approval = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$approval) {
            throw new InvalidArgumentException("Approval not found or cannot be delegated");
        }

        $this->pdo->beginTransaction();

        try {
            // Update approval record
            $stmt = $this->pdo->prepare("
                UPDATE approvals
                SET approver_id = :delegate_to,
                    delegated_from = :delegate_from,
                    delegation_reason = :reason,
                    updated_at = NOW()
                WHERE id = :approval_id
            ");

            $stmt->execute([
                ':delegate_to' => $delegateTo,
                ':delegate_from' => $delegateFrom,
                ':reason' => $reason,
                ':approval_id' => $approvalId
            ]);

            // Log delegation
            $stmt = $this->pdo->prepare("
                INSERT INTO approval_delegation_log (
                    approval_id,
                    delegated_from,
                    delegated_to,
                    reason,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$approvalId, $delegateFrom, $delegateTo, $reason]);

            $this->pdo->commit();

            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to delegate approval: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Escalate stale approval requests
     *
     * @param int $hoursStale Number of hours before escalation (default 48)
     * @return int Number of escalations processed
     */
    public function escalateStaleRequests(int $hoursStale = 48): int
    {
        // Find stale pending requests
        $stmt = $this->pdo->prepare("
            SELECT
                ar.id,
                ar.entity_id AS po_id,
                ar.tier,
                ar.requested_by,
                ar.created_at
            FROM approval_requests ar
            WHERE ar.status = 'pending'
              AND ar.entity_type = 'purchase_order'
              AND ar.created_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ");
        $stmt->execute([':hours' => $hoursStale]);
        $staleRequests = $stmt->fetchAll(PDO::FETCH_OBJ);

        $escalated = 0;

        foreach ($staleRequests as $request) {
            // Get higher tier approvers
            $higherTier = $this->getHigherTierApprovers($request->tier);

            if (!empty($higherTier)) {
                // Create escalation
                foreach ($higherTier as $approver) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO approvals (
                            approval_request_id,
                            approver_id,
                            status,
                            is_escalated,
                            created_at
                        ) VALUES (?, ?, 'pending', 1, NOW())
                    ");
                    $stmt->execute([$request->id, $approver['user_id']]);
                }

                // Log escalation
                $this->logAudit($request->po_id, 'escalated', 'OPEN', [
                    'request_id' => $request->id,
                    'hours_stale' => $hoursStale,
                    'escalated_to_tier' => $request->tier + 1
                ]);

                $escalated++;
            }
        }

        return $escalated;
    }

    /**
     * Get approval history for purchase order
     *
     * @param int $poId Purchase order ID
     * @return array Approval history
     */
    public function getApprovalHistory(int $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ar.id AS request_id,
                ar.tier,
                ar.required_count,
                ar.status AS request_status,
                ar.created_at AS requested_at,
                u_req.full_name AS requested_by_name,
                a.id AS approval_id,
                a.status AS approval_status,
                a.comments,
                a.processed_at,
                u_app.full_name AS approver_name,
                u_app.role AS approver_role
            FROM approval_requests ar
            LEFT JOIN users u_req ON ar.requested_by = u_req.id
            LEFT JOIN approvals a ON ar.id = a.approval_request_id
            LEFT JOIN users u_app ON a.approver_id = u_app.id
            WHERE ar.entity_type = 'purchase_order'
              AND ar.entity_id = :po_id
            ORDER BY ar.created_at DESC, a.processed_at DESC
        ");

        $stmt->execute([':po_id' => $poId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Get approval threshold for amount and outlet
     */
    private function getThreshold(float $amount, string $outletId): array
    {
        // Check for outlet-specific thresholds
        $stmt = $this->pdo->prepare("
            SELECT threshold_config
            FROM outlet_approval_config
            WHERE outlet_id = ? AND active = 1
        ");
        $stmt->execute([$outletId]);
        $config = $stmt->fetch(PDO::FETCH_OBJ);

        $thresholds = $config ? json_decode($config->threshold_config, true) : self::DEFAULT_THRESHOLDS;

        // Find matching threshold
        foreach ($thresholds as $threshold) {
            $minAmount = $threshold['min_amount'];
            $maxAmount = $threshold['max_amount'];

            if ($amount >= $minAmount && ($maxAmount === null || $amount < $maxAmount)) {
                return $threshold;
            }
        }

        // Fallback to highest tier
        return end($thresholds);
    }

    /**
     * Check approval status after action
     */
    private function checkApprovalStatus(int $requestId, string $lastAction): string
    {
        // Get request details
        $stmt = $this->pdo->prepare("
            SELECT required_count
            FROM approval_requests
            WHERE id = ?
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_OBJ);

        // If any approval was rejected, whole request is rejected
        if ($lastAction === 'reject') {
            return 'rejected';
        }

        // Count approved
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS approved_count
            FROM approvals
            WHERE approval_request_id = ? AND status = 'approved'
        ");
        $stmt->execute([$requestId]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        // Check if required count met
        if ($result->approved_count >= $request->required_count) {
            return 'approved';
        }

        return 'pending';
    }

    /**
     * Get higher tier approvers for escalation
     */
    private function getHigherTierApprovers(int $currentTier): array
    {
        // Get thresholds for next tier
        $nextTier = $currentTier + 1;

        foreach (self::DEFAULT_THRESHOLDS as $threshold) {
            if ($threshold['tier'] === $nextTier) {
                // Get users with these roles
                $placeholders = implode(',', array_fill(0, count($threshold['roles']), '?'));
                $stmt = $this->pdo->prepare("
                    SELECT id AS user_id, full_name AS name, role, email
                    FROM users
                    WHERE role IN ($placeholders) AND active = 1
                ");
                $stmt->execute($threshold['roles']);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return [];
    }

    /**
     * Log audit entry
     */
    private function logAudit(int $poId, string $action, string $status, array $metadata = []): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_audit_log (
                    entity_type,
                    entity_pk,
                    transfer_pk,
                    action,
                    status,
                    actor_type,
                    actor_id,
                    metadata,
                    created_at
                ) VALUES (
                    'transfer',
                    :po_id,
                    :po_id,
                    :action,
                    :status,
                    :actor_type,
                    :actor_id,
                    :metadata,
                    NOW()
                )
            ");

            $stmt->execute([
                ':po_id' => $poId,
                ':action' => $action,
                ':status' => $status,
                ':actor_type' => $metadata['actor_type'] ?? 'user',
                ':actor_id' => $metadata['actor_id'] ?? null,
                ':metadata' => json_encode($metadata)
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
}
