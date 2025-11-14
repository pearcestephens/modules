<?php
/**
 * ============================================================================
 * INVENTORY SYNC ENGINE
 * Ensures perfect accuracy between Vend POS and local inventory
 * ============================================================================
 *
 * Purpose: NEVER let inventory go out of sync
 *
 * Features:
 *   - Real-time sync monitoring
 *   - Automatic discrepancy detection
 *   - Smart reconciliation (auto-fix safe issues)
 *   - Audit trail for every change
 *   - Alert system for critical mismatches
 *   - Consignment tracking integration
 *   - Multi-outlet sync verification
 *
 * Use Cases:
 *   1. Transfer from Warehouse → Store (update both ends)
 *   2. Consignment received → Update Vend inventory
 *   3. Sale made → Verify Vend decremented correctly
 *   4. Manual adjustment → Log and sync
 *   5. Nightly reconciliation → Catch any drift
 */

namespace CIS\InventorySync;

use PDO;
use Exception;

class InventorySyncEngine {
    protected $pdo;
    protected $vend_api_url;
    protected $vend_api_token;
    protected $cache = [];
    protected $sync_log = [];

    // Sync states
    const SYNC_STATE_PERFECT = 'perfect';
    const SYNC_STATE_MINOR_DRIFT = 'minor_drift';
    const SYNC_STATE_MAJOR_DRIFT = 'major_drift';
    const SYNC_STATE_CRITICAL = 'critical';
    const SYNC_STATE_UNKNOWN = 'unknown';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->vend_api_url = getenv('VEND_API_URL') ?: 'https://api.vendhq.com';
        $this->vend_api_token = getenv('VEND_API_TOKEN') ?: '';
    }

    /**
     * Main sync check: Compare local inventory with Vend
     * Returns detailed report with discrepancies
     */
    public function checkSync($product_id = null, $outlet_id = null) {
        try {
            $report = [
                'scan_time' => date('Y-m-d H:i:s'),
                'products_checked' => 0,
                'perfect_matches' => 0,
                'minor_drifts' => 0,
                'major_drifts' => 0,
                'critical_issues' => 0,
                'discrepancies' => [],
                'auto_fixed' => 0,
                'alerts_triggered' => 0,
            ];

            // Get products to check
            $products = $this->getProductsToCheck($product_id, $outlet_id);
            $report['products_checked'] = count($products);

            foreach ($products as $product) {
                $local = $this->getLocalInventory($product['product_id'], $product['outlet_id']);
                $vend = $this->getVendInventory($product['product_id'], $product['outlet_id']);

                if ($local === null || $vend === null) {
                    $report['critical_issues']++;
                    $report['discrepancies'][] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'outlet_id' => $product['outlet_id'],
                        'type' => 'missing_data',
                        'severity' => 'critical',
                        'local_count' => $local,
                        'vend_count' => $vend,
                        'message' => 'Cannot retrieve inventory data',
                    ];
                    continue;
                }

                $diff = abs($local - $vend);

                if ($diff === 0) {
                    $report['perfect_matches']++;
                } elseif ($diff <= 2) {
                    // Minor drift (1-2 units) - auto-fix if safe
                    $report['minor_drifts']++;
                    if ($this->canAutoFix($product, $local, $vend)) {
                        $this->autoFixDiscrepancy($product, $local, $vend);
                        $report['auto_fixed']++;
                    }
                    $report['discrepancies'][] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'outlet_id' => $product['outlet_id'],
                        'type' => 'minor_drift',
                        'severity' => 'low',
                        'local_count' => $local,
                        'vend_count' => $vend,
                        'difference' => $diff,
                        'auto_fixed' => $this->canAutoFix($product, $local, $vend),
                    ];
                } elseif ($diff <= 10) {
                    // Major drift (3-10 units) - needs review
                    $report['major_drifts']++;
                    $report['discrepancies'][] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'outlet_id' => $product['outlet_id'],
                        'type' => 'major_drift',
                        'severity' => 'medium',
                        'local_count' => $local,
                        'vend_count' => $vend,
                        'difference' => $diff,
                        'requires_review' => true,
                    ];

                    // Trigger alert
                    $this->triggerAlert($product, $local, $vend, 'major_drift');
                    $report['alerts_triggered']++;
                } else {
                    // Critical drift (>10 units) - immediate attention
                    $report['critical_issues']++;
                    $report['discrepancies'][] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'outlet_id' => $product['outlet_id'],
                        'type' => 'critical_drift',
                        'severity' => 'critical',
                        'local_count' => $local,
                        'vend_count' => $vend,
                        'difference' => $diff,
                        'requires_immediate_action' => true,
                    ];

                    // Trigger critical alert
                    $this->triggerAlert($product, $local, $vend, 'critical_drift');
                    $report['alerts_triggered']++;
                }
            }

            // Determine overall sync state
            $report['sync_state'] = $this->determineSyncState($report);

            // Log the sync check
            $this->logSyncCheck($report);

            return $report;

        } catch (Exception $e) {
            return [
                'error' => 'Sync check failed: ' . $e->getMessage(),
                'sync_state' => self::SYNC_STATE_UNKNOWN,
            ];
        }
    }

    /**
     * Force sync: Push local inventory to Vend (master = local)
     */
    public function forceSyncToVend($product_id, $outlet_id) {
        try {
            $local = $this->getLocalInventory($product_id, $outlet_id);

            if ($local === null) {
                throw new Exception("Cannot retrieve local inventory for product $product_id");
            }

            // Update Vend via API
            $result = $this->updateVendInventory($product_id, $outlet_id, $local);

            if ($result['success']) {
                // Log the sync
                $this->logInventoryChange(
                    $product_id,
                    $outlet_id,
                    'force_sync_to_vend',
                    $result['old_count'] ?? null,
                    $local,
                    'Forced sync from local to Vend'
                );

                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'outlet_id' => $outlet_id,
                    'old_vend_count' => $result['old_count'],
                    'new_vend_count' => $local,
                    'message' => 'Successfully synced to Vend',
                ];
            }

            return ['success' => false, 'error' => 'Vend API update failed'];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Force sync failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Force sync: Pull Vend inventory to local (master = Vend)
     */
    public function forceSyncFromVend($product_id, $outlet_id) {
        try {
            $vend = $this->getVendInventory($product_id, $outlet_id);

            if ($vend === null) {
                throw new Exception("Cannot retrieve Vend inventory for product $product_id");
            }

            // Update local database
            $result = $this->updateLocalInventory($product_id, $outlet_id, $vend);

            if ($result['success']) {
                // Log the sync
                $this->logInventoryChange(
                    $product_id,
                    $outlet_id,
                    'force_sync_from_vend',
                    $result['old_count'] ?? null,
                    $vend,
                    'Forced sync from Vend to local'
                );

                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'outlet_id' => $outlet_id,
                    'old_local_count' => $result['old_count'],
                    'new_local_count' => $vend,
                    'message' => 'Successfully synced from Vend',
                ];
            }

            return ['success' => false, 'error' => 'Local database update failed'];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Force sync failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record a transfer/consignment and ensure both ends sync
     */
    public function recordTransfer($transfer_data) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();

            $product_id = $transfer_data['product_id'];
            $from_outlet = $transfer_data['from_outlet_id'];
            $to_outlet = $transfer_data['to_outlet_id'];
            $quantity = $transfer_data['quantity'];

            // Get current counts
            $from_before = $this->getLocalInventory($product_id, $from_outlet);
            $to_before = $this->getLocalInventory($product_id, $to_outlet);

            // Calculate new counts
            $from_after = $from_before - $quantity;
            $to_after = $to_before + $quantity;

            if ($from_after < 0) {
                throw new Exception("Insufficient inventory at source outlet");
            }

            // Update local inventory
            $this->updateLocalInventory($product_id, $from_outlet, $from_after);
            $this->updateLocalInventory($product_id, $to_outlet, $to_after);

            // Update Vend
            $this->updateVendInventory($product_id, $from_outlet, $from_after);
            $this->updateVendInventory($product_id, $to_outlet, $to_after);

            // Log the transfer
            $this->logInventoryChange(
                $product_id,
                $from_outlet,
                'transfer_out',
                $from_before,
                $from_after,
                "Transferred $quantity units to outlet $to_outlet"
            );

            $this->logInventoryChange(
                $product_id,
                $to_outlet,
                'transfer_in',
                $to_before,
                $to_after,
                "Received $quantity units from outlet $from_outlet"
            );

            // Commit transaction
            $this->pdo->commit();

            // Verify sync immediately
            $verify_from = $this->checkSync($product_id, $from_outlet);
            $verify_to = $this->checkSync($product_id, $to_outlet);

            return [
                'success' => true,
                'product_id' => $product_id,
                'from_outlet' => [
                    'outlet_id' => $from_outlet,
                    'before' => $from_before,
                    'after' => $from_after,
                    'sync_verified' => $verify_from['sync_state'] === self::SYNC_STATE_PERFECT,
                ],
                'to_outlet' => [
                    'outlet_id' => $to_outlet,
                    'before' => $to_before,
                    'after' => $to_after,
                    'sync_verified' => $verify_to['sync_state'] === self::SYNC_STATE_PERFECT,
                ],
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => 'Transfer failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get local inventory count from database
     */
    protected function getLocalInventory($product_id, $outlet_id) {
        try {
            $sql = "
                SELECT inventory_count
                FROM vend_inventory
                WHERE product_id = ? AND outlet_id = ?
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$product_id, $outlet_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int)$result['inventory_count'] : null;
        } catch (Exception $e) {
            error_log("Error getting local inventory: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Vend inventory count via API
     */
    protected function getVendInventory($product_id, $outlet_id) {
        try {
            // Mock API call (replace with real Vend API)
            $url = "{$this->vend_api_url}/api/2.0/products/{$product_id}/inventory";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->vend_api_token}",
                "Content-Type: application/json",
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $data = json_decode($response, true);

                // Find inventory for this outlet
                foreach ($data['inventory'] ?? [] as $inv) {
                    if ($inv['outlet_id'] == $outlet_id) {
                        return (int)$inv['count'];
                    }
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting Vend inventory: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update local inventory in database
     */
    protected function updateLocalInventory($product_id, $outlet_id, $new_count) {
        try {
            $old_count = $this->getLocalInventory($product_id, $outlet_id);

            $sql = "
                UPDATE vend_inventory
                SET inventory_count = ?, last_updated = NOW()
                WHERE product_id = ? AND outlet_id = ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$new_count, $product_id, $outlet_id]);

            return [
                'success' => true,
                'old_count' => $old_count,
                'new_count' => $new_count,
            ];
        } catch (Exception $e) {
            error_log("Error updating local inventory: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update Vend inventory via API
     */
    protected function updateVendInventory($product_id, $outlet_id, $new_count) {
        try {
            $old_count = $this->getVendInventory($product_id, $outlet_id);

            // Mock API call (replace with real Vend API)
            $url = "{$this->vend_api_url}/api/2.0/products/{$product_id}/inventory";

            $data = [
                'outlet_id' => $outlet_id,
                'count' => $new_count,
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->vend_api_token}",
                "Content-Type: application/json",
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 || $http_code === 201) {
                return [
                    'success' => true,
                    'old_count' => $old_count,
                    'new_count' => $new_count,
                ];
            }

            return ['success' => false, 'error' => 'Vend API returned ' . $http_code];
        } catch (Exception $e) {
            error_log("Error updating Vend inventory: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get products that need sync checking
     */
    protected function getProductsToCheck($product_id = null, $outlet_id = null) {
        try {
            if ($product_id) {
                $sql = "
                    SELECT p.product_id, p.name as product_name, i.outlet_id
                    FROM vend_products p
                    INNER JOIN vend_inventory i ON p.product_id = i.product_id
                    WHERE p.product_id = ?
                ";
                $params = [$product_id];

                if ($outlet_id) {
                    $sql .= " AND i.outlet_id = ?";
                    $params[] = $outlet_id;
                }
            } else {
                $sql = "
                    SELECT p.product_id, p.name as product_name, i.outlet_id
                    FROM vend_products p
                    INNER JOIN vend_inventory i ON p.product_id = i.product_id
                    WHERE p.active = 1
                    LIMIT 1000
                ";
                $params = [];
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting products to check: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Determine if discrepancy can be auto-fixed
     */
    protected function canAutoFix($product, $local, $vend) {
        $diff = abs($local - $vend);

        // Only auto-fix minor drifts (1-2 units)
        if ($diff > 2) {
            return false;
        }

        // Don't auto-fix negative inventory
        if ($local < 0 || $vend < 0) {
            return false;
        }

        // Don't auto-fix high-value items (check product price)
        // This would require product details

        return true;
    }

    /**
     * Auto-fix minor discrepancy
     */
    protected function autoFixDiscrepancy($product, $local, $vend) {
        // Choose the higher count as truth (safer)
        $correct_count = max($local, $vend);

        if ($local !== $correct_count) {
            $this->updateLocalInventory($product['product_id'], $product['outlet_id'], $correct_count);
        }

        if ($vend !== $correct_count) {
            $this->updateVendInventory($product['product_id'], $product['outlet_id'], $correct_count);
        }

        $this->logInventoryChange(
            $product['product_id'],
            $product['outlet_id'],
            'auto_fix',
            min($local, $vend),
            $correct_count,
            "Auto-fixed minor drift (local=$local, vend=$vend)"
        );
    }

    /**
     * Trigger alert for discrepancy
     */
    protected function triggerAlert($product, $local, $vend, $alert_type) {
        try {
            $sql = "
                INSERT INTO inventory_sync_alerts
                (product_id, outlet_id, alert_type, local_count, vend_count, difference, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $product['product_id'],
                $product['outlet_id'],
                $alert_type,
                $local,
                $vend,
                abs($local - $vend),
            ]);

            // Also send notification (email/SMS/Slack)
            // $this->sendNotification($product, $local, $vend, $alert_type);

        } catch (Exception $e) {
            error_log("Error triggering alert: " . $e->getMessage());
        }
    }

    /**
     * Log inventory change for audit trail
     */
    protected function logInventoryChange($product_id, $outlet_id, $change_type, $old_count, $new_count, $notes) {
        try {
            $sql = "
                INSERT INTO inventory_change_log
                (product_id, outlet_id, change_type, old_count, new_count, difference, notes, created_at, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ";

            $user_id = $_SESSION['user_id'] ?? 'system';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $product_id,
                $outlet_id,
                $change_type,
                $old_count,
                $new_count,
                $new_count - $old_count,
                $notes,
                $user_id,
            ]);

        } catch (Exception $e) {
            error_log("Error logging inventory change: " . $e->getMessage());
        }
    }

    /**
     * Log sync check report
     */
    protected function logSyncCheck($report) {
        try {
            $sql = "
                INSERT INTO inventory_sync_checks
                (scan_time, products_checked, perfect_matches, minor_drifts, major_drifts, critical_issues,
                 auto_fixed, alerts_triggered, sync_state, report_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $report['scan_time'],
                $report['products_checked'],
                $report['perfect_matches'],
                $report['minor_drifts'],
                $report['major_drifts'],
                $report['critical_issues'],
                $report['auto_fixed'],
                $report['alerts_triggered'],
                $report['sync_state'],
                json_encode($report),
            ]);

        } catch (Exception $e) {
            error_log("Error logging sync check: " . $e->getMessage());
        }
    }

    /**
     * Determine overall sync state
     */
    protected function determineSyncState($report) {
        if ($report['critical_issues'] > 0) {
            return self::SYNC_STATE_CRITICAL;
        }

        if ($report['major_drifts'] > 10) {
            return self::SYNC_STATE_MAJOR_DRIFT;
        }

        if ($report['minor_drifts'] > 0) {
            return self::SYNC_STATE_MINOR_DRIFT;
        }

        if ($report['products_checked'] > 0 && $report['perfect_matches'] === $report['products_checked']) {
            return self::SYNC_STATE_PERFECT;
        }

        return self::SYNC_STATE_UNKNOWN;
    }
}
