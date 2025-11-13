<?php
/**
 * TransferReviewService
 *
 * Generates performance reviews, coaching messages, and gamification events
 * for transfers and purchase order receiving completion.
 *
 * Functions:
 * - generateReview(int $transferId): array
 * - computeMetrics(int $transferId): array
 * - saveReview(array $review): int|null
 * - scheduleWeeklyReports(): void
 *
 * This service writes AI insights via CISLogger::ai and action logs via PurchaseOrderLogger.
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use CIS\Consignments\Lib\PurchaseOrderLogger;

class TransferReviewService {

    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Generate a review for a transfer/purchase order
     * Performs metrics calculations and saves AI coaching insight
     * Returns review array
     */
    public function generateReview(int $transferId): array {
        // Compute metrics
        $metrics = $this->computeMetrics($transferId);

        // Build coaching message using simple templates and metrics
        $coaching = $this->buildCoachingMessage($metrics);

        $review = [
            'transfer_id' => $transferId,
            'metrics' => $metrics,
            'coaching' => $coaching,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Save review (to cis_ai_context via CISLogger::ai or to transfer_reviews table)
        try {
            // Save AI context via CISLogger if available
            if (class_exists('CISLogger')) {
                try {
                    \CISLogger::ai(
                        'transfer_review',
                        'purchase_orders',
                        "generate_review_transfer_{$transferId}",
                        json_encode($review['metrics']),
                        $review['coaching'],
                        $review['metrics'],
                        ['coaching' => $coaching],
                        $metrics['confidence'] ?? null,
                        ['transfer_review']
                    );
                } catch (\Exception $e) {
                    error_log('[TransferReviewService] CISLogger::ai failed: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log('[TransferReviewService] Failed to prepare AI review: ' . $e->getMessage());
        }

        // Also write to transfer_reviews table if exists
        $this->saveReviewToTable($review);

        // Write gamification events if applicable
        $this->awardGamification($metrics);

        return $review;
    }

    /**
     * Compute metrics for transfer: accuracy, timing, discrepancies, throughput
     */
    public function computeMetrics(int $transferId): array {
        // For purchase orders the transfer is a PO
        $stmt = $this->pdo->prepare("SELECT * FROM vend_consignments WHERE id = ? AND transfer_category = 'PURCHASE_ORDER'");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new \InvalidArgumentException("Transfer not found: {$transferId}");
        }

        // Gather lines
        $stmt = $this->pdo->prepare("SELECT product_id, expected_qty, received_qty, damaged_qty, scanned_time, created_at FROM vend_consignment_line_items WHERE consignment_id = ?");
        $stmt->execute([$transferId]);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalExpected = 0;
        $totalReceived = 0;
        $totalDamaged = 0;
        $discrepancies = 0;
        $lineCount = count($lines);
        $times = [];

        foreach ($lines as $line) {
            $expected = (int)($line['expected_qty'] ?? 0);
            $received = (int)($line['received_qty'] ?? 0);
            $damaged = (int)($line['damaged_qty'] ?? 0);
            $totalExpected += $expected;
            $totalReceived += $received;
            $totalDamaged += $damaged;
            if ($expected !== $received) $discrepancies++;
            if (!empty($line['scanned_time'])) {
                $times[] = strtotime($line['scanned_time']);
            }
        }

        $accuracy = $totalExpected > 0 ? round(($totalReceived / $totalExpected) * 100, 2) : 100.0;
        $avgTimePerItem = !empty($times) ? round((max($times) - min($times)) / max(1, $totalReceived), 2) : null;

        // Compute percentiles for timing if available
        sort($times);
        $p95 = null;
        if (!empty($times)) {
            $idx = (int)floor(0.95 * count($times)) - 1; if ($idx < 0) $idx = 0;
            $p95 = $times[$idx];
        }

        // Basic confidence heuristic
        $confidence = 0.5;
        if ($accuracy > 98 && $totalDamaged == 0) $confidence = 0.95;
        elseif ($accuracy > 95) $confidence = 0.85;
        elseif ($accuracy > 90) $confidence = 0.7;

        return [
            'transfer_id' => $transferId,
            'line_count' => $lineCount,
            'total_expected' => $totalExpected,
            'total_received' => $totalReceived,
            'total_damaged' => $totalDamaged,
            'discrepancies' => $discrepancies,
            'accuracy_percent' => $accuracy,
            'avg_time_per_item_seconds' => $avgTimePerItem,
            'p95_timestamp' => $p95,
            'confidence' => $confidence,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    private function buildCoachingMessage(array $metrics): string {
        $parts = [];
        $parts[] = sprintf("Accuracy: %s%%", $metrics['accuracy_percent']);
        $parts[] = sprintf("Items: %d lines", $metrics['line_count']);
        if ($metrics['total_damaged'] > 0) {
            $parts[] = sprintf("Damaged items: %d", $metrics['total_damaged']);
        }
        if ($metrics['discrepancies'] > 0) {
            $parts[] = sprintf("Discrepancies: %d lines - check quantities and barcodes", $metrics['discrepancies']);
        }
        if ($metrics['avg_time_per_item_seconds'] !== null) {
            $parts[] = sprintf("Avg time per item: %s seconds", $metrics['avg_time_per_item_seconds']);
        }
        $advice = [];
        if ($metrics['accuracy_percent'] < 95) {
            $advice[] = "Review scanned barcodes for mismatches and ensure correct quantities.";
        } else {
            $advice[] = "Great job — accuracy is high. Keep validating barcodes.";
        }

        if ($metrics['total_damaged'] > 0) {
            $advice[] = "Ensure packaging checks before shipment to reduce damaged items.";
        }

        return implode("; ", array_merge($parts, $advice));
    }

    private function saveReviewToTable(array $review): ?int {
        try {
            // Insert into consignment_metrics as the canonical place for metric/review data
            $stmt = $this->pdo->prepare("INSERT INTO consignment_metrics (transfer_id, total_items, total_quantity, status, processing_time_ms, created_at, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $review['metrics']['transfer_id'] ?? null,
                $review['metrics']['line_count'] ?? 0,
                $review['metrics']['total_received'] ?? 0,
                'reviewed',
                0,
                $review['created_at'],
                json_encode($review)
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('[TransferReviewService] Failed to save review to consignment_metrics: ' . $e->getMessage());
            return null;
        }
    }

    private function awardGamification(array $metrics): void {
        // Simple example: award points for accuracy
        try {
            // Award points and achievements using flagged_products gamification tables when available
            if (!empty($metrics['transfer_id']) && $metrics['accuracy_percent'] >= 98) {
                // Map outlet_id if present
                $outletId = $metrics['source_outlet_id'] ?? null;

                // Insert points
                if ($this->tableExists('flagged_products_points')) {
                    $stmt = $this->pdo->prepare("INSERT INTO flagged_products_points (user_id, outlet_id, points_earned, reason, accuracy_percentage, streak_days) VALUES (?, ?, ?, ?, ?, ?)");
                    // user_id unknown in this context - leave null
                    $stmt->execute([
                        null,
                        $outletId,
                        10,
                        'accuracy_bonus',
                        $metrics['accuracy_percent'],
                        0
                    ]);
                }

                // Award achievement if table exists
                if ($this->tableExists('flagged_products_achievements')) {
                    $stmt = $this->pdo->prepare("INSERT IGNORE INTO flagged_products_achievements (user_id, achievement_code, achievement_name, achievement_description, points_awarded) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        null,
                        'accuracy_98_plus',
                        'Accuracy Champion (98%+)',
                        'Awarded for achieving 98% or higher accuracy during receiving',
                        10
                    ]);
                }
            }
        } catch (\Exception $e) {
            error_log('[TransferReviewService] Failed to award gamification: ' . $e->getMessage());
        }
    }

    /**
     * Helper to check if a table exists in the current database
     */
    private function tableExists(string $tableName): bool {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            return !empty($row);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Schedule weekly reports - to be invoked by cron
     */
    public function scheduleWeeklyReports(): void {
        // Aggregate per-outlet metrics for last week and email
        // Implementation placeholder - real email sending uses existing mailer system
        try {
            $stmt = $this->pdo->prepare("SELECT outlet_id FROM vend_outlets");
            $stmt->execute();
            $outlets = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($outlets as $outletId) {
                $report = $this->aggregateOutletWeekly($outletId);
                // TODO: send email via mailer system
                // Mailer::sendOutletReport($outletId, $report);
            }
        } catch (\Exception $e) {
            error_log('[TransferReviewService] Failed to schedule weekly reports: ' . $e->getMessage());
        }
    }

    private function aggregateOutletWeekly(int $outletId): array {
        $oneWeekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stmt = $this->pdo->prepare("SELECT id FROM vend_consignments WHERE outlet_id = ? AND transfer_category = 'PURCHASE_ORDER' AND updated_at >= ?");
        $stmt->execute([$outletId, $oneWeekAgo]);
        $transferIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $aggregated = [
            'outlet_id' => $outletId,
            'period_start' => $oneWeekAgo,
            'transfer_count' => count($transferIds),
            'results' => []
        ];

        foreach ($transferIds as $tid) {
            $metrics = $this->computeMetrics((int)$tid);
            $aggregated['results'][] = $metrics;
        }

        // Compute summary
        $sumAccuracy = array_sum(array_column($aggregated['results'], 'accuracy_percent')) ?: 0;
        $aggregated['average_accuracy'] = $aggregated['transfer_count'] > 0 ? round($sumAccuracy / $aggregated['transfer_count'], 2) : null;

        return $aggregated;
    }
}
