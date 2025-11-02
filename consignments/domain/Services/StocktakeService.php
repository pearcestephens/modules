<?php
/**
 * Stocktake Service
 *
 * Domain service for stock count and variance adjustment workflows.
 * Handles stocktake business rules and adjustment generation.
 *
 * Business Rules:
 * - Stocktakes compare physical count vs system count
 * - Variances > $500 or > 50 units require approval
 * - Generates adjustment transfer for approved variances
 * - Full audit trail of all counts and adjustments
 * - Integrates with Lightspeed for stock level sync
 *
 * Workflow States:
 * DRAFT → APPROVED → ADJUSTED (with transfer generated)
 *
 * @package Consignments\Domain\Services
 */

declare(strict_types=1);

namespace Consignments\Domain\Services;

use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class StocktakeService
{
    private \PDO $pdo;
    private LightspeedClient $client;
    private LoggerInterface $logger;

    /** Approval thresholds */
    private const APPROVAL_VALUE_THRESHOLD = 500.00; // dollars
    private const APPROVAL_UNIT_THRESHOLD = 50; // units

    public function __construct(\PDO $pdo, LightspeedClient $client, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create new stocktake
     *
     * @param int $outletId Outlet where stocktake performed
     * @param array $counts Physical counts [{product_id, physical_count, unit_cost}]
     * @return array Created stocktake with ID and calculated variances
     * @throws \InvalidArgumentException if validation fails
     */
    public function createStocktake(int $outletId, array $counts): array
    {
        $this->validateCreateData($outletId, $counts);

        $this->pdo->beginTransaction();

        try {
            // Insert stocktake header
            $stmt = $this->pdo->prepare("
                INSERT INTO stocktakes (
                    outlet_id, status, created_at
                ) VALUES (?, 'DRAFT', NOW())
            ");

            $stmt->execute([$outletId]);
            $stocktakeId = (int)$this->pdo->lastInsertId();

            // Insert counts and calculate variances
            $itemStmt = $this->pdo->prepare("
                INSERT INTO stocktake_items (
                    stocktake_id, product_id, physical_count, system_count,
                    variance, unit_cost, variance_value
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $variances = [];

            foreach ($counts as $count) {
                // Get system count from vend_inventory
                $systemCount = $this->getSystemCount($outletId, $count['product_id']);

                // Calculate variance (physical - system)
                $variance = $count['physical_count'] - $systemCount;
                $unitCost = $count['unit_cost'] ?? 0.00;
                $varianceValue = $variance * $unitCost;

                $itemStmt->execute([
                    $stocktakeId,
                    $count['product_id'],
                    $count['physical_count'],
                    $systemCount,
                    $variance,
                    $unitCost,
                    $varianceValue
                ]);

                if ($variance !== 0) {
                    $variances[] = [
                        'product_id' => $count['product_id'],
                        'physical' => $count['physical_count'],
                        'system' => $systemCount,
                        'variance' => $variance,
                        'variance_value' => $varianceValue
                    ];
                }
            }

            // Calculate totals
            $totals = $this->calculateTotals($stocktakeId);

            // Update stocktake with totals
            $updateStmt = $this->pdo->prepare("
                UPDATE stocktakes
                SET total_variance_value = ?,
                    total_variance_units = ?,
                    requires_approval = ?
                WHERE id = ?
            ");

            $requiresApproval = $this->requiresApproval($variances);

            $updateStmt->execute([
                $totals['total_value'],
                $totals['total_units'],
                $requiresApproval ? 1 : 0,
                $stocktakeId
            ]);

            // Audit log
            $this->logAudit($stocktakeId, 'CREATE', null, [
                'status' => 'DRAFT',
                'outlet_id' => $outletId,
                'item_count' => count($counts),
                'variance_count' => count($variances),
                'total_variance_value' => $totals['total_value'],
                'requires_approval' => $requiresApproval
            ]);

            $this->pdo->commit();

            $this->logger->info('Stocktake created', [
                'stocktake_id' => $stocktakeId,
                'outlet_id' => $outletId,
                'item_count' => count($counts),
                'variance_count' => count($variances),
                'requires_approval' => $requiresApproval
            ]);

            return [
                'stocktake_id' => $stocktakeId,
                'outlet_id' => $outletId,
                'status' => 'DRAFT',
                'variances' => $variances,
                'totals' => $totals,
                'requires_approval' => $requiresApproval,
                'created_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create Stocktake', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate variances for a stocktake
     *
     * @param int $stocktakeId Stocktake ID
     * @return array Variance details grouped by positive/negative
     */
    public function calculateVariances(int $stocktakeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT product_id, physical_count, system_count, variance, variance_value
            FROM stocktake_items
            WHERE stocktake_id = ?
            ORDER BY ABS(variance_value) DESC
        ");

        $stmt->execute([$stocktakeId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $positive = []; // Overages (physical > system)
        $negative = []; // Shortages (physical < system)

        foreach ($items as $item) {
            if ($item['variance'] > 0) {
                $positive[] = $item;
            } elseif ($item['variance'] < 0) {
                $negative[] = $item;
            }
        }

        return [
            'positive' => $positive,
            'negative' => $negative,
            'total_positive_value' => array_sum(array_column($positive, 'variance_value')),
            'total_negative_value' => array_sum(array_column($negative, 'variance_value')),
            'net_variance_value' => array_sum(array_column($items, 'variance_value'))
        ];
    }

    /**
     * Check if stocktake requires approval based on variance size
     *
     * @param array $variances Variance items
     * @return bool True if approval required
     */
    public function requiresApproval(array $variances): bool
    {
        // Total absolute variance value
        $totalValue = 0.0;
        $totalUnits = 0;

        foreach ($variances as $variance) {
            $totalValue += abs($variance['variance_value']);
            $totalUnits += abs($variance['variance']);
        }

        return $totalValue > self::APPROVAL_VALUE_THRESHOLD
            || $totalUnits > self::APPROVAL_UNIT_THRESHOLD;
    }

    /**
     * Approve stocktake (change status DRAFT → APPROVED)
     *
     * @param int $stocktakeId Stocktake ID
     * @param int $approverId User ID of approver
     * @return bool True on success
     * @throws \InvalidArgumentException if stocktake not in DRAFT status
     */
    public function approve(int $stocktakeId, int $approverId): bool
    {
        $stocktake = $this->getStocktake($stocktakeId);

        if ($stocktake['status'] !== 'DRAFT') {
            throw new \InvalidArgumentException("Stocktake must be in DRAFT status to approve (currently {$stocktake['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE stocktakes
                SET status = 'APPROVED',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$approverId, $stocktakeId]);

            // Audit log
            $this->logAudit($stocktakeId, 'APPROVE', 'DRAFT', [
                'status' => 'APPROVED',
                'approved_by' => $approverId
            ], $approverId);

            $this->pdo->commit();

            $this->logger->info('Stocktake approved', [
                'stocktake_id' => $stocktakeId,
                'approver_id' => $approverId
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to approve Stocktake', [
                'stocktake_id' => $stocktakeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate adjustment transfer from approved stocktake
     *
     * Creates a transfer that adjusts stock levels to match physical count
     *
     * @param int $stocktakeId Stocktake ID
     * @return array Generated transfer details
     * @throws \InvalidArgumentException if stocktake not in APPROVED status
     */
    public function generateAdjustmentTransfer(int $stocktakeId): array
    {
        $stocktake = $this->getStocktake($stocktakeId);

        if ($stocktake['status'] !== 'APPROVED') {
            throw new \InvalidArgumentException("Stocktake must be in APPROVED status to generate adjustment (currently {$stocktake['status']})");
        }

        $this->pdo->beginTransaction();

        try {
            // Get all variance items
            $items = $this->getStocktakeItems($stocktakeId);
            $varianceItems = array_filter($items, function($item) {
                return $item['variance'] !== 0;
            });

            if (empty($varianceItems)) {
                throw new \InvalidArgumentException('No variances to adjust');
            }

            // Create adjustment transfer
            $transferStmt = $this->pdo->prepare("
                INSERT INTO stock_transfers (
                    type, outlet_id, status, stocktake_id,
                    notes, created_at
                ) VALUES (
                    'STOCKTAKE_ADJUSTMENT', ?, 'APPROVED', ?,
                    'Auto-generated from stocktake', NOW()
                )
            ");

            $transferStmt->execute([
                $stocktake['outlet_id'],
                $stocktakeId
            ]);

            $transferId = (int)$this->pdo->lastInsertId();

            // Add variance items to transfer
            $itemStmt = $this->pdo->prepare("
                INSERT INTO stock_transfer_items (
                    transfer_id, product_id, ordered_qty, adjustment_type
                ) VALUES (?, ?, ?, ?)
            ");

            foreach ($varianceItems as $item) {
                $adjustmentType = $item['variance'] > 0 ? 'ADD' : 'REMOVE';

                $itemStmt->execute([
                    $transferId,
                    $item['product_id'],
                    abs($item['variance']), // Use absolute value
                    $adjustmentType
                ]);
            }

            // Apply adjustments to vend_inventory
            $this->applyAdjustments($stocktake['outlet_id'], $varianceItems);

            // Update stocktake status
            $updateStmt = $this->pdo->prepare("
                UPDATE stocktakes
                SET status = 'ADJUSTED',
                    adjustment_transfer_id = ?,
                    adjusted_at = NOW()
                WHERE id = ?
            ");

            $updateStmt->execute([$transferId, $stocktakeId]);

            // Sync to Lightspeed
            $this->syncToLightspeed($stocktake['outlet_id'], $varianceItems);

            // Audit log
            $this->logAudit($stocktakeId, 'ADJUST', 'APPROVED', [
                'status' => 'ADJUSTED',
                'transfer_id' => $transferId,
                'adjustment_count' => count($varianceItems)
            ]);

            $this->pdo->commit();

            $this->logger->info('Stocktake adjustment transfer generated', [
                'stocktake_id' => $stocktakeId,
                'transfer_id' => $transferId,
                'adjustment_count' => count($varianceItems)
            ]);

            return [
                'stocktake_id' => $stocktakeId,
                'transfer_id' => $transferId,
                'adjustment_count' => count($varianceItems),
                'status' => 'ADJUSTED',
                'adjusted_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to generate adjustment transfer', [
                'stocktake_id' => $stocktakeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ===========================
    // PRIVATE HELPER METHODS
    // ===========================

    private function validateCreateData(int $outletId, array $counts): void
    {
        if ($outletId <= 0) {
            throw new \InvalidArgumentException('Invalid outlet_id');
        }

        if (!is_array($counts) || empty($counts)) {
            throw new \InvalidArgumentException('Counts must be non-empty array');
        }

        foreach ($counts as $count) {
            if (empty($count['product_id']) || !isset($count['physical_count'])) {
                throw new \InvalidArgumentException('Each count must have product_id and physical_count');
            }

            if ($count['physical_count'] < 0) {
                throw new \InvalidArgumentException('Physical count cannot be negative');
            }
        }
    }

    private function getSystemCount(int $outletId, string $productId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT inventory_count
            FROM vend_inventory
            WHERE outlet_id = ? AND product_id = ?
        ");

        $stmt->execute([$outletId, $productId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int)$row['inventory_count'] : 0;
    }

    private function calculateTotals(int $stocktakeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(ABS(variance_value)) AS total_value,
                SUM(ABS(variance)) AS total_units
            FROM stocktake_items
            WHERE stocktake_id = ?
        ");

        $stmt->execute([$stocktakeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_value' => round($row['total_value'] ?? 0.0, 2),
            'total_units' => (int)($row['total_units'] ?? 0)
        ];
    }

    private function getStocktake(int $stocktakeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stocktakes WHERE id = ?
        ");
        $stmt->execute([$stocktakeId]);
        $stocktake = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$stocktake) {
            throw new \InvalidArgumentException("Stocktake {$stocktakeId} not found");
        }

        return $stocktake;
    }

    private function getStocktakeItems(int $stocktakeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM stocktake_items WHERE stocktake_id = ?
        ");
        $stmt->execute([$stocktakeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function applyAdjustments(int $outletId, array $varianceItems): void
    {
        foreach ($varianceItems as $item) {
            $stmt = $this->pdo->prepare("
                UPDATE vend_inventory
                SET inventory_count = inventory_count + ?
                WHERE outlet_id = ? AND product_id = ?
            ");

            // variance is already positive/negative, so just add it
            $stmt->execute([
                $item['variance'],
                $outletId,
                $item['product_id']
            ]);
        }

        $this->logger->debug('Stock adjustments applied', [
            'outlet_id' => $outletId,
            'adjustment_count' => count($varianceItems)
        ]);
    }

    private function syncToLightspeed(int $outletId, array $varianceItems): void
    {
        try {
            foreach ($varianceItems as $item) {
                // Get new inventory count after adjustment
                $newCount = $this->getSystemCount($outletId, $item['product_id']);

                // Update Lightspeed inventory
                $this->client->put("/api/2.0/products/{$item['product_id']}/inventory.json", [
                    'inventory' => [
                        'outlet_id' => $outletId,
                        'count' => $newCount
                    ]
                ]);
            }

            $this->logger->info('Stocktake adjustments synced to Lightspeed', [
                'outlet_id' => $outletId,
                'adjustment_count' => count($varianceItems)
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync stocktake to Lightspeed', [
                'outlet_id' => $outletId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - adjustment should succeed even if LS sync fails
        }
    }

    private function logAudit(int $stocktakeId, string $action, ?string $oldStatus, array $newData, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stocktake_audit_log (
                stocktake_id, action, old_status, new_data, user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $stocktakeId,
            $action,
            $oldStatus,
            json_encode($newData),
            $userId
        ]);
    }
}
